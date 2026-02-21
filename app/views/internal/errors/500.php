<?php
declare(strict_types=1);
require_once __DIR__ . '/_error_header.php';
$code = '500';
$title = 'Erro interno';
$message = 'Ocorreu um erro interno no servidor. Tente novamente mais tarde. Se o problema continuar, contate o suporte.';
$actions = [
    ['label' => 'Voltar', 'href' => 'javascript:history.back()', 'class' => 'btn-secondary'],
    ['label' => 'Reportar', 'href' => 'mailto:suporte@exemplo.com', 'class' => 'btn-primary']
];
$suggestions = ['Tente recarregar a página após alguns minutos.', 'Anote o código de erro e informe ao suporte.'];
$details = ['timestamp' => date('c'), 'request' => $_SERVER['REQUEST_URI'] ?? '', 'trace_id' => $_SERVER['X_TRACE_ID'] ?? ($_SERVER['HTTP_X_REQUEST_ID'] ?? '')];
?>
<?php include __DIR__ . '/_error_card.php'; ?>
<div class="container text-center my-3">
    <button class="btn btn-link toggle-error-details" aria-expanded="false">Mostrar detalhes</button>
</div>
<?php $details = $details; include __DIR__ . '/_error_details.php'; ?>