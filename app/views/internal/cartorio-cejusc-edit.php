<?php
declare(strict_types=1);
require_once __DIR__ . '/../../services/CejuscRegistrosService.php';
require_once __DIR__ . '/../../helpers/DeleteAuthorization.php';
$id = trim((string) ($_GET['id'] ?? ''));
$registro = $id !== '' ? (new CejuscRegistrosService(Conection::getInstance()->getConnection()))->find($id) : null;
if (!$registro) { http_response_code(404); echo 'Registro CEJUSC não encontrado.'; exit; }
$podeRemoverRegistroCejusc = DeleteAuthorization::usuarioPodeExcluirComoServidorOuChefia(trim((string) ($_SESSION['id_usuario'] ?? '')));
$assetsRoot = dirname(__DIR__, 3) . '/public/assets';
$v2ColorsCssV = @filemtime($assetsRoot . '/css/shared/v2-colors.css') ?: time();
$v2PageListCssV = @filemtime($assetsRoot . '/css/shared/v2-page-list.css') ?: time();
$cejuscCssV = @filemtime($assetsRoot . '/css/modules/cartorio/cejusc-v2.css') ?: time();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Edição Cejusc - Primeira Vara Crime de Ilhéus</title>
    <link rel="stylesheet" href="/assets/css/shared/v2-colors.css?v=<?php echo (int) $v2ColorsCssV; ?>">
    <link rel="stylesheet" href="/assets/css/shared/v2-page-list.css?v=<?php echo (int) $v2PageListCssV; ?>">
    <?php include __DIR__ . '/_internal_head_styles.php'; ?>
    <link rel="stylesheet" href="/assets/css/modules/cartorio/cejusc-v2.css?v=<?php echo (int) $cejuscCssV; ?>">
    <?php include __DIR__ . '/_toast_script.php'; ?>
    <script src="/assets/js/shared/cartorio-cejusc.js"></script>
</head>
<body class="page-internal cejusc-v2-page cejusc-form-page cejusc-edit-page">
    <div class="spacing-top-page"></div>
    <div class="header-page"><div class="location"><span class="all">Interno - Cartório - Cejusc -</span><span class="specific">Edição</span></div></div>
    <main class="container-full card-page">
        <div class="card-title"><h1 class="title-page">Editar cadastro Cejusc</h1></div>
        <form id="cejusc-form">
            <input type="hidden" name="id" value="<?php echo htmlspecialchars($id, ENT_QUOTES); ?>">
            <div class="cejusc-form-section-title"><h2>Informações do cadastro</h2></div>
            <div class="cejusc-form-grid">
                <div class="col filter cejusc-form-field cejusc-field-process">
                    <label for="filter-processo">Número do processo <span class="label-hint-obrigatorio">*obrigatório</span></label>
                    <input type="text" id="filter-processo" name="numero_processo" required inputmode="numeric" maxlength="25" value="<?php echo htmlspecialchars((string) $registro['numero_processo'], ENT_QUOTES); ?>">
                </div>
                <div class="col filter cejusc-form-field cejusc-field-class">
                    <label for="filter-classe">Classe <span class="label-hint-automatizado">*automatizado</span></label>
                    <input type="text" id="filter-classe" data-datajud-related="true" data-datajud-silent-loading="true" value="<?php echo htmlspecialchars((string) ($registro['classe_descricao'] ?? $registro['classe_codigo'] ?? ''), ENT_QUOTES); ?>" readonly>
                </div>
            </div>
            <input type="hidden" id="hidden-classe-codigo" name="classe_codigo" value="<?php echo htmlspecialchars((string) ($registro['classe_codigo'] ?? ''), ENT_QUOTES); ?>">
            <input type="hidden" id="hidden-classe-descricao" name="classe_descricao" value="<?php echo htmlspecialchars((string) ($registro['classe_descricao'] ?? ''), ENT_QUOTES); ?>">
            <div class="cejusc-form-grid">
                <div class="col filter cejusc-form-field cejusc-field-party">
                    <label for="parte">Nome da parte <span class="label-hint-obrigatorio">*obrigatório</span></label>
                    <input type="text" id="parte" name="nome_parte" required maxlength="180" autocomplete="name" value="<?php echo htmlspecialchars((string) $registro['nome_parte'], ENT_QUOTES); ?>">
                </div>
                <div class="col filter cejusc-form-field cejusc-field-date">
                    <label for="data-envio">Data de envio ao CEJUSC <span class="label-hint-obrigatorio">*obrigatório</span></label>
                    <input type="date" id="data-envio" name="data_envio_cejusc" required value="<?php echo htmlspecialchars((string) $registro['data_envio_cejusc'], ENT_QUOTES); ?>">
                </div>
            </div>
            <div class="card-buttons">
                <button type="button" class="btn btn-primary-outline" onclick="location.href='/frame.php?page=cejusc'">Voltar</button>
                <div class="display-flex row row-fit gap-y-md align-center justify-end cejusc-edit-actions">
                    <?php if ($podeRemoverRegistroCejusc): ?><button type="button" class="btn btn-danger" id="delete" data-cejusc-delete>Excluir</button><?php endif; ?>
                    <button class="btn btn-primary" type="submit">Salvar alterações</button>
                </div>
            </div>
        </form>
    </main>
    <div class="footer-page"><div class="location"><span class="all">Aplicativo web desenvolvido integralmente por </span><span class="specific">Jorge Reis Develop</span><span class="all">- Copyright 2018 a 2026 - Todos os direitos reservados.</span></div></div>
    <?php include __DIR__ . '/_datajud_scripts.php'; ?>
    <script>if (window.App && App.CartorioCejusc) App.CartorioCejusc.bindEdit(document.getElementById('cejusc-form'));</script>
</body>
</html>
