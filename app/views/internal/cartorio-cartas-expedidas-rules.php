<?php
declare(strict_types=1);

header('Content-Type: text/html; charset=UTF-8');
if (function_exists('mb_internal_encoding')) {
    mb_internal_encoding('UTF-8');
}

require_once __DIR__ . '/../../repositories/AlocacoesRegrasRepository.php';

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

$ruleName = 'ALLOCATE-CARTASEXPEDIDAS';
$ruleDescription = 'Alocação automática - Envio de Cartas Expedidas';
$errors = [];
$successMessage = '';
$usersList = [];
$selectedUserId = '1';
$ruleRow = null;
$ruleUsuarioId = '';
$ruleUsuarioNome = 'Nenhum usuário definido';

try {
    $usersList = [];
    $stmtUsers = $connection->prepare('SELECT `id_usuario`, `nome`, `sobrenome` FROM `usuarios` ORDER BY `nome`, `sobrenome`');
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
    $ruleUsuarioNome = resolveUsuarioNome($connection, $ruleUsuarioId);
    $selectedUserId = (string) ($ruleRow['usuario_id'] ?? $selectedUserId);
    if ($selectedUserId === '' && !empty($usersList)) {
        $selectedUserId = $usersList[0]['id'];
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!$podeAlterarRegra) {
            $errors[] = 'Seu perfil não tem permissão para alterar esta regra. Apenas admin, diretora ou magistrada podem alterar.';
        } elseif (($postedUserId = trim((string) ($_POST['id_user'] ?? ''))) === '') {
            $errors[] = 'Selecione um servidor antes de salvar.';
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
} catch (\Throwable $exception) {
    $errors[] = 'Erro ao acessar as regras: ' . $exception->getMessage();
}
?>
<!DOCTYPE html>
<html>
    <head>
        <?php include __DIR__ . '/_theme_init.php'; ?>
        <meta lang="pt-BR">
        <meta charset="utf-8">
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <title>Cartas Expedidas - Primeira Vara Crime de Ilhéus</title>
        <meta name="description" content="">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <link rel="stylesheet" href="/assets/css/shared/reset.css">
        <link rel="stylesheet" href="/assets/css/shared/color.css">
        <link rel="stylesheet" href="/assets/css/shared/page.css">
        <link rel="stylesheet" href="/assets/css/shared/form.css">
        <link rel="stylesheet" href="/assets/css/shared/table.css">

    </head>
<body class="page-internal">

    <!-- SPACING -->
    <div class="spacing-top-page"></div>

    <div class="header-page">
        <!-- Mapa de navegação do site -->
        <div class="location">
            <span class="all">Internal - Cartório - Cartas Expedidas -</span>
            <span class="specific">Regras</span>
        </div>

        <!-- Título e descrição da página -->
        <h1 class="title-page">Regras de Alocação de Carta Expedida</h1>
        <p class="description-page">Caso tenha alguma dificuldade no preenchimento das regras, verifique os arquivos abaixo para obter ajuda.</p>

        <!-- Badges de arquivos para download -->
        <div class="badge file">
            <img src="/assets/icons/Office-File-Pdf-1--Streamline-Ultimate.png" alt="Ícone de arquivo PDF">
            <span>Guia Completo - Divisão de Tarefas</span>
        </div>
    </div>

    <div class="card-page">
        <form method="post" class="rule-form" style="width: 100%">
            <div class="row table subtitle left padding-tb-lg padding-lr-lg">Regras de alocação automática</div>    
            <?php if (!$podeAlterarRegra): ?>
                <div class="row table left padding-tb-md padding-lr-lg border-bottom full-line">
                    <div class="col">
                        <label class="status status-primary-light">Apenas perfis admin, diretora ou magistrada podem alterar esta regra. Você pode visualizar a configuração atual.</label>
                    </div>
                </div>
            <?php endif; ?>

            <div class="row table buttons left padding-lr-md padding-tb-md full-line">
                <span class="minor-info" style="font-weight: 600;">Acompanhamento e envio de Cartas Expedidas</span>
            </div>

            <div class="row table left padding-tb-sm padding-lr-lg">
                <div class="col">
                    <label for="filter-processo">Responsável</label>
                    <select class="width-lg height-md" id="filter-processo" name="id_user" <?php echo $podeAlterarRegra ? '' : 'disabled'; ?> required>
                        <option value="" disabled<?php echo $selectedUserId === '' ? ' selected' : ''; ?>>Selecione um servidor</option>
                        <?php foreach ($usersList as $user): ?>
                            <option value="<?php echo htmlspecialchars($user['id'], ENT_QUOTES); ?>"<?php echo $selectedUserId === $user['id'] ? ' selected' : ''; ?>>
                                <?php echo htmlspecialchars($user['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div> 

            <?php if (!empty($ruleRow['id'] ?? '')): ?>
                <div class="row table <?php if($ruleUsuarioNome === ''){ echo 'info'; } else { echo 'success'; } ?>">
                    <span><?php echo htmlspecialchars(sprintf('Regra atual: %s - %s', $ruleRow['id'] ?? 'sem ID', $ruleUsuarioNome)); ?></span>
                </div>
            <?php endif; ?>

            <!-- Ações -->
            <div class="row table right padding-tb-md padding-lr-lg row-gap-10">
                <button type="button" class="btn btn-secondary" id="voltar-btn">Voltar</button>
                <?php if ($podeAlterarRegra): ?>
                    <button type="submit" class="btn btn-primary">Salvar</button>
                <?php endif; ?>
            </div>

            <!-- Informações -->
            <?php if ($successMessage): ?>
                <div class="row table left padding-lr-sm padding-tb-sm">
                    <div class="col">
                        <label class="status status-finished padding-lr-lg"><?php echo htmlspecialchars($successMessage); ?></label>
                    </div>
                </div>
            <?php endif; ?>
            <?php if (!empty($errors)): ?>
                <div class="row table left padding-lr-sm padding-tb-sm">
                    <div class="col">
                        <?php foreach ($errors as $error): ?>
                            <label class="status status-cancelled padding-lr-lg"><?php echo htmlspecialchars($error); ?></label>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
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
            const btn = document.getElementById('voltar-btn');
            if (btn) {
                btn.addEventListener('click', (event) => {
                    event.preventDefault();
                    window.location.href = '/frame.php?page=cartasexpedidascreate';
                }, { capture: true });
            }
        })();
    </script>
</body>
</html>
