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
require_once $basePath . '/app/services/GabineteMonitoramentoEletronicoService.php';

$connection = Conection::getInstance()->getConnection();

$payload = file_get_contents('php://input') ?: '';
$parsed = json_decode($payload, true);
if (!is_array($parsed)) {
    $parsed = $_POST;
}

function assertCnjFormattedValueMon(array $input, string $key, string $fieldLabel): void
{
    if (!array_key_exists($key, $input)) {
        return;
    }
    $value = trim((string) ($input[$key] ?? ''));
    if ($value === '') {
        return;
    }
    if (!preg_match('/^\d{7}-\d{2}\.\d{4}\.\d\.\d{2}\.\d{4}$/', $value)) {
        throw new InvalidArgumentException($fieldLabel . ' deve ser enviado no formato CNJ completo (0000000-00.0000.0.00.0000).');
    }
}

function respondGmeCreateError(int $statusCode, string $message): void
{
    http_response_code($statusCode);
    echo json_encode(['error' => $message], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    if (!Security::isAuthenticated()) {
        Security::clearAuthenticatedSession();
        respondGmeCreateError(401, 'Sessão expirada. Faça login novamente.');
    }

    $sessionToken = trim((string) ($_SESSION['token'] ?? ''));
    if ($sessionToken === '') {
        Security::clearAuthenticatedSession();
        respondGmeCreateError(401, 'Sessão expirada. Faça login novamente.');
    }

    $usuario = new Usuario();
    $user = $usuario->validarToken($sessionToken);
    if ($user === null) {
        Security::clearAuthenticatedSession();
        respondGmeCreateError(401, 'Sessão expirada. Faça login novamente.');
    }

    $usuarioId = trim((string) ($user['id_usuario'] ?? ($_SESSION['id_usuario'] ?? '')));
    if ($usuarioId === '') {
        Security::clearAuthenticatedSession();
        respondGmeCreateError(401, 'Sessão inválida.');
    }

    if (!AccessEvaluator::canAccessPage('monitoramentogabinetecreate', UsuarioPerfisAutorizacao::perfisDoUsuario($usuarioId))) {
        respondGmeCreateError(403, 'Acesso negado.');
    }

    assertCnjFormattedValueMon($parsed, 'numero_processo', 'Número do processo');
    $parsed['usuario_cadastro_id'] = $usuarioId;

    $service = new GabineteMonitoramentoEletronicoService($connection);
    $newId = $service->create($parsed);
    http_response_code(201);
    echo json_encode(['id' => $newId], JSON_UNESCAPED_UNICODE);
} catch (InvalidArgumentException $exception) {
    http_response_code(400);
    echo json_encode(['error' => $exception->getMessage()], JSON_UNESCAPED_UNICODE);
} catch (Throwable $exception) {
    http_response_code(500);
    echo json_encode(['error' => 'Erro ao registrar: ' . $exception->getMessage()], JSON_UNESCAPED_UNICODE);
}
