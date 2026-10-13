<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../repositories/CartasRecebidasSubtarefasRepository.php';
require_once __DIR__ . '/../../../helpers/CartasRecebidasUiCopy.php';
require_once __DIR__ . '/../../../helpers/RegistroMetadataHelper.php';

$idCarta = trim((string) ($row['IdCarta'] ?? ''));
$status = strtolower(trim((string) ($row['Status'] ?? '')));
$allSt = is_array($row['SubtarefasAll'] ?? null) ? $row['SubtarefasAll'] : [];
$subtarefasPendentes = [];
$subtarefaAtiva = null;
foreach ($allSt as $subtarefa) {
    if (!is_array($subtarefa) || !CartasRecebidasSubtarefasRepository::isTipoAtivo((string) ($subtarefa['tipo'] ?? ''))) {
        continue;
    }
    $subStatus = strtolower(trim((string) ($subtarefa['status'] ?? '')));
    if ($subStatus === 'em_andamento' && $subtarefaAtiva === null) {
        $subtarefaAtiva = $subtarefa;
    }
    if (in_array($subStatus, ['pendente', 'em_andamento'], true)) {
        $subtarefasPendentes[] = $subtarefa;
    }
}
usort($subtarefasPendentes, static function (array $a, array $b): int {
    return ((int) ($a['ordem'] ?? 0)) <=> ((int) ($b['ordem'] ?? 0));
});

