<?php
declare(strict_types=1);

header('Content-Type: text/html; charset=UTF-8');
if (function_exists('mb_internal_encoding')) mb_internal_encoding('UTF-8');

require_once __DIR__ . '/../../helpers/Conection.php';
require_once __DIR__ . '/../../repositories/ProcessosPartesRepository.php';

$id = trim((string) ($_GET['id'] ?? ''));
if ($id === '') { header('Location: /frame.php?page=consultaprocessos'); exit; }

$connection = Conection::getInstance()->getConnection();
$repo = new ProcessosPartesRepository($connection);
$vinculo = $repo->findById($id);
if ($vinculo === null) { header('Location: /frame.php?page=consultaprocessos'); exit; }

$tiposValidos = [
    'reu' => 'Réu', 'indiciado' => 'Indiciado', 'sentenciado' => 'Sentenciado', 'requerido' => 'Requerido',
    'vitima' => 'Vítima', 'requerente' => 'Requerente', 'testemunha_defesa' => 'Testemunha defesa',
    'testemunha_denuncia' => 'Testemunha denúncia', 'advogado' => 'Advogado', 'defensor_publico' => 'Defensor público',
];
$polosValidos = ['ativo' => 'Ativo', 'passivo' => 'Passivo', 'terceiros' => 'Terceiros'];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <?php include __DIR__ . '/_theme_init.php'; ?>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Editar vínculo processo-parte</title>
    <link rel="stylesheet" href="/assets/css/shared/reset.css">
    <link rel="stylesheet" href="/assets/css/shared/color.css">
    <link rel="stylesheet" href="/assets/css/shared/page.css">
    <link rel="stylesheet" href="/assets/css/shared/form.css">
    <link rel="stylesheet" href="/assets/css/shared/table.css">
    <?php include __DIR__ . '/_toast_script.php'; ?>
</head>
<body class="page-internal">
    <div class="spacing-top-page"></div>
    <div class="header-page">
        <div class="location"><span class="all">Interno - Consultas -</span> <span class="specific">Editar vínculo</span></div>
        <h1 class="title-page">Editar vínculo: <?php echo htmlspecialchars($vinculo['numero_processo'] ?? ''); ?> — <?php echo htmlspecialchars($vinculo['nome_completo'] ?? ''); ?></h1>
    </div>
    <div class="card-page">
        <input type="hidden" id="vinculo-id" value="<?php echo htmlspecialchars($vinculo['id'] ?? ''); ?>">
        <input type="hidden" id="processo-id" value="<?php echo htmlspecialchars($vinculo['processo_id'] ?? ''); ?>">
        <div class="row table subtitle left padding-tb-lg padding-lr-lg border-bottom">Dados do vínculo</div>
        <div class="row table left padding-tb-md padding-lr-lg border-bottom row-gap-10 full-line">
            <div class="col"><strong>Processo:</strong> <?php echo htmlspecialchars($vinculo['numero_processo'] ?? '—'); ?></div>
            <div class="col"><strong>Parte:</strong> <?php echo htmlspecialchars($vinculo['nome_completo'] ?? '—'); ?> (<?php echo htmlspecialchars($vinculo['cpf'] ?? '—'); ?>)</div>
        </div>
        <div class="row table left padding-tb-md padding-lr-lg border-bottom row-gap-10 full-line">
            <div class="col">
                <label for="tipo_parte">Tipo de parte <span class="label-hint-obrigatorio">*obrigatório</span></label>
                <select class="width-md height-md white" id="tipo_parte" required>
                    <?php foreach ($tiposValidos as $val => $label): ?>
                        <option value="<?php echo htmlspecialchars($val); ?>"<?php echo (($vinculo['tipo_parte'] ?? '') === $val) ? ' selected' : ''; ?>><?php echo htmlspecialchars($label); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col">
                <label for="polo">Polo <span class="label-hint-obrigatorio">*obrigatório</span></label>
                <select class="width-md height-md white" id="polo" required>
                    <?php foreach ($polosValidos as $val => $label): ?>
                        <option value="<?php echo htmlspecialchars($val); ?>"<?php echo (($vinculo['polo'] ?? '') === $val) ? ' selected' : ''; ?>><?php echo htmlspecialchars($label); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="row table left padding-tb-md padding-lr-lg border-bottom row-gap-10 full-line">
            <div class="col">
                <label><input type="checkbox" id="prioridade_mulher"<?php echo !empty($vinculo['prioridade_mulher']) ? ' checked' : ''; ?>> Prioridade mulher</label>
            </div>
            <div class="col">
                <label><input type="checkbox" id="prioridade_crianca_adolescente"<?php echo !empty($vinculo['prioridade_crianca_adolescente']) ? ' checked' : ''; ?>> Prioridade criança/adolescente</label>
            </div>
            <div class="col">
                <label><input type="checkbox" id="prioridade_idoso"<?php echo !empty($vinculo['prioridade_idoso']) ? ' checked' : ''; ?>> Prioridade idoso</label>
            </div>
            <div class="col">
                <label><input type="checkbox" id="prioridade_pessoa_deficiencia"<?php echo !empty($vinculo['prioridade_pessoa_deficiencia']) ? ' checked' : ''; ?>> Prioridade pessoa com deficiência</label>
            </div>
        </div>
        <div class="row table right buttons padding-tb-md padding-lr-lg border-top row-gap-10">
            <a href="/frame.php?page=consultaprocessosedit&id=<?php echo urlencode($vinculo['processo_id'] ?? ''); ?>" class="btn btn-secondary">Voltar</a>
            <button type="button" class="btn btn-primary" id="salvar-btn">Salvar</button>
        </div>
    </div>
    <script>
    document.getElementById('salvar-btn').addEventListener('click', function() {
        var id = document.getElementById('vinculo-id').value;
        var processoId = document.getElementById('processo-id').value;
        var payload = {
            id: id,
            tipo_parte: document.getElementById('tipo_parte').value,
            polo: document.getElementById('polo').value,
            prioridade_mulher: document.getElementById('prioridade_mulher').checked,
            prioridade_crianca_adolescente: document.getElementById('prioridade_crianca_adolescente').checked,
            prioridade_idoso: document.getElementById('prioridade_idoso').checked,
            prioridade_pessoa_deficiencia: document.getElementById('prioridade_pessoa_deficiencia').checked
        };
        this.disabled = true;
        fetch('/api/processos-partes/update.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(payload) })
            .then(function(r) { return r.json(); })
            .then(function(d) {
                if (d.success) {
                    if (typeof showToast === 'function') showToast({ type: 'success', message: 'Vínculo atualizado.' });
                    window.location.href = '/frame.php?page=consultaprocessosedit&id=' + encodeURIComponent(processoId);
                } else {
                    if (typeof showToast === 'function') showToast({ type: 'error', message: d.error || 'Erro.' });
                    document.getElementById('salvar-btn').disabled = false;
                }
            })
            .catch(function() { document.getElementById('salvar-btn').disabled = false; });
    });
    </script>
</body>
</html>
