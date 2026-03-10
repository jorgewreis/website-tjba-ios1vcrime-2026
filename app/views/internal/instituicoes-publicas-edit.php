<?php
declare(strict_types=1);
header('Content-Type: text/html; charset=UTF-8');
if (function_exists('mb_internal_encoding')) mb_internal_encoding('UTF-8');

require_once __DIR__ . '/../../helpers/Conection.php';
require_once __DIR__ . '/../../services/InstituicoesPublicasService.php';
require_once __DIR__ . '/../../../config/security.php';

$id = trim((string) ($_GET['id'] ?? ''));
if ($id === '') { header('Location: /frame.php?page=instituicoespublicas'); exit; }
$connection = Conection::getInstance()->getConnection();
$service = new InstituicoesPublicasService($connection);
$inst = $service->find($id);
if ($inst === null) { header('Location: /frame.php?page=instituicoespublicas'); exit; }
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <?php include __DIR__ . '/_theme_init.php'; ?>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Editar Instituição</title>
    <link rel="stylesheet" href="/assets/css/shared/reset.css">
    <link rel="stylesheet" href="/assets/css/shared/color.css">
    <link rel="stylesheet" href="/assets/css/shared/page.css">
    <link rel="stylesheet" href="/assets/css/shared/form.css">
    <?php include __DIR__ . '/_toast_script.php'; ?>
    <script src="/assets/js/shared/text-formatter.js"></script>
    <script src="/assets/js/shared/cnpj.js"></script>
</head>
<body class="page-internal">
    <div class="spacing-top-page"></div>
    <div class="header-page">
        <div class="location"><span class="all">Interno - Instituições -</span> <span class="specific">Editar</span></div>
        <h1 class="title-page"><?php echo htmlspecialchars($inst['nome'] ?? 'Instituição'); ?></h1>
    </div>
    <div class="card-page">
        <input type="hidden" id="inst-id" value="<?php echo htmlspecialchars($inst['id']); ?>">
        <div class="row table header">Dados</div>
        <div class="row filter">
            <div class="col filter">
                <label for="nome">Nome *</label>
                <input type="text" class="width-xl height-md" id="nome" value="<?php echo htmlspecialchars($inst['nome'] ?? ''); ?>" required>
            </div>
        </div>
        <div class="row filter">
            <div class="col filter">
                <label for="nome_fantasia">Nome fantasia / Sigla</label>
                <input type="text" class="width-md height-md" id="nome_fantasia" value="<?php echo htmlspecialchars($inst['nome_fantasia'] ?? ''); ?>">
            </div>
            <div class="col filter">
                <label for="cnpj">CNPJ</label>
                <input type="text" class="width-md height-md" id="cnpj" value="<?php
                    $cnpjVal = preg_replace('/\D+/', '', (string) ($inst['cnpj'] ?? ''));
                    echo htmlspecialchars($cnpjVal !== '' && strlen($cnpjVal) === 14 ? Security::formatCNPJ($cnpjVal) : ($inst['cnpj'] ?? ''));
                ?>">
            </div>
        </div>
        <div class="row filter">
            <div class="col filter">
                <label for="observacoes">Observações</label>
                <textarea class="width-xl height-md" id="observacoes" rows="2"><?php echo htmlspecialchars($inst['observacoes'] ?? ''); ?></textarea>
            </div>
        </div>
        <div class="row filter">
            <div class="col filter">
                <label><input type="checkbox" id="ativo"<?php echo !empty($inst['ativo']) ? ' checked' : ''; ?>> Ativo</label>
            </div>
        </div>
        <div class="row buttons">
            <a href="/frame.php?page=instituicoespublicas" class="btn btn-secondary">Voltar</a>
            <button type="button" class="btn btn-primary" id="salvar-btn">Salvar</button>
        </div>
    </div>
    <script>
    (function() {
        var nomeEl = document.getElementById('nome');
        var nomeFantasiaEl = document.getElementById('nome_fantasia');
        if (nomeEl) {
            nomeEl.addEventListener('blur', function() {
                this.value = (this.value || '').trim().replace(/\s+/g, ' ').slice(0, 255);
            });
        }
        if (nomeFantasiaEl) {
            nomeFantasiaEl.addEventListener('blur', function() {
                this.value = (this.value || '').trim().toUpperCase().slice(0, 120);
            });
        }
        if (typeof App !== 'undefined' && App.CNPJ && App.CNPJ.init) {
            App.CNPJ.init('#cnpj');
        }
    })();
    document.getElementById('salvar-btn').addEventListener('click', function() {
        var id = document.getElementById('inst-id').value;
        var cnpjRaw = (document.getElementById('cnpj').value || '').replace(/\D/g, '');
        if (cnpjRaw && cnpjRaw.length === 14 && typeof App !== 'undefined' && App.CNPJ && !App.CNPJ.validar(cnpjRaw)) {
            if (typeof showToast === 'function') showToast({ type: 'error', message: 'CNPJ inválido. Verifique os dígitos.' });
            return;
        }
        var nomeVal = (document.getElementById('nome').value || '').trim().replace(/\s+/g, ' ').slice(0, 255);
        var nomeFantasiaVal = (document.getElementById('nome_fantasia').value || '').trim().toUpperCase().slice(0, 120);
        var payload = {
            id: id,
            nome: nomeVal,
            nome_fantasia: nomeFantasiaVal === '' ? null : nomeFantasiaVal,
            cnpj: cnpjRaw === '' ? null : cnpjRaw,
            observacoes: (document.getElementById('observacoes').value || '').trim() || null,
            ativo: document.getElementById('ativo').checked ? 1 : 0
        };
        this.disabled = true;
        fetch('/api/instituicoes-publicas/update.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(payload) })
        .then(function(r) { return r.json(); })
        .then(function(d) {
            if (d.success) { if (typeof showToast === 'function') showToast({ type: 'success', message: 'Salvo.' }); }
            else { if (typeof showToast === 'function') showToast({ type: 'error', message: d.error || 'Erro.' }); }
            document.getElementById('salvar-btn').disabled = false;
        })
        .catch(function() { document.getElementById('salvar-btn').disabled = false; });
    });
    </script>
</body>
</html>
