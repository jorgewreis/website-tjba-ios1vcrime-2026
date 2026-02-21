<?php
declare(strict_types=1);
$code = '404';
$title = 'Página Não Encontrada';
$message = 'Desculpe, a página que você está procurando não existe ou foi movida.';
$actions = [
    ['label' => 'Página Inicial', 'href' => '/frame.php?page=dashboard', 'class' => 'btn-primary'],
    ['label' => 'Voltar', 'href' => 'javascript:history.back()', 'class' => 'btn-outline-primary']
];
$suggestions = ['Verifique se o endereço está correto.', 'Use a navegação para localizar o que procura.'];
$details = ['Código de erro' => "HTTP {$code}", 'URL solicitada' => $_SERVER['REQUEST_URI'] ?? '/', 'Data/Hora' => date('d/m/Y, H:i:s')];
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