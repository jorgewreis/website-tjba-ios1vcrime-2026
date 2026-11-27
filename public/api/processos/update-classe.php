<?php
declare(strict_types=1);


require_once dirname(__DIR__, 3) . '/app/helpers/ApiErrorReporter.php';
ApiErrorReporter::register('public/api/processos/update-classe.php');
header('Content-Type: application/json; charset=UTF-8');

$basePath = dirname(__DIR__, 3);
require_once $basePath . '/app/helpers/ApiAccessGuard.php';
require_once $basePath . '/app/helpers/ProcessoSetorAuthorization.php';
require_once $basePath . '/app/helpers/Conection.php';
require_once $basePath . '/app/helpers/BootstrapHelper.php';
require_once $basePath . '/app/repositories/ProcessosRepository.php';
require_once $basePath . '/app/services/ProcessoClasseSyncService.php';

ApiAccessGuard::requireAccess(['consultaprocessosedit'], true);

$payload = json_decode(file_get_contents('php://input') ?: '[]', true);
if (!is_array($payload)) {
    $payload = $_POST;
}

$processoId = trim((string) ($payload['processo_id'] ?? ''));
$codigo = $payload['codigo'] ?? null;

try {
    if ($processoId === '') {
        http_response_code(400);
        echo json_encode(['error' => 'Identificador do processo é obrigatório.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $connection = Conection::getInstance()->getConnection();
    if ((new ProcessosRepository($connection))->findById($processoId) === null) {
        http_response_code(404);
        echo json_encode(['error' => 'Processo não encontrado no cadastro local.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $usuarioId = trim((string) ($_SESSION['id_usuario'] ?? ''));
    if ($usuarioId === '') {
        http_response_code(401);
        echo json_encode(['error' => 'Sessão expirada. Faça login novamente.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    if (!$connection->beginTransaction()) {
        throw new RuntimeException('Não foi possível iniciar a atualização.');
    }
    try {
        if (!ProcessoSetorAuthorization::canModifyProcess($connection, $usuarioId, $processoId, true)) {
            $connection->rollBack();
            http_response_code(403);
            echo json_encode(['error' => 'Acesso negado ao processo.'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        if (!is_string($codigo) || !preg_match('/^[0-9]{1,20}$/D', trim($codigo)) || array_key_exists('nome', $payload) || array_key_exists('descricao', $payload)) {
            throw new InvalidArgumentException('Informe somente o código numérico da classe do catálogo.');
        }
        if ((new ClassesRepository($connection))->findByCodigo(trim($codigo)) === null) {
            throw new InvalidArgumentException('Código de classe não encontrado no catálogo.');
        }
        $service = new ProcessoClasseSyncService($connection);
        $result = $service->updateClasseManual($processoId, trim($codigo), null);
        if (!$connection->commit()) {
            throw new RuntimeException('Não foi possível confirmar a atualização.');
        }
    } catch (Throwable $error) {
        if ($connection->inTransaction()) {
            $connection->rollBack();
        }
        throw $error;
    }

    echo json_encode(array_merge(['success' => true], $result), JSON_UNESCAPED_UNICODE);
} catch (InvalidArgumentException $e) {
    http_response_code(400);
    echo json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Erro ao atualizar classe do processo: ' . ApiErrorReporter::genericMessage($e, 'public/api/processos/update-classe.php')], JSON_UNESCAPED_UNICODE);
}
