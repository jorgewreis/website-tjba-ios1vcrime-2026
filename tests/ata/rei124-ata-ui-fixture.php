<?php
declare(strict_types=1);

// Synthetic browser fixture. The production ata JavaScript is loaded unchanged.
if (basename(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '') !== 'rei124-ata-ui-fixture.php') {
    return false;
}
session_start();
require_once '/repo/app/helpers/Conection.php';
$pdo = Conection::getInstance()->getConnection();
$row = $pdo->prepare('SELECT arquivo_relativo, nome_original FROM sala_audiencias_ata WHERE pauta = ?');
$row->execute(['p']);
$ata = $row->fetch(PDO::FETCH_ASSOC) ?: [];
$relative = (string)($ata['arquivo_relativo'] ?? '');
$hasFile = $relative !== '';
$sha256 = $hasFile && is_file('/repo/storage/' . $relative) ? hash_file('sha256', '/repo/storage/' . $relative) : '';
?><!doctype html>
<html lang="pt-BR"><head><meta charset="utf-8"><title>Fixture sintética de atas</title></head>
<body><main>
  <div class="register" data-pauta="p">
    <span class="ata-status-text"><?= $hasFile ? htmlspecialchars((string)$ata['nome_original'], ENT_QUOTES, 'UTF-8') : 'Nenhum arquivo enviado' ?></span>
    <output id="persisted-ata" data-sha256="<?= htmlspecialchars($sha256, ENT_QUOTES, 'UTF-8') ?>"></output>
    <input type="file" class="ata-file-input" accept="application/pdf,.pdf" aria-label="Selecionar PDF da ata">
    <button type="button" class="btn-upload-ata" data-pauta="p"><?= $hasFile ? 'Substituir PDF' : 'Enviar PDF' ?></button>
  </div>
  <div id="test-toast" role="status" aria-live="polite"></div>
</main>
<script>window.showToast=function(data){document.querySelector('#test-toast').textContent=String(data.message||'');};</script>
<script src="/assets/js/modules/audiencias/atas-list.js"></script>
</body></html>
