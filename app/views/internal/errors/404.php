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
