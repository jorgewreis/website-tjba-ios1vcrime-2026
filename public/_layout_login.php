<?php
declare(strict_types=1);

/**
 * Shell reutilizável para páginas públicas (login, cadastro, recuperar/redefinir senha, alterar senha).
 *
 * Variáveis esperadas (todas opcionais exceto $iframeSrc):
 *   string  $iframeSrc      — URL do conteúdo a carregar no iframe (obrigatório)
 *   string  $pageTitle      — <title> da página
 *   string  $leftPhrase     — frase exibida no lado esquerdo
 *   string|null $leftBannerInfo — banner informativo (feriado/fim de semana), exibido em destaque
 */

$iframeSrc    = $iframeSrc    ?? '';
$pageTitle    = $pageTitle    ?? 'Primeira Vara Criminal de Ilhéus - Sistema de Gestão';
$leftPhrase   = $leftPhrase   ?? 'A excelência nasce de um fluxo inteligente, padronização segura, responsabilidade clara e foco na efetividade jurisdicional.';
$leftBannerInfo = $leftBannerInfo ?? null;
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8'); ?></title>

    <link rel="stylesheet" href="/assets/css/shared/reset.css">
    <link rel="stylesheet" href="/assets/css/shared/color.css">
    <link rel="stylesheet" href="/assets/css/shared/form.css">
    <link rel="stylesheet" href="/assets/css/shared/table.css">
    <link rel="stylesheet" href="/assets/css/shared/layout.css">
    <link rel="stylesheet" href="/assets/css/shared/page.css">
    <link rel="stylesheet" href="/assets/css/shared/login.css">
    <link rel="stylesheet" href="/assets/css/shared/toast.css">

    <link rel="stylesheet" href="https://cdn-uicons.flaticon.com/3.0.0/uicons-regular-straight/css/uicons-regular-straight.css">
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

    <!-- PAGE DISPOSITIVOS INCOMPATÍVEIS -->
    <div class="noview"></div>

    <!-- CONTEÚDO DO SITE -->
    <div class="view layout-wraper">

        <div class="layout-container">
            <div class="layout-page">

                <!-- LEFT -->
                <div class="page-left">
                    <div class="title-system">
                        <span class="title">PRIMEIRA VARA</span>
                        <span class="info-title">CRIMINAL</span>
                        <p class="text"><?php echo htmlspecialchars($leftPhrase, ENT_QUOTES, 'UTF-8'); ?></p>
                    </div>
                    <!-- FOOTER - SYSTEM -->
                    <div class="footer-page" style="position: absolute; bottom: 0;">
                        <?php if ($leftBannerInfo !== null && $leftBannerInfo !== ''): ?>
                            <span class="info-app" style="background-color: goldenrod; color: #303030;"><?php echo htmlspecialchars((string) $leftBannerInfo, ENT_QUOTES, 'UTF-8'); ?></span>
                        <?php endif; ?>
                        <div class="info-page">
                            <span class="info-line">Rua Dr. Hygino Filho, bloco C, 1º andar, sala 132 - Jardim Atlântico, Ilhéus - BA</span>
                            <span class="info-line">Tel.: 73 3234-3428 - WhatsApp: 71 99610-8241 - Email: ilheus1vcriminal@tjba.jus.br</span>
                        </div>
                        <div class="info-app">Sistema desenvolvido integralmente por Jorge Reis Developer - 2026 - Todos os direitos reservados</div>
                    </div>
                </div>

                <!-- RIGHT — carregado via iframe -->
                <div class="page-right page-right-iframe">
                    <iframe
                        id="login-iframe"
                        src="<?php echo htmlspecialchars($iframeSrc, ENT_QUOTES, 'UTF-8'); ?>"
                        title="Conteúdo da página"
                        scrolling="no"
                    ></iframe>
                </div>

            </div>
        </div>

        <div class="layout-overlay"></div>

        <div id="toast-container" class="toast-container" aria-live="polite"></div>
    </div>

    <script src="/assets/js/shared/navigate-buttons.js" defer></script>
    <script src="/assets/js/shared/toast.js" defer></script>
    <script>
        window.addEventListener('message', function (e) {
            if (!e.data || typeof e.data !== 'object') return;

            var type = e.data.type;

            // Toast enviado pelo iframe via toast-helper.js (showToast → postMessage APP_TOAST)
            if (type === 'APP_TOAST') {
                if (window.App && App.Toast && App.Toast.show) {
                    App.Toast.show(e.data.payload || {});
                }
                return;
            }

            // Navegação explícita do iframe (ex.: após login, após cadastro)
            if (type === 'APP_NAVIGATE') {
                var href = (e.data.href || '').trim();
                if (href) {
                    window.location.href = href;
                }
                return;
            }

            // Ajuste de altura dinâmica do iframe (enviado pelo ResizeObserver do conteúdo)
            if (type === 'APP_RESIZE') {
                var iframe = document.getElementById('login-iframe');
                var h = parseInt(e.data.height, 10);
                if (iframe && h > 0) {
                    iframe.style.height = h + 'px';
                }
                return;
            }
        });
    </script>

</body>
</html>
