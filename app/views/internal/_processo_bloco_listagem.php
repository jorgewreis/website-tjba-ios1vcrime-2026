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
    <div class="main-processo process-info" title="Clique para copiar">
        <div class="left">
            <div class="numero major-info"><?php echo htmlspecialchars($numero, ENT_QUOTES, 'UTF-8'); ?></div>
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
            <div class="processo-classe<?php echo $classeNot ? ' not' : ' processo-bloco-tooltip'; ?>"<?php if (!$classeNot && $classeTexto !== ''): ?> data-title="<?php echo htmlspecialchars($classeTexto, ENT_QUOTES, 'UTF-8'); ?>"<?php endif; ?>><?php echo htmlspecialchars($classeTexto, ENT_QUOTES, 'UTF-8'); ?></div>
            <div class="processo-assunto<?php echo $assuntoNot ? ' not' : ' processo-bloco-tooltip'; ?>"<?php if (!$assuntoNot && $assuntoTexto !== ''): ?> data-title="<?php echo htmlspecialchars($assuntoTexto, ENT_QUOTES, 'UTF-8'); ?>"<?php endif; ?>><?php echo htmlspecialchars($assuntoTexto, ENT_QUOTES, 'UTF-8'); ?></div>
        </div>
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
                        <span class="major-info processo-bloco-copy-hitarea">
                            <span class="processo-bloco-copy-value"><?php echo htmlspecialchars($numero, ENT_QUOTES, 'UTF-8'); ?></span>
                            <img class="btn-mini-icon" src="<?php echo htmlspecialchars($iconCopiar, ENT_QUOTES, 'UTF-8'); ?>" alt="" width="20" height="20">
                        </span>
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
    </div>
</div>
