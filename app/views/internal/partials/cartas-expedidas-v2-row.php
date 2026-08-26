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
$showEdit = !empty($registroMetadata['show_edit']) && $editHref !== '';
$mostrarCopiaWhatsapp = $modeloEnvio && !$isTerminal && $numero !== '' && $destino !== '';
$whatsappTexto = $mostrarCopiaWhatsapp
    ? $numero . ' - Carta Precatória expedida *aguardando envio* para ' . $destino
    : '';
$historicoLinhas = RegistroMetadataHelper::linhasHistoricoV2CartasExpedidas($row, $connection);

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
$classeExibicao = $sigiloso ? 'SIGILOSO - ' . $classeTexto : $classeTexto;
$classeSemDados = !$classeValid;
$assuntoSemDados = !$assuntoValid;
$mostrarSincronizar = $classeSemDados && $assuntoSemDados;
$classeLinhaExibicao = $classeSemDados ? 'Nenhuma classe cadastrada' : $classeExibicao;
if ($sigiloso && $classeSemDados) {
    $classeLinhaExibicao = 'SIGILOSO - Nenhuma classe cadastrada';
}
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
    if ($sigiloso && str_starts_with($classeLinhaExibicao, 'SIGILOSO - ')) {
        $classeLinhaExibicao = 'SIGILOSO - ' . $limparCodigoClasseCompacta(substr($classeLinhaExibicao, 11));
    }
}
if (!$assuntoSemDados) {
    $assuntoExibicao = $limparCodigosAssuntosCompacto($assuntoExibicao);
}
$iconSigilo = $mostrarSincronizar
    ? '/assets/icons/Sign-Badge-Rectangular-Round-2--Streamline-Ultimate.png'
    : '/assets/icons/Sign-Badge-Rectangular-Round--Streamline-Ultimate.png';
$iconPrioridade = '/assets/icons/Sign-Badge-Circle--Streamline-Ultimate.png';
$iconAbrir = '/assets/icons/Resize-Expand-Corner-1--Streamline-Ultimate.png';
$iconSincronizar = '/assets/icons/Server-Sync-1--Streamline-Ultimate.png';
?>
<div class="register-line full" data-carta-id="<?php echo htmlspecialchars($idCarta, ENT_QUOTES); ?>" data-registro-id="<?php echo htmlspecialchars($idCarta, ENT_QUOTES); ?>">
    <div class="card-line left">
        <div class="register-block">
            <div class="container-process container-sm">
                <div class="container-line">
                    <div class="left">
                        <!-- mostrar essa div se o processo tiver sigilo -->
                        <div class="line-cube box-private<?php echo $sigiloso ? '' : ' open'; ?>" title="<?php echo $sigiloso ? 'Sigiloso' : 'Processo sem sigilo'; ?>" aria-label="<?php echo $sigiloso ? 'Sigiloso' : 'Processo sem sigilo'; ?>">
                            <img class="cube-icon" src="<?php echo htmlspecialchars($iconSigilo, ENT_QUOTES); ?>" alt="">
                        </div>

                        <div
                            class="line-content box-number<?php echo $numero !== '' ? ' js-v2-copy-processo' : ''; ?>"
                            <?php if ($numero !== ''): ?>
                                role="button"
                                tabindex="0"
                                data-processo-numero="<?php echo htmlspecialchars($numero, ENT_QUOTES); ?>"
                                title="Copiar número do processo"
                                aria-label="Copiar número do processo"
                            <?php endif; ?>>
                            <span class="content-number"><?php echo htmlspecialchars($numero, ENT_QUOTES); ?></span>
                        </div>
                    </div>

                    <div class="right">
                        <div class="line-cube box-open<?php echo $temPrioridadeVisual ? ' open' : ''; ?>"<?php if ($temPrioridadeVisual): ?> title="<?php echo htmlspecialchars(implode(' | ', $prioridadesTexto), ENT_QUOTES); ?>" aria-label="<?php echo htmlspecialchars(implode(' | ', $prioridadesTexto), ENT_QUOTES); ?>"<?php endif; ?>>
                            <?php if ($temPrioridadeVisual): ?>
                                <img class="cube-icon" src="<?php echo htmlspecialchars($iconPrioridade, ENT_QUOTES); ?>" alt="">
                            <?php endif; ?>
                        </div>
                        <?php if ($mostrarSincronizar): ?>
                            <!-- mostrar essa div desse jeito se o processo não tiver nenhuma classe ou assunto cadastrado -->
                            <div class="line-cube box-open open" title="Sincronizar dados do processo" aria-label="Sincronizar dados do processo">
                                <img class="cube-icon" src="<?php echo htmlspecialchars($iconSincronizar, ENT_QUOTES); ?>" alt="">
                            </div>
                        <?php else: ?>
                            <div class="line-cube box-open open" title="<?php echo $abrirDisabled ? 'Processo não cadastrado no sistema' : 'Abrir processo'; ?>" aria-label="<?php echo $abrirDisabled ? 'Processo não cadastrado no sistema' : 'Abrir processo'; ?>"<?php if (!$abrirDisabled && $abrirHref !== ''): ?> role="button" tabindex="0" data-nav-href="<?php echo htmlspecialchars($abrirHref, ENT_QUOTES); ?>"<?php endif; ?>>
                                <img class="cube-icon" src="<?php echo htmlspecialchars($iconAbrir, ENT_QUOTES); ?>" alt="">
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
                    <div class="register-block-status-text <?php echo htmlspecialchars($classeEtapa, ENT_QUOTES); ?>">
                        <span class="register-block-status-text-text"><?php echo htmlspecialchars($textoEtapa, ENT_QUOTES); ?></span>
                        <button type="button"
                            class="button-icon-internal js-continuar-subtarefa"
                            data-subtarefa-id="<?php echo htmlspecialchars($subtarefaAtivaId, ENT_QUOTES); ?>"
                            title="<?php echo htmlspecialchars(CartasExpedidasUiCopy::tituloBotaoPorTipo(trim((string) ($subtarefaAtivaUi['tipo'] ?? ''))), ENT_QUOTES); ?>"
                            aria-label="Continuar">
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
