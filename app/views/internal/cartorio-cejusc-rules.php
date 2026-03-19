<?php
declare(strict_types=1);

header('Content-Type: text/html; charset=UTF-8');
if (function_exists('mb_internal_encoding')) {
    mb_internal_encoding('UTF-8');
}

require_once __DIR__ . '/../../repositories/AlocacoesRegrasRepository.php';

function resolveUsuarioNomeCejusc(PDO $connection, ?string $usuarioId): string
{
    $usuarioId = trim((string) ($usuarioId ?? ''));
    if ($usuarioId === '') {
        return 'Nenhum usuário definido';
    }

    $stmt = $connection->prepare('SELECT `nome`, `sobrenome` FROM `usuarios` WHERE `id_usuario` = :usuario_id LIMIT 1');
    $stmt->bindValue(':usuario_id', $usuarioId);
    $stmt->execute();

    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($usuario === false) {
        return 'Usuário não encontrado';
    }

    $nome = trim((string) ($usuario['nome'] ?? ''));
    $sobrenome = trim((string) ($usuario['sobrenome'] ?? ''));
    $nomeCompleto = $sobrenome !== '' ? $nome . ' ' . $sobrenome : $nome;

    return $nomeCompleto !== '' ? $nomeCompleto : 'Nome não informado';
}

/**
 * Estudantes (perfil ESTUDANTE) não podem ser alocados em regras internas do cartório.
 */
function isUsuarioEstudante(PDO $connection, string $usuarioId): bool
{
    $usuarioId = trim($usuarioId);
    if ($usuarioId === '') {
        return false;
    }

    $stmt = $connection->prepare('SELECT 1 FROM usuario_perfil WHERE usuario = :usuario_id AND UPPER(TRIM(perfil)) = :perfil LIMIT 1');
    $stmt->bindValue(':usuario_id', $usuarioId);
    $stmt->bindValue(':perfil', 'ESTUDANTE');
    $stmt->execute();

    return $stmt->fetch(PDO::FETCH_ASSOC) !== false;
}

$connection = Conection::getInstance()->getConnection();

$perfisAlterarRegra = ['ADMIN', 'DIRETORA', 'MAGISTRADA'];

$userPerfis = [];
$userId = $_SESSION['id_usuario'] ?? null;
if ($userId !== null && $userId !== '') {
    $stmt = $connection->prepare('SELECT DISTINCT UPPER(TRIM(perfil)) AS perfil FROM usuario_perfil WHERE usuario = ? AND perfil IS NOT NULL');
    $stmt->execute([$userId]);
    $userPerfis = $stmt->fetchAll(PDO::FETCH_COLUMN);
}
$podeAlterarRegra = count(array_intersect($userPerfis, $perfisAlterarRegra)) > 0;

$ruleName = 'ALLOCATE-CEJUSC';
$ruleDescription = 'Alocação automática - Tarefas Cejusc';
$errors = [];
$successMessage = '';
$usersList = [];
$selectedUserId = '';
$ruleRow = null;
$ruleUsuarioId = '';
$ruleUsuarioNome = 'Nenhum usuário definido';

