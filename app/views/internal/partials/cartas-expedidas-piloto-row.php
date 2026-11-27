<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../helpers/CartasExpedidasTempoEtapa.php';
require_once __DIR__ . '/../../../helpers/ProcessoNivelSigilo.php';

$rotuloSigilo = ProcessoNivelSigilo::rotulo($sigiloso, $row['processo_nivel_sigilo'] ?? null);

$prior = (string)($prioridadesPiloto[$idCarta] ?? '');
$priorLabel = CartasExpedidasPrioridade::ROTULOS[$prior] ?? 'Sem prioridade definida';
$priorIcon = CartasExpedidasPrioridade::ICONES[$prior] ?? '·';
$numeroVisivel = $numero !== '' ? $numero : trim((string)($row['Processo'] ?? ''));
$canCancel = !empty($_SESSION['id_usuario']) && !$isTerminal;
$detailId = 'ce-detail-' . $idCarta;
$etapaPiloto = (string) ($row['EtapaEfetiva'] ?? '');
$agoraPiloto = $ceAgora ?? time();
$inicioEtapa = !$isTerminal && !$fluxoInconsistente
    ? CartasExpedidasTempoEtapa::inicio($etapaPiloto, $row['Data'] ?? null, $row['SubtarefasAll'] ?? [])
    : null;
