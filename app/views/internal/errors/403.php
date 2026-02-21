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
            <span class="title" style="color: crimson;">Erro 403 - Acesso negado</span>
            <span class="info-title">Seu usuário está autenticado, mas não tem permissão para visualizar este conteúdo. Se você acredita que isso é um engano, solicite ao administrador a liberação do perfil adequado ou retorne para a página anterior e continue pelo menu autorizado.</span>
        </div>
    </div>
</body>
</html>
