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
$participadasComAta = array_values(array_filter($minhasRows, static function ($row): bool {
    return !empty($row['id_ata']);
}));
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
        <p class="description-page">Lista das audiências em que você participou e que já possuem ata vinculada para download.</p>
    </div>

    <div class="container-full card-page">
        <div class="display-flex row row-full align-center justify-between padding-x-md padding-y-md border-bottom">
            <div class="display-flex row row-fit justify-start"><h2>Minhas audiências com ata</h2></div>
        </div>
        <?php if (count($participadasComAta) === 0): ?>
        <div class="padding-x-md padding-y-lg">
            <p class="description-page">Nenhuma ata vinculada encontrada para suas participações.</p>
        </div>
        <?php endif; ?>
        <?php foreach ($participadasComAta as $m):
            $idPauta = htmlspecialchars((string) ($m['pauta'] ?? ''), ENT_QUOTES, 'UTF-8');
            $expira = trim((string) ($m['expires_at'] ?? ''));
            $expiraFmt = $expira !== '' ? date('d/m/Y H:i', strtotime($expira)) : '';
            ?>
        <div class="display-flex row row-full justify-between padding-x-sm padding-y-sm border-bottom align-center flex-wrap gap-y-sm">
            <div class="display-flex row row-fit justify-start gap-y-sm align-center flex-wrap">
                <div class="col width-280">
                    <span class="minor-info">Data</span>
                    <span class="major-info"><?php echo htmlspecialchars(date('d/m/Y', strtotime((string) $m['data_audiencia'])) . ' · ' . substr((string) $m['hora_audiencia'], 0, 5), ENT_QUOTES, 'UTF-8'); ?></span>
                </div>
                <div class="col width-400">
                    <span class="minor-info">Processo</span>
                    <span class="major-info"><?php echo htmlspecialchars((string) ($m['numero_processo'] ?? '—'), ENT_QUOTES, 'UTF-8'); ?></span>
                </div>
                <div class="col width-360">
                    <span class="minor-info">Situação da ata</span>
                    <span class="major-info">Disponível para download</span>
                    <?php if ($expiraFmt !== ''): ?>
                    <span class="minor-info">Válida até <?php echo htmlspecialchars($expiraFmt, ENT_QUOTES, 'UTF-8'); ?></span>
                    <?php endif; ?>
                </div>
            </div>
            <div class="display-flex row row-fit justify-end">
                <?php if ($podeInscricaoComoEstudante): ?>
                <button type="button" class="btn btn-primary" data-nav-href="/api/sala-audiencias-ata/download.php?pauta=<?php echo urlencode((string) $m['pauta']); ?>" data-nav-target="_blank">Baixar PDF</button>
                <?php else: ?>
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
