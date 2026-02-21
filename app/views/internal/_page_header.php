<?php
declare(strict_types=1);
?>
<?php $title = $title ?? ''; ?>
<?php $description = $description ?? ''; ?>

<header class="mb-4 page-header">
    <div class="container">
        <div class="d-flex align-items-center gap-3">
            <a href="/home.php" class="text-decoration-none d-flex align-items-center">
                <img src="/assets/img/favicon.ico" alt="Logo" width="40" height="40" class="me-2">
                <div>
                    <div class="h6 mb-0"><?php echo htmlspecialchars($title); ?></div>
                    <small class="text-muted"><?php echo htmlspecialchars($description); ?></small>
                </div>
            </a>
        </div>
    </div>
</header>