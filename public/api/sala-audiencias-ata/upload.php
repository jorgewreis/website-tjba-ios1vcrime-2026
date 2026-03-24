<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=UTF-8');

$basePath = dirname(__DIR__, 3);
require_once $basePath . '/app/helpers/Conection.php';
require_once $basePath . '/app/helpers/BootstrapHelper.php';
require_once $basePath . '/app/models/Usuario.php';
require_once $basePath . '/app/access/AccessEvaluator.php';
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
    $usuario = new Usuario();
    $perfils = $usuario->listarPerfisUsuario($usuarioId);
    if (!is_array($perfils)) {
        $perfils = [];
    }
    if (!AccessEvaluator::canAccessPage('salaata', $perfils)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Acesso negado.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $idPauta = trim((string) ($_POST['pauta'] ?? ''));
    $file = $_FILES['arquivo'] ?? null;
    if (!is_array($file)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Nenhum arquivo enviado.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $service = new SalaAudienciasAtaService(Conection::getInstance()->getConnection());
    $service->uploadPdf($idPauta, $file, $usuarioId);
    echo json_encode(['success' => true], JSON_UNESCAPED_UNICODE);
} catch (InvalidArgumentException $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Erro ao enviar o arquivo: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
