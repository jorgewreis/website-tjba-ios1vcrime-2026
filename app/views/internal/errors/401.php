<?php
declare(strict_types=1);
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