<?php
declare(strict_types=1);

/** @var array<string, mixed>|null $oficioMetadata */
if (!isset($oficioMetadata) || !is_array($oficioMetadata)) {
    return;
}

$criadorNome = trim((string) ($oficioMetadata['criador_nome'] ?? '—'));
$dataCadastro = trim((string) ($oficioMetadata['data_cadastro_texto'] ?? ''));
$dataEventoClasse = trim((string) ($oficioMetadata['data_evento_classe'] ?? ''));
$dataEventoTexto = trim((string) ($oficioMetadata['data_evento_texto'] ?? ''));
$idOficio = trim((string) ($oficioMetadata['id'] ?? ''));
$showEdit = !empty($oficioMetadata['show_edit']);
$showCancel = !empty($oficioMetadata['show_cancel']);
$editHref = trim((string) ($oficioMetadata['edit_href'] ?? ''));
$cancelEndpoint = trim((string) ($oficioMetadata['cancel_endpoint'] ?? ''));

$mostrarEvento = ($dataEventoClasse === 'updated' || $dataEventoClasse === 'canceled') && $dataEventoTexto !== '';
$classesEvento = $mostrarEvento ? 'data-update-date ' . $dataEventoClasse : '';

$mostrarAcoes = ($showEdit && $editHref !== '') || ($showCancel && $cancelEndpoint !== '' && $idOficio !== '');
?>
<div class="container-data-create">
    <div class="data-create-user">Ofício cadastrado por <?php echo htmlspecialchars($criadorNome, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php if ($dataCadastro !== ''): ?>
    <div class="data-create-date"><?php echo htmlspecialchars($dataCadastro, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>
    <div class="data-create-buttons">
        <div class="left">
            <?php if ($mostrarEvento): ?>
            <div class="<?php echo htmlspecialchars($classesEvento, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($dataEventoTexto, ENT_QUOTES, 'UTF-8'); ?></div>
            <?php endif; ?>
            <?php if ($idOficio !== ''): ?>
            <div class="data-id">Registrado sob ID: <?php echo htmlspecialchars($idOficio, ENT_QUOTES, 'UTF-8'); ?></div>
            <?php endif; ?>
        </div>
        <?php if ($mostrarAcoes): ?>
        <div class="right">
            <?php if ($showEdit && $editHref !== ''): ?>
            <button type="button"
                class="btn-mini tooltip"
                data-action="editar-oficio"
                data-edit-href="<?php echo htmlspecialchars($editHref, ENT_QUOTES, 'UTF-8'); ?>"
                data-title="Editar ofício"
                aria-label="Editar ofício">
                <img class="btn-mini-icon" src="/assets/icons/Notes-Edit--Streamline-Ultimate.png" alt="">
            </button>
            <?php endif; ?>
            <?php if ($showCancel && $cancelEndpoint !== '' && $idOficio !== ''): ?>
            <button type="button"
                class="btn-mini tooltip"
                data-action="cancelar-oficio"
                data-oficio-id="<?php echo htmlspecialchars($idOficio, ENT_QUOTES, 'UTF-8'); ?>"
                data-cancel-endpoint="<?php echo htmlspecialchars($cancelEndpoint, ENT_QUOTES, 'UTF-8'); ?>"
                data-title="Cancelar ofício"
                aria-label="Cancelar ofício">
                <img class="btn-mini-icon" src="/assets/icons/Notes-Remove--Streamline-Ultimate.png" alt="">
            </button>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
</div>
