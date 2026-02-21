<?php
declare(strict_types=1);
require_once __DIR__ . '/_error_header.php';
$code = '503';
$title = 'Manutenção';
$message = 'O sistema encontra-se em manutenção programada. Trabalhamos para restabelecer o serviço o quanto antes.';
$actions = [
    ['label' => 'Status do Sistema', 'href' => '/status', 'class' => 'btn-primary'],
    ['label' => 'Voltar', 'href' => '/home.php', 'class' => 'btn-secondary']
];
$suggestions = ['Verifique novamente em alguns minutos.', 'Se necessário, contate o suporte para mais detalhes.'];
$details = ['timestamp' => date('c'), 'expected_return' => 'Em breve'];
?>
<?php include __DIR__ . '/_error_card.php'; ?>
<div class="container text-center my-3">
    <button class="btn btn-link toggle-error-details" aria-expanded="false">Mostrar detalhes</button>
</div>
<?php $details = $details; include __DIR__ . '/_error_details.php'; ?>

