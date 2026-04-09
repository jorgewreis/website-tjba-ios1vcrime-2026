<?php
declare(strict_types=1);

Security::requireAuthentication();

require_once __DIR__ . '/../../helpers/Conection.php';
require_once __DIR__ . '/../../services/ProdutividadeService.php';

$connection = Conection::getInstance()->getConnection();
$service = new ProdutividadeService($connection);
$userId = isset($_SESSION['id_usuario']) ? trim((string) $_SESSION['id_usuario']) : '';

$prodWeekPoints = 0.0;
$prodRankingPos = null;
$prodRankingSetor = '';
$prodError = '';
if ($userId !== '') {
    try {
        $today = date('Y-m-d');
        $resumo = $service->getResumoCards($userId, [
            'status' => 'ativo',
            'tipo_lancamento' => '',
            'data_inicio' => $today,
            'data_fim' => $today,
        ]);
        $prodWeekPoints = (float) (($resumo['week']['current'] ?? 0));

        if ($service->usuarioPodeVerRankingProdutividade($userId)) {
            $ranking = $service->listRanking($userId, [
                'status' => 'ativo',
            ], 200);
            $setores = $ranking['setores'] ?? [];
            foreach ($setores as $setor) {
                foreach (($setor['rows'] ?? []) as $row) {
                    if ((string) ($row['usuario_id'] ?? '') === $userId) {
                        $prodRankingPos = (int) ($row['posicao'] ?? 0);
                        $prodRankingSetor = (string) ($setor['setor_nome'] ?? '');
                        break 2;
                    }
                }
            }
        }
    } catch (Throwable $e) {
        $prodError = $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>Dashboard - Primeira Vara Crime de Ilhéus</title>
    <meta name="description" content="">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?php include __DIR__ . '/_internal_head_styles.php'; ?>

    <link rel="stylesheet" href="https://cdn-uicons.flaticon.com/3.0.0/uicons-regular-rounded/css/uicons-regular-rounded.css">
    <?php include __DIR__ . '/_toast_script.php'; ?>
</head>
<body class="page-internal">

    <div class="spacing-top-page"></div>

    <div class="header-page">
        <div class="location">
            <span class="all">Interno -</span>
            <span class="specific">Dashboard</span>
        </div>
        <h1 class="title-page">Dashboard</h1>
        <p class="description-page">Tudo o que você precisa saber (em um só lugar).</p>
    </div>

    <div class="container-full card-page">
        <div class="display-flex row row-full align-center padding-x-md padding-y-md border-bottom">
            <h2>Visão geral</h2>
        </div>
        <div class="display-flex row row-full padding-x-md padding-y-md gap-y-md">
            <div class="col full-line">
                <p class="minor-info">Conteúdo inicial do dashboard. Substitua por widgets e painéis reais conforme a implementação.</p>
            </div>
        </div>
    </div>

    <div class="container-full card-page margin-top-md" id="produtividade-dashboard-widget">
        <div class="display-flex row row-full align-center justify-between padding-x-md padding-y-md border-bottom">
            <h2>Produtividade da semana</h2>
            <button type="button" class="btn btn-secondary-outline height-md" data-nav-href="/frame.php?page=produtividade">Abrir módulo</button>
        </div>
        <div class="display-flex row row-full padding-x-md padding-y-md gap-y-md border-bottom">
            <div class="col width-300">
                <span class="minor-info">Pontos na semana</span>
                <span class="major-info"><?php echo number_format($prodWeekPoints, 2, ',', '.'); ?> pts</span>
            </div>
            <div class="col width-500">
                <span class="minor-info">Posição no ranking mensal do setor (Cartório/Gabinete)</span>
                <span class="major-info">
                    <?php if ($prodRankingPos !== null && $prodRankingPos > 0): ?>
                        #<?php echo (int) $prodRankingPos; ?><?php echo $prodRankingSetor !== '' ? ' em ' . htmlspecialchars($prodRankingSetor, ENT_QUOTES, 'UTF-8') : ''; ?>
                    <?php else: ?>
                        Sem posição no período
                    <?php endif; ?>
                </span>
            </div>
        </div>
        <?php if ($prodError !== ''): ?>
        <div class="display-flex row row-full padding-x-md padding-y-sm border-bottom">
            <span class="status status-cancelled"><?php echo htmlspecialchars('Erro ao carregar widget de produtividade: ' . $prodError, ENT_QUOTES, 'UTF-8'); ?></span>
        </div>
        <?php endif; ?>
    </div>

    <div class="footer-page">
        <div class="location">
            <span class="all">Aplicativo web desenvolvido integralmente por </span>
            <span class="specific">Jorge Reis Develop</span>
            <span class="all">- Copyright 2018 a 2026 - Todos os direitos reservados.</span>
        </div>
    </div>
    <?php include __DIR__ . '/_profile_saved_reload_parent.php'; ?>
    <script src="/assets/js/modules/produtividade/badge-popup.js"></script>
</body>
</html>
