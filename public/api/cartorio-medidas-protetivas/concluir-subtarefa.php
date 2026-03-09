<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=UTF-8');

$basePath = dirname(__DIR__, 3);
require_once $basePath . '/app/helpers/Conection.php';
require_once $basePath . '/app/helpers/BootstrapHelper.php';
require_once $basePath . '/app/services/MedidasProtetivasService.php';

$connection = Conection::getInstance()->getConnection();

$payload = file_get_contents('php://input') ?: '';
$parsed = json_decode($payload, true);
if (!is_array($parsed)) {
    $parsed = $_POST;
}

$subtarefaId = trim((string) ($parsed['subtarefa_id'] ?? $parsed['subtarefaId'] ?? $_GET['subtarefa_id'] ?? ''));
$dadosRecebidos = $parsed['dados_especificos'] ?? null;

$nomeUsuario = '';
$usuarioId = isset($_SESSION['id_usuario']) ? trim((string) $_SESSION['id_usuario']) : '';
if ($usuarioId !== '') {
    $stmt = $connection->prepare('SELECT `nome`, `sobrenome` FROM `usuarios` WHERE `id_usuario` = :id LIMIT 1');
    $stmt->bindValue(':id', $usuarioId);
    $stmt->execute();
    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($usuario !== false) {
        $nome = trim((string) ($usuario['nome'] ?? ''));
        $sobrenome = trim((string) ($usuario['sobrenome'] ?? ''));
        $nomeUsuario = $sobrenome !== '' ? $nome . ' ' . $sobrenome : $nome;
    }
}

$dadosEspecificos = null;
$merge = [];
if ($nomeUsuario !== '') {
    $merge['nome'] = $nomeUsuario;
    $merge['usuario_id'] = $usuarioId;
}
if ($dadosRecebidos !== null) {
    $arr = is_string($dadosRecebidos) ? json_decode($dadosRecebidos, true) : $dadosRecebidos;
    if (is_array($arr)) {
        $merge = array_merge($arr, $merge);
    }
}
if (!empty($merge)) {
    $dadosEspecificos = json_encode($merge, JSON_UNESCAPED_UNICODE);
}

if ($subtarefaId === '') {
    http_response_code(400);
    echo json_encode(['error' => 'Identificador da subtarefa é obrigatório.'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $service = new MedidasProtetivasService($connection);
    $service->concluirSubtarefa($subtarefaId, $dadosEspecificos);
    echo json_encode(['success' => true], JSON_UNESCAPED_UNICODE);
} catch (InvalidArgumentException $exception) {
    http_response_code(400);
    echo json_encode(['error' => $exception->getMessage()], JSON_UNESCAPED_UNICODE);
} catch (Throwable $exception) {
    http_response_code(500);
    echo json_encode(['error' => 'Erro ao concluir subtarefa: ' . $exception->getMessage()], JSON_UNESCAPED_UNICODE);
}
