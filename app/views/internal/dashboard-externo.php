<?php
declare(strict_types=1);

Security::requireAuthentication();

require_once __DIR__ . '/../../helpers/UserAccessHelper.php';
require_once __DIR__ . '/../../models/Usuario.php';

$dashUsuario = new Usuario();
$dashUserId = trim((string) ($_SESSION['id_usuario'] ?? ''));
$dashPerfis = $dashUserId !== '' ? $dashUsuario->listarPerfisUsuario($dashUserId) : [];
$dashHasEstudante = false;
foreach ($dashPerfis as $p) {
    if (strtoupper(trim((string) $p)) === 'ESTUDANTE') {
        $dashHasEstudante = true;
        break;
    }
}
$dashIsEstudanteExterno = UserAccessHelper::isUsuarioExterno($dashPerfis) && $dashHasEstudante;
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>Início — Área externa — Primeira Vara Crime de Ilhéus</title>
    <meta name="description" content="Painel inicial para usuários externos (estudantes ou consultores).">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?php include __DIR__ . '/_internal_head_styles.php'; ?>

    <link rel="stylesheet" href="https://cdn-uicons.flaticon.com/3.0.0/uicons-regular-rounded/css/uicons-regular-rounded.css">
    <?php include __DIR__ . '/_toast_script.php'; ?>
</head>
<body class="page-internal">

    <div class="spacing-top-page"></div>

    <div class="header-page">
        <div class="location">
            <span class="all">Área externa —</span>
            <span class="specific">Início</span>
        </div>
        <h1 class="title-page">Bem-vindo(a)</h1>
        <p class="description-page"><?php echo $dashIsEstudanteExterno
            ? 'Este é o seu painel de acesso. Pelo menu lateral você pode consultar CEP, acompanhar a pauta/atas de audiências para estudantes e, nas configurações, atualizar seus dados pessoais.'
            : 'Este é o seu painel de acesso ao sistema. Utilize o menu para as consultas liberadas ao seu perfil (partes, instituições públicas, CEP) e seus dados pessoais nas configurações.'; ?></p>
    </div>

    <div class="container-full card-page">
        <div class="display-flex row row-full align-center padding-x-md padding-y-md border-bottom">
            <h2>O que você pode fazer aqui</h2>
        </div>
        <div class="display-flex row row-full padding-x-md padding-y-md gap-y-md">
            <div class="col full-line">
                <ul class="minor-info" style="margin: 0; padding-left: 1.25rem; line-height: 1.6;">
                    <?php if ($dashIsEstudanteExterno): ?>
                    <li><strong>Consulta CEP:</strong> disponível no menu lateral, seção <em>Consultas</em>.</li>
                    <li><strong>Audiências (estudante):</strong> acompanhe pauta, atas e painel em tempo real na seção <em>Estudantes</em>.</li>
                    <li><strong>Dados pessoais:</strong> no ícone de configurações (engrenagem), altere senha ou dados cadastrais quando necessário.</li>
                    <li><strong>Limitação:</strong> perfil <em>estudante</em> não inclui consulta de partes, instituições ou processos; tarefas de cartório e gabinete também não estão disponíveis.</li>
                    <?php else: ?>
                    <li><strong>Consultas:</strong> pelo menu lateral — <em>Consulta Partes</em>, <em>Instituições Públicas</em> e <em>CEP</em> (conforme seu perfil de consultor externo).</li>
                    <li><strong>Dados pessoais:</strong> no ícone de configurações (engrenagem), altere senha ou dados cadastrais quando necessário.</li>
                    <li><strong>Limitação:</strong> consulta de processos e tarefas de cartório, gabinete e gestão interna não estão disponíveis para usuários externos.</li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
        <div class="display-flex row row-full padding-x-md padding-y-md gap-y-md border-top">
            <div class="col full-line">
                <p class="minor-info" style="margin: 0;">Em caso de dúvida sobre liberação de acesso, procure a diretoria ou o administrador do sistema.</p>
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
    <?php include __DIR__ . '/_profile_saved_reload_parent.php'; ?>
</body>
</html>
