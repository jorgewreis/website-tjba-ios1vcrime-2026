<?php

declare(strict_types=1);

/**
 * Card de listagem v2 — uma carta expedida.
 *
 * @var array<string, mixed> $row
 * @var bool $isGestao
 * @var string $currentUserId
 * @var array{usuario_id: string, perfis: list<string>} $registroListViewerContext
 * @var PDO $connection
 */

require_once __DIR__ . '/../../../repositories/CartasExpedidasSubtarefasRepository.php';
require_once __DIR__ . '/../../../helpers/CartasExpedidasUiCopy.php';
require_once __DIR__ . '/../../../helpers/CartorioAlocacaoChipHelper.php';
require_once __DIR__ . '/../../../helpers/RegistroMetadataHelper.php';

$idCarta = trim((string) ($row['IdCarta'] ?? ''));
$st = strtolower(trim((string) ($row['Status'] ?? '')));
/** @var list<array<string, mixed>> $allSt */
$allSt = is_array($row['SubtarefasAll'] ?? null) ? $row['SubtarefasAll'] : [];
$subtarefaAtivaUi = CartasExpedidasSubtarefasRepository::resolverSubtarefaAtivaPorLista($allSt);

$isConcluida = in_array($st, ['concluída', 'concluida', 'finalizada'], true);
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
} elseif (!$isTerminal) {
    $modeloEnvio = true;
}

$badgePrincipalTexto = 'Em andamento';
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

