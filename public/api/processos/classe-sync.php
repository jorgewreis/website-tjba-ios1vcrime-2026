<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=UTF-8');

$basePath = dirname(__DIR__, 3);
require_once $basePath . '/app/helpers/ApiAccessGuard.php';
require_once $basePath . '/app/helpers/ProcessoSetorAuthorization.php';
require_once $basePath . '/app/helpers/Conection.php';
require_once $basePath . '/app/helpers/BootstrapHelper.php';
require_once $basePath . '/app/helpers/CnjValidator.php';
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

try {
    $usuarioId = isset($_SESSION['id_usuario']) ? trim((string) $_SESSION['id_usuario']) : '';
    if ($usuarioId === '') {
        http_response_code(401);
        echo json_encode(['error' => 'Sessão expirada. Faça login novamente.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    try {
        $digits = CnjValidator::digits20OrThrow($numero);
    } catch (InvalidArgumentException $e) {
        http_response_code(400);
        echo json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // A classe enviada pelo navegador não comprova sua origem. Exigir processo
    // local e consultar a fonte no servidor antes de qualquer gravação.
    $connection = Conection::getInstance()->getConnection();
    if ((new ProcessosRepository($connection))->findByNumero($digits) === null) {
        http_response_code(404);
        echo json_encode(['error' => 'Processo não encontrado no cadastro local.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $config = require $basePath . '/public/api/datajud/config.php';
    if (!is_array($config) || trim((string) ($config['api_key'] ?? '')) === '') {
        throw new RuntimeException('Integração DataJud indisponível.');
    }
    $resultado = (new DataJudService($config))->searchProcesso($digits);
    $classe = $resultado['classe'] ?? null;
    $codigo = is_array($classe) ? ($classe['codigo'] ?? null) : null;
    $descricao = is_array($classe) ? ($classe['descricao'] ?? null) : null;
    if (($resultado['resultado_unico_verificado'] ?? null) !== true
        || ($resultado['sigilo_ausencia_verificada'] ?? null) !== true
        || !empty($resultado['sigilo_restrito'])
        || !(is_int($codigo) || is_string($codigo))
        || !preg_match('/^[0-9]+$/D', (string) $codigo)
        || (int) $codigo <= 0
        || !is_string($descricao) || trim($descricao) === '') {
        http_response_code(422);
        echo json_encode(['error' => 'O DataJud não retornou classe única e verificável para sincronização.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $escritaIniciada = true;
    $result = (new ProcessoDataJudSyncService($connection))->sync(
        $digits,
        ['codigo' => (string) $codigo, 'descricao' => trim($descricao)],
        null,
        false,
        static function (PDO $db) use ($usuarioId, $digits): bool {
            $processo = (new ProcessosRepository($db))->findByNumero($digits);
            $processoId = trim((string) ($processo['id'] ?? ''));
            return $processoId !== ''
                && ProcessoSetorAuthorization::canModifyProcess($db, $usuarioId, $processoId, true);
        }
    )['classe'];
    echo json_encode($result, JSON_UNESCAPED_UNICODE);
} catch (InvalidArgumentException $e) {
    if ($escritaIniciada) {
        http_response_code(409);
        echo json_encode(['error' => 'Não foi possível confirmar a sincronização. Confira os dados antes de tentar novamente.', 'code' => 'sincronizacao_resultado_indeterminado'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    if ($escritaIniciada) {
        http_response_code(409);
        echo json_encode(['error' => 'Não foi possível confirmar a sincronização. Confira os dados antes de tentar novamente.', 'code' => 'sincronizacao_resultado_indeterminado'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    http_response_code(500);
    echo json_encode(['error' => 'Não foi possível sincronizar a classe.'], JSON_UNESCAPED_UNICODE);
}
