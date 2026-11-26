<?php
declare(strict_types=1);


require_once dirname(__DIR__, 3) . '/app/helpers/ApiErrorReporter.php';
ApiErrorReporter::register('public/api/processos-partes/create.php');
header('Content-Type: application/json; charset=UTF-8');

$basePath = dirname(__DIR__, 3);
require_once $basePath . '/app/helpers/ApiAccessGuard.php';
ApiAccessGuard::requireAccess(['processospartescreate', 'consultaprocessosedit'], true);
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

$processoId = trim((string) ($parsed['processo_id'] ?? ''));
$parteId = trim((string) ($parsed['parte_id'] ?? ''));
$tipoParte = trim((string) ($parsed['tipo_parte'] ?? ''));
$polo = trim((string) ($parsed['polo'] ?? ''));
if ($processoId === '' || $parteId === '') {
    http_response_code(400);
    echo json_encode(['error' => 'processo_id e parte_id são obrigatórios.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$prioridades = [
    'prioridade_mulher' => $parsed['prioridade_mulher'] ?? false,
    'prioridade_crianca_adolescente' => $parsed['prioridade_crianca_adolescente'] ?? false,
    'prioridade_idoso' => $parsed['prioridade_idoso'] ?? false,
    'prioridade_pessoa_deficiencia' => $parsed['prioridade_pessoa_deficiencia'] ?? false,
];

try {
    if (!$connection->beginTransaction()) {
        throw new RuntimeException('Não foi possível iniciar a operação.');
    }
    if (!ProcessoSetorAuthorization::canModifyProcess(
        $connection,
        trim((string) ($_SESSION['id_usuario'] ?? '')),
        $processoId
    )) {
        $connection->rollBack();
        http_response_code(403);
        echo json_encode(['error' => 'Acesso negado ao processo.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    if ((new PartesRepository($connection))->findById($parteId) === null) {
        throw new InvalidArgumentException('Pessoa nÃ£o encontrada.');
    }
    $repository = new ProcessosPartesRepository($connection);
    $newId = $repository->addParte($processoId, $parteId, $tipoParte, $polo, $prioridades);
    $partesRepository = new PartesRepository($connection);
    $partesRepository->syncProfissaoFromTipoProcessoParte($parteId, $tipoParte);
    $connection->commit();
    http_response_code(201);
    echo json_encode(['id' => $newId], JSON_UNESCAPED_UNICODE);
} catch (InvalidArgumentException $exception) {
    if ($connection->inTransaction()) $connection->rollBack();
    http_response_code(400);
    echo json_encode(['error' => $exception->getMessage()], JSON_UNESCAPED_UNICODE);
} catch (Throwable $exception) {
    ApiErrorReporter::logUnexpected($exception, 'public/api/processos-partes/create.php');
    http_response_code(500);
    echo json_encode(['error' => 'Erro ao vincular parte ao processo.'], JSON_UNESCAPED_UNICODE);
}
