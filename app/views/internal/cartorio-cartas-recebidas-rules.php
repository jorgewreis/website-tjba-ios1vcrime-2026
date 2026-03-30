<?php
declare(strict_types=1);

header('Content-Type: text/html; charset=UTF-8');
if (function_exists('mb_internal_encoding')) {
    mb_internal_encoding('UTF-8');
}

require_once __DIR__ . '/../../repositories/AlocacoesRegrasRepository.php';
require_once __DIR__ . '/../../helpers/AlocacaoRegrasUsuariosHelper.php';

function resolveUsuarioNome(PDO $connection, ?string $usuarioId): string
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

$connection = Conection::getInstance()->getConnection();

/** Perfis que podem alterar a regra de alocação (tabela usuario_perfil). */
$perfisAlterarRegra = ['ADMIN', 'DIRETORA', 'MAGISTRADA'];

$userPerfis = [];
$userId = $_SESSION['id_usuario'] ?? null;
if ($userId !== null && $userId !== '') {
    $stmt = $connection->prepare('SELECT DISTINCT UPPER(TRIM(perfil)) AS perfil FROM usuario_perfil WHERE usuario = ? AND perfil IS NOT NULL');
    $stmt->execute([$userId]);
    $userPerfis = $stmt->fetchAll(PDO::FETCH_COLUMN);
}
$podeAlterarRegra = count(array_intersect($userPerfis, $perfisAlterarRegra)) > 0;

$ruleName = 'ALLOCATE-CARTASRECEBIDAS';
$ruleDescription = 'Alocação automática - Devolução de Cartas Recebidas';
$errors = [];
$successMessage = '';
$usersList = [];
$selectedUserId = '';
$ruleRow = null;
$ruleUsuarioId = '';
$ruleUsuarioNome = 'Nenhum usuário definido';

