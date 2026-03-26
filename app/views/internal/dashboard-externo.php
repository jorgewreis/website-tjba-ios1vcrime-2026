<?php
declare(strict_types=1);

Security::requireAuthentication();

require_once __DIR__ . '/../../helpers/UserAccessHelper.php';
require_once __DIR__ . '/../../models/Usuario.php';
require_once __DIR__ . '/../../helpers/Conection.php';

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

$dashPrimeiroNome = '';
$dashSexoUsuario = '';
if ($dashUserId !== '') {
    try {
        $dashPdo = Conection::getInstance()->getConnection();
        $stHasSexo = $dashPdo->prepare(
            "SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'usuarios' AND COLUMN_NAME = 'sexo'"
        );
        $stHasSexo->execute();
        $hasSexoColumn = ((int) $stHasSexo->fetchColumn()) > 0;

        $sqlUser = $hasSexoColumn
            ? 'SELECT `nome`, `sexo` FROM `usuarios` WHERE `id_usuario` = :id LIMIT 1'
            : 'SELECT `nome` FROM `usuarios` WHERE `id_usuario` = :id LIMIT 1';
        $stUser = $dashPdo->prepare($sqlUser);
        $stUser->bindValue(':id', $dashUserId);
        $stUser->execute();
        $dashRowUser = $stUser->fetch(PDO::FETCH_ASSOC);

        if (is_array($dashRowUser)) {
            $nomeCompleto = trim((string) ($dashRowUser['nome'] ?? ''));
            if ($nomeCompleto !== '') {
                $dashPrimeiroNome = trim((string) preg_split('/\s+/u', $nomeCompleto)[0]);
            }
            $dashSexoUsuario = trim((string) ($dashRowUser['sexo'] ?? ''));
        }
    } catch (Throwable $e) {
        $dashPrimeiroNome = '';
        $dashSexoUsuario = '';
    }
}

$dashSexoNorm = mb_strtolower($dashSexoUsuario, 'UTF-8');
$dashSaudacaoBase = 'Bem-vindo(a)';
if (in_array($dashSexoNorm, ['feminino', 'f', 'mulher'], true)) {
    $dashSaudacaoBase = 'Bem-vinda';
} elseif (in_array($dashSexoNorm, ['masculino', 'm', 'homem'], true)) {
    $dashSaudacaoBase = 'Bem-vindo';
}
$dashTituloBoasVindas = $dashSaudacaoBase . ($dashPrimeiroNome !== '' ? ', ' . $dashPrimeiroNome : '');
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
    <style>
        .external-dashboard-list {
            margin: 0;
            padding: 0;
            list-style: none;
            display: flex;
            flex-direction: column;
            width: 100%;
            gap: 12px;
        }
        .external-dashboard-list li {
            margin: 0;
            padding: 12px 14px;
            border: 1px solid transparent;
            border-radius: 8px;
            line-height: 1.6;
            background: var(--color-background-elevated);
            color: var(--color-text-primary);
        }
        .external-dashboard-list li:hover {
            background: var(--color-primary-bg-light);
            color: var(--color-primary-text);
        }
        .external-dashboard-note {
            margin: 0;
            padding: 12px 14px;
            border: 1px solid transparent;
            border-radius: 8px;
            background: var(--color-warning-bg-light);
            color: var(--color-warning-text);
            line-height: 1.5;
        }
    </style>
</head>
<body class="page-internal">

    <div class="spacing-top-page"></div>

    <div class="header-page">
        <div class="location">
            <span class="all">Área externa —</span>
            <span class="specific">Início</span>
        </div>
        <h1 class="title-page"><?php echo htmlspecialchars($dashTituloBoasVindas, ENT_QUOTES, 'UTF-8'); ?></h1>
        <p class="description-page"><?php echo $dashIsEstudanteExterno
            ? 'Este é o seu painel de acesso. Pelo menu lateral você pode consultar CEP, realizar inscrições e acompanhar suas participações no Painel em tempo real, acessar downloads em Atas de Audiências e, nas configurações, atualizar seus dados pessoais.'
            : 'Este é o seu painel de acesso ao sistema. Utilize o menu para as consultas liberadas ao seu perfil (partes, instituições públicas, CEP) e seus dados pessoais nas configurações.'; ?></p>
    </div>

    <div class="container-full card-page">
        <div class="display-flex row row-full align-center padding-x-md padding-y-md border-bottom">
            <h2>O que você pode fazer aqui</h2>
        </div>
        <div class="display-flex row row-full padding-x-md padding-y-md gap-y-md">
            <div class="col full-line">
                <ul class="minor-info external-dashboard-list">
                    <?php if ($dashIsEstudanteExterno): ?>
                    <li><strong>Consulta CEP:</strong> disponível no menu lateral, seção <em>Consultas</em>.</li>
                    <li><strong>Painel em tempo real:</strong> faça inscrições e acompanhe suas participações na seção <em>Estudantes</em>.</li>
                    <li><strong>Atas de Audiências:</strong> acesse os links de download das atas vinculadas na seção <em>Estudantes</em>.</li>
                    <li><strong>Dados pessoais:</strong> no ícone de configurações (engrenagem), altere senha ou dados cadastrais quando necessário.</li>
                    <?php else: ?>
                    <li><strong>Consultas:</strong> pelo menu lateral — <em>Consulta Partes</em>, <em>Instituições Públicas</em> e <em>CEP</em> (conforme seu perfil de consultor externo).</li>
                    <li><strong>Dados pessoais:</strong> no ícone de configurações (engrenagem), altere senha ou dados cadastrais quando necessário.</li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
        <div class="display-flex row row-full padding-x-md padding-y-md gap-y-md border-top">
            <div class="col full-line">
                <p class="minor-info external-dashboard-note">Em caso de dúvida sobre liberação de acesso, procure a diretoria da Vara ou o administrador do sistema.</p>
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
