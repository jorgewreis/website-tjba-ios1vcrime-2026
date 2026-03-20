<?php
declare(strict_types=1);

header('Content-Type: text/html; charset=UTF-8');
if (function_exists('mb_internal_encoding')) {
    mb_internal_encoding('UTF-8');
}

require_once __DIR__ . '/../../helpers/Conection.php';
require_once __DIR__ . '/../../services/FeriadosService.php';

$id = trim((string) ($_GET['id'] ?? ''));
if ($id === '') {
    header('Location: /frame.php?page=feriadoslist');
    exit;
}

$connection = Conection::getInstance()->getConnection();
$service = new FeriadosService($connection);
$row = $service->find($id);
if ($row === null) {
    header('Location: /frame.php?page=feriadoslist');
    exit;
}

$labelsTipo = [
    'estadual' => 'Estadual',
    'municipal' => 'Municipal',
    'federal' => 'Federal',
    'religioso' => 'Religioso',
    'compensacao' => 'Compensação',
    'recesso' => 'Recesso',
];
$labelsRegra = [
    'fixa' => 'Fixa',
    'unica' => 'Única',
    'movel' => 'Móvel',
];

$tipoCur = (string) ($row['tipo_feriado'] ?? '');
$regraCur = (string) ($row['regra_data'] ?? '');
$iniCur = (string) ($row['data_inicio'] ?? '');
$fimCur = (string) ($row['data_fim'] ?? '');
$descCur = (string) ($row['descricao'] ?? '');
$ativoCur = !empty($row['ativo']);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Editar feriado</title>
    <?php include __DIR__ . '/_internal_head_styles.php'; ?>
    <link rel="stylesheet" href="https://cdn-uicons.flaticon.com/3.0.0/uicons-regular-rounded/css/uicons-regular-rounded.css">
    <?php include __DIR__ . '/_toast_script.php'; ?>
