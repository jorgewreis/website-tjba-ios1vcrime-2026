<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=UTF-8');

$basePath = dirname(__DIR__, 3);
require_once $basePath . '/app/helpers/Conection.php';
require_once $basePath . '/app/helpers/BootstrapHelper.php';
require_once $basePath . '/app/helpers/CartorioSubtarefaUsuarioSessao.php';
require_once $basePath . '/app/services/GuiasService.php';

if (!bootstrapRequireTrustedPostRequest()) {
    http_response_code(403);
    echo json_encode(['error' => 'Requisição inválida.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$connection = Conection::getInstance()->getConnection();

$rawPayload = file_get_contents('php://input') ?: '';
$parsed = json_decode($rawPayload, true);
if (!is_array($parsed)) {
    $parsed = $_POST;
}

$subtarefaId = trim((string) ($parsed['id'] ?? $parsed['subtarefa_id'] ?? $parsed['subtarefaId'] ?? $_GET['id'] ?? $_GET['subtarefa_id'] ?? ''));
$dadosEspecificos = CartorioSubtarefaUsuarioSessao::mergeDadosEspecificosComSessao(
    $connection,
    $parsed['dados_especificos'] ?? null
);

if ($subtarefaId === '') {
    http_response_code(400);
    echo json_encode(['error' => 'Identificador da subtarefa é obrigatório.'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $service = new GuiasService($connection);
    $resultado = $service->concluirSubtarefa($subtarefaId, $dadosEspecificos);
    $body = ['success' => true];
    if (isset($resultado['valor_multa_calculado'])) {
        $body['valor_multa_calculado'] = $resultado['valor_multa_calculado'];
    }
    if (isset($resultado['tarefa_principal_concluida'])) {
        $body['tarefa_principal_concluida'] = (bool) $resultado['tarefa_principal_concluida'];
    }
    if (isset($resultado['tarefa_principal_mensagem'])) {
        $body['tarefa_principal_mensagem'] = (string) $resultado['tarefa_principal_mensagem'];
    }
    echo json_encode($body, JSON_UNESCAPED_UNICODE);
} catch (\InvalidArgumentException $exception) {
    http_response_code(400);
    echo json_encode(['error' => $exception->getMessage()], JSON_UNESCAPED_UNICODE);
} catch (\Throwable $exception) {
    http_response_code(500);
    echo json_encode(['error' => 'Erro ao concluir subtarefa: ' . $exception->getMessage()], JSON_UNESCAPED_UNICODE);
}