$isConcluida = in_array($status, ['concluída', 'concluida', 'finalizada'], true);
$isCancelada = $status === 'cancelada';
$tituloStatus = CartasRecebidasUiCopy::resolverTituloTarefaListagem((string) ($row['Status'] ?? ''), $allSt);
$badgeText = $isConcluida ? 'Finalizada' : ($isCancelada ? 'Cancelada' : $tituloStatus);
$badgeClass = CartasRecebidasUiCopy::classeCssBadgeStatusTarefa((string) ($row['Status'] ?? ''));
if ($badgeClass === 'status-cancelled') $badgeClass = 'status-canceled';
$numero = trim((string) ($row['numero_processo'] ?? $row['Processo'] ?? ''));
$classe = trim((string) ($row['bloco_processo_classe_texto'] ?? ''));
$classeSemDados = !empty($row['bloco_processo_classe_not']);
$assunto = trim((string) ($row['bloco_processo_assunto_texto'] ?? ''));
$assuntoSemDados = !empty($row['bloco_processo_assunto_not']);
$prioridades = is_array($row['bloco_processo_prioridades'] ?? null) ? $row['bloco_processo_prioridades'] : [];
$prioridadeLabels = array_values(array_filter(array_map(static fn(array $p): string => trim((string) ($p['label'] ?? '')), $prioridades)));
$prioridadeTexto = implode(' | ', $prioridadeLabels);
$classeExibida = $classeSemDados ? 'Nenhuma classe cadastrada' : preg_replace('/^\s*\d+\s*-\s*/u', '', $classe);
$assuntoExibido = $assuntoSemDados ? 'Nenhum assunto cadastrado' : implode(' | ', array_map(static function (string $parte): string {
    return preg_replace('/^\s*\d+\s*-\s*/u', '', trim($parte)) ?: trim($parte);
}, preg_split('/\s*;\s*/u', $assunto) ?: []));
$destino = trim((string) ($row['Comarca'] ?? ''));
$uf = trim((string) ($row['Estado'] ?? ''));
$comarca = $destino !== '' ? $destino . ($uf !== '' ? ' - ' . $uf : '') : $uf;
$finalidade = trim((string) ($row['Finalidade'] ?? ''));
$prazo = trim((string) ($row['Prazo'] ?? ''));
$abrirHref = trim((string) ($row['bloco_processo_abrir_href'] ?? ''));
$abrirDisabled = !empty($row['bloco_processo_abrir_disabled']);
$metadata = RegistroMetadataHelper::fromListRow($row, 'cartas_recebidas', $registroListViewerContext, $connection);
$editHref = trim((string) ($metadata['edit_href'] ?? ''));
$showEdit = !empty($metadata['show_edit']) && $editHref !== '' && !$isConcluida && !$isCancelada;
$historico = RegistroMetadataHelper::resolveUltimaSubtarefaConcluida($allSt, $connection);
$historicoData = trim((string) ($historico['data'] ?? ''));
if ($historicoData !== '' && ($historicoTimestamp = strtotime($historicoData)) !== false) {
    $historicoData = date('d/m/Y H:i', $historicoTimestamp);
}
$historicoTexto = $historico !== null ? trim($historicoData . ((string) ($historico['editor'] ?? '') !== '' ? ' | ' . $historico['editor'] : '')) : '';
$subtarefaId = trim((string) ($subtarefaAtiva['id'] ?? ''));
$subtarefaTipo = trim((string) ($subtarefaAtiva['tipo'] ?? ''));
if ($isConcluida || $isCancelada) $subtarefasPendentes = [];
$showDelete = !empty($metadata['show_delete']);
?>
<div class="register-line full carta-expedida-v2-row carta-recebida-v2-row" data-carta-id="<?php echo htmlspecialchars($idCarta, ENT_QUOTES); ?>" data-registro-id="<?php echo htmlspecialchars($idCarta, ENT_QUOTES); ?>">
    <div class="left carta-expedida-left">
        <div class="carta-expedida-process-group">
            <div class="register-block carta-process-tools">
                <div class="line-cube carta-process-tool js-v2-copy-processo" role="button" tabindex="0" data-processo-numero="<?php echo htmlspecialchars($numero, ENT_QUOTES); ?>" title="Copiar número do processo" aria-label="Copiar número do processo">
                    <img src="/assets/icons/Coding-Apps-Website-Web-Form-Number-Field--Streamline-Ultimate.png" alt="">
                </div>
                <div class="line-cube box-open open carta-process-tool-open" title="<?php echo $abrirDisabled ? 'Processo não cadastrado no sistema' : 'Abrir processo'; ?>" aria-label="<?php echo $abrirDisabled ? 'Processo não cadastrado no sistema' : 'Abrir processo'; ?>" <?php if (!$abrirDisabled && $abrirHref !== ''): ?>role="button" tabindex="0" data-nav-href="<?php echo htmlspecialchars($abrirHref, ENT_QUOTES); ?>"<?php endif; ?>>
                    <img class="cube-icon" src="/assets/icons/Cartas-Resize-Expand-Corner--Streamline-Ultimate.png" alt="">
                </div>
            </div>
            <div class="register-block process carta-process-details">
                <div class="container-process container-sm">
                    <div class="container-line"><div class="left carta-process-number-line">
                        <div class="line-content box-number"><span class="content-number"><?php echo htmlspecialchars($numero, ENT_QUOTES); ?></span></div>
                        <?php if (!empty($row['processo_sigiloso'])): ?><div class="line-cube box-private" title="Sigiloso"><img class="cube-icon" src="/assets/icons/View-Off--Streamline-Ultimate.png" alt=""></div><?php endif; ?>
                        <?php if ($prioridadeTexto !== ''): ?><div class="line-cube box-open open" title="<?php echo htmlspecialchars($prioridadeTexto, ENT_QUOTES); ?>"><img class="cube-icon" src="/assets/icons/Alert-Diamond--Streamline-Ultimate.png" alt=""></div><?php endif; ?>
                    </div></div>
                    <div class="container-line"><div class="line-content double-line">
                        <span class="content-classe<?php echo $classeSemDados ? ' over' : ''; ?>"><?php echo htmlspecialchars((string) $classeExibida, ENT_QUOTES); ?></span>
                        <span class="content-assuntos<?php echo $assuntoSemDados ? ' over' : ''; ?>"><?php if ($prioridadeTexto !== ''): ?><strong><?php echo htmlspecialchars($prioridadeTexto, ENT_QUOTES); ?></strong><?php echo $assuntoExibido !== '' ? ' | ' : ''; ?><?php endif; ?><?php echo htmlspecialchars((string) $assuntoExibido, ENT_QUOTES); ?></span>
                    </div></div>
                </div>
            </div>
        </div>
    </div>
    <div class="right carta-expedida-right">
        <div class="register-block"><div class="register-block-content">
            <div class="register-block-content-item"><strong class="register-block-content-item-value"><?php echo htmlspecialchars($comarca, ENT_QUOTES); ?></strong></div>
            <div class="register-block-content-item"><span class="register-block-content-item-text">Finalidade:</span><span class="register-block-content-item-value"><?php echo htmlspecialchars($finalidade, ENT_QUOTES); ?></span></div>
            <div class="register-block-content-item"><span class="register-block-content-item-text">Prazo:</span><span class="register-block-content-item-value"><?php echo htmlspecialchars($prazo . ' dias', ENT_QUOTES); ?></span></div>
        </div></div>
        <div class="carta-expedida-status-group">
            <div class="register-block"><div class="register-block-status">
                <div class="badge-status-primary <?php echo htmlspecialchars($badgeClass, ENT_QUOTES); ?>"><span class="badge-status-text"><?php echo htmlspecialchars($badgeText, ENT_QUOTES); ?></span></div>
                <?php if ($showEdit): ?><button type="button" class="button-icon-right carta-row-action" data-nav-href="<?php echo htmlspecialchars($editHref, ENT_QUOTES); ?>" title="Editar" aria-label="Editar"><img src="/assets/icons/Eject-Button--Streamline-Ultimate.png" alt=""></button><?php endif; ?>
                <?php if ($showDelete): ?><button type="button" class="button-icon-right carta-row-action" data-action="excluir-registro" data-registro-id="<?php echo htmlspecialchars($idCarta, ENT_QUOTES); ?>" data-delete-endpoint="<?php echo htmlspecialchars((string) ($metadata['delete_endpoint'] ?? ''), ENT_QUOTES); ?>" title="<?php echo htmlspecialchars((string) ($metadata['delete_label'] ?? 'Excluir carta recebida'), ENT_QUOTES); ?>" aria-label="<?php echo htmlspecialchars((string) ($metadata['delete_label'] ?? 'Excluir carta recebida'), ENT_QUOTES); ?>"><img src="/assets/icons/Notes-Remove--Streamline-Ultimate.png" alt=""></button><?php endif; ?>
                <?php if (!$isConcluida && !$isCancelada && $subtarefaAtiva !== null): ?><button type="button" class="button-icon-internal carta-row-action btn-concluir-subtarefa" data-subtarefa-id="<?php echo htmlspecialchars($subtarefaId, ENT_QUOTES); ?>" data-subtarefa-tipo="<?php echo htmlspecialchars($subtarefaTipo, ENT_QUOTES); ?>" title="<?php echo htmlspecialchars(CartasRecebidasUiCopy::tituloBotaoPorTipo($subtarefaTipo), ENT_QUOTES); ?>" aria-label="<?php echo htmlspecialchars(CartasRecebidasUiCopy::tituloBotaoPorTipo($subtarefaTipo), ENT_QUOTES); ?>"><img src="/assets/icons/Button-Play-1--Streamline-Ultimate.png" alt=""></button><?php endif; ?>
                <?php if ($subtarefasPendentes !== []): ?><label class="button-table carta-row-action" title="Expandir etapas"><input type="radio" name="open-subtask" value="<?php echo htmlspecialchars($idCarta, ENT_QUOTES); ?>" aria-controls="subtask-<?php echo htmlspecialchars($idCarta, ENT_QUOTES); ?>" aria-expanded="false"><i class="fi fi-rr-angle-small-down"></i></label><?php endif; ?>
            </div></div>
            <div class="block-separator-line"></div>
            <div class="container-historical container-sm media-1024"><div class="container-line"><div class="line-content double-line">
                <span class="content-classe">Última movimentação</span>
                <span class="content-assuntos<?php echo $historicoTexto === '' ? ' over' : ''; ?>"><?php echo htmlspecialchars($historicoTexto !== '' ? $historicoTexto : 'Nenhuma movimentação registrada', ENT_QUOTES); ?></span>
            </div></div></div>
        </div>
    </div>
