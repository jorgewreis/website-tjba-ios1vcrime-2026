<?php

declare(strict_types=1);

/**
 * Card de listagem v2 — uma carta expedida.
 *
 * @var array<string, mixed> $row
 * @var array{usuario_id: string, perfis: list<string>} $registroListViewerContext
 * @var PDO $connection
 */

require_once __DIR__ . '/../../../repositories/CartasExpedidasSubtarefasRepository.php';
require_once __DIR__ . '/../../../helpers/CartasExpedidasUiCopy.php';
require_once __DIR__ . '/../../../helpers/RegistroMetadataHelper.php';

$idCarta = trim((string) ($row['IdCarta'] ?? ''));
$nomeResponsavelEnvio = isset($responsavelRegraEnvio) && class_exists(CartasExpedidasEnvioRegra::class, false)
    ? CartasExpedidasEnvioRegra::responsavelNaEtapa((string) ($row['EtapaEfetiva'] ?? ''), $responsavelRegraEnvio)
    : '';
$st = CartasExpedidasEstadoFluxo::normalizar((string) ($row['Status'] ?? ''));
$isAguardandoCumprimento = ($row['EtapaEfetiva'] ?? '') === 'devolucao' || $st === 'aguardando cumprimento';
/** @var list<array<string, mixed>> $allSt */
$allSt = is_array($row['SubtarefasAll'] ?? null) ? $row['SubtarefasAll'] : [];
$fluxoInconsistente = !empty($row['FluxoInconsistente']);
$subtarefaAtivaUi = $fluxoInconsistente ? null : CartasExpedidasSubtarefasRepository::resolverSubtarefaAtivaPorLista($allSt);

$isConcluida = in_array($st, ['concluída', 'concluida', 'finalizada'], true) || (($row['EtapaEfetiva'] ?? '') === 'encerrada' && $st !== 'cancelada');
$isCancelada = $st === 'cancelada';
$isTerminal = $isConcluida || $isCancelada;

$modeloEnvio = false;
$modeloDevolucao = false;
if (!$isTerminal && $subtarefaAtivaUi !== null) {
    $tipoAtivo = trim((string) ($subtarefaAtivaUi['tipo'] ?? ''));
    if ($tipoAtivo === CartasExpedidasSubtarefasRepository::TIPO_DEVOLUCAO) {
        $modeloDevolucao = true;
    } else {
        $modeloEnvio = true;
    }
} elseif (!$isTerminal && !$fluxoInconsistente && ($row['EtapaEfetiva'] ?? '') === 'envio') {
    $modeloEnvio = true;
}

$badgePrincipalTexto = 'Aguardando envio';
$badgePrincipalClasse = 'status-current';
$textoEtapa = CartasExpedidasUiCopy::resolverTituloTarefaListagem((string) ($row['Status'] ?? ''), $allSt, $subtarefaAtivaUi);
$classeEtapa = CartasExpedidasUiCopy::classeCssBadgeStatusTarefaListagem((string) ($row['Status'] ?? ''), $subtarefaAtivaUi);
$mostrarContinuar = false;

if ($isConcluida) {
    $badgePrincipalTexto = 'Finalizado';
    $badgePrincipalClasse = 'status-finished';
    $textoEtapa = '';
} elseif ($isCancelada) {
    $badgePrincipalTexto = 'Cancelada';
    $badgePrincipalClasse = 'status-canceled';
    $textoEtapa = '';
} elseif ($modeloDevolucao) {
    $badgePrincipalTexto = 'Pendente';
    $badgePrincipalClasse = 'status-pending';
    $mostrarContinuar = $subtarefaAtivaUi !== null;
} else {
    $mostrarContinuar = $subtarefaAtivaUi !== null;
}

if (!$isTerminal && $fluxoInconsistente) {
    $badgePrincipalTexto = 'Etapa a conferir';
    $badgePrincipalClasse = 'status-pending';
    $textoEtapa = 'Etapa a conferir';
    $classeEtapa = 'status-pending';
    $mostrarContinuar = false;
}

