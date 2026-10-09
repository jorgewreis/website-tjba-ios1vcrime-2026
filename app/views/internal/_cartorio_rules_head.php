<?php
declare(strict_types=1);

/**
 * Cabeçalho HTML + header-page para telas cartorio-*-rules.php.
 *
 * Variáveis esperadas no escopo do include:
 * - $pageTitle (string, opcional) — <title>; se vazio, usa $h1Text
 * - $locationAll (string) — conteúdo de span.all (ex.: Interno - Cartório - Ceapa -)
 * - $nomeCurtoModulo (string) — ex.: Ceapa; monta specific = "{Nome} - Regras" e h1 = "{Nome} — Regras de alocação"
 * - $htmlLang (string, opcional) — default pt-BR
 */
$htmlLang = isset($htmlLang) && $htmlLang !== '' ? $htmlLang : 'pt-BR';
$nomeCurtoModulo = trim((string) ($nomeCurtoModulo ?? ''));
$h1Text = $nomeCurtoModulo !== '' ? $nomeCurtoModulo . ' — Regras de alocação' : 'Regras de alocação';
$locationSpecific = $nomeCurtoModulo !== '' ? $nomeCurtoModulo . ' - Regras' : 'Regras';
$pageTitle = isset($pageTitle) && trim((string) $pageTitle) !== '' ? trim((string) $pageTitle) : $h1Text;
$description = (string) ($description ?? '');
?>
<!DOCTYPE html>
<html lang="<?php echo htmlspecialchars($htmlLang, ENT_QUOTES, 'UTF-8'); ?>">
    <head>
        <meta charset="utf-8">
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <title><?php echo htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8'); ?></title>
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <?php include __DIR__ . '/_internal_head_styles.php'; ?>
        <?php include __DIR__ . '/_toast_script.php'; ?>
    </head>
<body class="page-internal">

    <div class="spacing-top-page"></div>

    <div class="header-page">
        <div class="location">
            <span class="all"><?php echo htmlspecialchars((string) ($locationAll ?? ''), ENT_QUOTES, 'UTF-8'); ?></span>
            <span class="specific"><?php echo htmlspecialchars($locationSpecific, ENT_QUOTES, 'UTF-8'); ?></span>
        </div>
        <h1 class="title-page"><?php echo htmlspecialchars($h1Text, ENT_QUOTES, 'UTF-8'); ?></h1>
    </div>
