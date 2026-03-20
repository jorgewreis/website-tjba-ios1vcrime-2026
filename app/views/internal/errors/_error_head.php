<?php
declare(strict_types=1);

$pageTitle = $pageTitle ?? 'Erro — Primeira Vara Crime de Ilhéus';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8'); ?></title>
    <?php include __DIR__ . '/../_internal_head_styles.php'; ?>
    <link rel="stylesheet" href="/assets/css/shared/error.css">
    <link rel="stylesheet" href="https://cdn-uicons.flaticon.com/3.0.0/uicons-regular-rounded/css/uicons-regular-rounded.css">
    <?php include __DIR__ . '/../_toast_script.php'; ?>
</head>
<body class="page-internal">
