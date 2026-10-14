<?php
declare(strict_types=1);

/**
 * Bloco visual processo + classe + prioridades (listagens).
 *
 * Espera linha enriquecida por ProcessoBlocoListagemHelper::appendBlocoData().
 *
 * Variantes opcionais (definidas no escopo do include):
 * - $processo_bloco_model = 'full': usa a largura maior e mantém códigos de classe/assuntos.
 * - $processo_bloco_variant = 'estudantes': listagens de estudantes.
 *     - Oculta o rodapé direito (botões abrir/copiar);
 *     - Mascara o número do processo quando $row['processo_sigiloso'] for verdadeiro.
 * - $processo_bloco_variant = 'consulta-processos': na consulta de processos,
 *   mantém o botão de abrir mesmo quando classe e assunto ainda não existem.
 *   Após definida, a variável é "sticky" (persiste para os próximos includes do
 *   mesmo escopo); cada view de estudantes a define uma única vez antes do
 *   foreach que usa este partial. Demais views nunca devem defini-la.
 *
 * Documentação (foundation): docs/00-foundation/bloco-processo-listagem.md
 *
 * @var array<string, mixed> $row
 */

$processoBlocoVariant = isset($processo_bloco_variant) ? (string) $processo_bloco_variant : '';
$processoBlocoRowV2 = !empty($processo_bloco_row_v2);
$processoBlocoIsEstudantes = ($processoBlocoVariant === 'estudantes');
$processoBlocoHideActions = $processoBlocoIsEstudantes;
$processoBlocoForceOpenWhenMissingData = ($processoBlocoVariant === 'consulta-processos');
$processoBlocoShowPoloPassivo = !isset($processo_bloco_show_polo_passivo) || (bool) $processo_bloco_show_polo_passivo;
$processoBlocoModel = isset($processo_bloco_model) ? (string) $processo_bloco_model : 'compacto';
$processoBlocoIsFull = ($processoBlocoModel === 'full');

