<?php
declare(strict_types=1);

/** @var array<string, mixed>|null $registroMetadata */
/** @var array<string, mixed>|null $oficioMetadata */
$meta = null;
if (isset($registroMetadata) && is_array($registroMetadata)) {
    $meta = $registroMetadata;
} elseif (isset($oficioMetadata) && is_array($oficioMetadata)) {
    $meta = $oficioMetadata;
}
if ($meta === null) {
    return;
}

$userPrefix = trim((string) ($meta['user_prefix'] ?? 'Cadastrado por'));
$criadorNome = trim((string) ($meta['criador_nome'] ?? ''));
require_once __DIR__ . '/../../helpers/RegistroMetadataHelper.php';
$linhaAutorFixa = trim((string) ($meta['linha_autor'] ?? ''));
$linhaAutor = $linhaAutorFixa !== ''
    ? $linhaAutorFixa
    : RegistroMetadataHelper::formatarLinhaAutor($userPrefix, $criadorNome);
$dataCadastro = trim((string) ($meta['data_cadastro_texto'] ?? ''));
$dataEventoClasse = trim((string) ($meta['data_evento_classe'] ?? ''));
$dataEventoTexto = trim((string) ($meta['data_evento_texto'] ?? ''));
$idRegistro = trim((string) ($meta['id'] ?? ''));
$showEdit = !empty($meta['show_edit']);
$showCancel = !empty($meta['show_cancel']);
$editHref = trim((string) ($meta['edit_href'] ?? ''));
$cancelEndpoint = trim((string) ($meta['cancel_endpoint'] ?? ''));
$editLabel = trim((string) ($meta['edit_label'] ?? 'Editar registro'));
$cancelLabel = trim((string) ($meta['cancel_label'] ?? 'Cancelar registro'));
$idTitle = $idRegistro !== '' ? 'ID ' . $idRegistro : '';

$mostrarEvento = ($dataEventoClasse === 'updated' || $dataEventoClasse === 'canceled') && $dataEventoTexto !== '';
$classesEvento = $mostrarEvento ? 'data-update-date ' . $dataEventoClasse : '';

$mostrarEdit = $showEdit && $editHref !== '';
$mostrarId = $idRegistro !== '';
$mostrarCancel = $showCancel && $cancelEndpoint !== '' && $idRegistro !== '';
$mostrarAcoes = $mostrarEdit || $mostrarId || $mostrarCancel;
?>
<div class="container-data-create">
    <div class="data-create-user"><?php echo htmlspecialchars($linhaAutor, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php if ($dataCadastro !== ''): ?>
    <div class="data-create-date"><?php echo htmlspecialchars($dataCadastro, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>
    <div class="data-create-buttons">
        <div class="left">
            <?php if ($mostrarEvento): ?>
            <div class="<?php echo htmlspecialchars($classesEvento, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($dataEventoTexto, ENT_QUOTES, 'UTF-8'); ?></div>
            <?php endif; ?>
        </div>
        <?php if ($mostrarAcoes): ?>
        <div class="right">
            <?php if ($mostrarEdit): ?>
            <button type="button"
                class="btn-mini processo-bloco-tooltip processo-bloco-tooltip--btn"
                data-action="editar-registro editar-oficio"
                data-edit-href="<?php echo htmlspecialchars($editHref, ENT_QUOTES, 'UTF-8'); ?>"
                data-title="<?php echo htmlspecialchars($editLabel, ENT_QUOTES, 'UTF-8'); ?>"
                aria-label="<?php echo htmlspecialchars($editLabel, ENT_QUOTES, 'UTF-8'); ?>">
                <img class="btn-mini-icon" src="/assets/icons/Notes-Edit--Streamline-Ultimate.png" alt="">
            </button>
            <?php endif; ?>
            <?php if ($mostrarId): ?>
            <button type="button"
                class="btn-mini processo-bloco-tooltip processo-bloco-tooltip--btn btn-mini-id"
                data-title="<?php echo htmlspecialchars($idTitle, ENT_QUOTES, 'UTF-8'); ?>"
                aria-label="<?php echo htmlspecialchars($idTitle, ENT_QUOTES, 'UTF-8'); ?>">
                <img class="btn-mini-icon" src="/assets/icons/Notes-Question--Streamline-Ultimate.png" alt="">
            </button>
            <?php endif; ?>
            <?php if ($mostrarCancel): ?>
            <button type="button"
                class="btn-mini processo-bloco-tooltip processo-bloco-tooltip--btn"
                data-action="cancelar-registro cancelar-oficio"
                data-registro-id="<?php echo htmlspecialchars($idRegistro, ENT_QUOTES, 'UTF-8'); ?>"
                data-oficio-id="<?php echo htmlspecialchars($idRegistro, ENT_QUOTES, 'UTF-8'); ?>"
                data-cancel-endpoint="<?php echo htmlspecialchars($cancelEndpoint, ENT_QUOTES, 'UTF-8'); ?>"
                data-title="<?php echo htmlspecialchars($cancelLabel, ENT_QUOTES, 'UTF-8'); ?>"
                aria-label="<?php echo htmlspecialchars($cancelLabel, ENT_QUOTES, 'UTF-8'); ?>">
                <img class="btn-mini-icon" src="/assets/icons/Notes-Remove--Streamline-Ultimate.png" alt="">
            </button>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
</div>