try {
    $stmtUsers = $connection->prepare("
        SELECT u.`id_usuario`, u.`nome`, u.`sobrenome`
        FROM `usuarios` u
        WHERE NOT EXISTS (
            SELECT 1
            FROM `usuario_perfil` up
            WHERE up.`usuario` = u.`id_usuario`
              AND UPPER(TRIM(up.`perfil`)) = 'ESTUDANTE'
        )
        ORDER BY u.`nome`, u.`sobrenome`
    ");
    $stmtUsers->execute();
    while (($row = $stmtUsers->fetch(PDO::FETCH_ASSOC)) !== false) {
        $id = trim((string) ($row['id_usuario'] ?? ''));
        $nome = trim((string) ($row['nome'] ?? ''));
        $sobrenome = trim((string) ($row['sobrenome'] ?? ''));
        $name = $sobrenome !== '' ? $nome . ' ' . $sobrenome : $nome;
        if ($id !== '' && $name !== '') {
            $usersList[] = ['id' => $id, 'name' => $name];
        }
    }
    if (empty($usersList)) {
        $errors[] = 'Nenhum usuário foi encontrado na tabela usuarios.';
    }

    $regrasRepo = new AlocacoesRegrasRepository($connection);
    $ruleRow = $regrasRepo->findByRegra($ruleName);
    $ruleUsuarioId = (string) ($ruleRow['usuario_id'] ?? '');
    $ruleUsuarioNome = resolveUsuarioNomeCejusc($connection, $ruleUsuarioId);
    $selectedUserId = (string) ($ruleRow['usuario_id'] ?? $selectedUserId);
    if ($selectedUserId === '' && !empty($usersList)) {
        $selectedUserId = $usersList[0]['id'];
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = trim((string) ($_POST['action'] ?? 'save'));
        if (!$podeAlterarRegra) {
            $errors[] = 'Seu perfil não tem permissão para alterar esta regra. Apenas admin, diretora ou magistrada podem alterar.';
        } elseif ($action === 'clear') {
            try {
                $usuarioAtualizaId = null;
                if (isset($_SESSION['id_usuario'])) {
                    $usuarioAtualizaId = trim((string) $_SESSION['id_usuario']);
                }
                $ruleRow = $regrasRepo->clearAllocation($ruleName, $usuarioAtualizaId !== '' ? $usuarioAtualizaId : null);
                $ruleUsuarioId = '';
                $ruleUsuarioNome = resolveUsuarioNomeCejusc($connection, null);
                $successMessage = 'Alocação removida com sucesso. A tarefa ficará sem alocação automática por regra.';
                $selectedUserId = '';
            } catch (\InvalidArgumentException | \RuntimeException $exception) {
                $errors[] = $exception->getMessage();
            } catch (\Throwable $exception) {
                $errors[] = 'Erro inesperado ao remover a alocação: ' . $exception->getMessage();
            }
        } else {
            if (($postedUserId = trim((string) ($_POST['id_user'] ?? ''))) === '') {
                $errors[] = 'Selecione um servidor antes de salvar.';
            } else {
                if (isUsuarioEstudante($connection, $postedUserId)) {
                    $errors[] = 'Não é permitido alocar um estudante em regras internas do cartório.';
                } else {
                    try {
                        $usuarioAtualizaId = null;
                        if (isset($_SESSION['id_usuario'])) {
                            $usuarioAtualizaId = trim((string) $_SESSION['id_usuario']);
                        }
                        $ruleRow = $regrasRepo->save($ruleName, $ruleDescription, $postedUserId, $usuarioAtualizaId !== '' ? $usuarioAtualizaId : null);
                        $ruleUsuarioId = (string) ($ruleRow['usuario_id'] ?? $postedUserId);
                        $ruleUsuarioNome = resolveUsuarioNomeCejusc($connection, $ruleUsuarioId);
                        $successMessage = 'Regra atualizada com ID ' . ($ruleRow['id'] ?? 'sem ID') . '.';
                        $selectedUserId = (string) ($ruleRow['usuario_id'] ?? $postedUserId);
                    } catch (\InvalidArgumentException | \RuntimeException $exception) {
                        $errors[] = $exception->getMessage();
                    } catch (\Throwable $exception) {
                        $errors[] = 'Erro inesperado ao salvar a regra: ' . $exception->getMessage();
                    }
                }
            }
        }
    }
} catch (\Throwable $exception) {
    $errors[] = 'Erro ao acessar as regras: ' . $exception->getMessage();
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
    <head>
        <meta charset="utf-8">
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <title>Cejusc - Regras - Primeira Vara Crime de Ilhéus</title>
        <meta name="description" content="">
        <meta name="viewport" content="width=device-width, initial-scale=1">
                <?php include __DIR__ . '/_internal_head_styles.php'; ?>

    </head>
<body class="page-internal">

    <div class="spacing-top-page"></div>

    <div class="header-page">
        <div class="location">
            <span class="all">Interno - Cejusc -</span>
            <span class="specific">Regras</span>
        </div>
        <h1 class="title-page">Regras de Alocação - Cejusc</h1>
        <p class="description-page">Defina o servidor responsável pela alocação automática das tarefas Cejusc (envio da decisão e juntada do comprovante).</p>
    </div>

    <div class="container-full card-page">
        <form method="post" class="rule-form" style="width: 100%">
            <div class="display-flex row row-full align-center justify-between padding-x-md padding-y-md border-bottom">
                <div class="display-flex row row-fit justify-start">
                    <h2>Regras de alocação automática</h2>
                </div>
                <div class="display-flex row row-fit justify-end gap-y-md"></div>
            </div>

            <?php if (!$podeAlterarRegra): ?>
                <div class="display-flex row row-full padding-x-md padding-y-md border-bottom">
                    <div class="col">
                        <label class="status status-primary-light">Apenas perfis admin, diretora ou magistrada podem alterar esta regra. Você pode visualizar a configuração atual.</label>
                    </div>
                </div>
            <?php endif; ?>

            <div class="display-flex row row-full filters justify-between padding-x-md padding-y-md border-bottom gap-y-md">
                <div class="display-flex row row-fit justify-start gap-y-md">
                    <div class="col filter">
                        <label for="filter-processo">Servidor</label>
                        <select class="width-600 height-md" id="filter-processo" name="id_user" data-original-user-name="<?php echo htmlspecialchars($ruleUsuarioNome, ENT_QUOTES, 'UTF-8'); ?>" data-original-user-id="<?php echo htmlspecialchars($selectedUserId, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $podeAlterarRegra ? '' : 'disabled'; ?>>
                            <option value=""<?php echo $selectedUserId === '' ? ' selected' : ''; ?>>Sem alocação automática</option>
                            <?php foreach ($usersList as $user): ?>
                                <option value="<?php echo htmlspecialchars($user['id'], ENT_QUOTES); ?>"<?php echo $selectedUserId === $user['id'] ? ' selected' : ''; ?>>
                                    <?php echo htmlspecialchars($user['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="display-flex row row-fit justify-end gap-y-md"></div>
            </div>

            <?php if (!empty($ruleRow['id'] ?? '')): ?>
                <div class="display-flex row row-full padding-x-md padding-y-md border-bottom <?php echo $ruleUsuarioNome === '' ? 'info' : 'success'; ?>">
                    <div class="col">
                        <span><?php echo htmlspecialchars(sprintf('Cejusc: %s - %s', $ruleRow['id'] ?? 'sem ID', $ruleUsuarioNome)); ?></span>
                    </div>
                </div>
            <?php endif; ?>

            <div class="display-flex row row-full justify-end gap-y-md padding-x-md padding-y-md border-top">
                <button type="button" class="btn btn-secondary" id="voltar-btn">Voltar</button>
                <?php if ($podeAlterarRegra): ?>
                    <button type="submit" class="btn btn-danger-outline" id="remover-alocacao-btn" name="action" value="clear" formnovalidate disabled>Remover alocação</button>
                    <button type="submit" class="btn btn-primary" id="salvar-btn" name="action" value="save" disabled>Salvar</button>
                <?php endif; ?>
            </div>

            <?php if ($successMessage): ?>
                <div class="display-flex row row-full padding-x-md padding-y-md border-bottom">
                    <div class="col">
                        <label class="status status-finished"><?php echo htmlspecialchars($successMessage); ?></label>
                    </div>
                </div>
            <?php endif; ?>
            <?php if (!empty($errors)): ?>
                <div class="display-flex row row-full padding-x-md padding-y-md border-bottom">
                    <div class="col">
                        <?php foreach ($errors as $error): ?>
                            <label class="status status-cancelled"><?php echo htmlspecialchars($error); ?></label>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
        </form>
    </div>

    <div class="footer-page">
        <div class="location">
            <span class="all">Aplicativo web desenvolvido integralmente por </span>
            <span class="specific">Jorge Reis Develop</span>
            <span class="all">- Copyright 2018 a 2026 - Todos os direitos reservados.</span>
        </div>
    </div>
    <script>
        (function () {
            var btn = document.getElementById('voltar-btn');
            if (btn) {
                btn.addEventListener('click', function (e) {
                    e.preventDefault();
                    window.location.href = '/frame.php?page=cejusc';
                }, { capture: true });
            }
        })();

        (() => {
            const saveBtn = document.getElementById('salvar-btn');
            const clearBtn = document.getElementById('remover-alocacao-btn');
            const select = document.querySelector('select[name="id_user"]');
            if (!saveBtn || !select) return;

            const originalId = (select.getAttribute('data-original-user-id') || '').trim();

            const syncSaveButton = () => {
                const currentId = (select.value || '').trim();
                const effectiveOriginalId = originalId !== '' ? originalId : currentId;
                const changed = currentId !== effectiveOriginalId;

                if (changed) saveBtn.removeAttribute('disabled');
                else saveBtn.setAttribute('disabled', '');

                if (clearBtn) {
                    if (effectiveOriginalId !== '') clearBtn.removeAttribute('disabled');
                    else clearBtn.setAttribute('disabled', '');
                }
            };

            select.addEventListener('change', syncSaveButton);
            syncSaveButton();
        })();
    </script>
</body>
</html>
