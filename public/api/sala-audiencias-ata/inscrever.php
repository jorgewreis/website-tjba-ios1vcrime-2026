<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=UTF-8');

$basePath = dirname(__DIR__, 3);
require_once $basePath . '/app/helpers/Conection.php';
require_once $basePath . '/app/helpers/BootstrapHelper.php';
require_once $basePath . '/app/helpers/UsuarioPerfisAutorizacao.php';
require_once $basePath . '/app/models/Usuario.php';
require_once $basePath . '/app/access/AccessEvaluator.php';
require_once $basePath . '/app/access/ProfileNormalizer.php';
require_once $basePath . '/app/helpers/UserAccessHelper.php';
require_once $basePath . '/app/services/SalaAudienciasAtaService.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Método não permitido.'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $usuarioId = isset($_SESSION['id_usuario']) ? trim((string) $_SESSION['id_usuario']) : '';
    if ($usuarioId === '') {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Sessão expirada. Faça login novamente.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $perfilsEfetivos = UsuarioPerfisAutorizacao::perfisDoUsuario($usuarioId);
    if (!AccessEvaluator::canAccessPage('estudaata', $perfilsEfetivos)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Acesso negado.'], JSON_UNESCAPED_UNICODE);
        exit;
    }
    $usuario = new Usuario();
    $perfilsCadastro = $usuario->listarPerfisUsuario($usuarioId);
    if (!is_array($perfilsCadastro)) {
        $perfilsCadastro = [];
    }
    $norm = ProfileNormalizer::normalize($perfilsCadastro);
    if (!UserAccessHelper::isUsuarioExterno($perfilsCadastro) || !ProfileNormalizer::hasEstudante($norm)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Apenas estudantes podem se inscrever.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $payload = json_decode(file_get_contents('php://input') ?: '[]', true);
    if (!is_array($payload)) {
        $payload = $_POST;
    }
    $idPauta = trim((string) ($payload['pauta'] ?? ''));

    $service = new SalaAudienciasAtaService(Conection::getInstance()->getConnection());
    $service->inscreverEstudante($idPauta, $usuarioId);
    echo json_encode(['success' => true], JSON_UNESCAPED_UNICODE);
} catch (InvalidArgumentException $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Erro ao inscrever: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