$numero = trim((string) ($row['numero_processo'] ?? ''));
$mascararDadosEstudantesSigilosos = false;
if ($processoBlocoIsEstudantes) {
    require_once __DIR__ . '/../../helpers/ProcessoBlocoListagemHelper.php';
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
if ($sigiloso) {
    $classeLinhaExibicao = 'SIGILOSO - ' . $classeLinhaExibicao;
}
$assuntoExibicao = $assuntoSemDados
    ? 'Nenhum assunto cadastrado. Clique em sincronizar para buscar os dados com a API DataJud'
    : ($processoBlocoIsFull ? str_replace('; ', ' | ', $assuntoTexto) : $limparCodigosAssuntosCompacto($assuntoTexto));

$iconSigilo = $processoSemClasseAssunto
    ? '/assets/icons/Sign-Badge-Rectangular-Round-2--Streamline-Ultimate.png'
    : '/assets/icons/Sign-Badge-Rectangular-Round--Streamline-Ultimate.png';
$iconPrioridade = '/assets/icons/Sign-Badge-Circle--Streamline-Ultimate.png';
$iconAbrir = '/assets/icons/Resize-Expand-Corner-1--Streamline-Ultimate.png';
$iconSincronizar = '/assets/icons/Server-Sync-1--Streamline-Ultimate.png';
?>
<div class="container-processo container-process <?php echo $processoBlocoIsFull ? 'container-lg' : 'container-sm'; ?>" data-processo-bloco="1">
    <div class="container-line">
        <div class="left<?php echo $processoBlocoRowV2 ? ' carta-process-number-line' : ''; ?>">
            <!-- mostrar essa div se o processo tiver sigilo -->
            <div class="line-cube box-private<?php echo $sigiloso ? '' : ' open'; ?>" title="<?php echo $sigiloso ? 'Sigiloso' : 'Processo sem sigilo'; ?>" aria-label="<?php echo $sigiloso ? 'Sigiloso' : 'Processo sem sigilo'; ?>">
                <img class="cube-icon" src="<?php echo htmlspecialchars($iconSigilo, ENT_QUOTES, 'UTF-8'); ?>" alt="">
            </div>

            <div
                class="line-content box-number<?php echo $numero !== '' ? ' process-info' : ''; ?>"
                <?php if ($numero !== ''): ?>
                    role="button"
                    tabindex="0"
                    title="Copiar número do processo"
                    aria-label="Copiar número do processo"
                <?php endif; ?>>
                <span class="content-number major-info"><?php echo htmlspecialchars($numero, ENT_QUOTES, 'UTF-8'); ?></span>
            </div>
        </div>

        <div class="right">
            <div class="line-cube box-open<?php echo $temPrioridadeVisual ? ' open' : ''; ?>"<?php if ($temPrioridadeVisual): ?> title="<?php echo htmlspecialchars(implode(' | ', $prioridadesTexto), ENT_QUOTES, 'UTF-8'); ?>" aria-label="<?php echo htmlspecialchars(implode(' | ', $prioridadesTexto), ENT_QUOTES, 'UTF-8'); ?>"<?php endif; ?>>
                <?php if ($temPrioridadeVisual): ?>
                    <img class="cube-icon" src="<?php echo htmlspecialchars($iconPrioridade, ENT_QUOTES, 'UTF-8'); ?>" alt="">
                <?php endif; ?>
            </div>
            <?php if ($mostrarSincronizar): ?>
                <!-- mostrar essa div desse jeito se o processo não tiver nenhuma classe ou assunto cadastrado -->
                <div
                    class="line-cube box-open open js-processo-datajud-sync<?php echo $numero !== '' ? '' : ' disabled'; ?>"
                    title="Sincronizar dados do processo"
                    aria-label="Sincronizar dados do processo"
                    <?php if ($numero !== ''): ?>
                        role="button"
                        tabindex="0"
                        data-processo-numero="<?php echo htmlspecialchars($numero, ENT_QUOTES, 'UTF-8'); ?>"
                    <?php else: ?>
                        aria-disabled="true"
                    <?php endif; ?>>
                    <img class="cube-icon" src="<?php echo htmlspecialchars($iconSincronizar, ENT_QUOTES, 'UTF-8'); ?>" alt="">
                </div>
            <?php elseif (!$processoBlocoHideActions): ?>
                <div class="line-cube box-open open" title="<?php echo $abrirDisabled ? 'Processo não cadastrado no sistema' : 'Abrir processo'; ?>" aria-label="<?php echo $abrirDisabled ? 'Processo não cadastrado no sistema' : 'Abrir processo'; ?>"<?php if (!$abrirDisabled && $abrirHref !== ''): ?> role="button" tabindex="0" data-nav-href="<?php echo htmlspecialchars($abrirHref, ENT_QUOTES, 'UTF-8'); ?>"<?php endif; ?>>
                    <img class="cube-icon" src="<?php echo htmlspecialchars($iconAbrir, ENT_QUOTES, 'UTF-8'); ?>" alt="">
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="container-line">
        <div class="line-content double-line">
            <span class="content-classe<?php echo $classeSemDados ? ' over' : ''; ?>"><?php echo htmlspecialchars($classeLinhaExibicao, ENT_QUOTES, 'UTF-8'); ?></span>
            <span class="content-assuntos<?php echo $assuntoSemDados ? ' over' : ''; ?>">
                <?php if ($temPrioridadeVisual): ?>
                    <strong><?php echo htmlspecialchars(implode(' | ', $prioridadesTexto), ENT_QUOTES, 'UTF-8'); ?></strong><?php echo $assuntoExibicao !== '' ? ' | ' : ''; ?>
                <?php endif; ?>
                <?php echo htmlspecialchars($assuntoExibicao, ENT_QUOTES, 'UTF-8'); ?>
            </span>
            <?php if ($processoBlocoIsFull && $processoBlocoShowPoloPassivo && $poloPassivoTexto !== ''): ?>
                <span class="content-polo">Polo passivo:</span>
                <span class="content-partes"><?php echo htmlspecialchars($poloPassivoTexto, ENT_QUOTES, 'UTF-8'); ?></span>
            <?php endif; ?>
        </div>
    </div>
</div>
