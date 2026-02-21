<?php
declare(strict_types=1);

Security::requireAuthentication();
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Medidas Protetivas - Primeira Vara Crime</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/custom.css">
</head>
<body>
    <?php
    $title = 'Medidas Protetivas';
    $description = 'Espaço onde as medidas protetivas são decididas, acompanhadas e cumpridas, para que o pedido seja cumprido de forma eficiente e segura.';
    include __DIR__ . '/_page_header.php';
    ?>

    <div class="container-xl py-2">
        <section>
            <div class="card">

            </div>
        </section>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>