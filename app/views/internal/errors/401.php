<?php
declare(strict_types=1);
$code = '401';
$title = 'Acesso Não Autorizado';
$message = 'Você não tem permissão para acessar esta página ou sua sessão expirou. Faça login novamente e tente acessar pelo menu do sistema.';
$actions = [
    ['label' => 'Voltar ao Login', 'href' => '/index.php', 'class' => 'btn-primary'],
    ['label' => 'Página Inicial', 'href' => '/dashboard.php', 'class' => 'btn-secondary']
];
$suggestions = ['Recarregue a página e tente novamente.', 'Se persistir, contate o administrador.'];
$details = ['timestamp' => date('c'), 'request' => $_SERVER['REQUEST_URI'] ?? '', 'user_id' => $_SESSION['id_usuario'] ?? 'anon'];
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Erro <?php echo htmlspecialchars($code); ?> - Primeira Vara Crime</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/custom.css">
</head>
<body class="page-internal">
    <?php include __DIR__ . '/_error_card.php'; ?>

    <div class="container text-center my-3">
        <button class="toggle-error-details btn btn-link" aria-expanded="false">Mostrar detalhes técnicos</button>
    </div>

    <?php $details = $details; include __DIR__ . '/_error_details.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>