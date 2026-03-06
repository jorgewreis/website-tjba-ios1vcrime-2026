<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/security.php';
require_once __DIR__ . '/../app/helpers/bootstrap.php';

$faculdades = [
    'UESC - Universidade Estadual de Santa Cruz',
    'FTC - Faculdade de Tecnologia e Ciências (Ilhéus)',
    'UNIME - União Metropolitana de Educação e Cultura',
    'UFSB - Universidade Federal do Sul da Bahia',
    'Outra (Ilhéus/região)',
];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cadastro de estudantes - Primeira Vara Crime de Ilhéus</title>
    <link rel="stylesheet" href="/assets/css/shared/reset.css">
    <link rel="stylesheet" href="/assets/css/shared/variables.css">
    <link rel="stylesheet" href="/assets/css/shared/base.css">
    <link rel="stylesheet" href="/assets/css/shared/form.css">
    <link rel="stylesheet" href="/assets/css/shared/table.css">
    <link rel="stylesheet" href="/assets/css/shared/login.css">
    <link rel="icon" type="image/x-icon" href="/assets/img/favicon.ico">
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
</head>
<body>

    <div class="noview"></div>

    <div class="view layout-wraper">
        <div class="layout-container">
            <div class="layout-page">

                <div class="page-left">
                    <div class="title-system">
                        <span class="title">PRIMEIRA VARA</span>
                        <span class="info-title">CRIMINAL</span>
                        <p class="text">A excelência nasce de um fluxo inteligente, padronização segura, responsabilidade clara e foco na efetividade jurisdicional.</p>
                    </div>
                </div>

                <div class="page-right">
                    <div class="title-page">
                        <span class="title">Cadastro de estudantes</span>
                        <span class="info-title">Estudantes de Direito podem se cadastrar para acompanhamento de audiências, atas e pautas. Preencha os dados abaixo. Cadastros inativos por mais de 30 dias são removidos automaticamente.</span>
                    </div>

                    <form class="form-login" id="formEstudante">
                        <div class="row">
                            <div class="col">
                                <label for="nome_completo">Nome completo *</label>
                                <input class="width-md white" type="text" id="nome_completo" name="nome_completo" required minlength="3" placeholder="Nome e sobrenome">
                            </div>
                        </div>

                        <div class="row">
                            <div class="col">
                                <label for="cpf">CPF *</label>
                                <input class="width-md white" type="text" id="cpf" name="cpf" required placeholder="000.000.000-00">
                            </div>
                        </div>

                        <div class="row">
                            <div class="col">
                                <label for="telefone">Telefone com DDD *</label>
                                <input class="width-md white" type="text" id="telefone" name="telefone" required placeholder="(73) 99999-8888">
                            </div>
                        </div>

                        <div class="row table">
                            <div class="col">
                                <label class="checkbox-label">
                                    <input type="checkbox" id="telefone_whatsapp" name="telefone_whatsapp" value="1">
                                    <span>Este número é WhatsApp</span>
                                </label>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col">
                                <label for="faculdade">Faculdade *</label>
                                <select class="width-md white" id="faculdade" name="faculdade" required>
                                    <option value="">Selecione a faculdade</option>
                                    <?php foreach ($faculdades as $f): ?>
                                        <option value="<?php echo htmlspecialchars($f, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($f, ENT_QUOTES, 'UTF-8'); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col">
                                <label for="email">E-mail (opcional)</label>
                                <input class="width-md white" type="email" id="email" name="email" placeholder="seu@email.com">
                            </div>
                        </div>

                        <div class="row">
                            <div class="col">
                                <label for="semestre">Semestre (opcional)</label>
                                <input class="width-md white" type="text" id="semestre" name="semestre" placeholder="Ex.: 3º semestre">
                            </div>
                        </div>

                        <div class="row" style="background-color: transparent; padding: 20px 0 10px 0; width: 100%; border-bottom: 1px solid var(--color-gray-400);">
                            <button class="btn btn-primary width-md" type="submit">Enviar cadastro</button>
                        </div>

                        <div class="row">
                            <div class="alert-heading danger width-md" id="alert-form" style="display: none;">
                                <div class="alert-header">
                                    <span class="text">Erro</span>
                                </div>
                                <span class="alert-text" id="erro-msg"></span>
                            </div>
                            <div class="alert-heading success width-md" id="alert-success" style="display: none;">
                                <div class="alert-header">
                                    <span class="text">Sucesso</span>
                                </div>
                                <span class="alert-text" id="success-msg"></span>
                            </div>
                        </div>

                        <div class="row" style="border: none; padding-top: 10px;">
                            <div class="col">
                                <a href="/index.php" class="link-login">Voltar para login</a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <div class="layout-overlay"></div>
    </div>

    <script src="/assets/js/shared/cpf.js" defer></script>
    <script defer>
        document.addEventListener("DOMContentLoaded", async () => {
            await App.CPF.init("#cpf");

            const form = document.getElementById("formEstudante");
            const alertError = document.getElementById("alert-form");
            const alertSuccess = document.getElementById("alert-success");
            const erroMsg = document.getElementById("erro-msg");
            const successMsg = document.getElementById("success-msg");

            function onlyDigits(s) {
                return (s || "").toString().replace(/\D/g, "");
            }

            form.addEventListener("submit", async (e) => {
                e.preventDefault();
                alertError.style.display = "none";
                alertSuccess.style.display = "none";

                const cpfDigits = onlyDigits(document.getElementById("cpf").value);
                if (cpfDigits.length !== 11) {
                    erroMsg.textContent = "Informe um CPF válido.";
                    alertError.style.display = "block";
                    return;
                }

                const btn = form.querySelector("button[type=submit]");
                const originalText = btn.textContent;
                btn.disabled = true;
                btn.textContent = "Enviando...";

                try {
                    const formData = new FormData(form);
                    const payload = {
                        nome_completo: formData.get("nome_completo") || "",
                        cpf: cpfDigits,
                        telefone: formData.get("telefone") || "",
                        telefone_whatsapp: form.querySelector("#telefone_whatsapp").checked,
                        faculdade: formData.get("faculdade") || "",
                        email: formData.get("email") || "",
                        semestre: formData.get("semestre") || "",
                    };

                    const res = await fetch("/api/cadastro_estudantes.php", {
                        method: "POST",
                        headers: { "Content-Type": "application/json", "Accept": "application/json" },
                        body: JSON.stringify(payload),
                    });
                    const data = await res.json().catch(() => ({}));

                    if (data.success) {
                        successMsg.textContent = data.message || "Cadastro realizado com sucesso.";
                        alertSuccess.style.display = "block";
                        form.reset();
                    } else {
                        erroMsg.textContent = data.message || "Erro ao enviar. Tente novamente.";
                        alertError.style.display = "block";
                    }
                } catch (err) {
                    erroMsg.textContent = "Erro de conexão. Tente novamente.";
                    alertError.style.display = "block";
                } finally {
                    btn.disabled = false;
                    btn.textContent = originalText;
                }
            });
        });
    </script>
</body>
</html>
