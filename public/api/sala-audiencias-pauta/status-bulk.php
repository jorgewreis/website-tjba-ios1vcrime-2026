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
require_once $basePath . '/app/services/SalaAudienciasAtaService.php';

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
    $sql = "SELECT a.`id_pauta`, a.`status`, a.`data_audiencia`, a.`hora_audiencia`, a.`data_hora_inicio_efetivo`, a.`data_hora_fim_efetivo`, a.`duracao_estimada_minutos`,
                   COALESCE(p.`sigiloso`, 0) AS `processo_sigiloso`,
                   CASE WHEN i.`id` IS NOT NULL THEN 1 ELSE 0 END AS `ja_inscrito`,
                   (SELECT COUNT(*) FROM `sala_audiencias_pauta_parte` pp WHERE pp.`pauta` = a.`id_pauta` AND pp.`ativo` = 1 AND pp.`maioridade` = 'menor') AS `qtd_menor`,
                   (SELECT COUNT(*) FROM `sala_audiencias_estudante_inscricao` inv WHERE inv.`pauta` = a.`id_pauta`) AS `total_inscritos_pauta`
            FROM `sala_audiencias_pauta` a
            LEFT JOIN `processos` p ON p.`id` = a.`processo`
            LEFT JOIN `sala_audiencias_estudante_inscricao` i ON i.`pauta` = a.`id_pauta` AND i.`usuario` = ?
            WHERE a.`ativo` = 1 AND a.`id_pauta` IN ($marks)";
    $st = $pdo->prepare($sql);
    $st->bindValue(1, $usuarioId);
    foreach ($ids as $i => $id) {
        $st->bindValue($i + 2, $id);
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
        $totalIns = (int) ($r['total_inscritos_pauta'] ?? 0);
        $jaInscrito = (int) ($r['ja_inscrito'] ?? 0) === 1;
        $sigiloso = (int) ($r['processo_sigiloso'] ?? 0) !== 0;
        $envolveMenor = (int) ($r['qtd_menor'] ?? 0) > 0;
        $vagasEsgotadas = $totalIns >= SalaAudienciasAtaService::MAX_ESTUDANTES_INSCRITOS_POR_AUDIENCIA;
        $podeInscrever = !$jaInscrito && !$vagasEsgotadas && !$sigiloso && !$envolveMenor;
        $items[$id] = [
            'id_pauta' => $id,
            'status' => $status,
            'data_audiencia' => trim((string) ($r['data_audiencia'] ?? '')),
            'hora_audiencia' => trim((string) ($r['hora_audiencia'] ?? '')),
            'data_hora_inicio_efetivo' => trim((string) ($r['data_hora_inicio_efetivo'] ?? '')),
            'data_hora_fim_efetivo' => trim((string) ($r['data_hora_fim_efetivo'] ?? '')),
            'duracao_estimada_minutos' => (int) ($r['duracao_estimada_minutos'] ?? 0),
            'processo_sigiloso' => $sigiloso,
            'ja_inscrito' => $jaInscrito,
            'envolve_menor' => $envolveMenor,
            'vagas_esgotadas' => $vagasEsgotadas,
            'total_inscritos_pauta' => $totalIns,
            'pode_inscrever' => $podeInscrever,
        ];
    }

    echo json_encode(['success' => true, 'items' => $items], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Erro ao consultar status.'], JSON_UNESCAPED_UNICODE);
}
