<?php
declare(strict_types=1);

header('Content-Type: text/html; charset=UTF-8');
if (function_exists('mb_internal_encoding')) {
    mb_internal_encoding('UTF-8');
}

require_once __DIR__ . '/../../repositories/AlocacoesRegrasRepository.php';
require_once __DIR__ . '/../../helpers/UserAccessHelper.php';
require_once __DIR__ . '/../../helpers/AvatarCatalog.php';
require_once __DIR__ . '/../../helpers/AvatarInitialsPalette.php';
require_once __DIR__ . '/../../helpers/SexoHelper.php';

$connection = Conection::getInstance()->getConnection();
$userId = $userId ?? ($_SESSION['id_usuario'] ?? '');
$dados = null;
$perfis = [];
$regrasAlocacao = [];
$ultimaAlteracaoDados = 'não registrada';
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
    if ($dados !== null && !empty($dados['updated_at'])) {
        $dt = \DateTime::createFromFormat('Y-m-d H:i:s', $dados['updated_at']);
        if ($dt === false) {
            $dt = \DateTime::createFromFormat('Y-m-d H:i:s.u', $dados['updated_at']);
        }
        $ultimaAlteracaoDados = $dt ? $dt->format('d/m/Y H:i') : trim($dados['updated_at']);
    }
}

$isUsuarioExterno = UserAccessHelper::isUsuarioExterno($perfis);

if ($isUsuarioExterno) {
    $regrasAlocacao = [];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['csrf_token']) && Security::verifyCSRFToken($_POST['csrf_token'] ?? null)) {
    $email = trim((string) ($_POST['email'] ?? ''));
    $telefone = trim((string) ($_POST['telefone'] ?? ''));
    $sexo = SexoHelper::normalize(trim((string) ($_POST['sexo'] ?? '')));
    if (!SexoHelper::isValid($sexo)) {
        $mensagemErro = 'Selecione o sexo.';
    }
    // Cargo e setor não são editáveis nesta tela (apenas ADMIN/CHEFIA em usuários).
    $cargo = trim((string) ($dados['cargo'] ?? ''));
    $instituicao = trim((string) ($dados['instituicao'] ?? ''));

    if ($mensagemErro === '') {
        $resultado = $usuario->atualizarDadosPessoais($userId, [
            'email' => $email,
            'telefone' => $telefone,
            'sexo' => $sexo,
            'cargo' => $cargo,
            'instituicao' => $instituicao,
        ]);
        $avatarResult = ['success' => true];
        if (!$isUsuarioExterno && $resultado['success']) {
            $slugPost = trim((string) ($_POST['avatar_slug'] ?? ''));
            if ($slugPost !== '') {
                $avatarResult = $usuario->atualizarAvatarSlug($userId, $slugPost);
            }
        }
        if ($resultado['success'] && $avatarResult['success']) {
            $_SESSION['flash_profile_saved_toast'] = 'Dados atualizados com sucesso.';
            $dashPage = $isUsuarioExterno ? 'dashboardexterno' : 'dashboard';
            header('Location: /frame.php?page=' . $dashPage . '&profile_saved=1', true, 302);
            exit;
        } elseif (!$resultado['success']) {
            $mensagemErro = $resultado['message'] ?? 'Não foi possível atualizar os dados.';
        } else {
            $mensagemErro = $avatarResult['message'] ?? 'Não foi possível atualizar o avatar.';
        }
    }
}

$csrfToken = Security::generateCSRFToken();

$cargoOpcoes = ['Estagiário', 'Técnico Judiciário', 'Analista Judiciário', 'Magistrado'];
$setorOpcoes = ['Cartório', 'Gabinete', 'Sala de Audiências', 'Vara'];

$perfisTexto = count($perfis) > 0 ? implode(', ', $perfis) : 'nenhum perfil vinculado';
$perfisTexto = function_exists('mb_strtolower') ? mb_strtolower($perfisTexto, 'UTF-8') : strtolower($perfisTexto);
$ultimaAlteracaoDadosLower = function_exists('mb_strtolower') ? mb_strtolower($ultimaAlteracaoDados, 'UTF-8') : strtolower($ultimaAlteracaoDados);

