<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=UTF-8');

$basePath = dirname(__DIR__, 3);
require_once $basePath . '/app/helpers/Conection.php';
require_once $basePath . '/app/helpers/BootstrapHelper.php';
require_once $basePath . '/app/models/Usuario.php';
require_once $basePath . '/app/access/AccessEvaluator.php';
require_once $basePath . '/app/access/ProfileNormalizer.php';
require_once $basePath . '/app/helpers/UserAccessHelper.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Método não permitido.'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $usuarioId = isset($_SESSION['id_usuario']) ? trim((string) $_SESSION['id_usuario']) : '';
    if ($usuarioId === '') {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Sessão expirada.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $usuario = new Usuario();
    $perfils = $usuario->listarPerfisUsuario($usuarioId);
    if (!is_array($perfils)) {
        $perfils = [];
    }
    if (!AccessEvaluator::canAccessPage('estudapainel', $perfils)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Acesso negado.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $norm = ProfileNormalizer::normalize($perfils);
    if (!UserAccessHelper::isUsuarioExterno($perfils) || !ProfileNormalizer::hasEstudante($norm)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Acesso restrito ao perfil estudante.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $payload = json_decode(file_get_contents('php://input') ?: '{}', true);
    $idsRaw = isset($payload['pautas']) && is_array($payload['pautas']) ? $payload['pautas'] : [];
    $ids = [];
    foreach ($idsRaw as $id) {
        $v = trim((string) $id);
        if ($v !== '' && preg_match('/^[A-Za-z0-9_-]{1,80}$/', $v)) {
            $ids[$v] = true;
        }
    }
    $ids = array_keys($ids);
    if (count($ids) === 0) {
        echo json_encode(['success' => true, 'items' => []], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $pdo = Conection::getInstance()->getConnection();
    $marks = implode(',', array_fill(0, count($ids), '?'));
    $sql = "SELECT `id_pauta`, `status`, `data_audiencia`, `hora_audiencia`, `data_hora_inicio_efetivo`, `data_hora_fim_efetivo`, `duracao_estimada_minutos`
            FROM `sala_audiencias_pauta`
            WHERE `ativo` = 1 AND `id_pauta` IN ($marks)";
    $st = $pdo->prepare($sql);
    foreach ($ids as $i => $id) {
        $st->bindValue($i + 1, $id);
    }
    $st->execute();
    $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $items = [];
    foreach ($rows as $r) {
        $id = trim((string) ($r['id_pauta'] ?? ''));
        if ($id === '') {
            continue;
        }
        $status = trim((string) ($r['status'] ?? ''));
        if ($status === 'agendada') {
            $status = 'designada';
        } elseif ($status === 'adiada') {
            $status = 'redesignada';
        }
        $items[$id] = [
            'status' => $status,
            'data_audiencia' => trim((string) ($r['data_audiencia'] ?? '')),
            'hora_audiencia' => trim((string) ($r['hora_audiencia'] ?? '')),
            'data_hora_inicio_efetivo' => trim((string) ($r['data_hora_inicio_efetivo'] ?? '')),
            'data_hora_fim_efetivo' => trim((string) ($r['data_hora_fim_efetivo'] ?? '')),
            'duracao_estimada_minutos' => (int) ($r['duracao_estimada_minutos'] ?? 0),
        ];
    }

    echo json_encode(['success' => true, 'items' => $items], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Erro ao consultar status.'], JSON_UNESCAPED_UNICODE);
}
