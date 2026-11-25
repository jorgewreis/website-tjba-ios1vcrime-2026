<?php
declare(strict_types=1);


require_once dirname(__DIR__, 2) . '/app/helpers/ApiErrorReporter.php';
ApiErrorReporter::register('public/api/alterar_senha.php');
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/security.php';
require_once __DIR__ . '/../../app/helpers/bootstrap.php';
require_once __DIR__ . '/../../app/models/Usuario.php';

header('Content-Type: application/json; charset=utf-8');

Security::requireAuthentication();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método não permitido.']);
    exit;
}

$payload = json_decode(file_get_contents('php://input'), true);
if (!is_array($payload)) {
    $payload = $_POST;
}

$csrf = $payload['csrf_token'] ?? null;
if (!Security::verifyCSRFToken(is_string($csrf) ? $csrf : null)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Sessão inválida. Recarregue a página e tente novamente.']);
    exit;
}

$senhaAtual     = trim((string) ($payload['senha_atual']     ?? ''));
$novaSenha      = trim((string) ($payload['nova_senha']      ?? ''));
$confirmarSenha = trim((string) ($payload['confirmar_senha'] ?? ''));

if ($senhaAtual === '' || $novaSenha === '' || $confirmarSenha === '') {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Preencha todos os campos.']);
    exit;
}

if ($novaSenha !== $confirmarSenha) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'A nova senha e a confirmação não coincidem.']);
    exit;
}

$idUsuario = Security::getUserId();
if (!$idUsuario) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Usuário não autenticado.']);
    exit;
}

try {
    $usuario   = new Usuario();
    $resultado = $usuario->alterarSenha($idUsuario, $senhaAtual, $novaSenha);

    if ($resultado['success'] ?? false) {
        echo json_encode(['success' => true, 'message' => $resultado['message'] ?? 'Senha alterada com sucesso.']);
    } else {
        http_response_code(422);
        echo json_encode(['success' => false, 'message' => $resultado['message'] ?? 'Não foi possível alterar a senha.']);
    }
} catch (Throwable $e) {
    error_log('alterar_senha API: ' . ApiErrorReporter::genericMessage($e, 'public/api/alterar_senha.php'));
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro interno. Tente novamente mais tarde.']);
}
