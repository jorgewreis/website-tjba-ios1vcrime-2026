<?php
declare(strict_types=1);

header('Content-Type: text/html; charset=UTF-8');
if (function_exists('mb_internal_encoding')) {
    mb_internal_encoding('UTF-8');
}

require_once __DIR__ . '/../../services/SalaAudienciasAtaService.php';
require_once __DIR__ . '/../../models/Usuario.php';
require_once __DIR__ . '/../../helpers/UserAccessHelper.php';
require_once __DIR__ . '/../../access/ProfileNormalizer.php';

$userId = trim((string) ($_SESSION['id_usuario'] ?? ''));
$usuarioModel = new Usuario();
$perfilsUsuario = $userId !== '' ? $usuarioModel->listarPerfisUsuario($userId) : [];
if (!is_array($perfilsUsuario)) {
    $perfilsUsuario = [];
}
$normPerfis = ProfileNormalizer::normalize($perfilsUsuario);
$podeInscricaoComoEstudante = UserAccessHelper::isUsuarioExterno($perfilsUsuario) && ProfileNormalizer::hasEstudante($normPerfis);

$service = new SalaAudienciasAtaService(Conection::getInstance()->getConnection());
$minhasRows = $userId !== '' ? $service->listMinhasInscricoesEstudante($userId) : [];
?>
<!DOCTYPE html>
<html lang="pt-BR">
    <head>
        <meta charset="utf-8">
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <title>Atas de Audiências - Estudantes</title>
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <?php include __DIR__ . '/_internal_head_styles.php'; ?>
        <link rel="stylesheet" href="https://cdn-uicons.flaticon.com/3.0.0/uicons-regular-rounded/css/uicons-regular-rounded.css">
        <?php include __DIR__ . '/_toast_script.php'; ?>
        <?php include __DIR__ . '/_copy_process_script.php'; ?>
    </head>
<body class="page-internal">
    <div class="spacing-top-page"></div>

    <div class="header-page">
        <div class="location">
            <span class="all">Estudantes - Audiências -</span>
            <span class="specific">Atas de audiências</span>
        </div>
        <h1 class="title-page">Atas de Audiências</h1>
        <p class="description-page">Lista das audiências em que você se inscreveu, com situação de disponibilidade da ata para download.</p>
    </div>

    <div class="container-full card-page">
        <div class="display-flex row row-full align-center justify-between padding-x-lg padding-y-md border-bottom">
            <div class="display-flex row row-fit justify-start padding-x-md"><h2>Minhas audiências</h2></div>
        </div>
        <?php if (count($minhasRows) === 0): ?>
        <div class="padding-x-md padding-y-lg">
            <p class="description-page">Nenhuma inscrição encontrada.</p>
        </div>
        <?php endif; ?>
        <?php foreach ($minhasRows as $m):
            $idPauta = htmlspecialchars((string) ($m['pauta'] ?? ''), ENT_QUOTES, 'UTF-8');
            $temAta = !empty($m['id_ata']);
            $expira = trim((string) ($m['expires_at'] ?? ''));
            $expiraFmt = $expira !== '' ? date('d/m', strtotime($expira)) : '';
            ?>
        <div class="display-flex row row-full justify-between padding-x-sm padding-y-sm border-bottom align-center flex-wrap gap-y-sm">
            <div class="display-flex row row-fit justify-start gap-y-sm align-center flex-wrap">
                <div class="col width-300">
                    <span class="minor-info">Data</span>
                    <span class="major-info"><?php echo htmlspecialchars(date('d/m/Y', strtotime((string) $m['data_audiencia'])) . ' · ' . substr((string) $m['hora_audiencia'], 0, 5), ENT_QUOTES, 'UTF-8'); ?></span>
                </div>
                <div class="col width-500">
                    <span class="minor-info">Processo</span>
                    <span class="major-info"><?php echo htmlspecialchars((string) ($m['numero_processo'] ?? '—'), ENT_QUOTES, 'UTF-8'); ?></span>
                </div>
                <div class="col width-400">
                    <span class="minor-info">Ata para download</span>
                    <span class="major-info">
                        <?php echo $temAta ? 'Disponível até ' . htmlspecialchars($expiraFmt, ENT_QUOTES, 'UTF-8') : 'Ainda não disponível'; ?>
                    </span>
                </div>
            </div>
            <div class="display-flex row row-fit justify-end padding-x-md">
                <?php if ($temAta && $podeInscricaoComoEstudante): ?>
                <button type="button" class="btn btn-primary" data-nav-href="/api/sala-audiencias-ata/download.php?pauta=<?php echo urlencode((string) $m['pauta']); ?>" data-nav-target="_blank">Baixar Ata (PDF)</button>
                <?php elseif ($temAta): ?>
                <span class="minor-info">Download exclusivo para estudantes.</span>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <div class="footer-page">
        <div class="location">
            <span class="all">Aplicativo web desenvolvido integralmente por </span>
            <span class="specific">Jorge Reis Develop</span>
            <span class="all">- Copyright 2018 a 2026 - Todos os direitos reservados.</span>
        </div>
    </div>

</body>
</html>
