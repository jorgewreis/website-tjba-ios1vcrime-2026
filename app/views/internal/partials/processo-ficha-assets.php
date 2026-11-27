<?php $fichaAssetsDir = dirname(__DIR__, 4) . '/public/assets'; ?>
<link rel="stylesheet" href="/assets/css/shared/processo-ficha.css?v=<?= (int) filemtime($fichaAssetsDir . '/css/shared/processo-ficha.css') ?>">
<script src="/assets/js/shared/processo-ficha.js?v=<?= (int) filemtime($fichaAssetsDir . '/js/shared/processo-ficha.js') ?>" defer></script>
