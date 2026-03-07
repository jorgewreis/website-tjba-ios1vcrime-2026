<?php
declare(strict_types=1);

Security::requireAuthentication();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <?php include __DIR__ . '/_theme_init.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Documentos - Sistema de Gestão</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/custom.css">
</head>
<body class="page-internal">
<div class="container py-4">
    <h1 class="h4 mb-3">Documentos / Atas</h1>
    <p class="text-muted">Área para upload e download de atas e documentos oficiais.</p>

    <div class="card">
        <div class="card-body">
            <p>Upload seguro de documentos e controle de acesso por permissões.</p>

            <form id="uploadForm" enctype="multipart/form-data" method="post" action="/api/documents.php">
                <div class="mb-3">
                    <label for="file" class="form-label">Selecione o arquivo</label>
                    <input class="form-control" type="file" id="file" name="file" required>
                </div>
                <div class="mb-3">
                    <label for="title" class="form-label">Título</label>
                    <input class="form-control" type="text" id="title" name="title" maxlength="255">
                </div>
                <button class="btn btn-primary" type="submit">Enviar</button>
            </form>
            <div id="uploadResult" class="mt-3"></div>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.getElementById('uploadForm')?.addEventListener('submit', async function (e) {
    e.preventDefault();
    const form = e.target;
    const data = new FormData(form);
    try {
        const res = await fetch(form.action, { method: 'POST', body: data });
        const json = await res.json();
        document.getElementById('uploadResult').textContent = json.message || (json.success ? 'Upload OK' : 'Erro');
    } catch (err) {
        document.getElementById('uploadResult').textContent = 'Erro de rede';
    }
});
</script>
</body>
</html>