$numero = trim((string) ($row['numero_processo'] ?? ''));
$classeTexto = trim((string) ($row['bloco_processo_classe_texto'] ?? ''));
$classeValid = empty($row['bloco_processo_classe_not']);
$assuntoTexto = trim((string) ($row['bloco_processo_assunto_texto'] ?? ''));
$assuntoValid = empty($row['bloco_processo_assunto_not']);
/** @var list<array{codigo: string, label: string, icon: string}> $prioridades */
$prioridades = is_array($row['bloco_processo_prioridades'] ?? null) ? $row['bloco_processo_prioridades'] : [];
$sigiloso = !empty($row['processo_sigiloso']);
$abrirHref = trim((string) ($row['bloco_processo_abrir_href'] ?? ''));
$abrirDisabled = !empty($row['bloco_processo_abrir_disabled']);

$comarca = trim((string) ($row['Comarca'] ?? ''));
$estado = trim((string) ($row['Estado'] ?? ''));
$destino = $comarca !== '' ? $comarca . ($estado !== '' ? ' - ' . $estado : '') : $estado;
$finalidade = trim((string) ($row['Finalidade'] ?? ''));
$prazo = trim((string) ($row['Prazo'] ?? ''));

// Calcular dias restantes do prazo a partir da conclusão da subtarefa de ENVIO.
// Se a subtarefa de envio ainda não foi concluída (carta apenas cadastrada),
// o sufixo do prazo não é mostrado.
$prazoRestanteTexto = '';
$prazoRestanteAlerta = false;
$prazoDiasInt = is_numeric($prazo) ? (int) $prazo : 0;
if ($prazoDiasInt > 0 && $modeloDevolucao && !$isTerminal && !$fluxoInconsistente) {
    $envioConcluidoEm = '';
    foreach ($allSt as $subtarefa) {
        if (!is_array($subtarefa)) {
            continue;
        }
        $tipoSub = mb_strtolower(trim((string) ($subtarefa['tipo'] ?? '')), 'UTF-8');
        $statusSub = CartasExpedidasEstadoFluxo::normalizar((string) ($subtarefa['status'] ?? ''));
        if ($tipoSub === CartasExpedidasSubtarefasRepository::TIPO_ENVIO
            && in_array($statusSub, ['concluida', 'concluída'], true)) {
            $envioConcluidoEm = trim((string) ($subtarefa['updated_at'] ?? ''));
            break;
        }
    }
    if ($envioConcluidoEm !== '') {
        $tsEnvio = strtotime($envioConcluidoEm);
        if ($tsEnvio !== false) {
            try {
                $dataEnvio = new DateTimeImmutable(date('Y-m-d', $tsEnvio));
                $dataHoje = new DateTimeImmutable(date('Y-m-d'));
                $dataLimite = $dataEnvio->modify('+' . $prazoDiasInt . ' days');
                $intervalo = $dataHoje->diff($dataLimite);
                $diasRestantes = (int) $intervalo->days * ($intervalo->invert ? -1 : 1);
                if ($diasRestantes >= 0) {
                    $prazoRestanteTexto = '(' . $diasRestantes . ')';
                    $prazoRestanteAlerta = $diasRestantes < 10;
                } else {
                    $vencidoHa = abs($diasRestantes);
                    $unidade = $vencidoHa === 1 ? 'dia' : 'dias';
                    $prazoRestanteTexto = '(vencido há ' . $vencidoHa . ' ' . $unidade . ')';
                    $prazoRestanteAlerta = true;
                }
            } catch (\Exception $e) {
                $prazoRestanteTexto = '';
            }
        }
    }
}

