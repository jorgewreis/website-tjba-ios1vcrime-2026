<?php
declare(strict_types=1);

/**
 * Bloco visual processo + classe + prioridades (listagens).
 *
 * Espera linha enriquecida por ProcessoBlocoListagemHelper::appendBlocoData().
 *
 * @var array<string, mixed> $row
 */

$numero = trim((string) ($row['numero_processo'] ?? ''));
$classeTexto = trim((string) ($row['bloco_processo_classe_texto'] ?? ''));
$classeNot = !empty($row['bloco_processo_classe_not']);
$assuntoTexto = trim((string) ($row['bloco_processo_assunto_texto'] ?? 'Assunto não cadastrado'));
$assuntoNot = !empty($row['bloco_processo_assunto_not']);
/** @var list<array{codigo: string, label: string, icon: string}> $prioridades */
$prioridades = is_array($row['bloco_processo_prioridades'] ?? null) ? $row['bloco_processo_prioridades'] : [];
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
                    <span class="prioridade-chip processo-bloco-tooltip" data-title="<?php echo htmlspecialchars($plabel, ENT_QUOTES, 'UTF-8'); ?>" role="img" aria-label="<?php echo htmlspecialchars($plabel, ENT_QUOTES, 'UTF-8'); ?>">
                        <img class="prioridade-chip-icon" src="<?php echo htmlspecialchars($picon, ENT_QUOTES, 'UTF-8'); ?>" alt="" width="14" height="14">
                    </span>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <div class="footer-processo">
        <div class="left">
            <div class="processo-classe<?php echo $classeNot ? ' not' : ''; ?>"><?php echo htmlspecialchars($classeTexto, ENT_QUOTES, 'UTF-8'); ?></div>
            <div class="processo-assunto<?php echo $assuntoNot ? ' not' : ''; ?>"><?php echo htmlspecialchars($assuntoTexto, ENT_QUOTES, 'UTF-8'); ?></div>
        </div>
        <div class="right">
            <div class="buttons">
                <button type="button"
                    class="btn btn-secondary-outline processo-bloco-act-btn processo-bloco-tooltip processo-bloco-tooltip--btn"
                    <?php if (!$abrirDisabled && $abrirHref !== ''): ?>data-nav-href="<?php echo htmlspecialchars($abrirHref, ENT_QUOTES, 'UTF-8'); ?>"<?php endif; ?>
                    <?php if ($abrirDisabled): ?>disabled aria-disabled="true"<?php endif; ?>
                    data-title="<?php echo $abrirDisabled ? 'Processo não cadastrado no sistema' : 'Abrir processo'; ?>"
                    aria-label="<?php echo $abrirDisabled ? 'Processo não cadastrado no sistema' : 'Abrir processo'; ?>"
                    title="<?php echo $abrirDisabled ? 'Processo não cadastrado no sistema' : 'Abrir processo'; ?>">
                    <img class="processo-bloco-act-btn-icon" src="<?php echo htmlspecialchars($iconAbrir, ENT_QUOTES, 'UTF-8'); ?>" alt="" width="20" height="20">
                </button>
                <button type="button"
                    class="btn btn-secondary-outline processo-bloco-act-btn processo-bloco-tooltip processo-bloco-tooltip--btn js-copy-numero-processo-btn"
                    data-copy-numero-processo="<?php echo htmlspecialchars($numero, ENT_QUOTES, 'UTF-8'); ?>"
                    data-title="Copiar número do processo"
                    aria-label="Copiar número do processo"
                    title="Copiar número do processo"
                    <?php if ($numero === ''): ?>disabled aria-disabled="true"<?php endif; ?>>
                    <img class="processo-bloco-act-btn-icon" src="<?php echo htmlspecialchars($iconCopiar, ENT_QUOTES, 'UTF-8'); ?>" alt="" width="20" height="20">
                </button>
            </div>
        </div>
    </div>
</div>
