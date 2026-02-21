<?php
declare(strict_types=1);
require_once __DIR__ . '/_error_header.php';
$code = '404';
$title = 'Página não encontrada';
$message = 'A página solicitada não foi encontrada. Verifique a URL ou utilize a navegação do sistema.';
$actions = [
    ['label' => 'Ir para Início', 'href' => '/home.php', 'class' => 'btn-primary'],
    ['label' => 'Buscar', 'href' => '/home.php', 'class' => 'btn-secondary']
];
$suggestions = ['Verifique se o endereço está correto.', 'Consulte o menu para localizar a funcionalidade desejada.'];
$details = ['timestamp' => date('c'), 'request' => $_SERVER['REQUEST_URI'] ?? ''];
?>
<?php include __DIR__ . '/_error_card.php'; ?>
<div class="container text-center my-3">
    <button class="btn btn-link toggle-error-details" aria-expanded="false">Mostrar detalhes</button>
</div>
<?php $details = $details; include __DIR__ . '/_error_details.php'; ?>

<?php
declare(strict_types=1);
?>


<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Primeira Vara Crime de Ilhéus - Sistema de Gestão</title>

    <link rel="stylesheet" href="/assets/css/shared/reset.css">
    <link rel="stylesheet" href="/assets/css/shared/color.css">
    <link rel="stylesheet" href="/assets/css/shared/layout.css">
    <link rel="stylesheet" href="/assets/css/shared/components.css">
    <link rel="stylesheet" href="/assets/css/shared/forms.css">
    <link rel="stylesheet" href="/assets/css/shared/pages.css">
    <link rel="stylesheet" href="/assets/css/shared/style.css">

</head>
<body>
    <div class="container-main">
        <div class="title-page">
            <span class="title" style="color: crimson;">Erro 404 - Página não encontrada</span>
            <span class="info-title">A página que você tentou acessar não existe, foi removida ou o endereço foi digitado incorretamente. Verifique o link e tente novamente. Se você chegou aqui a partir de um botão ou menu do sistema, atualize a página e retorne para a página inicial para continuar.</span>
        </div>
    </div>
</body>
</html>
