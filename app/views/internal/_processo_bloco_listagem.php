<?php
declare(strict_types=1);

/**
 * Bloco visual processo + classe + prioridades (listagens).
 *
 * Espera linha enriquecida por ProcessoBlocoListagemHelper::appendBlocoData().
 *
 * Variantes opcionais (definidas no escopo do include):
 * - $processo_bloco_variant = 'estudantes': listagens de estudantes.
 *     - Oculta o rodapé direito (botões abrir/copiar);
 *     - Mascara o número do processo quando $row['processo_sigiloso'] for verdadeiro.
 *   Após definida, a variável é "sticky" (persiste para os próximos includes do
 *   mesmo escopo); cada view de estudantes a define uma única vez antes do
 *   foreach que usa este partial. Demais views nunca devem defini-la.
 *
 * Documentação (foundation): docs/00-foundation/bloco-processo-listagem.md
 *
 * @var array<string, mixed> $row
 */

$processoBlocoVariant = isset($processo_bloco_variant) ? (string) $processo_bloco_variant : '';
$processoBlocoIsEstudantes = ($processoBlocoVariant === 'estudantes');
$processoBlocoHideActions = $processoBlocoIsEstudantes;

$numero = trim((string) ($row['numero_processo'] ?? ''));
$processoBlocoMascaradoEstudante = false;
if ($processoBlocoIsEstudantes && $numero !== '') {
    require_once __DIR__ . '/../../helpers/ProcessoBlocoListagemHelper.php';
    if (ProcessoBlocoListagemHelper::deveMascararNumeroNaVarianteEstudantes($row)) {
        $numero = ProcessoBlocoListagemHelper::mascararNumeroProcessoSigiloso($numero);
        $processoBlocoMascaradoEstudante = true;
    }
}
$classeTexto = trim((string) ($row['bloco_processo_classe_texto'] ?? ''));
$classeNot = !empty($row['bloco_processo_classe_not']);
$assuntoTexto = trim((string) ($row['bloco_processo_assunto_texto'] ?? 'Assunto não cadastrado'));
$assuntoNot = !empty($row['bloco_processo_assunto_not']);
/** @var list<array{codigo: string, label: string, icon: string}> $prioridades */
$prioridades = is_array($row['bloco_processo_prioridades'] ?? null) ? $row['bloco_processo_prioridades'] : [];
if ($processoBlocoMascaradoEstudante) {
    $prioridades = [];
}
$abrirHref = trim((string) ($row['bloco_processo_abrir_href'] ?? ''));
$abrirDisabled = !empty($row['bloco_processo_abrir_disabled']);
$iconAbrir = '/assets/icons/Resize-Expand-Corner--Streamline-Ultimate.png';
$iconCopiar = '/assets/icons/Task-Checklist--Streamline-Ultimate.png';
?>
<div class="container-processo" data-processo-bloco="1">
    <div class="main-processo">
        <div class="left">
            <div class="numero"><?php echo htmlspecialchars($numero, ENT_QUOTES, 'UTF-8'); ?></div>
        </div>
        <div class="right">
            <div class="prioridades">
                <?php foreach ($prioridades as $p): ?>
                    <?php
                    $plabel = trim((string) ($p['label'] ?? ''));
                    $picon = trim((string) ($p['icon'] ?? ''));
                    if ($plabel === '' || $picon === '') {
                        continue;
                    }
                    ?>
                    <div class="btn-micro tooltip processo-bloco-tooltip" data-title="<?php echo htmlspecialchars($plabel, ENT_QUOTES, 'UTF-8'); ?>" role="img" aria-label="<?php echo htmlspecialchars($plabel, ENT_QUOTES, 'UTF-8'); ?>">
                        <img class="btn-micro-icon" src="<?php echo htmlspecialchars($picon, ENT_QUOTES, 'UTF-8'); ?>" alt="" width="14" height="14">
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <div class="footer-processo">
        <div class="left">
            <div class="processo-classe<?php echo $classeNot ? ' not' : ' processo-bloco-tooltip'; ?>"<?php if (!$classeNot && $classeTexto !== ''): ?> title="<?php echo htmlspecialchars($classeTexto, ENT_QUOTES, 'UTF-8'); ?>"<?php endif; ?>><span class="processo-bloco-text-ellipsis"><?php echo htmlspecialchars($classeTexto, ENT_QUOTES, 'UTF-8'); ?></span></div>
            <div class="processo-assunto<?php echo $assuntoNot ? ' not' : ' processo-bloco-tooltip'; ?>"<?php if (!$assuntoNot && $assuntoTexto !== ''): ?> title="<?php echo htmlspecialchars($assuntoTexto, ENT_QUOTES, 'UTF-8'); ?>"<?php endif; ?>><span class="processo-bloco-text-ellipsis"><?php echo htmlspecialchars($assuntoTexto, ENT_QUOTES, 'UTF-8'); ?></span></div>
        </div>
        <?php if (!$processoBlocoHideActions): ?>
        <div class="right">
            <div class="buttons">
                <?php if (!$abrirDisabled && $abrirHref !== ''): ?>
                    <div
                        class="btn-mini tooltip processo-bloco-tooltip processo-bloco-tooltip--btn"
                        role="button"
                        tabindex="0"
                        data-nav-href="<?php echo htmlspecialchars($abrirHref, ENT_QUOTES, 'UTF-8'); ?>"
                        data-title="Abrir processo"
                        aria-label="Abrir processo">
                        <img class="btn-mini-icon" src="<?php echo htmlspecialchars($iconAbrir, ENT_QUOTES, 'UTF-8'); ?>" alt="" width="20" height="20">
                    </div>
                <?php else: ?>
                    <div
                        class="btn-mini tooltip processo-bloco-tooltip processo-bloco-mini-disabled"
                        role="button"
                        tabindex="-1"
                        aria-disabled="true"
                        data-title="Processo não cadastrado no sistema"
                        aria-label="Processo não cadastrado no sistema">
                        <img class="btn-mini-icon" src="<?php echo htmlspecialchars($iconAbrir, ENT_QUOTES, 'UTF-8'); ?>" alt="" width="20" height="20">
                    </div>
                <?php endif; ?>
                <?php if ($numero !== ''): ?>
                    <div
                        class="btn-mini tooltip processo-bloco-tooltip processo-bloco-tooltip--btn process-info"
                        role="button"
                        tabindex="0"
                        data-title="Copiar número do processo"
                        aria-label="Copiar número do processo">
                        <span class="major-info processo-bloco-copy-value"><?php echo htmlspecialchars($numero, ENT_QUOTES, 'UTF-8'); ?></span>
                        <img class="btn-mini-icon" src="<?php echo htmlspecialchars($iconCopiar, ENT_QUOTES, 'UTF-8'); ?>" alt="" width="20" height="20">
                    </div>
                <?php else: ?>
                    <div
                        class="btn-mini tooltip processo-bloco-tooltip processo-bloco-mini-disabled"
                        role="button"
                        tabindex="-1"
                        aria-disabled="true"
                        data-title="Copiar número do processo"
                        aria-label="Copiar número do processo">
                        <img class="btn-mini-icon" src="<?php echo htmlspecialchars($iconCopiar, ENT_QUOTES, 'UTF-8'); ?>" alt="" width="20" height="20">
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>
