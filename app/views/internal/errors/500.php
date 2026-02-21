<?php
declare(strict_types=1);
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