try {
    $usersList = AlocacaoRegrasUsuariosHelper::listUsuariosInternos($connection);
    if ($usersList === []) {
        $errors[] = 'Nenhum usuário interno elegível foi encontrado.';
    }

    $regrasRepo = new AlocacoesRegrasRepository($connection);
    $ruleRow = $regrasRepo->findByRegra($ruleName);
    $ruleUsuarioId = (string) ($ruleRow['usuario_id'] ?? '');
    $ruleUsuarioNome = resolveUsuarioNome($connection, $ruleUsuarioId);
    $selectedUserId = trim((string) ($ruleRow['usuario_id'] ?? ''));
    if ($selectedUserId !== '') {
        $idsInList = array_column($usersList, 'id');
        if (!in_array($selectedUserId, $idsInList, true)) {
            $usersList[] = ['id' => $selectedUserId, 'name' => $ruleUsuarioNome . ' (fora da lista atual)'];
        }
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = trim((string) ($_POST['action'] ?? 'save'));
        if (!$podeAlterarRegra) {
            $errors[] = 'Seu perfil não tem permissão para alterar esta regra. Apenas admin, diretora ou magistrada podem alterar.';
        } elseif ($action === 'clear') {
            try {
                $usuarioAtualizaId = isset($_SESSION['id_usuario']) ? trim((string) $_SESSION['id_usuario']) : null;
                $ruleRow = $regrasRepo->clearAllocation($ruleName, $usuarioAtualizaId !== '' ? $usuarioAtualizaId : null);
                $ruleUsuarioId = '';
                $ruleUsuarioNome = resolveUsuarioNome($connection, null);
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
                if (!AlocacaoRegrasUsuariosHelper::isUsuarioElegivelParaRegraInterna($connection, $postedUserId)) {
                    $errors[] = 'Apenas servidores internos com perfil operacional podem ser alocados nesta regra.';
                } else {
                    try {
                        $usuarioAtualizaId = null;
                        if (isset($_SESSION['id_usuario'])) {
                            $usuarioAtualizaId = trim((string) $_SESSION['id_usuario']);
                        }
                        $ruleRow = $regrasRepo->save($ruleName, $ruleDescription, $postedUserId, $usuarioAtualizaId !== '' ? $usuarioAtualizaId : null);
                        $ruleUsuarioId = (string) ($ruleRow['usuario_id'] ?? $postedUserId);
                        $ruleUsuarioNome = resolveUsuarioNome($connection, $ruleUsuarioId);
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
<html>
    <head>
        <meta lang="pt-BR">
        <meta charset="utf-8">
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <title>Cartas Recebidas - Regras - Primeira Vara Crime de Ilhéus</title>
        <meta name="description" content="">
        <meta name="viewport" content="width=device-width, initial-scale=1">
                <?php include __DIR__ . '/_internal_head_styles.php'; ?>
        <?php include __DIR__ . '/_toast_script.php'; ?>


    </head>
<body class="page-internal">

    <!-- SPACING -->
    <div class="spacing-top-page"></div>

    <div class="header-page">
        <!-- Mapa de navegação do site -->
        <div class="location">
            <span class="all">Internal - Cartório - Cartas Recebidas -</span>
            <span class="specific">Regras</span>
        </div>

        <!-- Título e descrição da página -->
        <h1 class="title-page">Regras de Alocação de Cartas Recebidas</h1>
        <p class="description-page">Caso tenha alguma dificuldade no preenchimento das regras, verifique os arquivos abaixo para obter ajuda.</p>

        <!-- Badges de arquivos para download -->
        <div class="badge file">
            <img src="/assets/icons/Office-File-Pdf-1--Streamline-Ultimate.png" alt="Ícone de arquivo PDF">
            <span>Guia Completo - Divisão de Tarefas</span>
        </div>
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
                            <option value="" disabled<?php echo $selectedUserId === '' ? ' selected' : ''; ?>>Selecione aqui</option>
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
                        <span><?php echo htmlspecialchars(sprintf('Cartas Recebidas: %s - %s', $ruleRow['id'] ?? 'sem ID', $ruleUsuarioNome)); ?></span>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Ações -->
            <div class="display-flex row row-full justify-end gap-y-md padding-x-md padding-y-md border-top">
                <button type="button" class="btn btn-secondary" id="voltar-btn">Voltar</button>
                <?php if ($podeAlterarRegra): ?>
                    <button type="submit" class="btn btn-danger-outline" id="remover-alocacao-btn" name="action" value="clear" formnovalidate disabled>Remover alocação</button>
                    <button type="submit" class="btn btn-primary" id="salvar-btn" name="action" value="save" disabled>Salvar</button>
                <?php endif; ?>
            </div>

        </form>
    </div>

    <!-- Develop Info -->
    <div class="footer-page">
        <div class="location">
            <span class="all">Aplicativo web desenvolvido integralmente por </span>
            <span class="specific">Jorge Reis Develop</span>
            <span class="all">- Copyright 2018 a 2026 - Todos os direitos reservados.</span>
        </div>
    </div>
    <script>
        (() => {
            const successMessage = <?php echo json_encode($successMessage, JSON_UNESCAPED_UNICODE); ?>;
            const errors = <?php echo json_encode(array_values($errors), JSON_UNESCAPED_UNICODE); ?>;
            if (successMessage && typeof showToast === 'function') {
                showToast({ type: 'success', variant: 'full', title: 'Sucesso', message: successMessage });
            }
            if (Array.isArray(errors) && errors.length && typeof showToast === 'function') {
                errors.forEach((message) => showToast({ type: 'error', variant: 'full', title: 'Erro', message }));
            }
        })();

        (() => {
            const btn = document.getElementById('voltar-btn');
            if (btn) {
                btn.addEventListener('click', (event) => {
                    event.preventDefault();
                    window.location.href = '/frame.php?page=cartasrecebidas';
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
                const changed = currentId !== originalId;

                if (changed) saveBtn.removeAttribute('disabled');
                else saveBtn.setAttribute('disabled', '');

                if (clearBtn) {
                    if (originalId !== '') clearBtn.removeAttribute('disabled');
                    else clearBtn.setAttribute('disabled', '');
                }
            };

            select.addEventListener('change', syncSaveButton);
            syncSaveButton();
        })();
    </script>
</body>
</html>