$numero = trim((string) ($row['numero_processo'] ?? ''));
$classeTexto = trim((string) ($row['bloco_processo_classe_texto'] ?? ''));
$classeValid = empty($row['bloco_processo_classe_not']);
$assuntoTexto = trim((string) ($row['bloco_processo_assunto_texto'] ?? ''));
$assuntoValid = empty($row['bloco_processo_assunto_not']);
/** @var list<array{codigo: string, label: string, icon: string}> $prioridades */
$prioridades = is_array($row['bloco_processo_prioridades'] ?? null) ? $row['bloco_processo_prioridades'] : [];
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
if ($prazoDiasInt > 0) {
    $envioConcluidoEm = '';
    foreach ($allSt as $subtarefa) {
        if (!is_array($subtarefa)) {
            continue;
        }
        $tipoSub = trim((string) ($subtarefa['tipo'] ?? ''));
        $statusSub = strtolower(trim((string) ($subtarefa['status'] ?? '')));
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
        if (trim((string) ($subtarefa['tipo'] ?? '')) !== CartasExpedidasSubtarefasRepository::TIPO_DEVOLUCAO) {
            continue;
        }
        $stSub = strtolower(trim((string) ($subtarefa['status'] ?? '')));
        if ($stSub !== 'concluida' && $stSub !== 'concluída') {
            continue;
        }
        $dadosJson = $subtarefa['dados_especificos'] ?? null;
        $dados = is_string($dadosJson) ? json_decode($dadosJson, true) : (is_array($dadosJson) ? $dadosJson : []);
        if (!is_array($dados)) {
            $dados = [];
        }
        $resultado = trim((string) ($dados['resultado_devolucao'] ?? $dados['resultado'] ?? ''));
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
$showEdit = !empty($registroMetadata['show_edit']) && $editHref !== '';
$mostrarCopiaWhatsapp = $modeloEnvio && !$isTerminal && $numero !== '' && $destino !== '';
$whatsappTexto = $mostrarCopiaWhatsapp
    ? $numero . ' - Carta Precatória expedida *aguardando envio* para ' . $destino
    : '';
$historicoLinhas = RegistroMetadataHelper::linhasHistoricoV2CartasExpedidas($row, $connection);

$subtarefaAtivaId = $subtarefaAtivaUi !== null ? trim((string) ($subtarefaAtivaUi['id'] ?? '')) : '';

$iconAbrir = '/assets/icons/Resize-Expand-Corner--Streamline-Ultimate.png';
$iconCopiar = '/assets/icons/Task-Checklist--Streamline-Ultimate.png';
?>
<div class="register-line full" data-carta-id="<?php echo htmlspecialchars($idCarta, ENT_QUOTES); ?>" data-registro-id="<?php echo htmlspecialchars($idCarta, ENT_QUOTES); ?>">
    <div class="card-line left">
        <div class="register-block">
            <div class="register-block-process">
                <div style="display: flex; flex-direction: row; flex-wrap: nowrap; align-items: center; 
                justify-content: flex-start;">
                <?php if ($numero !== ''): ?>
                    <span class="register-block-process-number"><?php echo htmlspecialchars($numero, ENT_QUOTES); ?></span>
                <?php endif; ?>
                </div>

                <div style="display: flex; flex-direction: row; flex-wrap: nowrap; align-items: center; 
                justify-content: flex-end; gap: 10px;">                
                    <?php if ($prioridades !== []): ?>
                        <div class="register-block-process-icons">
                            <?php foreach ($prioridades as $prioridade): ?>
                                <?php
                                $plabel = trim((string) ($prioridade['label'] ?? ''));
                                $picon = trim((string) ($prioridade['icon'] ?? ''));
                                if ($plabel === '' || $picon === '') {
                                    continue;
                                }
                                ?>
                                <img class="register-block-process-priority-icon" src="<?php echo htmlspecialchars($picon, ENT_QUOTES); ?>" alt="<?php echo htmlspecialchars($plabel, ENT_QUOTES); ?>" title="<?php echo htmlspecialchars($plabel, ENT_QUOTES); ?>">
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                    <div class="register-block-process-icons">
                        <?php if (!$abrirDisabled && $abrirHref !== ''): ?>
                            <span role="button" tabindex="0" data-nav-href="<?php echo htmlspecialchars($abrirHref, ENT_QUOTES); ?>" title="Abrir processo" aria-label="Abrir processo">
                                <img class="icon-mini-process" src="<?php echo htmlspecialchars($iconAbrir, ENT_QUOTES); ?>" alt="">
                            </span>
                        <?php else: ?>
                            <img class="icon-mini-process" src="<?php echo htmlspecialchars($iconAbrir, ENT_QUOTES); ?>" alt="Abrir" title="Processo não cadastrado no sistema">
                        <?php endif; ?>
                        <?php if ($numero !== ''): ?>
                            <button type="button" class="js-v2-copy-processo" data-processo-numero="<?php echo htmlspecialchars($numero, ENT_QUOTES); ?>" title="Copiar número do processo" aria-label="Copiar número do processo">
                                <img class="icon-mini-process" src="<?php echo htmlspecialchars($iconCopiar, ENT_QUOTES); ?>" alt="">
                            </button>
                        <?php else: ?>
                            <img class="icon-mini-process" src="<?php echo htmlspecialchars($iconCopiar, ENT_QUOTES); ?>" alt="Copiar">
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="register-block-process-classe<?php echo $classeValid ? ' valid' : ''; ?>">
                <span class="register-block-process-classe-text"><?php echo htmlspecialchars($classeTexto, ENT_QUOTES); ?></span>
            </div>
            <div class="register-block-process-assuntos<?php echo $assuntoValid ? ' valid' : ''; ?>">
                <span class="register-block-process-assuntos-text"><?php echo htmlspecialchars($assuntoTexto, ENT_QUOTES); ?></span>
            </div>
        </div>

        <div class="block-separator-line"></div>

        <div class="register-block">
            <div class="register-block-title">Dados da Carta</div>
            <div class="register-block-content">
                <div class="register-block-content-item">
                    <span class="register-block-content-item-text">Destino:</span>
                    <span class="register-block-content-item-value"><?php echo htmlspecialchars($destino, ENT_QUOTES); ?></span>
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
    <div class="card-line right">
        <div class="register-block">
            <div class="register-block-status">
                <?php if (!$isTerminal): ?>
                    <?php
                    $alocadoUnico = CartorioAlocacaoChipHelper::resolverAlocadoUnicoCartasExpedidasListagem(
                        is_array($row['Alocados'] ?? null) ? $row['Alocados'] : [],
                        $allSt
                    );
                    if ($alocadoUnico !== null) {
                        $chip = CartorioAlocacaoChipHelper::buildChipData($alocadoUnico, [
                            'entity_id' => $idCarta,
                            'origem' => 'cartas_expedidas.v2_list',
                        ]);
                        if ($chip !== null) {
                            $tipo = $chip['tipo'];
                            $nomeExibir = RegistroMetadataHelper::compactarNome(trim((string) ($alocadoUnico['nome_completo'] ?? '')));
                            if ($nomeExibir === '') {
                                $nomeExibir = trim((string) ($chip['iniciais'] ?? ''));
                            }
                            $usuarioIdStr = $chip['usuario_id'];
                            $podeRemover = $isGestao || ($tipo === 'specific' && $usuarioIdStr === $currentUserId);
                            ?>
                            <div class="badge-allocated allocated-direct" title="<?php echo htmlspecialchars($chip['title'], ENT_QUOTES); ?>">
                                <span class="badge-allocated-text"><?php echo htmlspecialchars($nomeExibir, ENT_QUOTES); ?></span>
                                <?php if ($podeRemover): ?>
                                    <button type="button"
                                        class="badge-allocated-icon js-desalocar"
                                        data-alocado-id="<?php echo htmlspecialchars($chip['alocado_id'], ENT_QUOTES); ?>"
                                        data-tipo="<?php echo htmlspecialchars($tipo, ENT_QUOTES); ?>"
                                        data-endpoint="/api/cartorio-cartas-expedidas/desalocar.php"
                                        title="Remover alocação"
                                        aria-label="Remover alocação">
                                        <i class="fi fi-rr-cross-circle" aria-hidden="true"></i>
                                    </button>
                                <?php else: ?>
                                    <span class="badge-allocated-icon hidden"><i class="fi fi-rr-cross-circle" aria-hidden="true"></i></span>
                                <?php endif; ?>
                            </div>
                            <?php
                        }
                    }
                    ?>
                <?php endif; ?>
                <div class="badge-status-primary <?php echo htmlspecialchars($badgePrincipalClasse, ENT_QUOTES); ?>">
                    <span class="badge-status-text"><?php echo htmlspecialchars($badgePrincipalTexto, ENT_QUOTES); ?></span>
                </div>
            </div>
            <div class="register-block-status">
                <?php if ($mostrarCopiaWhatsapp): ?>
                    <button type="button"
                        class="button-icon-right js-v2-copy-whatsapp"
                        data-whatsapp-text="<?php echo htmlspecialchars($whatsappTexto, ENT_QUOTES); ?>"
                        title="Copiar texto para WhatsApp"
                        aria-label="Copiar texto para WhatsApp">
                        <img src="/assets/icons/Task-Checklist--Streamline-Ultimate.png" alt="">
                    </button>
                <?php endif; ?>
                <button type="button" class="button-icon-right" data-nav-href="<?php echo htmlspecialchars($editHref, ENT_QUOTES); ?>" title="Editar" aria-label="Editar">
                    <img src="/assets/icons/Notes-Edit--Streamline-Ultimate.png" alt="">
                </button>
                <?php if ($mostrarContinuar && $textoEtapa !== ''): ?>
                    <?php
                    $tipoSubtarefaAtiva = trim((string) ($subtarefaAtivaUi['tipo'] ?? ''));
                    $tituloBotaoEtapa = CartasExpedidasUiCopy::tituloBotaoPorTipo($tipoSubtarefaAtiva);
                    ?>
                    <div class="register-block-status-text <?php echo htmlspecialchars($classeEtapa, ENT_QUOTES); ?>">
                        <span class="register-block-status-text-text"><?php echo htmlspecialchars($textoEtapa, ENT_QUOTES); ?></span>
                        <?php if ($tipoSubtarefaAtiva === CartasExpedidasSubtarefasRepository::TIPO_ENVIO): ?>
                            <select class="width-200 height-sm js-cartas-expedidas-subtarefa-complemento"
                                data-subtarefa-id="<?php echo htmlspecialchars($subtarefaAtivaId, ENT_QUOTES); ?>"
                                data-complemento-campo="metodo_envio"
                                aria-label="Método de envio">
                                <option value="">Método</option>
                                <option value="email">E-mail</option>
                                <option value="malote_digital">Malote Digital</option>
                                <option value="pje">PJe</option>
                                <option value="esaj">eSAJ</option>
                                <option value="eproc">ePROC</option>
                            </select>
                        <?php elseif ($tipoSubtarefaAtiva === CartasExpedidasSubtarefasRepository::TIPO_DEVOLUCAO): ?>
                            <select class="width-300 height-sm js-cartas-expedidas-subtarefa-complemento"
                                data-subtarefa-id="<?php echo htmlspecialchars($subtarefaAtivaId, ENT_QUOTES); ?>"
                                data-complemento-campo="resultado_devolucao"
                                aria-label="Resultado da devolução">
                                <option value="">Resultado</option>
                                <option value="devolvida_sem_cumprimento">Não cumprida</option>
                                <option value="cumprida_negativamente">Cumprida negativamente</option>
                                <option value="cumprida_parcialmente">Cumprida parcialmente</option>
                                <option value="cumprida_positivamente">Cumprida integralmente</option>
                            </select>
                        <?php endif; ?>
                        <button type="button"
                            class="button-icon-internal js-continuar-subtarefa"
                            data-subtarefa-id="<?php echo htmlspecialchars($subtarefaAtivaId, ENT_QUOTES); ?>"
                            data-subtarefa-tipo="<?php echo htmlspecialchars($tipoSubtarefaAtiva, ENT_QUOTES); ?>"
                            title="<?php echo htmlspecialchars($tituloBotaoEtapa, ENT_QUOTES); ?>"
                            aria-label="<?php echo htmlspecialchars($tituloBotaoEtapa, ENT_QUOTES); ?>">
                            <img src="/assets/icons/Navigation-Arrows-Right-1--Streamline-Ultimate.png" alt="">
                        </button>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <?php if (!$isCancelada): ?>
            <div class="block-separator-line"></div>
        <?php endif; ?>

        <div class="register-block media-1024">
            <div class="register-block-title">Histórico</div>
            <?php foreach ($historicoLinhas as $hist): ?>
                <div class="register-block-content<?php echo ($hist['content_class'] ?? '') !== '' ? ' ' . htmlspecialchars((string) $hist['content_class'], ENT_QUOTES) : ''; ?>">
                    <?php
                    $dh = trim((string) ($hist['data_hora'] ?? ''));
                    $rotulo = trim((string) ($hist['rotulo'] ?? ''));
                    $autor = trim((string) ($hist['autor'] ?? ''));
                    if ($dh !== '' && $rotulo !== ''):
                        echo htmlspecialchars($dh, ENT_QUOTES) . ' | ' . htmlspecialchars($rotulo, ENT_QUOTES) . ': ';
                    ?>
                        <span class="register-block-content-text"><?php echo htmlspecialchars($autor, ENT_QUOTES); ?></span>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
