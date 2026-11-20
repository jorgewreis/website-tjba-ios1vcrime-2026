<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');

$basePath = dirname(__DIR__, 3);
require_once $basePath . '/app/helpers/Conection.php';
require_once $basePath . '/app/helpers/BootstrapHelper.php';
require_once $basePath . '/config/security.php';
require_once $basePath . '/app/helpers/UsuarioPerfisAutorizacao.php';
require_once $basePath . '/app/models/Usuario.php';
require_once $basePath . '/app/access/AccessEvaluator.php';
require_once $basePath . '/app/access/ProfileNormalizer.php';
require_once $basePath . '/app/helpers/UserAccessHelper.php';
require_once $basePath . '/app/services/SalaAudienciasAtaService.php';
require_once $basePath . '/app/helpers/PrioridadesProcessuaisCatalog.php';
require_once $basePath . '/app/helpers/SalaAudienciasAtaSigiloHelper.php';
require_once $basePath . '/app/helpers/SalaAudienciasEstudantePrivacidade.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Método não permitido.'], JSON_UNESCAPED_UNICODE);
    exit;
}

if (!Security::isAuthenticated()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Sessão expirada. Faça login novamente.'], JSON_UNESCAPED_UNICODE);
    exit;
}

if (!bootstrapRequireTrustedPostRequest()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Requisição inválida.'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $usuarioId = isset($_SESSION['id_usuario']) ? trim((string) $_SESSION['id_usuario']) : '';
    if ($usuarioId === '') {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Sessão expirada.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $perfilsEfetivos = UsuarioPerfisAutorizacao::perfisDoUsuario($usuarioId);
    if (!AccessEvaluator::canAccessPage('estudapainel', $perfilsEfetivos)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Acesso negado.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $usuario = new Usuario();
    $perfilsCadastro = $usuario->listarPerfisUsuario($usuarioId);
    if (!is_array($perfilsCadastro)) {
        $perfilsCadastro = [];
    }
    $norm = ProfileNormalizer::normalize($perfilsCadastro);
    if (!UserAccessHelper::isUsuarioExterno($perfilsCadastro) || !ProfileNormalizer::hasEstudante($norm)) {
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
    $codigosPrioridadeBloqueio = PrioridadesProcessuaisCatalog::codigosBloqueantesInscricaoEstudante();
    $marksPrioridade = implode(',', array_fill(0, count($codigosPrioridadeBloqueio), '?'));
    $sql = "SELECT a.`id_pauta`, a.`status`, a.`data_audiencia`, a.`hora_audiencia`, a.`data_hora_inicio_efetivo`, a.`data_hora_fim_efetivo`, a.`duracao_estimada_minutos`,
                   " . SalaAudienciasAtaSigiloHelper::sqlProcessoSigilosoEfetivo('p', 'a') . " AS `processo_sigiloso`,
                   CASE WHEN i.`id` IS NOT NULL THEN 1 ELSE 0 END AS `ja_inscrito`,
                   (SELECT COUNT(*) FROM `sala_audiencias_pauta_parte` pp WHERE pp.`pauta` = a.`id_pauta` AND pp.`ativo` = 1 AND pp.`maioridade` = 'menor') AS `qtd_menor`,
                   (SELECT COUNT(*) FROM `sala_audiencias_estudante_inscricao` inv WHERE inv.`pauta` = a.`id_pauta`) AS `total_inscritos_pauta`,
                   CASE WHEN EXISTS (
                        SELECT 1 FROM `processo_prioridades` pri
                        WHERE pri.`processo_id` = a.`processo`
                          AND pri.`codigo` IN ($marksPrioridade)
                   ) THEN 1 ELSE 0 END AS `tem_prioridade_bloqueio`
            FROM `sala_audiencias_pauta` a
            LEFT JOIN `processos` p ON p.`id` = a.`processo`
            LEFT JOIN `sala_audiencias_estudante_inscricao` i ON i.`pauta` = a.`id_pauta` AND i.`usuario` = ?
            WHERE a.`ativo` = 1 AND a.`id_pauta` IN ($marks)";
    $st = $pdo->prepare($sql);
    if ($st === false) {
        throw new RuntimeException('Falha ao preparar consulta.');
    }
    foreach ($codigosPrioridadeBloqueio as $i => $codigo) {
        $st->bindValue($i + 1, $codigo);
    }
    $indiceUsuario = count($codigosPrioridadeBloqueio) + 1;
    $st->bindValue($indiceUsuario, $usuarioId);
    foreach ($ids as $i => $id) {
        $st->bindValue($indiceUsuario + $i + 1, $id);
    }
    if (!$st->execute()) {
        throw new RuntimeException('Falha ao executar consulta.');
    }
    $rows = $st->fetchAll(PDO::FETCH_ASSOC);
    if ($rows === false) {
        throw new RuntimeException('Falha ao consultar pautas.');
    }

    $items = [];
    $agoraStatusTs = time();
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
        } elseif ($status === 'encerrada') {
            $status = 'finalizada';
        }
        $horarioDesignadoTs = strtotime(trim((string) ($r['data_audiencia'] ?? '') . ' ' . (string) ($r['hora_audiencia'] ?? '')));
        if ($status === 'designada' && $horarioDesignadoTs !== false && $horarioDesignadoTs < $agoraStatusTs) {
            $status = 'atrasada';
        }
        $totalIns = (int) ($r['total_inscritos_pauta'] ?? 0);
        $jaInscrito = (int) ($r['ja_inscrito'] ?? 0) === 1;
        $sigiloso = (int) ($r['processo_sigiloso'] ?? 0) !== 0;
        $envolveMenor = (int) ($r['qtd_menor'] ?? 0) > 0;
        $temPrioridadeBloqueio = (int) ($r['tem_prioridade_bloqueio'] ?? 0) !== 0;
        if (SalaAudienciasEstudantePrivacidade::indisponivel($r)) {
            $items[$id] = SalaAudienciasEstudantePrivacidade::respostaIndisponivel($id);
            continue;
        }
        $vagasEsgotadas = $totalIns >= SalaAudienciasAtaService::MAX_ESTUDANTES_INSCRITOS_POR_AUDIENCIA;
        $podeInscrever = !$jaInscrito
            && !$vagasEsgotadas
            && !$sigiloso
            && !$envolveMenor
            && !$temPrioridadeBloqueio;
        $items[$id] = [
            'id_pauta' => $id,
            'status' => $status,
            'data_audiencia' => trim((string) ($r['data_audiencia'] ?? '')),
            'hora_audiencia' => trim((string) ($r['hora_audiencia'] ?? '')),
            'data_hora_inicio_efetivo' => trim((string) ($r['data_hora_inicio_efetivo'] ?? '')),
            'data_hora_fim_efetivo' => trim((string) ($r['data_hora_fim_efetivo'] ?? '')),
            'duracao_estimada_minutos' => (int) ($r['duracao_estimada_minutos'] ?? 0),
            'ja_inscrito' => $jaInscrito,
            'vagas_esgotadas' => $vagasEsgotadas,
            'total_inscritos_pauta' => $totalIns,
            'pode_inscrever' => $podeInscrever,
        ];
    }
    echo json_encode(['success' => true, 'items' => (object) $items, 'completo' => true, 'consultados' => $ids], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Erro ao consultar status.'], JSON_UNESCAPED_UNICODE);
}
