<?php
declare(strict_types=1);

Security::requireAuthentication();
$fokusLawUserName = trim((string) ($_SESSION['nome'] ?? ''));
if ($fokusLawUserName === '' && isset($user)) {
    $fokusLawUserName = is_array($user)
        ? trim((string) ($user['nome'] ?? ''))
        : (is_object($user) ? trim((string) ($user->nome ?? '')) : '');
}
$fokusLawCss = dirname(__DIR__, 3) . '/public/assets/css/modules/fokuslaw/fokuslaw-module.css';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($fokusLawModuleTitle, ENT_QUOTES, 'UTF-8'); ?> | FokusLaw</title>
    <link rel="stylesheet" href="/assets/css/shared/v2-colors.css">
    <link rel="stylesheet" href="/assets/css/shared/v2-page-list.css">
    <?php include __DIR__ . '/_internal_head_css.php'; ?>
    <link rel="stylesheet" href="https://cdn-uicons.flaticon.com/3.0.0/uicons-regular-rounded/css/uicons-regular-rounded.css">
    <link rel="stylesheet" href="/assets/css/modules/fokuslaw/fokuslaw-module.css?v=<?php echo (int) (@filemtime($fokusLawCss) ?: time()); ?>">
</head>
<body class="page-internal fokuslaw-module-page">
    <div class="spacing-top-page"></div>
    <header class="header-page">
        <div class="location"><span class="all">Interno - FokusLaw -</span> <span class="specific"><?php echo htmlspecialchars($fokusLawModuleTitle, ENT_QUOTES, 'UTF-8'); ?></span></div>
    </header>

    <main class="container-full card-page fokuslaw-module-shell">
        <div class="card-title">
            <div>
                <h1 class="title-page"><?php echo htmlspecialchars($fokusLawModuleTitle, ENT_QUOTES, 'UTF-8'); ?></h1>
                <p class="fokuslaw-module-description"><?php echo htmlspecialchars($fokusLawModuleDescription, ENT_QUOTES, 'UTF-8'); ?></p>
            </div>
            <span class="fokuslaw-module-badge">Ambiente de desenvolvimento</span>
        </div>

        <section class="fokuslaw-module-content" aria-label="Conteúdo do módulo">
            <?php if (!empty($fokusLawModuleInteractive) && $fokusLawModuleTitle === 'Expedições'): ?>
            <div class="fokuslaw-toolbar"><input id="fl-search" type="search" placeholder="Buscar processo, destinatário ou documento"><button type="button" class="btn btn-primary" id="fl-new">Nova expedição</button></div>
            <div id="fl-message" role="status"></div>
            <div class="fokuslaw-table-wrap"><table class="fokuslaw-table"><thead><tr><th>Processo</th><th>Destinatário</th><th>Documento</th><th>Responsável</th><th>Status</th><th>Envio</th><th>Ações</th></tr></thead><tbody id="fl-rows"></tbody></table></div>
            <dialog id="fl-dialog"><form id="fl-form" method="dialog"><h2>Nova expedição</h2><label>Processo CNJ<input name="numero_processo" required maxlength="30" placeholder="0000000-00.0000.0.00.0000"></label><label>Destinatário<input name="destinatario" required maxlength="180"></label><label>Tipo de documento<input name="tipo_documento" required maxlength="120"></label><label>Descrição<input name="descricao_documento" maxlength="255"></label><label>Responsável<select name="responsavel_id" id="fl-responsavel"></select></label><label class="fokuslaw-checkbox"><input type="checkbox" name="is_teste" value="1"> Registro fictício de teste</label><div class="fokuslaw-dialog-actions"><button type="button" id="fl-close">Cancelar</button><button class="btn btn-primary" type="submit">Salvar</button></div></form></dialog>
            <script src="/assets/js/modules/fokuslaw/expedicoes.js?v=<?php echo (int) (@filemtime(dirname(__DIR__, 3) . '/public/assets/js/modules/fokuslaw/expedicoes.js') ?: time()); ?>"></script>
            <?php else: ?>
            <div class="fokuslaw-empty-state">
                <span class="fokuslaw-empty-icon" aria-hidden="true"><i class="<?php echo htmlspecialchars($fokusLawModuleIcon, ENT_QUOTES, 'UTF-8'); ?>"></i></span>
                <h2>Área preparada para desenvolvimento</h2>
                <p>O módulo está integrado à sua sessão de usuário. Os fluxos e os dados serão definidos antes da implementação das funcionalidades.</p>
                <?php if ($fokusLawUserName !== ''): ?><small>Usuário conectado: <?php echo htmlspecialchars($fokusLawUserName, ENT_QUOTES, 'UTF-8'); ?></small><?php endif; ?>
            </div>
            <?php endif; ?>
        </section>
    </main>
</body>
</html>
