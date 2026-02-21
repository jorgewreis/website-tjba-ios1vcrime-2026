<?php
declare(strict_types=1);
$code = '404';
$title = 'Página não encontrada';
$message = 'A página solicitada não foi encontrada. Verifique a URL ou utilize a navegação do sistema.';
$actions = [
    ['label' => 'Ir para Início', 'href' => '/dashboard.php', 'class' => 'btn-primary']
];
$suggestions = ['Verifique se o endereço está correto.', 'Consulte o menu para localizar a funcionalidade desejada.'];
$details = ['timestamp' => date('c'), 'request' => $_SERVER['REQUEST_URI'] ?? ''];
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
<body>
    <div class="container-xl py-2" style="margin-top: 110px;">
        <section>
            <h2 class="fw-semibold mb-3" style="color: var(--bs-danger);">Erro <?php echo htmlspecialchars($code); ?></h2>
            <p><?php echo htmlspecialchars($message); ?></p>
            <div class="d-flex justify-content-start gap-2 mt-4 mb-3 error-actions">
                <?php foreach ($actions as $a): ?>
                    <a href="<?php echo htmlspecialchars($a['href'] ?? '#'); ?>" class="btn <?php echo htmlspecialchars($a['class'] ?? 'btn-secondary'); ?>">
                        <?php echo htmlspecialchars($a['label'] ?? 'Ação'); ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </section>
    </div>
</body>
</html>