</div>
<?php if ($subtarefasPendentes !== []): ?>
<div class="display-flex col col-full subtask subtask-collapsed" id="subtask-<?php echo htmlspecialchars($idCarta, ENT_QUOTES); ?>" aria-hidden="true">
    <?php foreach ($subtarefasPendentes as $st):
        $stId = (string) ($st['id'] ?? '');
        $stTipo = trim((string) ($st['tipo'] ?? ''));
        $stStatus = strtolower(trim((string) ($st['status'] ?? '')));
        $stLabel = CartasRecebidasUiCopy::textoSpanStatusSubtarefa($stTipo, $stStatus);
        $btnTitle = CartasRecebidasUiCopy::tituloBotaoPorTipo($stTipo);
        $statusClass = CartasRecebidasUiCopy::classeCssSpanStatusSubtarefa($stStatus);
    ?>
    <div class="display-flex row row-full justify-between subtask padding-x-sm padding-y-sm border-top" data-subtarefa-id="<?php echo htmlspecialchars($stId, ENT_QUOTES); ?>" data-subtarefa-tipo="<?php echo htmlspecialchars($stTipo, ENT_QUOTES); ?>">
        <div class="display-flex row row-fit justify-start gap-y-sm align-center subtask-row-fields"><div class="col task-status"><div class="padding-x-lg height-sm status <?php echo htmlspecialchars($statusClass, ENT_QUOTES); ?>"><?php echo htmlspecialchars($stLabel, ENT_QUOTES); ?></div></div></div>
        <div class="display-flex row row-fit justify-end align-center gap-y-sm"><button type="button" class="btn btn-primary height-sm btn-concluir-subtarefa" data-subtarefa-id="<?php echo htmlspecialchars($stId, ENT_QUOTES); ?>" data-subtarefa-tipo="<?php echo htmlspecialchars($stTipo, ENT_QUOTES); ?>" title="<?php echo htmlspecialchars($btnTitle, ENT_QUOTES); ?>"<?php echo $stStatus === 'em_andamento' ? '' : ' disabled'; ?>><span class="value-button"><?php echo htmlspecialchars($btnTitle, ENT_QUOTES); ?></span></button></div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>
