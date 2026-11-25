<?php
declare(strict_types=1);


require_once dirname(__DIR__, 3) . '/app/helpers/ApiErrorReporter.php';
ApiErrorReporter::register('public/api/processos-partes/update.php');
header('Content-Type: application/json; charset=UTF-8');

$basePath = dirname(__DIR__, 3);
require_once $basePath . '/app/helpers/ApiAccessGuard.php';
ApiAccessGuard::requireAccess(['processospartesedit'], true);
require_once $basePath . '/app/helpers/Conection.php';
require_once $basePath . '/app/helpers/BootstrapHelper.php';
require_once $basePath . '/app/repositories/ProcessosPartesRepository.php';
require_once $basePath . '/app/repositories/PartesRepository.php';
require_once $basePath . '/app/helpers/ProcessoSetorAuthorization.php';

try {
    $connection = Conection::getInstance()->getConnection();
} catch (Throwable $exception) {
    error_log('processos-partes: falha de conexão.');
    http_response_code(500);
    echo json_encode(['error' => 'Não foi possível concluir a operação.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$payload = file_get_contents('php://input') ?: '';
$parsed = json_decode($payload, true);
if (!is_array($parsed)) {
    $parsed = $_POST;
}

$id = trim((string) ($parsed['id'] ?? ''));
if ($id === '') {
    http_response_code(400);
    echo json_encode(['error' => 'Identificador do vínculo processo-parte é obrigatório.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$allowed = ['tipo_parte', 'polo', 'prioridade_mulher', 'prioridade_crianca_adolescente', 'prioridade_idoso', 'prioridade_pessoa_deficiencia'];
$data = array_intersect_key($parsed, array_flip($allowed));

try {
    $connection->beginTransaction();
    $repository = new ProcessosPartesRepository($connection);
    $processId = ProcessoSetorAuthorization::findProcessIdByLink($connection, $id);
    if ($processId === null) {
        $connection->commit();
        http_response_code(404);
        echo json_encode(['error' => 'Vínculo não encontrado.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    if (!ProcessoSetorAuthorization::canModifyProcess(
        $connection,
        trim((string) ($_SESSION['id_usuario'] ?? '')),
        $processId
    )) {
        $connection->rollBack();
        http_response_code(404);
        echo json_encode(['error' => 'Vínculo não encontrado.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $lock = $connection->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql' ? ' FOR UPDATE' : '';
    $locked = $connection->prepare('SELECT `processo_id`, `parte_id`, `tipo_parte` FROM `processos_partes` WHERE `id` = :id' . $lock);
    $locked->execute([':id' => $id]);
    $vinculo = $locked->fetch(PDO::FETCH_ASSOC);
    $locked->closeCursor();
    if ($vinculo === false || !hash_equals($processId, trim((string) $vinculo['processo_id']))) {
        $connection->rollBack();
        http_response_code(404);
        echo json_encode(['error' => 'Vínculo não encontrado.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    if ($data === []) {
        $connection->commit();
        echo json_encode(['success' => true], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $repository->update($id, $data);
    $tipoAtual = trim((string) (array_key_exists('tipo_parte', $data) ? $data['tipo_parte'] : ($vinculo['tipo_parte'] ?? '')));
    $parteIdVinculo = trim((string) ($vinculo['parte_id'] ?? ''));
    if ($parteIdVinculo !== '') {
        $partesRepository = new PartesRepository($connection);
        $partesRepository->syncProfissaoFromTipoProcessoParte($parteIdVinculo, $tipoAtual);
    }
    $connection->commit();
    echo json_encode(['success' => true], JSON_UNESCAPED_UNICODE);
} catch (InvalidArgumentException $exception) {
    if ($connection->inTransaction()) $connection->rollBack();
    http_response_code(400);
    echo json_encode(['error' => $exception->getMessage()], JSON_UNESCAPED_UNICODE);
} catch (Throwable $exception) {
    ApiErrorReporter::logUnexpected($exception, 'public/api/processos-partes/update.php');
    http_response_code(500);
    echo json_encode(['error' => 'Erro ao atualizar vínculo.'], JSON_UNESCAPED_UNICODE);
}
