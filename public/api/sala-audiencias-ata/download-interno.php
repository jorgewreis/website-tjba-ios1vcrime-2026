<?php
declare(strict_types=1);


require_once dirname(__DIR__, 3) . '/app/helpers/ApiErrorReporter.php';
ApiErrorReporter::register('public/api/sala-audiencias-ata/download-interno.php');
$basePath = dirname(__DIR__, 3);
require_once $basePath . '/app/helpers/Conection.php';
require_once $basePath . '/app/helpers/BootstrapHelper.php';
require_once $basePath . '/app/helpers/UsuarioPerfisAutorizacao.php';
require_once $basePath . '/app/access/AccessEvaluator.php';
require_once $basePath . '/app/services/SalaAudienciasAtaService.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'Método não permitido.';
    exit;
}

try {
    $usuarioId = isset($_SESSION['id_usuario']) ? trim((string) $_SESSION['id_usuario']) : '';
    if ($usuarioId === '') {
        http_response_code(401);
        header('Content-Type: text/plain; charset=UTF-8');
        echo 'Sessão expirada.';
        exit;
    }
    $perfils = UsuarioPerfisAutorizacao::perfisDoUsuario($usuarioId);
    if (!AccessEvaluator::canAccessPage('salaata', $perfils)) {
        http_response_code(403);
        header('Content-Type: text/plain; charset=UTF-8');
        echo 'Acesso negado.';
        exit;
    }

    $idPauta = trim((string) ($_GET['pauta'] ?? ''));
    $service = new SalaAudienciasAtaService(Conection::getInstance()->getConnection());
    $payload = $service->getDownloadPayloadInterno($idPauta);

    $nome = (string) $payload['nome_original'];
    if ($nome === '') {
        $nome = 'ata.pdf';
    }
    $safeName = preg_replace('/[^\p{L}\p{N}._\-\s\(\)]/u', '_', $nome) ?: 'ata.pdf';

    header('Content-Type: ' . ($payload['mime'] ?: 'application/pdf'));
    header('Content-Disposition: inline; filename="' . str_replace('"', '', $safeName) . '"');
    $len = @filesize($payload['full_path']);
    if ($len !== false) {
        header('Content-Length: ' . (string) $len);
    }
    readfile($payload['full_path']);
    exit;
} catch (InvalidArgumentException $e) {
    http_response_code(400);
    header('Content-Type: text/plain; charset=UTF-8');
    echo $e->getMessage();
    exit;
} catch (Throwable $e) {
    ApiErrorReporter::logUnexpected($e, 'public/api/sala-audiencias-ata/download-interno.php');
    http_response_code(500);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'Erro ao abrir o arquivo.';
    exit;
}
