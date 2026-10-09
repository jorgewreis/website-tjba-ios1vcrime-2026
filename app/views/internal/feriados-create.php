<?php
declare(strict_types=1);

header('Content-Type: text/html; charset=UTF-8');
if (function_exists('mb_internal_encoding')) {
    mb_internal_encoding('UTF-8');
}

require_once __DIR__ . '/../../services/FeriadosService.php';

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
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Novo feriado</title>
    <?php include __DIR__ . '/_internal_head_styles.php'; ?>
    <link rel="stylesheet" href="https://cdn-uicons.flaticon.com/3.0.0/uicons-regular-rounded/css/uicons-regular-rounded.css">
    <?php include __DIR__ . '/_toast_script.php'; ?>
</head>
<body class="page-internal">
    <div class="spacing-top-page"></div>
    <div class="header-page">
        <div class="location"><span class="all">Interno - Admin -</span> <span class="specific">Feriados · Novo</span></div>
        <h1 class="title-page">Novo feriado / recesso</h1>
    </div>

    <div class="container-full card-page">
        <div class="display-flex row row-full align-center padding-x-md padding-y-md border-bottom">
            <h2>Dados</h2>
        </div>

        <div class="display-flex row row-full padding-x-md padding-y-md border-bottom gap-y-md flex-wrap">
            <div class="col">
                <label for="tipo_feriado">Tipo <span class="label-hint-obrigatorio">*obrigatório</span></label>
                <select class="width-300 height-md" id="tipo_feriado" required>
                    <option value="">Selecione</option>
                    <?php foreach (FeriadosService::TIPOS_FERIADO as $t): ?>
                        <option value="<?php echo htmlspecialchars($t); ?>"><?php echo htmlspecialchars($labelsTipo[$t] ?? $t); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col">
                <label for="regra_data">Regra de data <span class="label-hint-obrigatorio">*obrigatório</span></label>
                <select class="width-400 height-md" id="regra_data" required>
                    <option value="">Selecione</option>
                    <?php foreach (FeriadosService::REGRAS_DATA as $r): ?>
                        <option value="<?php echo htmlspecialchars($r); ?>"><?php echo htmlspecialchars($labelsRegra[$r] ?? $r); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="display-flex row row-full padding-x-md padding-y-md border-bottom gap-y-md flex-wrap">
            <div class="col">
                <label for="data_inicio">Data início <span class="label-hint-obrigatorio">*obrigatório</span></label>
                <input type="date" class="width-300 height-md" id="data_inicio" required>
            </div>
            <div class="col">
                <label for="data_fim">Data fim <span class="label-hint-obrigatorio">*obrigatório</span></label>
                <input type="date" class="width-300 height-md" id="data_fim" required>
            </div>
            <div class="col display-none align-center padding-top-lg">
                <label class="inline display-flex align-center gap-y-sm">
                    <input type="checkbox" id="ativo" checked>
                    <span>Ativo</span>
                </label>
            </div>
        </div>

        <div class="display-flex row row-full padding-x-md padding-y-md border-bottom gap-y-md">
            <div class="col col-full">
                <label for="descricao">Descrição</label>
                <input type="text" class="width-700 height-md" id="descricao" maxlength="255" placeholder="Opcional, até 255 caracteres">
            </div>
        </div>

        <div class="display-flex row row-full justify-end gap-y-md padding-x-md padding-y-md border-top">
            <button type="button" class="btn btn-secondary height-md" id="voltar-btn">Voltar</button>
            <button type="button" class="btn btn-primary height-md" id="cadastrar-btn">Cadastrar</button>
        </div>
    </div>

    <script>
    (function() {
        var iniEl = document.getElementById('data_inicio');
        var fimEl = document.getElementById('data_fim');
        if (iniEl && fimEl) {
            iniEl.addEventListener('change', function() {
                var v = (iniEl.value || '').trim();
                if (v !== '') {
                    fimEl.value = v;
                }
            });
            fimEl.addEventListener('change', function() {
                var vi = (iniEl.value || '').trim();
                var vf = (fimEl.value || '').trim();
                if (vi !== '' && vf !== '' && vf < vi) {
                    if (typeof showToast === 'function') {
                        showToast({ type: 'error', variant: 'full', title: 'Erro', message: 'A data fim não pode ser anterior à data início.' });
                    }
                    fimEl.value = vi;
                }
            });
        }
        document.getElementById('voltar-btn').addEventListener('click', function() {
            window.location.href = '/frame.php?page=feriadoslist';
        });
    })();
    document.getElementById('cadastrar-btn').addEventListener('click', function() {
        var tipo = (document.getElementById('tipo_feriado').value || '').trim();
        var regra = (document.getElementById('regra_data').value || '').trim();
        var ini = (document.getElementById('data_inicio').value || '').trim();
        var fim = (document.getElementById('data_fim').value || '').trim();
        if (!tipo || !regra || !ini || !fim) {
            if (typeof showToast === 'function') showToast({ type: 'error', variant: 'full', title: 'Erro', message: 'Preencha os campos obrigatórios.' });
            return;
        }
        if (fim < ini) {
            if (typeof showToast === 'function') showToast({ type: 'error', variant: 'full', title: 'Erro', message: 'A data fim não pode ser anterior à data início.' });
            return;
        }
        var desc = (document.getElementById('descricao').value || '').trim();
        var ativo = document.getElementById('ativo').checked ? 1 : 0;
        var btn = this;
        btn.disabled = true;
        fetch('/api/admin/feriados/create.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'same-origin',
            body: JSON.stringify({
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
            if (o.ok && o.j.id) {
                var msgOk = 'Cadastrado.';
                var nEd = parseInt(o.j.editais_decurso_recalculados, 10);
                if (!isNaN(nEd) && nEd > 0) {
                    msgOk += ' Decurso de prazo recalculado em ' + nEd + ' ' + (nEd === 1 ? 'edital' : 'editais') + '.';
                }
                if (typeof showToast === 'function') showToast({ type: 'success', variant: 'full', title: 'Sucesso', message: msgOk });
                window.location.href = '/frame.php?page=feriadoslist';
            } else {
                if (typeof showToast === 'function') showToast({ type: 'error', variant: 'full', title: 'Erro', message: (o.j && o.j.error) ? o.j.error : 'Erro ao cadastrar.' });
                btn.disabled = false;
            }
        })
        .catch(function() { btn.disabled = false; });
    });
    </script>
</body>
</html>