$mostrarCartaParcial = false;
$cartaParcialLabel = '';
$cartaParcialValor = '';
$diasDevolucao = $prazo;
if ($isConcluida) {
    foreach ($allSt as $subtarefa) {
        if (!is_array($subtarefa)) {
            continue;
        }
        if (mb_strtolower(trim((string) ($subtarefa['tipo'] ?? '')), 'UTF-8') !== CartasExpedidasSubtarefasRepository::TIPO_DEVOLUCAO) {
            continue;
        }
        $stSub = CartasExpedidasEstadoFluxo::normalizar((string) ($subtarefa['status'] ?? ''));
        if ($stSub !== 'concluida' && $stSub !== 'concluída') {
            continue;
        }
        $dadosJson = $subtarefa['dados_especificos'] ?? null;
        $dados = is_string($dadosJson) ? json_decode($dadosJson, true) : (is_array($dadosJson) ? $dadosJson : []);
        if (!is_array($dados)) {
            $dados = [];
        }
        $resultado = trim((string) ($dados['resultado'] ?? ''));
        if ($resultado === 'cumprida_parcialmente') {
            $mostrarCartaParcial = true;
            $cartaParcialLabel = 'Cumprida parcialmente';
            $cartaParcialValor = $finalidade;
            $diasDevolucao = trim((string) ($dados['dias_cumprimento'] ?? $dados['prazo_cumprimento'] ?? ''));
            if ($diasDevolucao === '' && $prazo !== '') {
                $diasDevolucao = $prazo;
            }
            break;
        }
    }
}

$registroMetadata = RegistroMetadataHelper::fromListRow($row, 'cartas_expedidas', $registroListViewerContext, $connection);
$editHref = trim((string) ($registroMetadata['edit_href'] ?? ''));
$showEdit = !empty($registroMetadata['show_edit']) && $editHref !== '' && !$isAguardandoCumprimento && !$isTerminal;
$mostrarCopiaWhatsapp = $modeloEnvio && !$isTerminal && $numero !== '' && $destino !== '';
$whatsappTexto = $mostrarCopiaWhatsapp
    ? $numero . ' - Carta Precatória expedida *aguardando envio* para ' . $destino
    : '';
$historicoLinhas = RegistroMetadataHelper::linhasHistoricoV2CartasExpedidas($row, $connection);
$historicoRecente = $historicoLinhas !== [] ? $historicoLinhas[count($historicoLinhas) - 1] : null;
$statusVisivelTexto = $isTerminal || $textoEtapa === '' ? $badgePrincipalTexto : $textoEtapa;
$statusVisivelClasse = $isTerminal || $classeEtapa === '' ? $badgePrincipalClasse : $classeEtapa;
$mostrarReiniciar = !$fluxoInconsistente && strcasecmp(trim($statusVisivelTexto),'Aguardando envio')!==0;

$subtarefaAtivaId = $subtarefaAtivaUi !== null ? trim((string) ($subtarefaAtivaUi['id'] ?? '')) : '';

$prioridadesTexto = [];
foreach ($prioridades as $prioridade) {
    $codigoPrioridade = trim((string) ($prioridade['codigo'] ?? ''));
    $labelPrioridade = trim((string) ($prioridade['label'] ?? ''));
    if ($codigoPrioridade === '' || $labelPrioridade === '' || $codigoPrioridade === 'sigiloso') {
        continue;
    }
    $prioridadesTexto[] = $labelPrioridade;
}
$temPrioridadeVisual = $prioridadesTexto !== [];
$classeExibicao = $classeTexto;
$classeSemDados = !$classeValid;
$assuntoSemDados = !$assuntoValid;
$mostrarSincronizar = $classeSemDados && $assuntoSemDados;
$classeLinhaExibicao = $classeSemDados ? 'Nenhuma classe cadastrada' : $classeExibicao;
$assuntoExibicao = $assuntoSemDados
    ? 'Nenhum assunto cadastrado. Clique em sincronizar para buscar os dados com a API DataJud'
    : $assuntoTexto;
