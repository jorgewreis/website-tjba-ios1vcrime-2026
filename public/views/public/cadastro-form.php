<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../config/security.php';
require_once __DIR__ . '/../../../app/helpers/bootstrap.php';

$termosLgpdPdfUrl = '/documents/pdf/termos-lgpd-cadastro-estudantes.pdf';

$faculdades = [
    'Anhanguera - Polo Ilhéus',
    'Anhanguera - Polo Itabuna',
    'Estácio - Polo Ilhéus',
    'Estácio - Polo Itabuna',
    'Faculdade de Ilhéus',
    'UESC - Universidade Estadual de Santa Cruz',
    'UFSB - Universidade Federal do Sul da Bahia',
    'UNEX - Centro Universitário de Excelecia - Itabuna',
    'UNIME - União Metropolitana de Educação e Cultura - Itabuna',
    'Outra',
];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cadastro de estudantes</title>

    <link rel="stylesheet" href="/assets/css/shared/reset.css">
    <link rel="stylesheet" href="/assets/css/shared/color.css">
    <link rel="stylesheet" href="/assets/css/shared/form.css">
    <link rel="stylesheet" href="/assets/css/shared/table.css">
    <link rel="stylesheet" href="/assets/css/shared/layout.css">
    <link rel="stylesheet" href="/assets/css/shared/login.css">

    <link rel="stylesheet" href="https://cdn-uicons.flaticon.com/3.0.0/uicons-regular-rounded/css/uicons-regular-rounded.css">

    <script>
        (function() {
            var name = 'tjba_1vcrime_theme';
            var i = document.cookie.indexOf(name + '=');
            if (i === -1) return;
            i += name.length + 1;
            var end = document.cookie.indexOf(';', i);
            var t = decodeURIComponent((end === -1 ? document.cookie.slice(i) : document.cookie.slice(i, end)).trim());
            if (t === 'claro' || t === 'escuro' || t === 'auto') document.documentElement.setAttribute('data-theme', t);
        })();
    </script>

    <style>
        html, body {
            margin: 0;
            padding: 0;
            width: 100%;
            height: 100%;
            background-color: var(--color-background-elevated);
            overflow-x: hidden;
            overflow-y: auto;
        }
    </style>
