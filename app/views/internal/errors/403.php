<?php
declare(strict_types=1);
require_once __DIR__ . '/_error_header.php';
$code = '403';
$title = 'Acesso negado';
$message = 'Seu perfil não tem permissão para acessar este recurso. Se acredita que deveria ter acesso, solicite autorização à chefia.';
$actions = [
    ['label' => 'Solicitar Acesso', 'href' => 'mailto:suporte@exemplo.com', 'class' => 'btn-primary'],
    ['label' => 'Página Inicial', 'href' => '/home.php', 'class' => 'btn-secondary']
];
$suggestions = ['Verifique se você está logado com a conta correta.', 'Contate a administração para revisão de permissões.'];
$details = ['timestamp' => date('c'), 'request' => $_SERVER['REQUEST_URI'] ?? '', 'user_id' => $_SESSION['id_usuario'] ?? ''];
?>
<?php include __DIR__ . '/_error_card.php'; ?>
<div class="container text-center my-3">
    <button class="btn btn-link toggle-error-details" aria-expanded="false">Mostrar detalhes</button>
</div>
<?php $details = $details; include __DIR__ . '/_error_details.php'; ?>
