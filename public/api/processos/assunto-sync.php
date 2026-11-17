<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=UTF-8');

$basePath = dirname(__DIR__, 3);
require_once $basePath . '/app/helpers/ApiAccessGuard.php';
require_once $basePath . '/app/helpers/Conection.php';
require_once $basePath . '/app/helpers/BootstrapHelper.php';
require_once $basePath . '/app/helpers/CnjValidator.php';
require_once $basePath . '/app/helpers/ProcessoSetorAuthorization.php';
require_once $basePath . '/app/repositories/ProcessosRepository.php';
require_once $basePath . '/app/services/DataJudService.php';
require_once $basePath . '/app/services/ProcessoDataJudSyncService.php';

ApiAccessGuard::requireAccess(['consultaprocessos'], true);

$payload = json_decode(file_get_contents('php://input') ?: '[]', true);
if (!is_array($payload)) {
    $payload = $_POST;
}

$numero = isset($payload['numero']) ? (string) $payload['numero'] : '';
$escritaIniciada = false;
$autorizacaoSetorialNegada = false;
$fonteDataJudValidada = false;

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

    $connection = Conection::getInstance()->getConnection();
    $processo = (new ProcessosRepository($connection))->findByNumero($digits);
    if ($processo === null) {
        http_response_code(404);
        echo json_encode(['error' => 'Processo não encontrado no cadastro local.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $processoId = trim((string) ($processo['id'] ?? ''));
    if ($processoId === '' || !ProcessoSetorAuthorization::canAccessProcess($connection, $usuarioId, $processoId, false)) {
        http_response_code(403);
        echo json_encode(['error' => 'Acesso ao processo não autorizado.', 'code' => 'acesso_setorial_obrigatorio'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $resultado = (new DataJudService($config))->searchProcesso($digits);
    if (($resultado['resultado_unico_verificado'] ?? null) !== true
        || ($resultado['sigilo_ausencia_verificada'] ?? null) !== true
        || ($resultado['fonte_confirmada_para_sincronizacao'] ?? null) !== true) {
        http_response_code(422);
        echo json_encode(['error' => 'O DataJud não retornou resultado único e verificável para sincronização.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    if (!empty($resultado['sigilo_restrito'])) {
        http_response_code(422);
        echo json_encode(['error' => 'O DataJud indica sigilo incompatível com esta sincronização.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $estado = $resultado['assuntos_estado'] ?? null;
    $assuntos = $resultado['assuntos'] ?? null;
    if (!in_array($estado, ['vazio', 'presente'], true)
        || !is_array($assuntos)
        || !array_is_list($assuntos)
        || ($estado === 'vazio' && $assuntos !== [])
        || ($estado === 'presente' && $assuntos === [])
        || ($resultado['assuntos_completos'] ?? null) !== true) {
        http_response_code(422);
        echo json_encode(['error' => 'O DataJud não retornou assuntos completos e verificáveis para sincronização.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    if ($assuntos === []) {
        http_response_code(422);
        echo json_encode(['error' => 'O DataJud não retornou assuntos sincronizáveis para este processo.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $fonteDataJudValidada = true;
    $beforeWrite = static function (PDO $writeConnection) use (
        $digits,
        $processoId,
        $usuarioId,
        &$autorizacaoSetorialNegada,
        &$escritaIniciada
    ): bool {
        $lockSql = 'SELECT `numero_processo` FROM `processos` WHERE `id` = :processo_id LIMIT 1';
        if ($writeConnection->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') {
            $lockSql .= ' FOR UPDATE';
        }
        $lock = $writeConnection->prepare($lockSql);
        $lock->execute([':processo_id' => $processoId]);
        $numeroAtual = $lock->fetchColumn();
        $lock->closeCursor();
        $digitosAtuais = $numeroAtual === false ? '' : preg_replace('/\D+/', '', (string) $numeroAtual);
        if (!is_string($digitosAtuais) || !hash_equals($digits, $digitosAtuais)
            || !ProcessoSetorAuthorization::canModifyProcess($writeConnection, $usuarioId, $processoId)) {
            $autorizacaoSetorialNegada = true;
            return false;
        }

        $escritaIniciada = true;
        return true;
    };
    $resultadoSync = (new ProcessoDataJudSyncService($connection))->sync(
        $digits,
        null,
        $assuntos,
        false,
        $beforeWrite
    );
    echo json_encode($resultadoSync['assuntos'], JSON_UNESCAPED_UNICODE);
} catch (InvalidArgumentException $e) {
    if ($autorizacaoSetorialNegada) {
        http_response_code(403);
        echo json_encode(['error' => 'Acesso ao processo não autorizado.', 'code' => 'acesso_setorial_obrigatorio'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    if ($escritaIniciada) {
        http_response_code(409);
        echo json_encode(['error' => 'Não foi possível confirmar a sincronização.', 'code' => 'sincronizacao_resultado_indeterminado'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
} catch (RuntimeException $e) {
    if ($autorizacaoSetorialNegada) {
        http_response_code(403);
        echo json_encode(['error' => 'Acesso ao processo não autorizado.', 'code' => 'acesso_setorial_obrigatorio'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    if ($escritaIniciada) {
        http_response_code(409);
        echo json_encode(['error' => 'Não foi possível confirmar a sincronização.', 'code' => 'sincronizacao_resultado_indeterminado'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    http_response_code($fonteDataJudValidada ? 500 : 502);
    echo json_encode(['error' => $fonteDataJudValidada ? 'Não foi possível sincronizar os assuntos do processo.' : 'Não foi possível consultar o DataJud.'], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    if ($autorizacaoSetorialNegada) {
        http_response_code(403);
        echo json_encode(['error' => 'Acesso ao processo não autorizado.', 'code' => 'acesso_setorial_obrigatorio'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    if ($escritaIniciada) {
        http_response_code(409);
        echo json_encode(['error' => 'Não foi possível confirmar a sincronização.', 'code' => 'sincronizacao_resultado_indeterminado'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    http_response_code(500);
    echo json_encode(['error' => 'Não foi possível sincronizar os assuntos do processo.'], JSON_UNESCAPED_UNICODE);
}
