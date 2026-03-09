<?php
declare(strict_types=1);

header('Content-Type: text/html; charset=UTF-8');
if (function_exists('mb_internal_encoding')) {
    mb_internal_encoding('UTF-8');
}

require_once __DIR__ . '/../../repositories/AlocacoesRegrasRepository.php';

$connection = Conection::getInstance()->getConnection();
$userId = $userId ?? ($_SESSION['id_usuario'] ?? '');
$dados = null;
$perfis = [];
$regrasAlocacao = [];
$ultimaAlteracaoSenha = 'Não registrada';
$mensagemErro = '';
$mensagemSucesso = '';

if ($userId !== '') {
    $dados = $usuario->getDadosPessoais($userId);
    $perfis = $usuario->listarPerfisUsuario($userId);
    if (!is_array($perfis)) {
        $perfis = [];
    }
    $regrasRepo = new AlocacoesRegrasRepository($connection);
    $regrasAlocacao = $regrasRepo->findByUsuarioId($userId);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['csrf_token']) && Security::verifyCSRFToken($_POST['csrf_token'] ?? null)) {
    $nome = trim((string) ($_POST['nome'] ?? ''));
    $sobrenome = trim((string) ($_POST['sobrenome'] ?? ''));
    $email = trim((string) ($_POST['email'] ?? ''));
    $telefone = trim((string) ($_POST['telefone'] ?? ''));
    $cargo = trim((string) ($_POST['cargo'] ?? ''));
    $instituicao = trim((string) ($_POST['instituicao'] ?? ''));

    if ($nome === '') {
        $mensagemErro = 'O nome é obrigatório.';
    } else {
        $resultado = $usuario->atualizarDadosPessoais($userId, [
            'nome' => $nome,
            'sobrenome' => $sobrenome,
            'email' => $email,
            'telefone' => $telefone,
            'cargo' => $cargo,
            'instituicao' => $instituicao,
        ]);
        if ($resultado['success']) {
            $dados = $usuario->getDadosPessoais($userId);
            $mensagemSucesso = 'Dados atualizados com sucesso.';
        } else {
            $mensagemErro = $resultado['message'] ?? 'Não foi possível atualizar os dados.';
        }
    }
}

$csrfToken = Security::generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="pt-BR">
    <head>
        <?php include __DIR__ . '/_theme_init.php'; ?>
        <meta charset="utf-8">
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <title>Alterar dados pessoais - Primeira Vara Crime de Ilhéus</title>
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <link rel="stylesheet" href="/assets/css/shared/reset.css">
        <link rel="stylesheet" href="/assets/css/shared/color.css">
        <link rel="stylesheet" href="/assets/css/shared/page.css">
        <link rel="stylesheet" href="/assets/css/shared/form.css">
        <link rel="stylesheet" href="/assets/css/shared/table.css">
        <link rel="stylesheet" href="https://cdn-uicons.flaticon.com/3.0.0/uicons-regular-rounded/css/uicons-regular-rounded.css">
        <?php include __DIR__ . '/_toast_script.php'; ?>
    </head>
