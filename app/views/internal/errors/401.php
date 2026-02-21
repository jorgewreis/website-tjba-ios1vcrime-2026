<?php
declare(strict_types=1);
require_once __DIR__ . '/_error_header.php';
$code = '401';
$title = 'Acesso não autorizado';
$message = 'Você não tem permissão para acessar esta página ou sua sessão expirou. Faça login novamente e tente acessar pelo menu do sistema.';
$actions = [
    ['label' => 'Voltar ao Login', 'href' => '/index.php', 'class' => 'btn-primary'],
    ['label' => 'Página Inicial', 'href' => '/home.php', 'class' => 'btn-secondary'],
    ['label' => 'Reportar problema', 'href' => 'mailto:suporte@exemplo.com', 'class' => 'btn-outline-secondary']
];
$suggestions = ['Recarregue a página e tente novamente.', 'Caso o problema persista, solicite liberação de acesso ao administrador.'];
$details = ['timestamp' => date('c'), 'request' => $_SERVER['REQUEST_URI'] ?? '', 'user_id' => $_SESSION['id_usuario'] ?? ''];
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
            <span class="title" style="color: crimson;">Erro 401 - Acesso não autorizado</span>
            <span class="info-title">Você não tem permissão para acessar esta página ou sua sessão expirou. Faça login novamente e tente acessar pelo menu do sistema. Se o problema persistir, solicite liberação de acesso ao administrador.</span>
        </div>
    </div>
</body>
</html>
