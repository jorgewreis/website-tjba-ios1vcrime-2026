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

require_once __DIR__ . '/../../helpers/RegistroMetadataHelper.php';
$idRegistro = trim((string) ($meta['id'] ?? ''));
$showEdit = !empty($meta['show_edit']);
$showCancel = !empty($meta['show_cancel']);
$showDelete = !empty($meta['show_delete']);
$editHref = trim((string) ($meta['edit_href'] ?? ''));
$cancelEndpoint = trim((string) ($meta['cancel_endpoint'] ?? ''));
$deleteEndpoint = trim((string) ($meta['delete_endpoint'] ?? ''));
$editLabel = trim((string) ($meta['edit_label'] ?? 'Editar registro'));
$cancelLabel = trim((string) ($meta['cancel_label'] ?? 'Cancelar registro'));
$deleteLabel = trim((string) ($meta['delete_label'] ?? 'Remover registro'));
$idTitle = $idRegistro !== '' ? 'ID ' . $idRegistro : '';

$mostrarEdit = $showEdit && $editHref !== '';
$mostrarId = $idRegistro !== '';
$mostrarCancel = $showCancel && $cancelEndpoint !== '' && $idRegistro !== '';
$mostrarDelete = $showDelete && $deleteEndpoint !== '' && $idRegistro !== '';
$mostrarAcoes = $mostrarEdit || $mostrarId || $mostrarCancel || $mostrarDelete;
$historicoLinhas = is_array($meta['historico_linhas'] ?? null) ? $meta['historico_linhas'] : [];
?>
<div class="container-historical container-sm">
    <div class="container-line">
        <div class="line-content double-line">
            <span class="content-classe">Histórico de movimentações</span>
            <?php if ($historicoLinhas === []): ?>
                <span class="content-assuntos over">Nenhuma movimentação registrada</span>
            <?php else: ?>
                <?php foreach ($historicoLinhas as $hist):
                    if (!is_array($hist)) {
                        continue;
                    }
                    $dh = trim((string) ($hist['data_hora'] ?? ''));
                    $rotulo = trim((string) ($hist['rotulo'] ?? ''));
                    $autor = trim((string) ($hist['autor'] ?? ''));
                    $contentClass = trim((string) ($hist['content_class'] ?? ''));
                    $historicoTexto = '';
                    if ($dh !== '' && $rotulo !== '') {
                        $historicoTexto = '- ' . $dh . ' | ' . $rotulo;
                        if ($autor !== '') {
                            $historicoTexto .= ' por ' . $autor;
                        }
                    }
                    if ($historicoTexto === '') {
                        continue;
                    }
                ?>
                    <span class="content-assuntos<?php echo $contentClass !== '' ? ' ' . htmlspecialchars($contentClass, ENT_QUOTES, 'UTF-8') : ''; ?>"><?php echo htmlspecialchars($historicoTexto, ENT_QUOTES, 'UTF-8'); ?></span>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($mostrarAcoes): ?>
    <div class="data-create-buttons historical-actions">
        <div class="left"></div>
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

            <?php if ($mostrarDelete): ?>
            <button type="button"
                class="btn-mini processo-bloco-tooltip processo-bloco-tooltip--btn"
                data-action="excluir-registro"
                data-registro-id="<?php echo htmlspecialchars($idRegistro, ENT_QUOTES, 'UTF-8'); ?>"
                data-delete-endpoint="<?php echo htmlspecialchars($deleteEndpoint, ENT_QUOTES, 'UTF-8'); ?>"
                data-title="<?php echo htmlspecialchars($deleteLabel, ENT_QUOTES, 'UTF-8'); ?>"
                aria-label="<?php echo htmlspecialchars($deleteLabel, ENT_QUOTES, 'UTF-8'); ?>">
                <img class="btn-mini-icon" src="/assets/icons/Notes-Remove--Streamline-Ultimate.png" alt="">
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

            <?php if ($mostrarId): ?>
            <button type="button"
                class="btn-mini processo-bloco-tooltip processo-bloco-tooltip--btn btn-mini-id"
                data-title="<?php echo htmlspecialchars($idTitle, ENT_QUOTES, 'UTF-8'); ?>"
                aria-label="<?php echo htmlspecialchars($idTitle, ENT_QUOTES, 'UTF-8'); ?>">
                <img class="btn-mini-icon" src="/assets/icons/Notes-Question--Streamline-Ultimate.png" alt="">
            </button>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
</div>
