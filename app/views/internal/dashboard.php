<?php
declare(strict_types=1);

Security::requireAuthentication();
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body>
    <?php include __DIR__ . '/_page_header.php'; 
    $title = 'Dashboard';
    $description = 'Tudo o que você precisa saber (em um só lugar)';
    ?>    
</body>
</html>