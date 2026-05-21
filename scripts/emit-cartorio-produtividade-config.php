<?php

declare(strict_types=1);

/**
 * OBSOLETO (2026-05): registro automático de produtividade removido.
 * Configs cartorio-produtividade-*.php foram excluídos; regras automáticas desativadas via
 * database/migrations/20260521_disable_produtividade_automatica_regras.sql.
 */
fwrite(STDERR, "emit-cartorio-produtividade-config.php está obsoleto; não gere mais configs de automação.\n");
exit(1);
