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
$hojeRows = $userId !== '' ? $service->listHojeParaInscricaoEstudante($userId) : [];
$minhasRows = $userId !== '' ? $service->listMinhasInscricoesEstudante($userId) : [];
$maxVagas = SalaAudienciasAtaService::MAX_ESTUDANTES_INSCRITOS_POR_AUDIENCIA;
?>
<!DOCTYPE html>
<html lang="pt-BR">
    <head>
        <meta charset="utf-8">
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <title>Painel em tempo real - Estudantes</title>
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
            <span class="specific">Painel em tempo real</span>
        </div>
        <h1 class="title-page">Painel em tempo real</h1>
        <p class="description-page">Área para inscrições e acompanhamento das suas participações em audiências.</p>
    </div>

    <div class="container-full card-page">
        <div class="display-flex row row-full align-center justify-between padding-x-md padding-y-md border-bottom">
            <div class="display-flex row row-fit justify-start"><h2>Audiências de hoje (inscrição)</h2></div>
        </div>
        <?php if (count($hojeRows) === 0): ?>
        <div class="padding-x-md padding-y-lg">
            <p class="description-page">Não há audiências elegíveis para inscrição hoje ou você já está inscrito em todas as opções disponíveis.</p>
        </div>
        <?php endif; ?>
        <?php foreach ($hojeRows as $row):
            $idPauta = htmlspecialchars((string) ($row['id_pauta'] ?? ''), ENT_QUOTES, 'UTF-8');
            $pode = !empty($row['pode_inscrever']);
            $ja = !empty($row['ja_inscrito']);
            $lotado = !empty($row['vagas_esgotadas']);
            $totalIns = (int) ($row['total_inscritos_pauta'] ?? 0);
            ?>
        <div class="display-flex col col-full register border-bottom" data-pauta="<?php echo $idPauta; ?>">
            <div class="display-flex row row-full justify-between padding-x-sm padding-y-sm align-center flex-wrap gap-y-sm">
                <div class="display-flex row row-fit justify-start gap-y-sm align-center flex-wrap">
                    <div class="col width-280">
                        <span class="minor-info">Horario</span>
                        <span class="major-info"><?php echo htmlspecialchars(substr((string) $row['hora_audiencia'], 0, 5), ENT_QUOTES, 'UTF-8'); ?></span>
                    </div>
                    <div class="col width-400 col-process process-info" title="clique para copiar">
                        <?php
                            $classeAudiencia = !empty($row['classe_nome']) ? $row['classe_nome'] : 'Classe não informada';
                            $classeStyleAudiencia = $classeAudiencia !== 'Classe não informada' ? 'style="color: var(--primary-color-active); font-weight: 600;"' : '';
                        ?>
                        <span class="minor-info text-ellipsis" <?php echo $classeStyleAudiencia; ?>><?php echo htmlspecialchars($classeAudiencia, ENT_QUOTES, 'UTF-8'); ?></span>
                        <span class="major-info"><?php echo htmlspecialchars((string) ($row['numero_processo'] ?? '—'), ENT_QUOTES, 'UTF-8'); ?></span>
                    </div>
                    <div class="col width-360">
                        <span class="minor-info">Tipo</span>
                        <span class="major-info text-ellipsis"><?php echo htmlspecialchars((string) $row['tipo_audiencia'], ENT_QUOTES, 'UTF-8'); ?></span>
                    </div>
                    <div class="col width-220">
                        <span class="minor-info">Vagas</span>
                        <span class="major-info"><?php echo $totalIns; ?> / <?php echo (int) $maxVagas; ?><?php echo $lotado ? ' (lotado)' : ''; ?></span>
                    </div>
                </div>
                <div class="display-flex row row-fit justify-end gap-y-sm align-center">
                    <?php if ($ja): ?>
                    <span class="minor-info">Voce esta inscrito.</span>
                    <?php elseif ($lotado): ?>
                    <span class="minor-info">Vagas esgotadas.</span>
                    <?php elseif ($podeInscricaoComoEstudante && $pode): ?>
                    <button type="button" class="btn btn-primary btn-inscrever-ata" data-pauta="<?php echo $idPauta; ?>">Inscrever-me</button>
                    <?php elseif (!$podeInscricaoComoEstudante): ?>
                    <span class="minor-info">Inscricao exclusiva para estudantes.</span>
                    <?php else: ?>
                    <button type="button" class="btn btn-primary" disabled>Inscrever-me</button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <div class="container-full card-page">
        <div class="display-flex row row-full align-center justify-between padding-x-md padding-y-md border-bottom">
            <div class="display-flex row row-fit justify-start"><h2>Minhas inscricoes</h2></div>
        </div>
        <?php if (count($minhasRows) === 0): ?>
        <div class="padding-x-md padding-y-lg">
            <p class="description-page">Nenhuma inscrição ativa com ata disponível ou aguardando publicação.</p>
        </div>
        <?php endif; ?>
        <?php foreach ($minhasRows as $m):
            $temAta = !empty($m['id_ata']);
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
                    <span class="minor-info">Situacao da ata</span>
                    <span class="major-info"><?php echo $temAta ? 'Disponivel para download' : 'Aguardando publicacao'; ?></span>
                    <?php if ($temAta && $expiraFmt !== ''): ?>
                    <span class="minor-info">Valida ate <?php echo htmlspecialchars($expiraFmt, ENT_QUOTES, 'UTF-8'); ?></span>
                    <?php endif; ?>
                </div>
            </div>
            <div class="display-flex row row-fit justify-end">
                <?php if ($temAta && $podeInscricaoComoEstudante): ?>
                <button type="button" class="btn btn-primary" data-nav-href="/api/sala-audiencias-ata/download.php?pauta=<?php echo urlencode((string) $m['pauta']); ?>" data-nav-target="_blank">Baixar PDF</button>
                <?php elseif ($temAta): ?>
                <span class="minor-info">Download exclusivo para estudantes.</span>
                <?php else: ?>
                <button type="button" class="btn btn-secondary" disabled>Aguardando ata</button>
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

    <script src="/assets/js/modules/audiencias/atas-estudantes.js?v=<?php echo @filemtime(__DIR__ . '/../../../public/assets/js/modules/audiencias/atas-estudantes.js') ?: time(); ?>"></script>
</body>
</html>