if ($inicioEtapa !== null && $inicioEtapa > $agoraPiloto) $inicioEtapa = null;
$prioridadesDoProcesso = $temPrioridadeVisual ? implode(', ', array_unique($prioridadesTexto)) : '';
$responsavelPilotoId = !$fluxoInconsistente ? match ($etapaPiloto) {
    'envio' => trim((string) ($responsavelRegraEnvio['id'] ?? '')),
    'devolucao' => trim((string) ($row['CriadoPor'] ?? '')),
    default => '',
} : '';
$podeGerenciarCarta = $canDelete || ($usuarioPiloto !== '' && $usuarioPiloto === $responsavelPilotoId);
$restricaoGerenciarCarta = 'Disponível apenas para o responsável da etapa, Chefia ou Admin';
$rotuloResponsavelPiloto = match ($etapaPiloto) {
    'envio' => 'Responsável pelo envio',
    'devolucao' => 'Responsável pelo cumprimento',
    default => 'Responsável',
};
$nomeResponsavelPiloto = $responsavelPilotoId !== '' ? match ($etapaPiloto) {
    'envio' => $nomeResponsavelEnvio,
    'devolucao' => trim((string) ($row['CriadoPorNome'] ?? '')),
    default => '',
} : '';
$partesResponsavel = preg_split('/\s+/u', $nomeResponsavelPiloto, -1, PREG_SPLIT_NO_EMPTY);
$nomeCurtoResponsavelPiloto = match (count($partesResponsavel ?: [])) {
    0 => '',
    1 => $partesResponsavel[0],
    default => $partesResponsavel[0] . ' ' . $partesResponsavel[count($partesResponsavel) - 1],
};
?>
<div class="ce-grid ce-entry">
  <div class="ce-process">
    <?php if ($numeroVisivel !== ''): ?><button class="ce-cnj" type="button" data-ce-copy="<?= $e($numeroVisivel) ?>" title="Copiar número do processo" aria-label="<?= $e('Copiar número '.$numeroVisivel) ?>"><?= $e($numeroVisivel) ?></button><?php else: ?>—<?php endif; ?>
    <?php if ($rotuloSigilo !== null): ?><span class="ce-double-tag ce-process-secrecy" title="<?= $e($rotuloSigilo) ?>"><span>Sigilo</span><span><?= $e($rotuloSigilo) ?></span></span><?php endif; ?>
    <?php if ($prioridadesDoProcesso !== ''): ?>
      <span class="ce-double-tag ce-process-priority" title="<?= $e($prioridadesDoProcesso) ?>"><span>Prioridade</span><span class="ce-process-priority-values"><?= $e($prioridadesDoProcesso) ?></span></span>
    <?php endif; ?>
    <?php if ($groupKey==='conferir'): ?><span class="ce-tag">Etapa a conferir</span><?php endif; ?>
  </div>
  <div class="ce-destino"><span class="ce-visually-hidden">Destino: </span><?= $e($destino ?: '—') ?></div>
  <div class="ce-finalidade" title="<?= $e($finalidade) ?>"><span class="ce-visually-hidden">Finalidade: </span><?= $e($finalidade ?: '—') ?></div>
  <div class="ce-prazo"><span class="ce-visually-hidden">Prazo total: </span><?= $e($prazo !== '' ? $prazo.' dias' : '—') ?><?php if ($prazoRestanteTexto !== ''): ?><small class="<?= $prazoRestanteAlerta?'danger':'' ?>"><?= $e($prazoRestanteTexto) ?></small><?php endif; ?></div>
  <div class="ce-assignee"><span class="ce-visually-hidden"><?= $e($rotuloResponsavelPiloto) ?>: </span><?= $e($nomeCurtoResponsavelPiloto ?: '—') ?></div>
  <span class="ce-priority p-<?= $e($prior) ?>" role="img" title="<?= $e($priorLabel) ?>" aria-label="<?= $e($priorLabel) ?>"><?= $e($priorIcon) ?></span>
  <div class="ce-stage-time">
    <?php if ($inicioEtapa !== null): ?>
      <span class="ce-stage-badge" title="<?= $e(($etapaPiloto === 'envio' ? 'Desde o cadastro: ' : 'Desde o envio: ') . date('d/m/Y H:i', $inicioEtapa)) ?>">
        <svg aria-hidden="true" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"><circle cx="10" cy="10" r="7.5"/><path d="M10 5.5V10l2.9 1.7"/></svg>
        <span class="ce-visually-hidden">Tempo na etapa: </span><span data-ce-stage-seconds="<?= $agoraPiloto - $inicioEtapa ?>"><?= $e(CartasExpedidasTempoEtapa::formatar($inicioEtapa, $agoraPiloto)) ?></span>
      </span>
    <?php else: ?><span class="ce-stage-missing" title="Tempo na etapa indisponível">—</span><?php endif; ?>
  </div>
  <div class="ce-row-actions">
    <?php if ($mostrarContinuar && $subtarefaAtivaId !== ''): ?><button type="button" class="ce-button small" data-ce-action="continuar" data-id="<?= $e($subtarefaAtivaId) ?>"><?= $isAguardandoCumprimento?'Juntar':'Enviar' ?></button><?php endif; ?>
    <details class="ce-dropdown"><summary aria-label="<?= $e('Ações da carta '.$numeroVisivel) ?>">⋯</summary>
    <div><button type="button" data-ce-details="<?= $e($detailId) ?>">Ver detalhes</button>
    <?php if (!$abrirDisabled && $abrirHref !== ''): ?><a href="<?= $e($abrirHref) ?>">Abrir processo</a><?php endif; ?>
    <?php if ($mostrarCopiaWhatsapp): ?><button type="button" data-ce-copy="<?= $e($whatsappTexto) ?>">Texto para WhatsApp</button><?php endif; ?></div></details>
  </div>
