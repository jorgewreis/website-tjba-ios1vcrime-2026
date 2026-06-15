<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=UTF-8');

$basePath = dirname(__DIR__, 3);
require_once $basePath . '/config/security.php';
require_once $basePath . '/app/helpers/Conection.php';
require_once $basePath . '/app/helpers/BootstrapHelper.php';
require_once $basePath . '/app/helpers/UsuarioPerfisAutorizacao.php';
require_once $basePath . '/app/models/Usuario.php';
require_once $basePath . '/app/access/AccessEvaluator.php';
require_once $basePath . '/app/access/ProfileNormalizer.php';
require_once $basePath . '/app/helpers/UserAccessHelper.php';
require_once $basePath . '/app/services/SalaAudienciasAtaService.php';
require_once $basePath . '/app/helpers/PrioridadesProcessuaisCatalog.php';
require_once $basePath . '/app/helpers/SalaAudienciasAtaSigiloHelper.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Método não permitido.'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    // #region agent log
    $debugRunId = 'map-prioridades-status-bulk-pre';
    $debugLogPath = dirname(__DIR__, 4) . '/debug-07d2e0.log';
    $debugLog = static function (string $hypothesisId, string $location, string $message, array $data = []) use ($debugLogPath, $debugRunId): void {
        $payload = [
            'sessionId' => '07d2e0',
            'runId' => $debugRunId,
            'hypothesisId' => $hypothesisId,
            'location' => $location,
            'message' => $message,
            'data' => $data,
            'timestamp' => (int) floor(microtime(true) * 1000),
        ];
        @file_put_contents($debugLogPath, json_encode($payload, JSON_UNESCAPED_UNICODE) . PHP_EOL, FILE_APPEND);
    };
    $debugLog(
        'H0',
        'public/api/sala-audiencias-pauta/status-bulk.php:23',
        'Entrada no endpoint status-bulk',
        [
            'method' => (string) ($_SERVER['REQUEST_METHOD'] ?? ''),
            'tem_sessao_usuario' => isset($_SESSION['id_usuario']),
        ]
    );
    // #endregion

    if (!Security::isAuthenticated()) {
        // #region agent log
        $debugLog('H0', 'public/api/sala-audiencias-pauta/status-bulk.php:41', 'Saida por sessao expirada', []);
        // #endregion
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Sessão expirada.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $usuarioId = trim((string) ($_SESSION['id_usuario'] ?? ''));
    if ($usuarioId === '') {
        // #region agent log
        $debugLog('H0', 'public/api/sala-audiencias-pauta/status-bulk.php:48', 'Saida por usuario vazio', []);
        // #endregion
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Sessão expirada.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $perfilsEfetivos = UsuarioPerfisAutorizacao::perfisDoUsuario($usuarioId);
    if (!AccessEvaluator::canAccessPage('estudapainel', $perfilsEfetivos)) {
        // #region agent log
        $debugLog('H0', 'public/api/sala-audiencias-pauta/status-bulk.php:54', 'Saida por acesso negado na pagina estudapainel', []);
        // #endregion
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
        // #region agent log
        $debugLog('H0', 'public/api/sala-audiencias-pauta/status-bulk.php:74', 'Saida por perfil nao estudante externo', ['perfis_total' => count($perfilsCadastro)]);
        // #endregion
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
    // #region agent log
    $hardcodedBloqueios = ['sigiloso', 'crianca_adolescente', 'violencia_domestica'];
    $catalogoBloqueios = PrioridadesProcessuaisCatalog::codigosBloqueantesInscricaoEstudante();
    $faltandoNoHardcoded = array_values(array_diff($catalogoBloqueios, $hardcodedBloqueios));
    $extraNoHardcoded = array_values(array_diff($hardcodedBloqueios, $catalogoBloqueios));
    $debugLog(
        'H1',
        'public/api/sala-audiencias-pauta/status-bulk.php:66',
        'Comparacao lista bloqueante hardcoded vs catalogo',
        [
            'hardcoded' => $hardcodedBloqueios,
            'catalogo' => $catalogoBloqueios,
            'faltando_no_hardcoded' => $faltandoNoHardcoded,
            'extra_no_hardcoded' => $extraNoHardcoded,
            'divergiu' => ($faltandoNoHardcoded !== [] || $extraNoHardcoded !== []),
            'total_pautas_consulta' => count($ids),
        ]
    );
    // #endregion
    /*
     * Os códigos bloqueantes devem espelhar
     * PrioridadesProcessuaisCatalog::codigosBloqueantesInscricaoEstudante().
     */
    $sql = "SELECT a.`id_pauta`, a.`status`, a.`data_audiencia`, a.`hora_audiencia`, a.`data_hora_inicio_efetivo`, a.`data_hora_fim_efetivo`, a.`duracao_estimada_minutos`,
                   " . SalaAudienciasAtaSigiloHelper::sqlProcessoSigilosoEfetivo('p', 'a') . " AS `processo_sigiloso`,
                   CASE WHEN i.`id` IS NOT NULL THEN 1 ELSE 0 END AS `ja_inscrito`,
                   (SELECT COUNT(*) FROM `sala_audiencias_pauta_parte` pp WHERE pp.`pauta` = a.`id_pauta` AND pp.`ativo` = 1 AND pp.`maioridade` = 'menor') AS `qtd_menor`,
                   (SELECT COUNT(*) FROM `sala_audiencias_estudante_inscricao` inv WHERE inv.`pauta` = a.`id_pauta`) AS `total_inscritos_pauta`,
                   CASE WHEN EXISTS (
                        SELECT 1 FROM `processo_prioridades` pri
                        WHERE pri.`processo_id` = a.`processo`
                          AND pri.`codigo` IN ('sigiloso', 'crianca_adolescente', 'violencia_domestica')
                   ) THEN 1 ELSE 0 END AS `tem_prioridade_bloqueio`
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
    // #region agent log
    $debugLog(
        'H2',
        'public/api/sala-audiencias-pauta/status-bulk.php:89',
        'Resultado da query status-bulk',
        [
            'rows_total' => count($rows),
            'amostra_pautas' => array_slice(array_values(array_map(static fn (array $r): string => (string) ($r['id_pauta'] ?? ''), $rows)), 0, 5),
        ]
    );
    // #endregion

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
        $temPrioridadeBloqueio = (int) ($r['tem_prioridade_bloqueio'] ?? 0) !== 0;
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
            'processo_sigiloso' => $sigiloso,
            'ja_inscrito' => $jaInscrito,
            'envolve_menor' => $envolveMenor,
            'tem_prioridade_bloqueio' => $temPrioridadeBloqueio,
            'vagas_esgotadas' => $vagasEsgotadas,
            'total_inscritos_pauta' => $totalIns,
            'pode_inscrever' => $podeInscrever,
        ];
    }
    // #region agent log
    $totalComBloqueio = 0;
    $totalPodeInscrever = 0;
    foreach ($items as $it) {
        if (!empty($it['tem_prioridade_bloqueio'])) {
            $totalComBloqueio++;
        }
        if (!empty($it['pode_inscrever'])) {
            $totalPodeInscrever++;
        }
    }
    $debugLog(
        'H3',
        'public/api/sala-audiencias-pauta/status-bulk.php:132',
        'Impacto de prioridade bloqueante no payload final',
        [
            'items_total' => count($items),
            'items_com_prioridade_bloqueio' => $totalComBloqueio,
            'items_pode_inscrever' => $totalPodeInscrever,
        ]
    );
    // #endregion

    echo json_encode(['success' => true, 'items' => $items], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    // #region agent log
    @file_put_contents(
        dirname(__DIR__, 4) . '/debug-07d2e0.log',
        json_encode([
            'sessionId' => '07d2e0',
            'runId' => 'map-prioridades-status-bulk-pre',
            'hypothesisId' => 'H4',
            'location' => 'public/api/sala-audiencias-pauta/status-bulk.php:catch',
            'message' => 'Excecao no endpoint status-bulk',
            'data' => ['erro' => $e->getMessage()],
            'timestamp' => (int) floor(microtime(true) * 1000),
        ], JSON_UNESCAPED_UNICODE) . PHP_EOL,
        FILE_APPEND
    );
    // #endregion
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Erro ao consultar status.'], JSON_UNESCAPED_UNICODE);
}