</head>
<body class="page-internal">
    <div class="spacing-top-page"></div>
    <div class="header-page">
        <div class="location"><span class="all">Interno - Admin -</span> <span class="specific">Feriados · Editar</span></div>
        <h1 class="title-page">Editar feriado / recesso</h1>
    </div>

    <input type="hidden" id="feriado-id" value="<?php echo htmlspecialchars($row['id']); ?>">

    <div class="container-full card-page">
        <div class="display-flex row row-full align-center padding-x-md padding-y-md border-bottom">
            <h2>Dados</h2>
        </div>

        <div class="display-flex row row-full padding-x-md padding-y-md border-bottom gap-y-md flex-wrap">
            <div class="col">
                <label for="tipo_feriado">Tipo <span class="label-hint-obrigatorio">*obrigatório</span></label>
                <select class="width-300 height-md" id="tipo_feriado" required>
                    <?php foreach (FeriadosService::TIPOS_FERIADO as $t): ?>
                        <option value="<?php echo htmlspecialchars($t); ?>"<?php echo $tipoCur === $t ? ' selected' : ''; ?>><?php echo htmlspecialchars($labelsTipo[$t] ?? $t); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col">
                <label for="regra_data">Regra de data <span class="label-hint-obrigatorio">*obrigatório</span></label>
                <select class="width-260 height-md" id="regra_data" required>
                    <?php foreach (FeriadosService::REGRAS_DATA as $r): ?>
                        <option value="<?php echo htmlspecialchars($r); ?>"<?php echo $regraCur === $r ? ' selected' : ''; ?>><?php echo htmlspecialchars($labelsRegra[$r] ?? $r); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="display-flex row row-full padding-x-md padding-y-md border-bottom gap-y-md flex-wrap">
            <div class="col">
                <label for="data_inicio">Data início <span class="label-hint-obrigatorio">*obrigatório</span></label>
                <input type="date" class="width-240 height-md" id="data_inicio" required value="<?php echo htmlspecialchars($iniCur); ?>">
            </div>
            <div class="col">
                <label for="data_fim">Data fim <span class="label-hint-obrigatorio">*obrigatório</span></label>
                <input type="date" class="width-240 height-md" id="data_fim" required value="<?php echo htmlspecialchars($fimCur); ?>">
            </div>
            <div class="col display-flex align-center padding-top-lg">
                <label class="inline display-flex align-center gap-y-sm">
                    <input type="checkbox" id="ativo"<?php echo $ativoCur ? ' checked' : ''; ?>>
                    <span>Ativo</span>
                </label>
            </div>
        </div>

        <div class="display-flex row row-full padding-x-md padding-y-md border-bottom gap-y-md">
            <div class="col col-full">
                <label for="descricao">Descrição</label>
                <input type="text" class="width-600 height-md" id="descricao" maxlength="255" value="<?php echo htmlspecialchars($descCur); ?>" placeholder="Opcional, até 255 caracteres">
            </div>
        </div>

        <div class="display-flex row row-full justify-between gap-y-md padding-x-md padding-y-md border-top flex-wrap">
            <button type="button" class="btn btn-secondary-outline height-md" id="excluir-btn">Excluir</button>
            <div class="display-flex row row-fit justify-end gap-y-md">
                <a href="/frame.php?page=feriadoslist" class="btn btn-secondary height-md">Voltar</a>
                <button type="button" class="btn btn-primary height-md" id="salvar-btn">Salvar</button>
            </div>
        </div>
    </div>

    <script>
    (function() {
        var idEl = document.getElementById('feriado-id');
        var fid = idEl ? idEl.value : '';

        document.getElementById('salvar-btn').addEventListener('click', function() {
            var tipo = (document.getElementById('tipo_feriado').value || '').trim();
            var regra = (document.getElementById('regra_data').value || '').trim();
            var ini = (document.getElementById('data_inicio').value || '').trim();
            var fim = (document.getElementById('data_fim').value || '').trim();
            if (!tipo || !regra || !ini || !fim) {
                if (typeof showToast === 'function') showToast({ type: 'error', message: 'Preencha os campos obrigatórios.' });
                return;
            }
            if (fim < ini) {
                if (typeof showToast === 'function') showToast({ type: 'error', message: 'A data fim deve ser igual ou posterior à data início.' });
                return;
            }
            var desc = (document.getElementById('descricao').value || '').trim();
            var ativo = document.getElementById('ativo').checked ? 1 : 0;
            var btn = this;
            btn.disabled = true;
            fetch('/api/admin/feriados/update.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                credentials: 'same-origin',
                body: JSON.stringify({
                    id: fid,
                    tipo_feriado: tipo,
                    regra_data: regra,
                    data_inicio: ini,
                    data_fim: fim,
                    descricao: desc === '' ? null : desc.slice(0, 255),
                    ativo: ativo
                })
            })
            .then(function(r) { return r.json().then(function(j) { return { ok: r.ok, j: j }; }); })
            .then(function(o) {
                if (o.ok) {
                    var msgOk = 'Salvo.';
                    var nEd = parseInt(o.j.editais_decurso_recalculados, 10);
                    if (!isNaN(nEd) && nEd > 0) {
                        msgOk += ' Decurso de prazo recalculado em ' + nEd + ' ' + (nEd === 1 ? 'edital' : 'editais') + '.';
                    }
                    if (typeof showToast === 'function') showToast({ type: 'success', message: msgOk });
                    window.location.href = '/frame.php?page=feriadoslist';
                } else {
                    if (typeof showToast === 'function') showToast({ type: 'error', message: (o.j && o.j.error) ? o.j.error : 'Erro ao salvar.' });
                    btn.disabled = false;
                }
            })
            .catch(function() { btn.disabled = false; });
        });

        document.getElementById('excluir-btn').addEventListener('click', function() {
            if (!fid) return;
            if (!confirm('Excluir este feriado permanentemente?')) return;
            var btn = this;
            btn.disabled = true;
            fetch('/api/admin/feriados/delete.php?id=' + encodeURIComponent(fid), { method: 'POST', credentials: 'same-origin' })
                .then(function(r) { return r.json().then(function(j) { return { ok: r.ok, j: j }; }); })
                .then(function(o) {
                    if (o.ok) {
                        var msgEx = 'Excluído.';
                        var nEd2 = parseInt(o.j.editais_decurso_recalculados, 10);
                        if (!isNaN(nEd2) && nEd2 > 0) {
                            msgEx += ' Decurso de prazo recalculado em ' + nEd2 + ' ' + (nEd2 === 1 ? 'edital' : 'editais') + '.';
                        }
                        if (typeof showToast === 'function') showToast({ type: 'success', message: msgEx });
                        window.location.href = '/frame.php?page=feriadoslist';
                    } else {
                        if (typeof showToast === 'function') showToast({ type: 'error', message: (o.j && o.j.error) ? o.j.error : 'Erro.' });
                        btn.disabled = false;
                    }
                })
                .catch(function() { btn.disabled = false; });
        });
    })();
    </script>
</body>
</html>
