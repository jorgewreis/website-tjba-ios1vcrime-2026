<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=UTF-8');

$basePath = dirname(__DIR__, 3);
require_once $basePath . '/app/helpers/Conection.php';
require_once $basePath . '/app/helpers/BootstrapHelper.php';
require_once $basePath . '/config/security.php';
require_once $basePath . '/app/models/Usuario.php';
require_once $basePath . '/app/helpers/UsuarioPerfisAutorizacao.php';
require_once $basePath . '/app/access/AccessEvaluator.php';
require_once $basePath . '/app/services/DataJudService.php';
require_once $basePath . '/app/services/CartasExpedidasService.php';

if (!bootstrapRequireTrustedPostRequest()) {
    http_response_code(403);
    echo json_encode(['error' => 'Requisição inválida.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$usuarioCadastroId = isset($_SESSION['id_usuario']) ? trim((string) $_SESSION['id_usuario']) : '';
if (!Security::isAuthenticated() || $usuarioCadastroId === '') {
    Security::clearAuthenticatedSession();
    http_response_code(401);
    echo json_encode(['error' => 'Usuário não autenticado.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$usuario = new Usuario();
$token = trim((string) ($_SESSION['token'] ?? ''));
if ($token === '' || !$usuario->validarToken($token)) {
    Security::clearAuthenticatedSession();
    http_response_code(401);
    echo json_encode(['error' => 'Sessão expirada. Faça login novamente.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$perfis = UsuarioPerfisAutorizacao::perfisDoUsuario($usuarioCadastroId);
if (!AccessEvaluator::canAccessPage('cartasexpedidascreate', $perfis)) {
    http_response_code(403);
    echo json_encode(['error' => 'Seu perfil não tem permissão para criar cartas expedidas.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$connection = Conection::getInstance()->getConnection();
$dataJudConfig = require $basePath . '/public/api/datajud/config.php';
$dataJudService = new DataJudService($dataJudConfig);

$payload = file_get_contents('php://input') ?: '';
$parsed = json_decode($payload, true);
if (!is_array($parsed)) {
    $parsed = $_POST;
}

$parsed['usuario_cadastro_id'] = $usuarioCadastroId;

function assertCnjFormattedValue(array $input, string $key, string $fieldLabel): void
{
    if (!array_key_exists($key, $input)) {
        return;
    }
    $value = trim((string) ($input[$key] ?? ''));
    if ($value === '') {
        return;
    }
    if (!preg_match('/^\d{7}-\d{2}\.\d{4}\.\d\.\d{2}\.\d{4}$/', $value)) {
        throw new \InvalidArgumentException($fieldLabel . ' deve ser enviado no formato CNJ completo (0000000-00.0000.0.00.0000).');
    }
}

try {
    assertCnjFormattedValue($parsed, 'numero_processo', 'Número do processo');

    $service = new CartasExpedidasService($connection, $dataJudService);
    $newId = $service->create($parsed);
    http_response_code(201);
    echo json_encode(['id' => $newId], JSON_UNESCAPED_UNICODE);
} catch (\InvalidArgumentException $exception) {
    http_response_code(400);
    echo json_encode(['error' => $exception->getMessage()], JSON_UNESCAPED_UNICODE);
} catch (\Throwable $exception) {
    http_response_code(500);
    echo json_encode(['error' => 'Erro ao criar carta expedida: ' . $exception->getMessage()], JSON_UNESCAPED_UNICODE);
}
