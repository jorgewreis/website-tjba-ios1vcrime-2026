<?php
declare(strict_types=1);

Security::requireAuthentication();
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <?php include __DIR__ . '/_theme_init.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Réus Presos - Primeira Vara Crime</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/custom.css">
</head>
<body class="page-internal">
    <?php
    $title = 'Réus Presos';
    $description = 'Espaço onde os réus presos são registrados, acompanhados e cumpridos, para que o pedido seja cumprido de forma eficiente e segura.';
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