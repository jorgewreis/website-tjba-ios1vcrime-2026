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
            <span class="title" style="color: crimson;">Erro 500 - Erro interno do servidor</span>
            <span class="info-title">O sistema encontrou uma falha ao processar sua solicitação. Tente novamente em alguns instantes. Se o erro persistir, registre o horário do ocorrido e informe ao suporte, incluindo o caminho/ação executada para que possamos localizar o problema com precisão.</span>
        </div>
    </div>
</body>
</html>