</div>
<template id="<?= $e($detailId) ?>">
 <div class="ce-detail">
  <p class="ce-status"><span class="ce-tag"><?= $e($statusVisivelTexto) ?></span></p>
  <p class="ce-detail-number"><?= $e($numeroVisivel) ?> <?php if ($rotuloSigilo !== null): ?><span class="ce-double-tag ce-process-secrecy" title="<?= $e($rotuloSigilo) ?>"><span>Sigilo</span><span><?= $e($rotuloSigilo) ?></span></span><?php endif; ?></p>
  <div class="ce-tabs" role="tablist" aria-label="Informações da carta">
    <button type="button" id="ce-tab-resumo" role="tab" aria-controls="ce-resumo" aria-selected="true" data-ce-tab>Resumo</button>
    <button type="button" id="ce-tab-historico" role="tab" aria-controls="ce-historico" aria-selected="false" tabindex="-1" data-ce-tab>Histórico (<?= count($historicoLinhas) ?>)</button>
  </div>
  <section class="ce-tab-body" id="ce-resumo" role="tabpanel" aria-labelledby="ce-tab-resumo">
    <dl><dt>Destino</dt><dd><?= $e($destino ?: '—') ?></dd><dt>Finalidade</dt><dd><?= $e($finalidade ?: '—') ?></dd>
      <dt>Prioridade da carta</dt><dd><span class="ce-priority p-<?= $e($prior) ?>" aria-hidden="true"><?= $e($priorIcon) ?></span> <?= $e($priorLabel) ?></dd>
      <?php if ($nomeCurtoResponsavelPiloto !== ''): ?><dt><?= $e($rotuloResponsavelPiloto) ?></dt><dd><?= $e($nomeCurtoResponsavelPiloto) ?></dd><?php endif; ?>
      <?php if ($classeTexto !== ''): ?><dt>Classe processual</dt><dd><?= $e($classeTexto) ?></dd><?php endif; ?>
      <?php if ($assuntoTexto !== ''): ?><dt>Assuntos</dt><dd><?= $e($assuntoTexto) ?></dd><?php endif; ?>
      <?php if ($temPrioridadeVisual): ?><dt>Prioridade do processo</dt><dd><?= $e(implode(', ',$prioridadesTexto)) ?></dd><?php endif; ?>
    </dl>
    <div class="ce-metrics"><div><small>Prazo total</small><strong><?= $e($prazo !== '' ? $prazo.' dias' : '—') ?></strong></div><div><small>Dias restantes</small><strong class="<?= $prazoRestanteAlerta?'danger':'' ?>"><?= $e($prazoRestanteTexto!==''?trim($prazoRestanteTexto,'()'):'—') ?></strong></div></div>
  </section>
  <section class="ce-tab-body" id="ce-historico" role="tabpanel" aria-labelledby="ce-tab-historico" hidden>
    <?php if ($historicoLinhas === []): ?><p>Nenhuma movimentação registrada.</p><?php else: ?>
    <ol class="ce-history"><?php foreach (array_reverse($historicoLinhas) as $linha): ?><li><strong><?= $e($linha['rotulo'] ?? $linha['etapa'] ?? $linha['titulo'] ?? 'Movimentação') ?></strong><br><?= $e($linha['data_hora'] ?? '') ?> <?= $e($linha['autor'] ?? '') ?></li><?php endforeach; ?></ol>
    <?php endif; ?>
  </section>
  <footer class="ce-detail-actions">
    <?php if ($showEdit): ?>
      <?php if ($podeGerenciarCarta): ?><a href="?page=cartasexpedidasedit&amp;id=<?= rawurlencode($idCarta) ?>&amp;piloto=1" class="ce-button" data-ce-frame data-title="Editar carta">Editar</a>
      <?php else: ?><button type="button" class="ce-button" disabled title="<?= $e($restricaoGerenciarCarta) ?>">Editar</button><?php endif; ?>
    <?php endif; ?>
    <?php if ($mostrarReiniciar && !$fluxoInconsistente): ?><button class="ce-button" type="button" <?php if ($podeGerenciarCarta): ?>data-ce-action="reiniciar" data-id="<?= $e($idCarta) ?>"<?php else: ?>disabled title="<?= $e($restricaoGerenciarCarta) ?>"<?php endif; ?>>Reiniciar envio</button><?php endif; ?>
    <?php if ($canCancel): ?><button class="ce-button danger" type="button" <?php if ($podeGerenciarCarta): ?>data-ce-action="cancelar" data-id="<?= $e($idCarta) ?>"<?php else: ?>disabled title="<?= $e($restricaoGerenciarCarta) ?>"<?php endif; ?>>Cancelar carta</button><?php endif; ?>
    <?php if ($canDelete): ?><button class="ce-button danger" type="button" data-ce-action="excluir" data-id="<?= $e($idCarta) ?>">Excluir carta</button><?php endif; ?>
  </footer>
 </div>
</template>
