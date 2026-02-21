<?php
declare(strict_types=1);
// expects: $code, $title, $message, $actions (array of ['label','href','class']), $suggestions (array)
$code = $code ?? 'Error';
$title = $title ?? '';
$message = $message ?? '';
$actions = $actions ?? [];
$suggestions = $suggestions ?? [];
?>
<main role="main" class="container-xl error-page py-5">
    <div class="row justify-content-center">
        <div class="col">
            <div class="card error-card shadow-sm">
                <div class="card-body text-center">
                    <div class="error-code display-1 fw-bold text-primary mb-3"><?php echo htmlspecialchars($code); ?></div>
                    <h1 class="h4 mb-2"><?php echo htmlspecialchars($title); ?></h1>
                    <p class="text-muted mb-3"><?php echo htmlspecialchars($message); ?></p>

                    <div class="d-flex justify-content-center gap-2 mb-3 error-actions">
                        <?php foreach ($actions as $a): ?>
                            <a href="<?php echo htmlspecialchars($a['href'] ?? '#'); ?>" class="btn <?php echo htmlspecialchars($a['class'] ?? 'btn-secondary'); ?>">
                                <?php echo htmlspecialchars($a['label'] ?? 'Ação'); ?>
                            </a>
                        <?php endforeach; ?>
                    </div>

                    <?php if (!empty($suggestions)): ?>
                        <div class="text-start small text-muted">
                            <strong>Sugestões:</strong>
                            <ul class="mb-0">
                                <?php foreach ($suggestions as $s): ?>
                                    <li><?php echo htmlspecialchars($s); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</main>

