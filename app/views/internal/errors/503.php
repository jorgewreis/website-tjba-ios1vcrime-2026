<?php
declare(strict_types=1);
$code = '503';
$title = 'Manutenção';
$message = 'O sistema encontra-se em manutenção programada. Trabalhamos para restabelecer o serviço o quanto antes.';
$actions = [
    ['label' => 'Status do Sistema', 'href' => '/status', 'class' => 'btn-primary'],
    ['label' => 'Voltar', 'href' => '/frame.php?page=dashboard', 'class' => 'btn-secondary']
];
$suggestions = ['Verifique novamente em alguns minutos.', 'Se necessário, contate o suporte para mais detalhes.'];
$details = ['timestamp' => date('c'), 'expected_return' => 'Em breve'];
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

    <div class="container text-center my-3">
        <button class="toggle-error-details btn btn-link" aria-expanded="false">Mostrar detalhes técnicos</button>
    </div>

    <?php $details = $details; include __DIR__ . '/_error_details.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>