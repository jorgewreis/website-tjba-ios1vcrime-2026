<?php
declare(strict_types=1);
$code = '403';
$title = 'Acesso Negado';
$message = 'Seu perfil não tem permissão para acessar este recurso. Solicite autorização à chefia se necessário.';
$actions = [
    ['label' => 'Solicitar Acesso', 'href' => 'mailto:jorgew.reis@outlook.com', 'class' => 'btn-primary'],
    ['label' => 'Página Inicial', 'href' => '/frame.php?page=dashboard', 'class' => 'btn-secondary']
];
$suggestions = ['Verifique se está logado com a conta correta.', 'Contate a administração para revisão de permissões.'];
$details = ['timestamp' => date('c'), 'request' => $_SERVER['REQUEST_URI'] ?? '', 'user_id' => $_SESSION['id_usuario'] ?? ''];
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
    <?php
    // Preparar variáveis para os partials reutilizáveis
    $error_code = $code;
    $error_title = $title;
    $error_message = $message;
    include __DIR__ . '/_error_header.php';
    ?>

    <!-- Ações e sugestões específicas desta página de erro (apresentação similar ao card antigo) -->
    <div class="container-xl text-center my-3">
        <div class="d-flex justify-content-center gap-2 mb-3">
            <?php foreach ($actions as $a): ?>
                <a href="<?php echo htmlspecialchars($a['href'] ?? '#'); ?>" class="btn <?php echo htmlspecialchars($a['class'] ?? 'btn-secondary'); ?>">
                    <?php echo htmlspecialchars($a['label'] ?? 'Ação'); ?>
                </a>
            <?php endforeach; ?>
        </div>

        <?php if (!empty($suggestions)): ?>
            <div class="text-start small text-muted container-md">
                <strong>Sugestões:</strong>
                <ul class="mb-0">
                    <?php foreach ($suggestions as $s): ?>
                        <li><?php echo htmlspecialchars($s); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <div class="my-3">
            <button class="toggle-error-details btn btn-link" aria-expanded="false">Mostrar detalhes técnicos</button>
        </div>
    </div>

    <?php $details = $details; include __DIR__ . '/_error_details.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>