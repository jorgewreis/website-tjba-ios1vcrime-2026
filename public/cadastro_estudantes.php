<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/security.php';
require_once __DIR__ . '/../app/helpers/bootstrap.php';

/** Caminho do PDF com os termos sobre LGPD (uso dos dados do estudante). Coloque o arquivo em public/documents/pdf/ */
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
    <title>Cadastro de estudantes - Primeira Vara Crime de Ilhéus</title>
    <link rel="stylesheet" href="/assets/css/shared/reset.css">
    <link rel="stylesheet" href="/assets/css/shared/color.css">
    <link rel="stylesheet" href="/assets/css/shared/form.css">
    <link rel="stylesheet" href="/assets/css/shared/table.css">
    <link rel="stylesheet" href="/assets/css/shared/login.css">
    <link rel="stylesheet" href="/assets/css/shared/page.css">
    <link rel="stylesheet" href="/assets/css/shared/toast.css">
    <link rel="stylesheet" href="https://cdn-uicons.flaticon.com/3.0.0/uicons-regular-rounded/css/uicons-regular-rounded.css">
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
                    <!-- FOOTER - SYSTEM -->
                    <div class="footer-page" style="position: absolute; bottom: 0;">
                        <div class="info-page">
                            <span class="info-line">Rua Dr. Hygino Filho, bloco C, 1º andar, sala 132 - Jardim Atlântico, Ilhéus - BA</span>
                            <span class="info-line">Tel.: 73 3234-3428 - Whatsapp: 71 99610-8241 - Email: ilheus1vcriminal@tjba.jus.br</span>
                        </div>
                        <div class="info-app">Sistema desenvolvido integralmente por Jorge Reis Developer - 2026 - Todos os direitos reservados</div>
                    </div>
                </div>

                <div class="page-right">
                    <div class="title-page">
                        <span class="title">Cadastro de estudantes</span>
                        <span class="info-title">Antes de se cadastrar, leia os termos sobre uso dos dados pessoais (LGPD).</span>
                        <a href="<?php echo htmlspecialchars($termosLgpdPdfUrl, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer" style="text-decoration: none; display: inline-block; margin-top: 8px;">
                            <span class="badge file">
                                <img src="/assets/icons/Office-File-Pdf-1--Streamline-Ultimate.png" alt="PDF">
                                <span>Termos sobre LGPD – Cadastro de estudantes</span>
                            </span>
                        </a>
                    </div>

                    <form class="form-login" id="formEstudante">
                        <div class="row">
                            <div class="col">
                                <label for="nome_completo">Nome completo *</label>
                                <input class="width-lg white" type="text" id="nome_completo" name="nome_completo" required minlength="3" maxlength="200" placeholder="Nome e sobrenome" autocomplete="name">
                            </div>
                        </div>

                        <div class="row">
                            <div class="col">
                                <label for="cpf">CPF *</label>
                                <input class="width-md white" type="text" id="cpf" name="cpf" required maxlength="14" placeholder="000.000.000-00">
                            </div>
                        </div>

                        <div class="row">
                            <div class="col">
                                <label for="telefone">Telefone com DDD *</label>
                                <input class="width-md white" type="text" id="telefone" name="telefone" required maxlength="20" placeholder="(73) 99999-8888">
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
                                <select class="width-lg white" id="faculdade" name="faculdade" required>
                                    <option value="">Selecione a faculdade</option>
                                    <?php foreach ($faculdades as $f): ?>
                                        <option value="<?php echo htmlspecialchars($f, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($f, ENT_QUOTES, 'UTF-8'); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col">
                                <label for="email">E-mail *</label>
                                <input class="width-lg white" type="email" id="email" name="email" required maxlength="255" placeholder="seu@email.com" autocomplete="email">
                            </div>
                        </div>

                        <div class="row">
                            <div class="col">
                                <label for="senha">Senha *</label>
                                <input class="width-md white" type="password" id="senha" name="senha" required minlength="6" maxlength="25" placeholder="Mínimo 6 caracteres" autocomplete="new-password">
                            </div>
                        </div>

                        <div class="row table">
                            <div class="col">
                                <label class="checkbox-label">
                                    <input type="checkbox" id="aceite_termos_lgpd" name="aceite_termos_lgpd" value="1" required>
                                    <span>Li e aceito os <a href="<?php echo htmlspecialchars($termosLgpdPdfUrl, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer">termos sobre LGPD</a></span>
                                </label>
                            </div>
                        </div>

                        <div class="row table left border-bottom padding-tb-md">                            
                            <button class="btn btn-primary" type="submit">Cadastrar</button>
                        </div>
                        <div class="row table left padding-tb-md">
                            <button class="btn btn-secondary" onclick="window.location.href='/index.php'">Voltar</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <div class="layout-overlay"></div>
        <div id="toast-container" class="toast-container" aria-live="polite"></div>
    </div>

    <script src="/assets/js/shared/cpf.js" defer></script>
    <script src="/assets/js/shared/toast.js" defer></script>
    <script src="/assets/js/modules/cartorio/cartas-expedidas/text-formatter.js"></script>
    <script defer>
        document.addEventListener("DOMContentLoaded", async () => {
            await App.CPF.init("#cpf");

            const form = document.getElementById("formEstudante");
            const inputNome = document.getElementById("nome_completo");

            // Formata nome com TextFormatter (padrão do sistema: maiúsculas/minúsculas e exceções de/da/do etc.), max 200 caracteres
            function formatarNome(val) {
                if (typeof TextFormatter !== "undefined" && typeof TextFormatter.formatProperName === "function") {
                    return TextFormatter.formatProperName(val || "", 200);
                }
                return (val || "").trim();
            }
            inputNome.addEventListener("blur", function () {
                const v = this.value.trim();
                if (v) this.value = formatarNome(v);
            });

            function onlyDigits(s) {
                return (s || "").toString().replace(/\D/g, "");
            }

            form.addEventListener("submit", async (e) => {
                e.preventDefault();

                const cpfDigits = onlyDigits(document.getElementById("cpf").value);
                if (cpfDigits.length !== 11) {
                    if (window.App && App.Toast) App.Toast.show({ type: "error", variant: "full", title: "Erro", message: "Informe um CPF válido." });
                    return;
                }
                const senha = (form.querySelector("#senha") && form.querySelector("#senha").value) || "";
                if (senha.length < 6) {
                    if (window.App && App.Toast) App.Toast.show({ type: "error", variant: "full", title: "Erro", message: "A senha deve ter no mínimo 6 caracteres." });
                    return;
                }
                if (!form.querySelector("#aceite_termos_lgpd").checked) {
                    if (window.App && App.Toast) App.Toast.show({ type: "error", variant: "full", title: "Atenção", message: "É necessário aceitar os termos sobre LGPD para realizar o cadastro." });
                    return;
                }
                const emailVal = (form.querySelector("#email") && form.querySelector("#email").value || "").trim();
                if (!emailVal) {
                    if (window.App && App.Toast) App.Toast.show({ type: "error", variant: "full", title: "Erro", message: "Informe o e-mail (necessário para recuperação de senha)." });
                    return;
                }

                const btn = form.querySelector("button[type=submit]");
                const originalText = btn.textContent;
                btn.disabled = true;
                btn.textContent = "Enviando...";

                try {
                    const formData = new FormData(form);
                    const nomeRaw = (formData.get("nome_completo") || "").trim();
                    const payload = {
                        nome_completo: nomeRaw ? formatarNome(nomeRaw) : "", // TextFormatter.formatProperName (padrão do sistema)
                        cpf: cpfDigits,
                        senha: senha,
                        telefone: (formData.get("telefone") || "").trim().slice(0, 20),
                        telefone_whatsapp: form.querySelector("#telefone_whatsapp").checked,
                        faculdade: formData.get("faculdade") || "",
                        email: (formData.get("email") || "").trim().toLowerCase().slice(0, 255),
                        aceite_termos_lgpd: true,
                    };

                    const res = await fetch("/api/cadastro_estudantes.php", {
                        method: "POST",
                        headers: { "Content-Type": "application/json", "Accept": "application/json" },
                        body: JSON.stringify(payload),
                    });
                    const data = await res.json().catch(() => ({}));

                    if (data.success) {
                        if (window.App && App.Toast) {
                            App.Toast.show({
                                type: "success",
                                variant: "full",
                                title: "Sucesso",
                                message: data.message || "Cadastro realizado com sucesso.",
                                onClose: function () { window.location.href = "/index.php"; }
                            });
                        }
                        form.reset();
                    } else {
                        if (window.App && App.Toast) App.Toast.show({ type: "error", variant: "full", title: "Erro", message: data.message || "Erro ao enviar. Tente novamente." });
                    }
                } catch (err) {
                    if (window.App && App.Toast) App.Toast.show({ type: "error", variant: "full", title: "Erro", message: "Erro de conexão. Tente novamente." });
                } finally {
                    btn.disabled = false;
                    btn.textContent = originalText;
                }
            });
        });
    </script>
</body>
</html>
