<?php
declare(strict_types=1);


require_once dirname(__DIR__, 3) . '/app/helpers/ApiErrorReporter.php';
ApiErrorReporter::register('public/api/sala-audiencias-ata/download.php');
$basePath = dirname(__DIR__, 3);
require_once $basePath . '/app/helpers/Conection.php';
require_once $basePath . '/app/helpers/BootstrapHelper.php';
require_once $basePath . '/config/security.php';
require_once $basePath . '/app/helpers/UsuarioPerfisAutorizacao.php';
require_once $basePath . '/app/models/Usuario.php';
require_once $basePath . '/app/access/AccessEvaluator.php';
require_once $basePath . '/app/access/ProfileNormalizer.php';
require_once $basePath . '/app/helpers/UserAccessHelper.php';
require_once $basePath . '/app/services/SalaAudienciasAtaService.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'Método não permitido.';
    exit;
}

try {
    if (!Security::isAuthenticated()) {
        http_response_code(401);
        header('Content-Type: text/plain; charset=UTF-8');
        echo 'Sessão expirada.';
        exit;
    }

    $usuarioId = isset($_SESSION['id_usuario']) ? trim((string) $_SESSION['id_usuario']) : '';
    if ($usuarioId === '') {
        http_response_code(401);
        header('Content-Type: text/plain; charset=UTF-8');
        echo 'Sessão expirada.';
        exit;
    }
    $perfilsEfetivos = UsuarioPerfisAutorizacao::perfisDoUsuario($usuarioId);
    if (!AccessEvaluator::canAccessPage('estudaata', $perfilsEfetivos)) {
        http_response_code(403);
        header('Content-Type: text/plain; charset=UTF-8');
        echo 'Acesso negado.';
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
        header('Content-Type: text/plain; charset=UTF-8');
        echo 'Acesso negado.';
        exit;
    }

    $idPauta = trim((string) ($_GET['pauta'] ?? ''));
    $service = new SalaAudienciasAtaService(Conection::getInstance()->getConnection());
    $payload = $service->getDownloadPayloadForEstudante($idPauta, $usuarioId);

    $nome = (string) $payload['nome_original'];
    if ($nome === '') {
        $nome = 'ata.pdf';
    }
    $safeName = preg_replace('/[^\p{L}\p{N}._\-\s\(\)]/u', '_', $nome) ?: 'ata.pdf';

    header('Content-Type: ' . ($payload['mime'] ?: 'application/pdf'));
    header('Content-Disposition: attachment; filename="' . str_replace('"', '', $safeName) . '"');
    $len = @filesize($payload['full_path']);
    if ($len !== false) {
        header('Content-Length: ' . (string) $len);
    }
    readfile($payload['full_path']);
    exit;
} catch (SalaAudienciasInscricaoIndisponivel $e) {
    http_response_code(400);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'Audiência indisponível.';
    exit;
} catch (InvalidArgumentException $e) {
    http_response_code(400);
    header('Content-Type: text/plain; charset=UTF-8');
    echo $e->getMessage();
    exit;
} catch (Throwable $e) {
    ApiErrorReporter::logUnexpected($e, 'public/api/sala-audiencias-ata/download.php');
    http_response_code(500);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'Erro ao baixar o arquivo.';
    exit;
}