<body class="page-internal">

    <div class="spacing-top-page"></div>

    <div class="header-page">
        <div class="location">
            <span class="all">Interno - Perfil -</span>
            <span class="specific">Dados pessoais</span>
        </div>
        <h1 class="title-page">Alterar dados pessoais</h1>
        <p class="description-page">Visualize e altere seus dados cadastrais. Alguns campos não podem ser alterados por você.</p>
    </div>

    <div class="card-page">
        <?php if ($dados === null): ?>
            <div class="row filter">
                <div class="col">
                    <label class="status status-cancelled">Não foi possível carregar seus dados.</label>
                </div>
            </div>
            <div class="row buttons">
                <button type="button" class="btn btn-secondary" id="voltar-btn">Voltar</button>
            </div>
        <?php else: ?>
            <form id="form-dados-pessoais" method="post" action="">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">

                <div class="row table header">Dados cadastrais</div>

                <div class="row filter">
                    <div class="col filter">
                        <label for="edit-id">Identificador</label>
                        <input type="text" id="edit-id" class="width-md height-md readonly" value="<?php echo htmlspecialchars($dados['id_usuario'] ?? '', ENT_QUOTES); ?>" readonly>
                    </div>
                    <div class="col filter">
                        <label for="edit-cpf">CPF</label>
                        <input type="text" id="edit-cpf" class="width-md height-md readonly" value="<?php echo htmlspecialchars($dados['cpf'] ?? '', ENT_QUOTES); ?>" readonly>
                    </div>
                </div>

                <div class="row filter">
                    <div class="col filter">
                        <label for="edit-nome">Nome <span class="label-hint-obrigatorio">*obrigatório</span></label>
                        <input type="text" id="edit-nome" name="nome" class="width-xl height-md" value="<?php echo htmlspecialchars($dados['nome'] ?? '', ENT_QUOTES); ?>" maxlength="255" required>
                    </div>
                    <div class="col filter">
                        <label for="edit-sobrenome">Sobrenome</label>
                        <input type="text" id="edit-sobrenome" name="sobrenome" class="width-xl height-md" value="<?php echo htmlspecialchars($dados['sobrenome'] ?? '', ENT_QUOTES); ?>" maxlength="255">
                    </div>
                </div>

                <div class="row filter">
                    <div class="col filter">
                        <label for="edit-email">E-mail</label>
                        <input type="email" id="edit-email" name="email" class="width-xl height-md" value="<?php echo htmlspecialchars($dados['email'] ?? '', ENT_QUOTES); ?>" maxlength="255">
                    </div>
                    <div class="col filter">
                        <label for="edit-telefone">Telefone</label>
                        <input type="text" id="edit-telefone" name="telefone" class="width-md height-md" value="<?php echo htmlspecialchars($dados['telefone'] ?? '', ENT_QUOTES); ?>" maxlength="50">
                    </div>
                </div>

                <div class="row filter">
                    <div class="col filter">
                        <label for="edit-cargo">Cargo</label>
                        <input type="text" id="edit-cargo" name="cargo" class="width-lg height-md" value="<?php echo htmlspecialchars($dados['cargo'] ?? '', ENT_QUOTES); ?>" maxlength="100">
                    </div>
                    <div class="col filter">
                        <label for="edit-instituicao">Instituição</label>
                        <input type="text" id="edit-instituicao" name="instituicao" class="width-lg height-md" value="<?php echo htmlspecialchars($dados['instituicao'] ?? '', ENT_QUOTES); ?>" maxlength="200">
                    </div>
                </div>

                <?php if ($mensagemErro !== ''): ?>
                <div class="row filter">
                    <div class="col">
                        <div class="alert-heading danger width-xl" role="alert">
                            <div class="alert-header"><span class="text">Aviso</span></div>
                            <span class="alert-text"><?php echo htmlspecialchars($mensagemErro, ENT_QUOTES, 'UTF-8'); ?></span>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                <?php if ($mensagemSucesso !== ''): ?>
                <div class="row filter">
                    <div class="col">
                        <div class="alert-heading success width-xl" role="status">
                            <div class="alert-header"><span class="text">Sucesso</span></div>
                            <span class="alert-text"><?php echo htmlspecialchars($mensagemSucesso, ENT_QUOTES, 'UTF-8'); ?></span>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <div class="row table header">Informações do perfil (somente leitura)</div>

                <div class="row filter">
                    <div class="col filter">
                        <label>Perfis vinculados</label>
                        <div class="width-xl height-md" style="padding: 8px 12px; background: var(--color-surface-elevated); border-radius: 4px; min-height: 42px; border: 1px solid var(--color-border-subtle);">
                            <?php if (count($perfis) > 0): ?>
                                <span class="minor-info"><?php echo htmlspecialchars(implode(', ', $perfis), ENT_QUOTES); ?></span>
                            <?php else: ?>
                                <span class="minor-info">Nenhum perfil vinculado</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="col filter">
                        <label>Última alteração de senha</label>
                        <input type="text" class="width-md height-md readonly" value="<?php echo htmlspecialchars($ultimaAlteracaoSenha, ENT_QUOTES); ?>" readonly>
                    </div>
                </div>

                <div class="row table header">Regras de alocação definidas para você</div>

                <div class="row filter">
                    <div class="col full-line">
                        <?php if (count($regrasAlocacao) > 0): ?>
                            <ul class="list-plain" style="margin: 0; padding-left: 1.2rem;">
                                <?php foreach ($regrasAlocacao as $regra): ?>
                                    <li style="margin-bottom: 6px;">
                                        <strong><?php echo htmlspecialchars($regra['regra'] ?? '', ENT_QUOTES); ?></strong>
                                        <?php if (!empty(trim((string) ($regra['descricao'] ?? '')))): ?>
                                            — <?php echo htmlspecialchars($regra['descricao'], ENT_QUOTES); ?>
                                        <?php endif; ?>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php else: ?>
                            <span class="minor-info">Nenhuma regra de alocação definida para seu usuário.</span>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="row buttons">
                    <button type="button" class="btn btn-secondary" id="voltar-btn">Voltar</button>
                    <button type="submit" class="btn btn-primary" id="salvar-btn">Salvar alterações</button>
                </div>
            </form>
        <?php endif; ?>
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
        document.getElementById('voltar-btn')?.addEventListener('click', function () {
            window.location.href = '/frame.php?page=dashboard';
        });

        document.getElementById('form-dados-pessoais')?.addEventListener('submit', function () {
            var nome = document.getElementById('edit-nome');
            if (nome && nome.value.trim() === '') {
                if (typeof showToast === 'function') {
                    showToast({ type: 'error', variant: 'full', title: 'Atenção', message: 'O nome é obrigatório.' });
                }
                return false;
            }
            return true;
        });
    })();
    </script>
</body>
</html>
