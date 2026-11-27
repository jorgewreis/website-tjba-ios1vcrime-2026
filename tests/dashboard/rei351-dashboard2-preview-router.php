<?php
declare(strict_types=1);

/** Local visual fixture only. Bind the PHP server to 127.0.0.1; no DB or real records are used. */
$root = dirname(__DIR__, 2);
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$synthetic = [
    'geral' => [
        'tarefas_abertas' => 0,
        'filas' => [],
        'cartas_cadastradas' => ['expedidas' => 0, 'recebidas' => 0, 'ultimos_30_dias' => ['labels' => [], 'expedidas' => [], 'recebidas' => []]],
        'oficios_cadastrados' => ['ano' => ['cartorio' => 0, 'gabinete' => 0], 'ultimos_30_dias' => ['labels' => [], 'cartorio' => [], 'gabinete' => []]],
        'cadastros_mensais' => ['labels' => [], 'values' => []], 'conclusoes_semanais' => array_fill(0, 7, 0),
    ],
    'setor' => ['areas' => ['CARTORIO'], 'selecionado' => null, 'conectado' => true, 'tarefas_abertas' => 0,
        'filas' => [['key' => 'cartas_expedidas', 'type' => 'aguardando_envio', 'label' => 'Carta aguardando envio', 'count' => 0, 'route' => 'cartasexpedidas']],
        'responsaveis_regras' => [], 'responsaveis_proprios' => [], 'prazos_vencidos' => ['expedidas' => 0, 'recebidas' => 0], 'prazos_vencidos_dias' => ['expedidas' => null, 'recebidas' => null], 'cadastros_mensais' => ['labels' => [], 'values' => []]],
    'usuario' => ['7d' => 0, '30d' => 0, 'weekly' => array_fill(0, 7, 0), 'weekly_by_module' => [], 'modules' => [], 'oficios_expedidos_30d' => 0, 'medidas_protetivas_expedidas_30d' => null],
    'agenda' => ['audiencias_7d' => 0, 'dias_nao_uteis_30d' => null], 'alertas' => [], 'atualizado_em' => '2026-11-27T00:00:00-03:00', 'fila_parcial' => false,
    'prazos_proximos_7d' => 0, 'cartas_recebidas_aguardando_devolucao' => 0,
];

if (in_array($path, ['/api/dashboard/resumo-v2.php', '/api/dashboard/resumo.php'], true)) {
    header('Content-Type: application/json; charset=UTF-8');
    header('Cache-Control: no-store');
    echo json_encode($synthetic, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    return true;
}
if ($path === '/preview-dashboard' || $path === '/preview-dashboard2') {
    if (!class_exists('Security')) {
        final class Security { public static function requireAuthentication(): void {} }
    }
    $_GET['page'] = $path === '/preview-dashboard' ? 'dashboard' : 'dashboard2';
    $_SESSION = ['id_usuario' => 'SYNTHETIC', 'nome' => 'Usuário sintético'];
    $userId = 'SYNTHETIC';
    $userPerfils = ['SERVIDOR-CARTORIO'];
    ob_start();
    require $root . '/app/views/internal/' . ($path === '/preview-dashboard' ? 'dashboard.php' : 'dashboard-2.php');
    $html = ob_get_clean();
    $banner = '<style>.synthetic-preview-banner{position:sticky;top:0;z-index:9999;padding:8px;text-align:center;background:#fff2cc;color:#523b00;font:bold 13px sans-serif;border:2px solid #d29b00}</style><aside class="synthetic-preview-banner" role="status">DADOS SINTÉTICOS · PRÉVIA VISUAL LOCAL · NÃO É HOMOLOGAÇÃO</aside>';
    echo str_replace('</body>', $banner . '</body>', $html);
    return true;
}
return false;