$nomeCompletoLeitura = '';
$avatarSlugForJs = AvatarCatalog::getDefaultSlug();
$avatarUiEntries = [];
$avatarPreviewPalette = ['bg' => '#6b7280', 'fg' => '#ffffff'];
$avatarPreviewInitials = '?';
if ($dados !== null) {
    $nomeCompletoLeitura = trim(trim((string) ($dados['nome'] ?? '')) . ' ' . trim((string) ($dados['sobrenome'] ?? '')));
    $avatarSlugForJs = $dados['avatar_slug'] ?? AvatarCatalog::getDefaultSlug();
    $avatarPreviewPalette = AvatarInitialsPalette::getOrCreate();
    $avatarPreviewInitials = AvatarCatalog::initialsFromNames(
        trim((string) ($dados['nome'] ?? '')),
        trim((string) ($dados['sobrenome'] ?? ''))
    );
}
if (!$isUsuarioExterno && $dados !== null) {
    foreach (AvatarCatalog::listForUi() as $av) {
        $avatarUiEntries[] = [
            'slug' => $av['slug'],
            'url' => (string) ($av['url'] ?? ''),
            'is_initial' => !empty($av['is_initial']),
        ];
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
    <head>
        <meta charset="utf-8">
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <title>Alterar dados pessoais - Primeira Vara Crime de Ilhéus</title>
        <meta name="viewport" content="width=device-width, initial-scale=1">
                <?php include __DIR__ . '/_internal_head_styles.php'; ?>

        <link rel="stylesheet" href="https://cdn-uicons.flaticon.com/3.0.0/uicons-regular-rounded/css/uicons-regular-rounded.css">
        <?php include __DIR__ . '/_toast_script.php'; ?>
        <style>
            .regras-alocacao-list { font-size: 12px; }
            .regras-alocacao-list .regras-alocacao-item {
                width: 100%;
                padding: 10px 12px;
                margin-bottom: 0;
                border-bottom: 1px solid var(--color-border);
                box-sizing: border-box;
            }
            .regras-alocacao-list .regras-alocacao-item:last-child { border-bottom: none; }
            .regras-alocacao-regra { font-weight: 600; }
            .regras-alocacao-desc { color: var(--color-text-secondary); }
            .avatar-edit-row { align-items: flex-start; }
            .avatar-edit-preview-wrap {
                flex-shrink: 0;
                width: 160px;
                height: 96px;
                border-radius: 8px;
                overflow: hidden;
                border: 1px solid var(--color-border);
                background: var(--color-background-subtle, #f3f4f6);
            }
            .avatar-edit-preview-img {
                display: block;
                width: 100%;
                height: 100%;
                object-fit: cover;
            }
            .avatar-edit-preview-initials {
                display: flex;
                align-items: center;
                justify-content: center;
                width: 100%;
                height: 100%;
                font-size: 1.35rem;
                font-weight: 600;
            }
        </style>
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

    <div class="container-full card-page">
        <?php if ($dados === null): ?>
            <div class="display-flex row row-full padding-x-md padding-y-md border-bottom gap-y-md">
                <div class="col">
                    <label class="status status-cancelled">Não foi possível carregar seus dados.</label>
                </div>
            </div>
            <div class="display-flex row row-full justify-end gap-y-md padding-x-md padding-y-md border-top">
                <button type="button" class="btn btn-secondary" id="voltar-btn">Voltar</button>
            </div>
        <?php else: ?>
            <form id="form-dados-pessoais" method="post" action="" style="width: 100%;">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">

                <div class="display-flex row row-full align-center padding-x-md padding-y-md border-bottom"><h2>Dados cadastrais</h2></div>

                <div class="display-flex row row-full padding-x-md padding-y-md border-bottom gap-y-md">
                    <div class="col">
                        <label for="edit-cpf">CPF</label>
                        <input type="text" id="edit-cpf" class="width-400 height-md readonly" value="<?php echo htmlspecialchars($dados['cpf'] ?? '', ENT_QUOTES); ?>" readonly>
                    </div>
                </div>

                <div class="display-flex row row-full padding-x-md padding-y-md border-bottom gap-y-md">
                    <div class="col">
                        <label for="edit-nome-completo">Nome completo</label>
                        <input type="text" id="edit-nome-completo" class="width-800 height-md readonly" value="<?php echo htmlspecialchars($nomeCompletoLeitura, ENT_QUOTES, 'UTF-8'); ?>" readonly aria-readonly="true" title="Somente leitura">
                    </div>
                </div>

                <div class="display-flex row row-full padding-x-md padding-y-md border-bottom gap-y-md">
                    <div class="col">
                        <label for="edit-email">E-mail</label>
                        <input type="email" id="edit-email" name="email" class="width-600 height-md" value="<?php echo htmlspecialchars($dados['email'] ?? '', ENT_QUOTES); ?>" maxlength="255">
                    </div>
                    <div class="col">
                        <label for="edit-telefone">Telefone</label>
                        <input type="text" id="edit-telefone" name="telefone" class="width-300 height-md" value="<?php echo htmlspecialchars($dados['telefone'] ?? '', ENT_QUOTES); ?>" maxlength="50">
                    </div>
                    <div class="col">
                        <label for="edit-sexo">Sexo</label>
                        <select id="edit-sexo" name="sexo" class="width-300 height-md">
                            <option value="">Selecione</option>
                            <option value="Feminino"<?php echo (($dados['sexo'] ?? '') === 'Feminino') ? ' selected' : ''; ?>>Feminino</option>
                            <option value="Masculino"<?php echo (($dados['sexo'] ?? '') === 'Masculino') ? ' selected' : ''; ?>>Masculino</option>
                            <option value="Outro"<?php echo (($dados['sexo'] ?? '') === 'Outro') ? ' selected' : ''; ?>>Outro</option>
                        </select>
                    </div>
                </div>

                <?php if (!$isUsuarioExterno): ?>
                    <?php
                    $currentAvatarSlug = $dados['avatar_slug'] ?? AvatarCatalog::getDefaultSlug();
                    $previewIsInitials = AvatarCatalog::isInitialsSlug($currentAvatarSlug);
                    $previewImgUrl = $previewIsInitials ? '' : AvatarCatalog::urlForSlug($currentAvatarSlug);
                    ?>
                    <div class="display-flex row row-full align-center padding-x-md padding-y-md border-bottom"><h2>Avatar</h2></div>
                    <div class="display-flex row row-full padding-x-md padding-y-md border-bottom gap-y-md avatar-edit-row">
                        <div class="col">
                            <div class="display-flex row row-full gap-y-md flex-wrap" style="align-items: flex-start; gap: 16px;">
                                <div class="avatar-edit-preview-wrap" id="avatar-preview-wrap" aria-live="polite">
                                    <img id="avatar-preview-img" class="avatar-edit-preview-img" src="<?php echo $previewIsInitials ? '' : htmlspecialchars($previewImgUrl, ENT_QUOTES, 'UTF-8'); ?>" alt="" width="96" height="96"<?php echo $previewIsInitials ? ' hidden' : ''; ?>>
                                    <span id="avatar-preview-initials" class="avatar-edit-preview-initials" style="background-color: <?php echo htmlspecialchars($avatarPreviewPalette['bg'], ENT_QUOTES, 'UTF-8'); ?>; color: <?php echo htmlspecialchars($avatarPreviewPalette['fg'], ENT_QUOTES, 'UTF-8'); ?>;"<?php echo $previewIsInitials ? '' : ' hidden'; ?>><?php echo htmlspecialchars($avatarPreviewInitials, ENT_QUOTES, 'UTF-8'); ?></span>
                                </div>
                                <div style="min-width: 0; flex: 1;">
                                    <label for="edit-avatar-slug">Imagem do perfil</label>
                                    <select id="edit-avatar-slug" name="avatar_slug" class="width-400 height-md" aria-describedby="avatar-slug-hint">
                                        <?php foreach (AvatarCatalog::listForUi() as $av): ?>
                                            <option value="<?php echo htmlspecialchars($av['slug'], ENT_QUOTES, 'UTF-8'); ?>"<?php echo ($currentAvatarSlug === $av['slug']) ? ' selected' : ''; ?>><?php echo htmlspecialchars($av['label'], ENT_QUOTES, 'UTF-8'); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if (!$isUsuarioExterno): ?>
                    <div class="display-flex row row-full padding-x-md padding-y-md border-bottom gap-y-md">
                        <div class="col">
                            <label for="edit-cargo">Cargo</label>
                            <select id="edit-cargo" class="width-400 height-md readonly" data-readonly="true" title="Somente leitura" aria-readonly="true" disabled tabindex="-1">
                                <option value="">Selecione (opcional)</option>
                                <?php foreach ($cargoOpcoes as $opt): ?>
                                    <option value="<?php echo htmlspecialchars($opt, ENT_QUOTES); ?>"<?php echo ($dados['cargo'] ?? '') === $opt ? ' selected' : ''; ?>><?php echo htmlspecialchars($opt); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col">
                            <label for="edit-setor">Setor</label>
                            <select id="edit-setor" class="width-400 height-md readonly" data-readonly="true" title="Somente leitura" aria-readonly="true" disabled tabindex="-1">
                                <option value="">Selecione (opcional)</option>
                                <?php foreach ($setorOpcoes as $opt): ?>
                                    <option value="<?php echo htmlspecialchars($opt, ENT_QUOTES); ?>"<?php echo ($dados['instituicao'] ?? '') === $opt ? ' selected' : ''; ?>><?php echo htmlspecialchars($opt); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="display-flex row row-full align-center padding-x-md padding-y-md border-bottom"><h2>Informações do perfil (somente leitura)</h2></div>

                    <div class="display-flex row row-full padding-x-md padding-y-md border-bottom gap-y-md">
                            <div class="col">
                                <label for="edit-perfis">Perfis vinculados</label>
                                <input type="text" id="edit-perfis" class="width-800 height-md readonly" value="<?php echo htmlspecialchars($perfisTexto, ENT_QUOTES); ?>" readonly>
                            </div>
                            <div class="col">
                                <label for="edit-ultima-dados">Última alteração de dados</label>
                                <input type="text" id="edit-ultima-dados" class="width-400 height-md readonly" value="<?php echo htmlspecialchars($ultimaAlteracaoDadosLower, ENT_QUOTES); ?>" readonly>
                            </div>
                    </div>

                    <div class="display-flex row row-full align-center padding-x-md padding-y-md border-bottom"><h2>Regras de alocação definidas para você</h2></div>

                    <div class="display-flex row row-full padding-x-md padding-y-md border-bottom gap-y-md">
                            <div class="col full-line">
                                <?php if (count($regrasAlocacao) > 0): ?>
                                    <div class="regras-alocacao-list" style="width: 100%;">
                                        <?php foreach ($regrasAlocacao as $idx => $regra): ?>
                                            <div class="col full-line register regras-alocacao-item">
                                                <span class="regras-alocacao-regra"><?php echo htmlspecialchars($regra['regra'] ?? '', ENT_QUOTES); ?></span>
                                                <?php if (!empty(trim((string) ($regra['descricao'] ?? '')))): ?>
                                                    <span class="regras-alocacao-desc"> — <?php echo htmlspecialchars($regra['descricao'], ENT_QUOTES); ?></span>
                                                <?php endif; ?>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <span class="minor-info">Nenhuma regra de alocação definida para seu usuário.</span>
                                <?php endif; ?>
                            </div>
                    </div>
                <?php endif; ?>

                <div class="display-flex row row-full justify-end gap-y-md padding-x-md padding-y-md border-top">
                    <button type="button" class="btn btn-secondary" id="voltar-btn">Voltar</button>
                    <button type="submit" class="btn btn-primary" id="salvar-btn" disabled>Salvar alterações</button>
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

    <?php if ($dados !== null): ?>
    <script>
        window.__ALTERAR_DADOS_CTX = {
            serverError: <?php echo json_encode($mensagemErro, JSON_UNESCAPED_UNICODE); ?>,
            serverSuccess: <?php echo json_encode($mensagemSucesso, JSON_UNESCAPED_UNICODE); ?>,
            interno: <?php echo json_encode(!$isUsuarioExterno, JSON_UNESCAPED_UNICODE); ?>,
            initial: {
                email: <?php echo json_encode($dados['email'] ?? '', JSON_UNESCAPED_UNICODE); ?>,
                telefone: <?php echo json_encode($dados['telefone'] ?? '', JSON_UNESCAPED_UNICODE); ?>,
                sexo: <?php echo json_encode($dados['sexo'] ?? '', JSON_UNESCAPED_UNICODE); ?>,
                avatarSlug: <?php echo json_encode($avatarSlugForJs, JSON_UNESCAPED_UNICODE); ?>
            },
            avatarEntries: <?php echo json_encode($avatarUiEntries, JSON_UNESCAPED_UNICODE); ?>
        };
        if (window.App && App.AlterarDados && typeof App.AlterarDados.initialize === 'function') {
            App.AlterarDados.initialize(window.__ALTERAR_DADOS_CTX);
        }
    </script>
    <?php endif; ?>
</body>
</html>
