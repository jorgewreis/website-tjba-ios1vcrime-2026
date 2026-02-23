<?php
declare(strict_types=1);
?>
<?php $title = $title ?? ''; ?>
<?php $description = $description ?? ''; ?>

<header class="container-fluid align-items-start justify-content-start mb-4">
    <div class="container-md d-flex align-items-start justify-content-start ps-4">
        <div class="col">
            <h2 class="row title-page mb-0"><?php echo htmlspecialchars($title); ?></h2>
            <p class="row subtitle-page mb-0"><?php echo htmlspecialchars($description); ?></p>
        </div>
    </div>
</header>