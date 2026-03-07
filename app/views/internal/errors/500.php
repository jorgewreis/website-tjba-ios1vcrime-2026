<?php
declare(strict_types=1);
$code = '500';
$title = 'Erro Interno do Servidor';
$message = 'Ocorreu um erro inesperado no servidor. Estamos trabalhando para corrigir o problema o quanto antes.';
$details = ['Código de erro' => "HTTP {$code}", 'URL solicitada' => $_SERVER['REQUEST_URI'] ?? '/', 'Data/Hora' => date('d/m/Y, H:i:s')];
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <?php include __DIR__ . '/../_theme_init.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Erro <?php echo htmlspecialchars($code); ?> - Primeira Vara Crime</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/shared/reset.css">
    <link rel="stylesheet" href="/assets/css/custom.css">
</head>
<body class="page-internal">
    <?php
    // Preparar variáveis para os partials reutilizáveis
    $error_icon_class = 'icon-danger';
    $error_text_class = 'text-danger';
    $error_code = $code;
    $error_title = $title;
    $error_message = $message;
    include __DIR__ . '/_error_header.php';
    ?>

    <?php
    // Carregar mensagens centralizadas e definir $info_text apenas se não houver sobrescrita
    $error_messages = include __DIR__ . '/error_messages.php';
    $info_text = $info_text ?? ($error_messages[$code] ?? '');
    $details = $details; include __DIR__ . '/_error_details.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>