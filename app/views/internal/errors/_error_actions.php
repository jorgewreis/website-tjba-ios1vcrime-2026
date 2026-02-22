<?php
// Renders actions and suggestions for error pages.
$actions = isset($actions) && is_array($actions) ? $actions : [];
$suggestions = isset($suggestions) && is_array($suggestions) ? $suggestions : [];
?>

<?php if (!empty($actions) || !empty($suggestions)): ?>
    <div class="container-xl text-center my-3">
        <?php if (!empty($actions)): ?>
            <div class="d-flex justify-content-center gap-2 mb-3">
                <?php foreach ($actions as $a): ?>
                    <a href="<?php echo htmlspecialchars($a['href'] ?? '#'); ?>" class="btn <?php echo htmlspecialchars($a['class'] ?? 'btn-secondary'); ?>">
                        <?php echo htmlspecialchars($a['label'] ?? 'Ação'); ?>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

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
    </div>
<?php endif; ?>