</head>
<body>

    <div class="title-page" style="margin-top: 30px;">
        <span class="title">Cadastro de estudantes</span>
        <span class="info-title">Antes de se cadastrar, leia os termos sobre uso dos dados pessoais (LGPD).</span>
        <button type="button" class="btn-link-badge" data-nav-href="<?php echo htmlspecialchars($termosLgpdPdfUrl, ENT_QUOTES, 'UTF-8'); ?>" data-nav-target="_blank" style="text-decoration: none; display: inline-block; margin-top: 8px;">
            <span class="badge file">
                <img src="/assets/icons/Office-File-Pdf-1--Streamline-Ultimate.png" alt="PDF">
                <span>Termos sobre LGPD – Cadastro de estudantes</span>
            </span>
        </button>
    </div>

    <form class="form-login" id="formEstudante">
        <div class="display-flex row row-full justify-start align-center padding-x-sm">
            <div class="col">
                <label for="nome_completo">Nome completo *</label>
                <input class="width-600" type="text" id="nome_completo" name="nome_completo" required minlength="3" maxlength="200" placeholder="Nome e sobrenome" autocomplete="name">
            </div>
        </div>

        <div class="display-flex row row-full justify-start align-center padding-x-sm">
            <div class="col">
                <label for="cpf">CPF *</label>
                <input class="width-400" type="text" id="cpf" name="cpf" required maxlength="14" placeholder="000.000.000-00">
            </div>
        </div>

        <div class="display-flex row row-full justify-start align-center padding-x-sm">
            <div class="col">
                <label for="sexo">Sexo *</label>
                <select class="width-300" id="sexo" name="sexo" required>
                    <option value="">Selecione</option>
                    <option value="Feminino">Feminino</option>
                    <option value="Masculino">Masculino</option>
                    <option value="Outro">Outro</option>
                </select>
            </div>
        </div>

        <div class="display-flex row row-full justify-start align-center padding-x-sm">
            <div class="col">
                <label for="telefone">Telefone com DDD *</label>
                <input class="width-400" type="text" id="telefone" name="telefone" required maxlength="20" placeholder="(73) 99999-8888">
            </div>
        </div>

        <div class="display-flex row row-full justify-start align-center padding-x-sm">
            <div class="col">
                <label class="checkbox-label">
                    <input type="checkbox" id="telefone_whatsapp" name="telefone_whatsapp" value="1">
                    <span>Este número é WhatsApp</span>
                </label>
            </div>
        </div>

        <div class="display-flex row row-full justify-start align-center padding-x-sm padding-y-sm">
            <div class="col">
                <label for="faculdade">Faculdade *</label>
                <select class="width-700" id="faculdade" name="faculdade" required>
                    <option value="">Selecione a faculdade</option>
                    <?php foreach ($faculdades as $f): ?>
                        <option value="<?php echo htmlspecialchars($f, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($f, ENT_QUOTES, 'UTF-8'); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="display-flex row row-full justify-start align-center padding-x-sm">
            <div class="col">
                <label for="email">E-mail *</label>
                <input class="width-600" type="email" id="email" name="email" required maxlength="255" placeholder="seu@email.com" autocomplete="email">
            </div>
        </div>

        <div class="display-flex row row-full justify-start align-center padding-x-sm">
            <div class="col">
                <label for="senha">Senha *</label>
                <input class="width-400" type="password" id="senha" name="senha" required minlength="<?php echo PASSWORD_MIN_LENGTH; ?>" maxlength="<?php echo PASSWORD_MAX_LENGTH; ?>" placeholder="Mín. <?php echo PASSWORD_MIN_LENGTH; ?> caracteres, 1 maiúscula, 1 minúscula, 1 número e 1 símbolo" autocomplete="new-password">
            </div>
        </div>

        <div class="display-flex row row-full justify-start align-center padding-x-sm">
            <div class="col">
                <label class="checkbox-label">
                    <input type="checkbox" id="aceite_termos_lgpd" name="aceite_termos_lgpd" value="1" required>
                    <span>Li e aceito os <button type="button" class="link-inline" data-nav-href="<?php echo htmlspecialchars($termosLgpdPdfUrl, ENT_QUOTES, 'UTF-8'); ?>" data-nav-target="_blank">termos sobre LGPD</button></span>
                </label>
            </div>
        </div>

        <div class="display-flex row row-full justify-start align-center padding-x-sm padding-y-md border-bottom">
            <button class="btn btn-primary width-300" type="submit">Cadastrar</button>
        </div>

        <div class="display-flex row row-full justify-start align-center padding-x-sm padding-y-md">
            <button type="button" class="btn btn-secondary width-300" data-nav-href="/index.php">Voltar</button>
        </div>
    </form>

    <script src="/assets/js/shared/navigate-buttons-iframe.js" defer></script>
    <script src="/assets/js/shared/cpf.js" defer></script>
    <script src="/assets/js/shared/toast-helper.js" defer></script>
    <script src="/assets/js/shared/text-formatter.js"></script>
    <script defer>
        // Shim para compatibilidade com libs que usam App.Toast
        window.App = window.App || {};
        App.Toast = { show: function(opts) { showToast(opts); } };

        document.addEventListener('DOMContentLoaded', async function () {
            await App.CPF.init('#cpf');

            const form = document.getElementById('formEstudante');
            const inputNome = document.getElementById('nome_completo');

            function formatarNome(val) {
                if (typeof TextFormatter !== 'undefined' && typeof TextFormatter.formatProperName === 'function') {
                    return TextFormatter.formatProperName(val || '', 200);
                }
                return (val || '').trim();
            }

            inputNome.addEventListener('blur', function () {
                const v = this.value.trim();
                if (v) this.value = formatarNome(v);
            });

            function onlyDigits(s) {
                return (s || '').toString().replace(/\D/g, '');
            }

            form.addEventListener('submit', async function (e) {
                e.preventDefault();
                const runId = 'run-' + Date.now().toString(36) + '-' + Math.random().toString(36).slice(2, 8);
                // #region agent log
                fetch('http://127.0.0.1:7494/ingest/64db24e1-3fcf-482a-aa07-c0ab6247ae38',{method:'POST',headers:{'Content-Type':'application/json','X-Debug-Session-Id':'07d2e0'},body:JSON.stringify({sessionId:'07d2e0',runId:runId,hypothesisId:'H4',location:'cadastro-form.php:196',message:'Submit iniciado no cadastro de estudante',data:{},timestamp:Date.now()})}).catch(()=>{});
                // #endregion

                const cpfDigits = onlyDigits(document.getElementById('cpf').value);
                if (cpfDigits.length !== 11) {
                    // #region agent log
                    fetch('http://127.0.0.1:7494/ingest/64db24e1-3fcf-482a-aa07-c0ab6247ae38',{method:'POST',headers:{'Content-Type':'application/json','X-Debug-Session-Id':'07d2e0'},body:JSON.stringify({sessionId:'07d2e0',runId:runId,hypothesisId:'H4',location:'cadastro-form.php:201',message:'Bloqueio frontend: CPF inválido',data:{cpfDigitsLength:cpfDigits.length},timestamp:Date.now()})}).catch(()=>{});
                    // #endregion
                    showToast({ type: 'error', title: 'Erro', message: 'Informe um CPF válido.' });
                    return;
                }

                const senha = (form.querySelector('#senha') && form.querySelector('#senha').value) || '';
                const senhaRegex = /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^\w\s]).{<?php echo PASSWORD_MIN_LENGTH; ?>,<?php echo PASSWORD_MAX_LENGTH; ?>}$/;
                if (!senhaRegex.test(senha)) {
                    // #region agent log
                    fetch('http://127.0.0.1:7494/ingest/64db24e1-3fcf-482a-aa07-c0ab6247ae38',{method:'POST',headers:{'Content-Type':'application/json','X-Debug-Session-Id':'07d2e0'},body:JSON.stringify({sessionId:'07d2e0',runId:runId,hypothesisId:'H4',location:'cadastro-form.php:210',message:'Bloqueio frontend: senha fora da política',data:{passwordLength:senha.length},timestamp:Date.now()})}).catch(()=>{});
                    // #endregion
                    showToast({ type: 'error', title: 'Erro', message: 'A senha deve seguir a política: <?php echo addslashes(Security::passwordPolicySummary()); ?>.' });
                    return;
                }

                if (!form.querySelector('#aceite_termos_lgpd').checked) {
                    // #region agent log
                    fetch('http://127.0.0.1:7494/ingest/64db24e1-3fcf-482a-aa07-c0ab6247ae38',{method:'POST',headers:{'Content-Type':'application/json','X-Debug-Session-Id':'07d2e0'},body:JSON.stringify({sessionId:'07d2e0',runId:runId,hypothesisId:'H4',location:'cadastro-form.php:216',message:'Bloqueio frontend: LGPD não aceito',data:{},timestamp:Date.now()})}).catch(()=>{});
                    // #endregion
                    showToast({ type: 'error', title: 'Atenção', message: 'É necessário aceitar os termos sobre LGPD para realizar o cadastro.' });
                    return;
                }

                const emailVal = (form.querySelector('#email') && form.querySelector('#email').value || '').trim();
                if (!emailVal) {
                    // #region agent log
                    fetch('http://127.0.0.1:7494/ingest/64db24e1-3fcf-482a-aa07-c0ab6247ae38',{method:'POST',headers:{'Content-Type':'application/json','X-Debug-Session-Id':'07d2e0'},body:JSON.stringify({sessionId:'07d2e0',runId:runId,hypothesisId:'H4',location:'cadastro-form.php:223',message:'Bloqueio frontend: email vazio',data:{},timestamp:Date.now()})}).catch(()=>{});
                    // #endregion
                    showToast({ type: 'error', title: 'Erro', message: 'Informe o e-mail (necessário para recuperação de senha).' });
                    return;
                }

                const btn = form.querySelector('button[type="submit"]');
                const originalText = btn ? btn.textContent : '';
                if (btn) { btn.disabled = true; btn.textContent = 'Enviando...'; }

                try {
                    const formData = new FormData(form);
                    const nomeRaw = (formData.get('nome_completo') || '').trim();
                    const payload = {
                        nome_completo: nomeRaw ? formatarNome(nomeRaw) : '',
                        cpf: cpfDigits,
                        senha: senha,
                        telefone: (formData.get('telefone') || '').trim().slice(0, 20),
                        sexo: (formData.get('sexo') || '').trim(),
                        telefone_whatsapp: form.querySelector('#telefone_whatsapp').checked,
                        faculdade: formData.get('faculdade') || '',
                        email: (formData.get('email') || '').trim().toLowerCase().slice(0, 255),
                        aceite_termos_lgpd: true,
                    };
                    // #region agent log
                    fetch('http://127.0.0.1:7494/ingest/64db24e1-3fcf-482a-aa07-c0ab6247ae38',{method:'POST',headers:{'Content-Type':'application/json','X-Debug-Session-Id':'07d2e0'},body:JSON.stringify({sessionId:'07d2e0',runId:runId,hypothesisId:'H1',location:'cadastro-form.php:239',message:'Payload pronto para API de cadastro estudante',data:{cpfDigitsLength:payload.cpf.length,hasTelefone:payload.telefone.length>0,sexo:payload.sexo||'',faculdadeSelecionada:payload.faculdade!==''},timestamp:Date.now()})}).catch(()=>{});
                    // #endregion

                    const res = await fetch('/api/cadastro_estudantes.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                        body: JSON.stringify(payload),
                    });
                    const data = await res.json().catch(() => ({}));
                    // #region agent log
                    fetch('http://127.0.0.1:7494/ingest/64db24e1-3fcf-482a-aa07-c0ab6247ae38',{method:'POST',headers:{'Content-Type':'application/json','X-Debug-Session-Id':'07d2e0'},body:JSON.stringify({sessionId:'07d2e0',runId:runId,hypothesisId:'H2',location:'cadastro-form.php:246',message:'Resposta recebida da API de cadastro estudante',data:{httpStatus:res.status,success:!!data.success,message:data.message||''},timestamp:Date.now()})}).catch(()=>{});
                    // #endregion

                    if (data.success) {
                        showToast({
                            type: 'success',
                            title: 'Sucesso',
                            message: data.message || 'Cadastro realizado com sucesso.',
                            onClose: function () {
                                if (window.parent !== window) {
                                    window.parent.location.href = '/index.php';
                                } else {
                                    window.location.href = '/index.php';
                                }
                            }
                        });
                        form.reset();
                    } else {
                        showToast({ type: 'error', title: 'Erro', message: data.message || 'Erro ao enviar. Tente novamente.' });
                    }
                } catch (err) {
                    showToast({ type: 'error', title: 'Erro', message: 'Erro de conexão. Tente novamente.' });
                } finally {
                    if (btn) { btn.disabled = false; btn.textContent = originalText; }
                }
            });
        });
    </script>

</body>
</html>
