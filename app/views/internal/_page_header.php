<?php
declare(strict_types=1);
?>
<?php $title = $title ?? ''; ?>
<?php $description = $description ?? ''; ?>

<header class="container-xl mb-4">
    <div class="d-flex align-items-center gap-3">
        <div>
            <h2 class="mb-0 text-primary"><?php echo htmlspecialchars($title); ?></h2>
            <p class="text-muted"><?php echo htmlspecialchars($description); ?></p>
        </div>
    </div>
</header>