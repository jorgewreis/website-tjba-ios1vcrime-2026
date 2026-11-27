<?php
declare(strict_types=1);

$base = dirname(__DIR__, 3);
require_once $base . '/app/helpers/ApiAccessGuard.php';
require_once $base . '/app/helpers/Conection.php';
require_once $base . '/app/services/ProcessoSigiloCadastroService.php';

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');
ApiErrorReporter::register('public/api/processos/nivel-sigilo.php');
ApiAccessGuard::requireAccess(['consultaprocessosedit'], true);

$responder = static function (int $status, array $dados): void {
    http_response_code($status);
    echo json_encode($dados, JSON_UNESCAPED_UNICODE);
};
try {
    $payload = json_decode(file_get_contents('php://input') ?: '', true);
    $id = $payload['processo_id'] ?? null;
    if (!is_string($id)) throw new ProcessoSigiloCadastroErro(400, 'Identificador de processo inválido.');
    $ator = trim((string) ($_SESSION['id_usuario'] ?? ''));
    $service = new ProcessoSigiloCadastroService(Conection::getInstance()->getConnection());
    $responder(200, $service->alterar(
        $id, $payload['nivel_sigilo'] ?? null, $payload['confirmei_pje'] ?? false, $ator,
        UsuarioPerfisAutorizacao::perfisDoUsuario($ator), (string) ($_SERVER['REMOTE_ADDR'] ?? '')
    ));
} catch (ProcessoSigiloCadastroErro $erro) {
    $responder($erro->http, ['error' => $erro->getMessage()]);
} catch (Throwable $erro) {
    ApiErrorReporter::logUnexpected($erro, 'public/api/processos/nivel-sigilo.php');
    $responder(500, ['error' => 'Não foi possível registrar o nível. Nenhuma alteração foi confirmada.']);
}
