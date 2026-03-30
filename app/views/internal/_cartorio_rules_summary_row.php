<?php
declare(strict_types=1);

/**
 * Linha de resumo da regra (id e nome do servidor).
 *
 * Variáveis: $ruleRow (com id), $ruleUsuarioNome, $locationSpecificPrefix (ex.: Ceapa - Regras), $resumoRowClass (info|success).
 */
if (empty($ruleRow['id'] ?? '')) {
    return;
}
$prefix = (string) ($locationSpecificPrefix ?? '');
$resumoRowClass = (string) ($resumoRowClass ?? 'info');
?>
            <div class="display-flex row row-full padding-x-md padding-y-md border-bottom <?php echo htmlspecialchars($resumoRowClass, ENT_QUOTES, 'UTF-8'); ?>">
                <div class="col">
                    <span><?php echo htmlspecialchars(sprintf('%s: %s - %s', $prefix, $ruleRow['id'] ?? 'sem ID', $ruleUsuarioNome)); ?></span>
                </div>
            </div>
