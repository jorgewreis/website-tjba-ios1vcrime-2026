<?php
declare(strict_types=1);
?>
<?php $title = $title ?? ''; ?>
<?php $description = $description ?? ''; ?>

<header class="mb-4 page-header" style="margin-top: 74px;>
    <div class="container-xl">
        <div class="d-flex align-items-center gap-3">
            <a href="/home.php" class="text-decoration-none d-flex align-items-center">
                <div>
                    <h2 class="h5 fw-semibold mb-3"><?php echo htmlspecialchars($title); ?></h2>
                    <p class="text-muted"><?php echo htmlspecialchars($description); ?></p>
                </div>
            </a>
        </div>
    </div>
</header>