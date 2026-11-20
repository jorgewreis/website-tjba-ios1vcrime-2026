<?php
declare(strict_types=1);

/**
 * Shell reutilizável para páginas públicas (login, cadastro, recuperar/redefinir senha, alterar senha).
 *
 * Variável obrigatória (definida pela página que inclui este template):
 *   string $iframeSrc  — URL do conteúdo a carregar no iframe
 *
 * Variáveis opcionais (sobrescrevem os padrões calculados aqui):
 *   string $pageTitle  — <title> da página
 *
 * O lado esquerdo (frase motivacional + banner de dia não útil) é calculado
 * integralmente aqui para que seja IDÊNTICO em todas as páginas.
 */

// ── Dependências do lado esquerdo ─────────────────────────────────────────────
if (!class_exists('FeriadosRepository', false)) {
    require_once __DIR__ . '/../app/repositories/FeriadosRepository.php';
}
if (!class_exists('RandomPhrase', false)) {
    require_once __DIR__ . '/../app/helpers/RandomPhrase.php';
}

// ── Frase motivacional ────────────────────────────────────────────────────────
$_leftPhrase = RandomPhrase::getRandom();

// ── Banner de dia não útil ────────────────────────────────────────────────────
$_leftBannerInfo = null;
$_hoje = new DateTimeImmutable('today');
$_isFimDeSemana = in_array((int) $_hoje->format('N'), [6, 7], true);
$_feriadoInfo = null;
try {
    $_conn = Conection::getInstance()->getConnection();
    $_feriadosRepo = new FeriadosRepository($_conn);
    $_feriadoInfo = $_feriadosRepo->getByDate($_hoje);
} catch (Throwable $_e) {
    error_log('_layout_login feriado check: ' . $_e->getMessage());
}
$_isFeriado = $_feriadoInfo !== null;

if ($_isFimDeSemana) {
    $_diaNome = ['segunda','terça','quarta','quinta','sexta','sábado','domingo'][(int)$_hoje->format('N') - 1];
    $_leftBannerInfo = 'Hoje é ' . $_diaNome . ', não temos expediente nesta data.';
} elseif ($_isFeriado && $_feriadoInfo) {
    $_meses = [1=>'janeiro','fevereiro','março','abril','maio','junho','julho','agosto','setembro','outubro','novembro','dezembro'];
    $_dia   = (int) $_hoje->format('j');
    $_mes   = $_meses[(int) $_hoje->format('n')] ?? $_hoje->format('n');
    $_dataExtenso = $_dia . ' de ' . $_mes;
    $_descricao = trim((string) ($_feriadoInfo['descricao'] ?? ''));
    if ($_descricao === '') {
        $_descricao = trim((string) ($_feriadoInfo['tipo_feriado'] ?? 'feriado'));
    }
    $_leftBannerInfo = 'Hoje é ' . $_dataExtenso . ', feriado ' . $_descricao . ', não temos expediente hoje.';
}

// ── Variáveis do template ─────────────────────────────────────────────────────
$iframeSrc  = $iframeSrc  ?? '';
$pageTitle  = $pageTitle  ?? 'Primeira Vara Criminal de Ilhéus - Sistema de Gestão';
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
                        <p class="text"><?php echo htmlspecialchars($_leftPhrase, ENT_QUOTES, 'UTF-8'); ?></p>
                    </div>
                    <!-- FOOTER - SYSTEM -->
                    <div class="footer-page" style="position: absolute; bottom: 0;">
                        <?php if ($_leftBannerInfo !== null): ?>
                            <span class="info-app" style="background-color: goldenrod; color: #303030; font-size: 14px; font-weight: 600; padding: 10px;">
                                <?php echo htmlspecialchars($_leftBannerInfo, ENT_QUOTES, 'UTF-8'); ?>
                            </span>
                        <?php endif; ?>
                        <div class="info-page">
                            <span class="info-line">Rua Dr. Hygino Filho, bloco C, 1º andar, sala 132 - Jardim Atlântico, Ilhéus - BA</span>
                            <span class="info-line">Tel.: 73 3234-3428 - WhatsApp: 71 99610-8241 - Email: ilheus1vcriminal@tjba.jus.br</span>
                        </div>
                        <div class="info-app footer-credit">
                            <span>Uma solução da Fokus Cloud para a Justiça</span>
                            <span>ios1vcrime.com.br · Versão V. 1.88 · Atualizado em 20/11/2026 às 11:21 · Todos os direitos reservados</span>
                        </div>
                    </div>
                </div>

                <!-- RIGHT — carregado via iframe -->
                <div class="page-right page-right-iframe">
                    <iframe
                        id="login-iframe"
                        src="<?php echo htmlspecialchars($iframeSrc, ENT_QUOTES, 'UTF-8'); ?>"
                        title="Conteúdo da página"
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
        });
    </script>

</body>
</html>