$limparCodigoClasseCompacta = static function (string $texto): string {
    $textoLimpo = preg_replace('/^\s*\d+\s*-\s*/u', '', trim($texto));
    return is_string($textoLimpo) && $textoLimpo !== '' ? $textoLimpo : trim($texto);
};
$limparCodigosAssuntosCompacto = static function (string $texto): string {
    $partes = preg_split('/\s*;\s*/u', trim($texto)) ?: [];
    $itens = [];
    foreach ($partes as $parte) {
        $parte = trim((string) $parte);
        if ($parte === '') {
            continue;
        }
        $parteLimpa = preg_replace('/^\s*\d+\s*-\s*/u', '', $parte);
        $itens[] = is_string($parteLimpa) && $parteLimpa !== '' ? $parteLimpa : $parte;
    }
    return $itens !== [] ? implode(' | ', $itens) : trim($texto);
};
if (!$classeSemDados) {
    $classeLinhaExibicao = $limparCodigoClasseCompacta($classeLinhaExibicao);
}
if (!$assuntoSemDados) {
    $assuntoExibicao = $limparCodigosAssuntosCompacto($assuntoExibicao);
}
$iconSigilo = '/assets/icons/View-Off--Streamline-Ultimate.png';
$iconPrioridade = '/assets/icons/Alert-Diamond--Streamline-Ultimate.png';
$iconAbrir = '/assets/icons/Cartas-Resize-Expand-Corner--Streamline-Ultimate.png';
$iconSincronizar = '/assets/icons/Server-Sync-1--Streamline-Ultimate.png';
if (!empty($cartasPiloto)) {
    require __DIR__ . '/cartas-expedidas-piloto-row.php';
    return;
}
?>
<div class="register-line full carta-expedida-v2-row" data-carta-id="<?php echo htmlspecialchars($idCarta, ENT_QUOTES); ?>" data-registro-id="<?php echo htmlspecialchars($idCarta, ENT_QUOTES); ?>">
    <div class="left carta-expedida-left">
        <div class="register-block carta-process-tools">
            <?php if ($numero !== ''): ?>
                <div class="line-cube carta-process-tool js-v2-copy-processo"
                    role="button"
                    tabindex="0"
                    data-processo-numero="<?php echo htmlspecialchars($numero, ENT_QUOTES); ?>"
                    title="Copiar número do processo"
                    aria-label="Copiar número do processo">
                    <img src="/assets/icons/Coding-Apps-Website-Web-Form-Number-Field--Streamline-Ultimate.png" alt="">
                </div>
            <?php else: ?>
                <div class="line-cube carta-process-tool" aria-hidden="true">
                    <img src="/assets/icons/Coding-Apps-Website-Web-Form-Number-Field--Streamline-Ultimate.png" alt="">
                </div>
            <?php endif; ?>
            <div class="line-cube box-open open carta-process-tool-open"
                title="<?php echo $abrirDisabled ? 'Processo não cadastrado no sistema' : 'Abrir processo'; ?>"
                aria-label="<?php echo $abrirDisabled ? 'Processo não cadastrado no sistema' : 'Abrir processo'; ?>"
                <?php if (!$abrirDisabled && $abrirHref !== ''): ?>role="button" tabindex="0" data-nav-href="<?php echo htmlspecialchars($abrirHref, ENT_QUOTES); ?>"<?php endif; ?>>
                <img class="cube-icon" src="<?php echo htmlspecialchars($iconAbrir, ENT_QUOTES); ?>" alt="">
            </div>
        </div>
        <div class="register-block process carta-process-details">
            <div class="container-process container-sm">
                <div class="container-line">
                    <div class="left carta-process-number-line">
                        <div class="line-content box-number">
                            <span class="content-number"><?php echo htmlspecialchars($numero, ENT_QUOTES); ?></span>
                        </div>
                        <?php if ($sigiloso): ?>
                            <div class="line-cube box-private" title="Sigiloso" aria-label="Sigiloso">
                                <img class="cube-icon" src="<?php echo htmlspecialchars($iconSigilo, ENT_QUOTES); ?>" alt="">
                            </div>
                        <?php endif; ?>
                        <?php if ($temPrioridadeVisual): ?>
                            <div class="line-cube box-open open" title="<?php echo htmlspecialchars(implode(' | ', $prioridadesTexto), ENT_QUOTES); ?>" aria-label="<?php echo htmlspecialchars(implode(' | ', $prioridadesTexto), ENT_QUOTES); ?>">
                                <img class="cube-icon" src="<?php echo htmlspecialchars($iconPrioridade, ENT_QUOTES); ?>" alt="">
                            </div>
                        <?php endif; ?>
                        <?php if ($mostrarSincronizar): ?>
                            <div class="line-cube box-open open js-v2-sync-processo<?php echo $numero !== '' ? '' : ' disabled'; ?>"
                                title="Sincronizar dados do processo"
                                aria-label="Sincronizar dados do processo"
                                <?php if ($numero !== ''): ?>role="button" tabindex="0" data-processo-numero="<?php echo htmlspecialchars($numero, ENT_QUOTES); ?>"<?php else: ?>aria-disabled="true"<?php endif; ?>>
                                <img class="cube-icon" src="<?php echo htmlspecialchars($iconSincronizar, ENT_QUOTES); ?>" alt="">
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="container-line">
                    <div class="line-content double-line">
                        <span class="content-classe<?php echo $classeSemDados ? ' over' : ''; ?>"><?php echo htmlspecialchars($classeLinhaExibicao, ENT_QUOTES); ?></span>
                        <span class="content-assuntos<?php echo $assuntoSemDados ? ' over' : ''; ?>">
                            <?php if ($temPrioridadeVisual): ?>
                                <strong><?php echo htmlspecialchars(implode(' | ', $prioridadesTexto), ENT_QUOTES); ?></strong><?php echo $assuntoExibicao !== '' ? ' | ' : ''; ?>
                            <?php endif; ?>
                            <?php echo htmlspecialchars($assuntoExibicao, ENT_QUOTES); ?>
                        </span>
                    </div>
                </div>
            </div>
        </div>
        <div class="register-block carta-info-column">
            <div class="register-block-content">
                    <?php if ($nomeResponsavelEnvio !== ''): ?>
                        <div class="register-block-content-item">
                            <span class="register-block-content-item-text">Envio:</span>
                            <span class="register-block-content-item-value"><?php echo htmlspecialchars($nomeResponsavelEnvio, ENT_QUOTES, 'UTF-8'); ?></span>
                        </div>
                    <?php endif; ?>
                <div class="register-block-content-item">
                    <strong class="register-block-content-item-value"><?php echo htmlspecialchars($destino, ENT_QUOTES); ?></strong>
                </div>
                <?php if ($mostrarCartaParcial): ?>
                    <div class="register-block-content-item">
                        <span class="register-block-content-item-text"><?php echo htmlspecialchars($cartaParcialLabel, ENT_QUOTES); ?></span>
                        <span class="register-block-content-item-value"><?php echo htmlspecialchars($cartaParcialValor, ENT_QUOTES); ?></span>
                    </div>
                    <div class="register-block-content-item">
                        <span class="register-block-content-item-text">Devolvida com:</span>
                        <span class="register-block-content-item-value"><?php echo htmlspecialchars($diasDevolucao !== '' ? $diasDevolucao . ' dias' : '', ENT_QUOTES); ?></span>
                    </div>
                <?php else: ?>
                    <div class="register-block-content-item">
                        <span class="register-block-content-item-text">Finalidade:</span>
                        <span class="register-block-content-item-value"><?php echo htmlspecialchars($finalidade, ENT_QUOTES); ?></span>
                    </div>
                    <div class="register-block-content-item">
                        <span class="register-block-content-item-text">Prazo:</span>
                        <span class="register-block-content-item-value"><?php
                            if ($prazo !== '') {
                                echo htmlspecialchars($prazo . ' dias', ENT_QUOTES);
                                if ($prazoRestanteTexto !== '') {
                                    echo ' <span class="prazo-restante' . ($prazoRestanteAlerta ? ' alerta' : '') . '">'
                                        . htmlspecialchars($prazoRestanteTexto, ENT_QUOTES)
                                        . '</span>';
                                }
                            }
                        ?></span>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="right carta-expedida-right">
        <div class="carta-expedida-status-group">
        <div class="register-block">
            <div class="register-block-status">
                <?php if ($fluxoInconsistente && !$isTerminal): ?>
                    <p class="text-warning" role="status">Não foi possível determinar a etapa desta carta. Confira o histórico antes de continuar.</p>
                <?php endif; ?>
                <div class="badge-status-primary <?php echo htmlspecialchars($statusVisivelClasse, ENT_QUOTES); ?>">
                    <span class="badge-status-text"><?php echo htmlspecialchars($statusVisivelTexto, ENT_QUOTES); ?></span>
                </div>
                <?php if ($mostrarCopiaWhatsapp): ?>
                    <button type="button"
                        class="button-icon-right carta-row-action js-v2-copy-whatsapp"
                        data-whatsapp-text="<?php echo htmlspecialchars($whatsappTexto, ENT_QUOTES); ?>"
                        title="Copiar texto para WhatsApp"
                        aria-label="Copiar texto para WhatsApp">
                        <img src="/assets/icons/Whatsapp-Logo--Streamline-Ultimate.png" alt="">
                    </button>
                <?php endif; ?>
                <?php if ($showEdit): ?>
                    <button type="button" class="button-icon-right carta-row-action" data-nav-href="<?php echo htmlspecialchars($editHref, ENT_QUOTES); ?>" title="Editar" aria-label="Editar">
                        <img src="/assets/icons/Eject-Button--Streamline-Ultimate.png" alt="">
                    </button>
                <?php endif; ?>
                <?php if ($mostrarReiniciar): ?>
                    <button type="button" class="button-icon-internal carta-row-action js-reiniciar-carta" data-carta-id="<?php echo htmlspecialchars($idCarta, ENT_QUOTES); ?>" data-modulo="expedidas" title="Reiniciar: voltar para Aguardando envio" aria-label="Reiniciar: voltar para Aguardando envio"><img src="/assets/icons/Button-Previous--Streamline-Ultimate.png" alt=""></button>
                <?php endif; ?>
                <?php if ($mostrarContinuar && $textoEtapa !== ''): ?>
                    <button type="button"
                        class="button-icon-internal carta-row-action js-continuar-subtarefa"
                        data-subtarefa-id="<?php echo htmlspecialchars($subtarefaAtivaId, ENT_QUOTES); ?>"
                        title="<?php echo htmlspecialchars(CartasExpedidasUiCopy::tituloBotaoPorTipo(trim((string) ($subtarefaAtivaUi['tipo'] ?? ''))), ENT_QUOTES); ?>"
                        aria-label="<?php echo htmlspecialchars(CartasExpedidasUiCopy::tituloBotaoPorTipo(trim((string) ($subtarefaAtivaUi['tipo'] ?? ''))), ENT_QUOTES); ?>">
                        <img src="/assets/icons/<?php echo $isAguardandoCumprimento ? 'Button-Next--Streamline-Ultimate.png' : 'Button-Play-1--Streamline-Ultimate.png'; ?>" alt="">
                    </button>
                <?php endif; ?>
            </div>
        </div>

        <?php if (!$isCancelada): ?>
            <div class="block-separator-line"></div>
        <?php endif; ?>

        <div class="container-historical container-sm media-1024">
            <div class="container-line">
                <div class="line-content double-line">
                    <span class="content-classe">Última movimentação</span>
                    <?php if ($historicoRecente === null): ?>
                        <span class="content-assuntos over">Nenhuma movimentação registrada</span>
                    <?php else: ?>
                        <?php
                        $historicoData = trim((string) ($historicoRecente['data_hora'] ?? ''));
                        $historicoAutor = trim((string) ($historicoRecente['autor'] ?? ''));
                        $historicoClasse = trim((string) ($historicoRecente['content_class'] ?? ''));
                        $historicoTexto = $historicoData;
                        if ($historicoAutor !== '') {
                            $historicoTexto .= ($historicoTexto !== '' ? ' | ' : '') . $historicoAutor;
                        }
                        ?>
                        <span class="content-assuntos<?php echo $historicoClasse !== '' ? ' ' . htmlspecialchars($historicoClasse, ENT_QUOTES) : ''; ?>"><?php echo htmlspecialchars($historicoTexto, ENT_QUOTES); ?></span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        </div>
    </div>
</div>
