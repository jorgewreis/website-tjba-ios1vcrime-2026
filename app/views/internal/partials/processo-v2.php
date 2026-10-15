<?php
declare(strict_types=1);

// Dados enriquecidos do processo; apresentação idêntica ao bloco das Cartas.
$processoBlocoVariant = isset($processo_bloco_variant) ? (string) $processo_bloco_variant : '';
$processoBlocoIsEstudantes = ($processoBlocoVariant === 'estudantes');
$processoBlocoHideActions = $processoBlocoIsEstudantes;
$processoBlocoForceOpenWhenMissingData = ($processoBlocoVariant === 'consulta-processos');
$processoBlocoShowPoloPassivo = !isset($processo_bloco_show_polo_passivo) || (bool) $processo_bloco_show_polo_passivo;
$processoBlocoModel = isset($processo_bloco_model) ? (string) $processo_bloco_model : 'compacto';
$processoBlocoIsFull = ($processoBlocoModel === 'full');

$numero = trim((string) ($row['numero_processo'] ?? ''));
$mascararDadosEstudantesSigilosos = false;
if ($processoBlocoIsEstudantes) {
    require_once __DIR__ . '/../../../helpers/ProcessoBlocoListagemHelper.php';
    $mascararDadosEstudantesSigilosos = ProcessoBlocoListagemHelper::deveMascararNumeroNaVarianteEstudantes($row);
    if ($mascararDadosEstudantesSigilosos && $numero !== '') {
        $numero = ProcessoBlocoListagemHelper::mascararNumeroProcessoSigiloso($numero);
    }
}
$classeTexto = trim((string) ($row['bloco_processo_classe_texto'] ?? ''));
$classeNot = !empty($row['bloco_processo_classe_not']);
$assuntoTexto = trim((string) ($row['bloco_processo_assunto_texto'] ?? 'Assunto não cadastrado'));
$assuntoNot = !empty($row['bloco_processo_assunto_not']);
/** @var list<array{codigo: string, label: string, icon: string}> $prioridades */
$prioridades = is_array($row['bloco_processo_prioridades'] ?? null) ? $row['bloco_processo_prioridades'] : [];
$sigiloso = !empty($row['processo_sigiloso']);
$abrirHref = trim((string) ($row['bloco_processo_abrir_href'] ?? ''));
$abrirDisabled = !empty($row['bloco_processo_abrir_disabled']);
$poloPassivoTexto = trim((string) ($row['bloco_processo_polo_passivo_texto'] ?? ''));
if ($mascararDadosEstudantesSigilosos && $poloPassivoTexto !== '') {
    $poloPassivoTexto = ProcessoBlocoListagemHelper::mascararResumoPoloPassivoSigiloso($poloPassivoTexto);
}

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
$classeSemDados = $classeNot;
$assuntoSemDados = $assuntoNot;
$processoSemClasseAssunto = !$processoBlocoHideActions && $classeSemDados && $assuntoSemDados;
$mostrarSincronizar = $processoSemClasseAssunto && !$processoBlocoForceOpenWhenMissingData;

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

$classeLinhaExibicao = $classeSemDados
    ? 'Nenhuma classe cadastrada'
    : ($processoBlocoIsFull ? $classeTexto : $limparCodigoClasseCompacta($classeTexto));

$assuntoExibicao = $assuntoSemDados
    ? 'Nenhum assunto cadastrado. Clique em sincronizar para buscar os dados com a API DataJud'
    : ($processoBlocoIsFull ? str_replace('; ', ' | ', $assuntoTexto) : $limparCodigosAssuntosCompacto($assuntoTexto));

$iconSigilo = '/assets/icons/View-Off--Streamline-Ultimate.png';
$iconPrioridade = '/assets/icons/Alert-Diamond--Streamline-Ultimate.png';
$iconAbrir = '/assets/icons/Cartas-Resize-Expand-Corner--Streamline-Ultimate.png';
$iconSincronizar = '/assets/icons/Server-Sync-1--Streamline-Ultimate.png';
?>
        <div class="register-block carta-process-tools">
            <?php if ($numero !== ''): ?>
                <div class="line-cube carta-process-tool process-info"
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
                            <div class="line-cube box-open open js-processo-datajud-sync<?php echo $numero !== '' ? '' : ' disabled'; ?>"
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
