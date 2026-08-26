<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=UTF-8');

$basePath = dirname(__DIR__, 3);
require_once $basePath . '/app/helpers/Conection.php';
require_once $basePath . '/app/helpers/BootstrapHelper.php';
require_once $basePath . '/app/helpers/CnjValidator.php';
require_once $basePath . '/app/services/DataJudService.php';
require_once $basePath . '/app/services/ProcessoClasseSyncService.php';
require_once $basePath . '/app/services/ProcessoAssuntoSyncService.php';

$payload = json_decode(file_get_contents('php://input') ?: '[]', true);
if (!is_array($payload)) {
    $payload = $_POST;
}

$numero = isset($payload['numero']) ? (string) $payload['numero'] : '';

try {
    $usuarioId = isset($_SESSION['id_usuario']) ? trim((string) $_SESSION['id_usuario']) : '';
    if ($usuarioId === '') {
        http_response_code(401);
        echo json_encode(['error' => 'Sessão expirada. Faça login novamente.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $digits = CnjValidator::digits20OrThrow($numero);
    $config = require $basePath . '/public/api/datajud/config.php';
    if (($config['api_key'] ?? '') === '') {
        throw new RuntimeException('Integração DataJud não configurada.');
    }

    $dataJud = new DataJudService($config);
    $classeSync = new ProcessoClasseSyncService(Conection::getInstance()->getConnection());
    $assuntoSync = new ProcessoAssuntoSyncService(Conection::getInstance()->getConnection());

    $resultadoBusca = $dataJud->searchProcesso($digits);
    $classe = is_array($resultadoBusca['classe'] ?? null) ? $resultadoBusca['classe'] : [];
    $assuntos = is_array($resultadoBusca['assuntos'] ?? null) ? $resultadoBusca['assuntos'] : [];
    $classeCodigo = trim((string) ($classe['codigo'] ?? ''));
    $classeDescricao = trim((string) ($classe['descricao'] ?? ''));
    $classeResultado = null;
    $assuntosResultado = null;

    if ($classeCodigo !== '') {
        $classeResultado = $classeSync->syncFromDataJud($digits, $classeCodigo, $classeDescricao);
    }

    if ($assuntos !== []) {
        $assuntosResultado = $assuntoSync->syncFromDataJud($digits, $assuntos);
    }

    if ($classeResultado === null && $assuntosResultado === null) {
        http_response_code(422);
        echo json_encode([
            'error' => 'O DataJud não retornou classe nem assuntos sincronizáveis para este processo.',
            'sigilo_restrito' => !empty($resultadoBusca['sigilo_restrito']),
            'indisponivel_na_base_publica' => !empty($resultadoBusca['indisponivel_na_base_publica']),
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $mensagens = [];
    if ($classeResultado !== null) {
        $acaoClasse = trim((string) ($classeResultado['action'] ?? ''));
        if ($acaoClasse === 'created') {
            $mensagens[] = 'Classe do processo cadastrada a partir do DataJud.';
        } elseif ($acaoClasse === 'updated') {
            $mensagens[] = 'Classe do processo atualizada a partir do DataJud.';
        } elseif ($acaoClasse === 'noop') {
            $mensagens[] = 'Classe do processo já estava sincronizada.';
        }
    }
    if ($assuntosResultado !== null) {
        $acaoAssuntos = trim((string) ($assuntosResultado['action'] ?? ''));
        if ($acaoAssuntos === 'created') {
            $mensagens[] = 'Assuntos do processo cadastrados a partir do DataJud.';
        } elseif ($acaoAssuntos === 'updated') {
            $mensagens[] = 'Assuntos do processo atualizados a partir do DataJud.';
        } elseif ($acaoAssuntos === 'noop') {
            $mensagens[] = 'Assuntos do processo já estavam sincronizados.';
        }
    }

    echo json_encode([
        'success' => true,
        'message' => implode(' ', $mensagens),
        'classe' => $classeResultado,
        'assuntos' => $assuntosResultado,
    ], JSON_UNESCAPED_UNICODE);
} catch (InvalidArgumentException $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
} catch (RuntimeException $e) {
    http_response_code(502);
    echo json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Erro ao sincronizar dados do processo: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
