<?php
declare(strict_types=1);
$code = '500';
$title = 'Erro Interno';
$message = 'Ocorreu um erro interno no servidor. Tente novamente mais tarde. Se o problema continuar, contate o suporte.';
$actions = [
    ['label' => 'Voltar', 'href' => 'javascript:history.back()', 'class' => 'btn-secondary'],
    ['label' => 'Reportar', 'href' => 'mailto:jorgew.reis@outlook.com', 'class' => 'btn-primary']
];
$suggestions = ['Tente recarregar a página após alguns minutos.', 'Anote o código de erro e informe ao suporte.'];
$details = ['timestamp' => date('c'), 'request' => $_SERVER['REQUEST_URI'] ?? '', 'trace_id' => $_SERVER['X_TRACE_ID'] ?? ($_SERVER['HTTP_X_REQUEST_ID'] ?? '')];
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Erro <?php echo htmlspecialchars($code); ?> - Primeira Vara Crime</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/custom.css">
</head>
<body class="page-internal">
    <?php include __DIR__ . '/_error_card.php'; ?>

    <div class="container-xl text-center my-3">
        <button class="toggle-error-details btn btn-link" aria-expanded="false">Mostrar detalhes técnicos</button>
    </div>

    <?php $details = $details; include __DIR__ . '/_error_details.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>