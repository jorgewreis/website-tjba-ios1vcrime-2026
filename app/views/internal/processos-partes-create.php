<?php
declare(strict_types=1);

header('Content-Type: text/html; charset=UTF-8');
if (function_exists('mb_internal_encoding')) mb_internal_encoding('UTF-8');

require_once __DIR__ . '/../../helpers/Conection.php';
require_once __DIR__ . '/../../repositories/ProcessosRepository.php';
require_once __DIR__ . '/../../repositories/ProcessosPartesRepository.php';

$processoId = trim((string) ($_GET['processo_id'] ?? ''));
if ($processoId === '') { header('Location: /frame.php?page=consultaprocessos'); exit; }

$connection = Conection::getInstance()->getConnection();
$processosRepo = new ProcessosRepository($connection);
$processo = $processosRepo->findById($processoId);
if ($processo === null) { header('Location: /frame.php?page=consultaprocessos'); exit; }

$tiposValidos = [
    'reu' => 'Réu',
    'indiciado' => 'Indiciado',
    'sentenciado' => 'Sentenciado',
    'requerido' => 'Requerido',
    'vitima' => 'Vítima',
    'requerente' => 'Requerente',
    'testemunha_defesa' => 'Testemunha defesa',
    'testemunha_denuncia' => 'Testemunha denúncia',
    'advogado' => 'Advogado',
    'defensor_publico' => 'Defensor público',
];
$polosValidos = ['ativo' => 'Ativo', 'passivo' => 'Passivo', 'terceiros' => 'Terceiros'];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
        <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Vincular parte ao processo</title>
            <?php include __DIR__ . '/_internal_head_styles.php'; ?>

    <?php include __DIR__ . '/_toast_script.php'; ?>
</head>
<body class="page-internal">
    <div class="spacing-top-page"></div>
    <div class="header-page">
        <div class="location"><span class="all">Interno - Consultas -</span> <span class="specific">Vincular parte</span></div>
        <h1 class="title-page">Vincular parte ao processo <?php echo htmlspecialchars($processo['numero_processo'] ?? ''); ?></h1>
    </div>
    <div class="card-page">
        <input type="hidden" id="processo-id" value="<?php echo htmlspecialchars($processoId); ?>">
        <div class="row table subtitle left padding-tb-lg padding-lr-lg border-bottom">Parte</div>
        <div class="row table left padding-tb-md padding-lr-lg border-bottom row-gap-10 full-line">
            <div class="col">
                <label for="parte-id">ID da parte (pessoa) <span class="label-hint-obrigatorio">*obrigatório</span></label>
                <input type="text" class="width-600 height-md white" id="parte-id" placeholder="ID da pessoa cadastrada (ex: PRT-...)" required>
            </div>
        </div>
        <div class="row table left padding-tb-md padding-lr-lg border-bottom row-gap-10 full-line">
            <div class="col">
                <label for="tipo_parte">Tipo de parte <span class="label-hint-obrigatorio">*obrigatório</span></label>
                <select class="width-200 height-md white" id="tipo_parte" required>
                    <option value="">Selecione</option>
                    <?php foreach ($tiposValidos as $val => $label): ?>
                        <option value="<?php echo htmlspecialchars($val); ?>"><?php echo htmlspecialchars($label); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col">
                <label for="polo">Polo <span class="label-hint-obrigatorio">*obrigatório</span></label>
                <select class="width-200 height-md white" id="polo" required>
                    <option value="">Selecione</option>
                    <?php foreach ($polosValidos as $val => $label): ?>
                        <option value="<?php echo htmlspecialchars($val); ?>"><?php echo htmlspecialchars($label); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="row table right buttons padding-tb-md padding-lr-lg border-top row-gap-10">
            <a href="/frame.php?page=consultaprocessosedit&id=<?php echo urlencode($processoId); ?>" class="btn btn-secondary">Voltar</a>
            <button type="button" class="btn btn-primary" id="vincular-btn">Vincular</button>
        </div>
    </div>
    <script>
    document.getElementById('vincular-btn').addEventListener('click', function() {
        var processoId = document.getElementById('processo-id').value;
        var parteId = (document.getElementById('parte-id').value || '').trim();
        var tipoParte = (document.getElementById('tipo_parte').value || '').trim();
        var polo = (document.getElementById('polo').value || '').trim();
        if (!parteId || !tipoParte || !polo) {
            if (typeof showToast === 'function') showToast({ type: 'error', message: 'Preencha parte, tipo e polo.' });
            return;
        }
        this.disabled = true;
        fetch('/api/processos-partes/create.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ processo_id: processoId, parte_id: parteId, tipo_parte: tipoParte, polo: polo })
        })
        .then(function(r) { return r.json(); })
        .then(function(d) {
            if (d.id) {
                if (typeof showToast === 'function') showToast({ type: 'success', message: 'Parte vinculada.' });
                window.location.href = '/frame.php?page=consultaprocessosedit&id=' + encodeURIComponent(processoId);
            } else {
                if (typeof showToast === 'function') showToast({ type: 'error', message: d.error || 'Erro.' });
                document.getElementById('vincular-btn').disabled = false;
            }
        })
        .catch(function() { document.getElementById('vincular-btn').disabled = false; });
    });
    </script>
</body>
</html>
