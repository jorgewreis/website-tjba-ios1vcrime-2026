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

$perfisAlterarRegra = ['ADMIN', 'DIRETORA', 'MAGISTRADA'];

$userPerfis = [];
$userId = $_SESSION['id_usuario'] ?? null;
if ($userId !== null && $userId !== '') {
    $stmt = $connection->prepare('SELECT DISTINCT UPPER(TRIM(perfil)) AS perfil FROM usuario_perfil WHERE usuario = ? AND perfil IS NOT NULL');
    $stmt->execute([$userId]);
    $userPerfis = $stmt->fetchAll(PDO::FETCH_COLUMN);
}
$podeAlterarRegra = count(array_intersect($userPerfis, $perfisAlterarRegra)) > 0;

$ruleName = 'ALLOCATE-GUIAS';
$ruleDescription = 'Alocação automática - Guias (expedição e envio)';
$errors = [];
$successMessage = '';
$usersList = [];
$selectedUserId = '1';
$ruleRow = null;
$ruleUsuarioId = '';
$ruleUsuarioNome = 'Nenhum usuário definido';

try {
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
                $usuarioAtualizaId = isset($_SESSION['id_usuario']) ? trim((string) $_SESSION['id_usuario']) : null;
                $ruleRow = $regrasRepo->save($ruleName, $ruleDescription, $postedUserId, $usuarioAtualizaId !== '' ? $usuarioAtualizaId : null);
                $ruleUsuarioId = (string) ($ruleRow['usuario_id'] ?? $postedUserId);
                $ruleUsuarioNome = resolveUsuarioNome($connection, $ruleUsuarioId);
                $successMessage = 'Regra atualizada com sucesso.';
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
<html lang="pt-BR">
    <head>
        <?php include __DIR__ . '/_theme_init.php'; ?>
        <meta charset="utf-8">
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <title>Guias - Regras - Primeira Vara Crime de Ilhéus</title>
        <meta name="description" content="">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <link rel="stylesheet" href="/assets/css/shared/reset.css">
        <link rel="stylesheet" href="/assets/css/shared/color.css">
        <link rel="stylesheet" href="/assets/css/shared/page.css">
        <link rel="stylesheet" href="/assets/css/shared/form.css">
        <link rel="stylesheet" href="/assets/css/shared/table.css">
        <?php include __DIR__ . '/_toast_script.php'; ?>
    </head>
<body class="page-internal">

    <div class="spacing-top-page"></div>

    <div class="header-page">
        <div class="location">
            <span class="all">Interno - Cartório - Guias -</span>
            <span class="specific">Regras</span>
        </div>
        <h1 class="title-page">Regras de Alocação de Guias</h1>
        <p class="description-page">Defina o servidor responsável pela alocação automática das guias.</p>
    </div>

    <div class="card-page">
        <form method="post" class="rule-form" style="width: 100%">
            <div class="row table header">Regras de alocação automática</div>
            <?php if (!$podeAlterarRegra): ?>
                <div class="row filter">
                    <div class="col">
                        <label class="status status-primary-light">Apenas perfis admin, diretora ou magistrada podem alterar esta regra. Você pode visualizar a configuração atual.</label>
                    </div>
                </div>
            <?php endif; ?>
            <div class="row filter">
                <div class="col filter">
                    <label for="id_user">Servidor</label>
                    <select class="width-lg height-md" id="id_user" name="id_user" <?php echo $podeAlterarRegra ? '' : 'disabled'; ?> required>
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
                <div class="row <?php echo $ruleUsuarioNome === '' ? 'info' : 'success'; ?>">
                    <span><?php echo htmlspecialchars(sprintf('Guias: %s - %s', $ruleRow['id'] ?? 'sem ID', $ruleUsuarioNome)); ?></span>
                </div>
            <?php endif; ?>

            <div class="row buttons">
                <button type="button" class="btn btn-secondary" id="voltar-btn">Voltar</button>
                <?php if ($podeAlterarRegra): ?>
                    <button type="submit" class="btn btn-primary">Salvar</button>
                <?php endif; ?>
            </div>

            <?php if ($successMessage): ?>
                <div class="row filter">
                    <div class="col">
                        <label class="status status-finished"><?php echo htmlspecialchars($successMessage); ?></label>
                    </div>
                </div>
            <?php endif; ?>
            <?php if (!empty($errors)): ?>
                <div class="row filter">
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
                btn.addEventListener('click', function (event) {
                    event.preventDefault();
                    window.location.href = '/frame.php?page=guias';
                }, { capture: true });
            }
        })();
    </script>
</body>
</html>
