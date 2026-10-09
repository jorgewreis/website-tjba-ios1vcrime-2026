<?php
declare(strict_types=1);

header('Content-Type: text/html; charset=UTF-8');
if (function_exists('mb_internal_encoding')) {
    mb_internal_encoding('UTF-8');
}

$routePage = trim((string) ($_GET['page'] ?? 'reuspresoscreate'));
$origem = (($_GET['origem'] ?? '') === 'reuspresossubstituto') ? 'reuspresossubstituto' : 'reuspresos';
?>
<!DOCTYPE html>
<html lang="pt-BR">
    <head>
        <meta charset="utf-8">
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <title>Réus Presos (Gabinete) — Cadastro</title>
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <?php include __DIR__ . '/_internal_head_styles.php'; ?>
        <link rel="stylesheet" href="https://cdn-uicons.flaticon.com/3.0.0/uicons-regular-rounded/css/uicons-regular-rounded.css">
        <?php include __DIR__ . '/_toast_script.php'; ?>
    </head>
<body class="page-internal">

    <div class="spacing-top-page"></div>

    <div class="header-page">
        <div class="location">
            <span class="all">Interno - Gabinete - Réus Presos -</span>
            <span class="specific">Cadastro</span>
        </div>
        <h1 class="title-page">Novo réu preso</h1>
        <p class="description-page">Informe o nome do réu. Processos, datas e movimentações serão cadastrados na edição.</p>
    </div>

    <div class="container-full card-page">

        <div class="display-flex row row-full align-center justify-between padding-x-md padding-y-md border-bottom">
            <h2 style="margin:0;">Dados mínimos</h2>
        </div>

        <div class="display-flex col col-full padding-x-md padding-y-md border-bottom gap-y-md">
            <div class="col">
                <label for="grp-nome">Nome completo do réu <span class="label-hint-obrigatorio">*obrigatório</span></label>
                <input type="text" class="width-800 height-md" id="grp-nome" name="nome" placeholder="Nome conforme nos autos" maxlength="255" required autocomplete="off" aria-label="Nome completo do réu">
            </div>
            <p class="minor-info" style="margin:0;">Após salvar, você será direcionado à edição para vincular processos e movimentações.</p>

            <div class="display-flex row row-full justify-end gap-y-md padding-x-md padding-y-md border-top" style="gap:12px;">
                <button type="button" class="btn btn-secondary" id="btn-grp-voltar">Voltar</button>
                <button type="button" class="btn btn-primary" id="btn-grp-salvar">Salvar</button>
            </div>
        </div>
    </div>

    <div class="footer-page">
        <div class="location">
            <span class="all">Aplicativo web desenvolvido integralmente por </span>
            <span class="specific">Jorge Reis Develop</span>
            <span class="all">- Copyright 2018 a 2026 - Todos os direitos reservados.</span>
        </div>
    </div>

    <script>
        (function () {
            const notify = (type, title, message) => {
                if (window.App && App.Page && typeof App.Page.notify === 'function') {
                    App.Page.notify(type, title, message);
                    return;
                }
                if (typeof showToast === 'function') showToast({ type, variant: 'full', title, message });
            };

            document.getElementById('btn-grp-voltar')?.addEventListener('click', function () {
                window.location.href = '/frame.php?page=<?php echo $origem; ?>';
            });

            document.getElementById('grp-nome')?.addEventListener('blur', function () {
                const nome = (this.value || '').trim();
                if (nome && typeof TextFormatter !== 'undefined' && TextFormatter.formatProperName) {
                    this.value = TextFormatter.formatProperName(nome, 255);
                }
            });

            document.getElementById('btn-grp-salvar')?.addEventListener('click', function () {
                const nome = (document.getElementById('grp-nome')?.value || '').trim();
                if (!nome) {
                    notify('error', 'Validação', 'Informe o nome do réu.');
                    return;
                }
                const btn = this;
                btn.disabled = true;
                fetch('/api/gabinete-reus-presos/create.php' + (new URLSearchParams(window.location.search).get('origem') === 'reuspresossubstituto' ? '?contexto=substituto' : ''), {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ nome: nome }),
                    credentials: 'same-origin'
                })
                .then(function (r) { return r.json().then(function (j) { return { ok: r.ok, j: j }; }); })
                .then(function (res) {
                    if (res.ok && res.j && res.j.id) {
                        notify('success', 'Sucesso', 'Cadastro criado. Redirecionando para edição.');
                        window.location.href = '/frame.php?page=reuspresosedit&id=' + encodeURIComponent(res.j.id) + (new URLSearchParams(window.location.search).get('origem') === 'reuspresossubstituto' ? '&origem=reuspresossubstituto' : '');
                    } else {
                        notify('error', 'Erro', (res.j && res.j.error) ? res.j.error : 'Falha ao salvar.');
                        btn.disabled = false;
                    }
                })
                .catch(function () {
                    notify('error', 'Erro', 'Erro de conexão.');
                    btn.disabled = false;
                });
            });
        })();
    </script>
</body>
</html>
