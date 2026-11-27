<?php
declare(strict_types=1);

$base = dirname(__DIR__, 3);
require_once $base . '/app/helpers/ApiAccessGuard.php';
require_once $base . '/app/helpers/ProcessoSetorAuthorization.php';
require_once $base . '/app/helpers/Conection.php';
require_once $base . '/app/helpers/AuditLoggerHelper.php';
require_once $base . '/app/access/ProfileNormalizer.php';
require_once $base . '/app/repositories/ProcessoNivelSigiloRepository.php';

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');
ApiErrorReporter::register('public/api/processos/nivel-sigilo.php');
ApiAccessGuard::requireAccess(['consultaprocessosedit'], true);

$responder = static function (int $status, array $dados): void {
    http_response_code($status);
    echo json_encode($dados, JSON_UNESCAPED_UNICODE);
};
$db = null;
try {
    $actor = (string) ($_SESSION['id_usuario'] ?? '');
    if (!in_array('ADMIN', ProfileNormalizer::normalize(UsuarioPerfisAutorizacao::perfisDoUsuario($actor)), true)) {
        $responder(403, ['error' => 'Apenas a administração pode registrar o nível.']);
        return;
    }
    $payload = json_decode(file_get_contents('php://input') ?: '', true);
    $id = $payload['processo_id'] ?? null;
    $nivel = ProcessoNivelSigilo::classificado($payload['nivel_sigilo'] ?? null);
    if (!is_string($id) || trim($id) === '' || strlen($id) > 80 || $nivel === null) {
        $responder(400, ['error' => 'Informe um nível de sigilo válido.']);
        return;
    }
    $db = Conection::getInstance()->getConnection();
    $repo = new ProcessoNivelSigiloRepository($db);
    if (!$repo->disponivel()) {
        $responder(409, ['error' => 'Registro de nível ainda indisponível.']);
        return;
    }
    if (!$db->beginTransaction()) throw new RuntimeException('Transação indisponível.');
    if (!ProcessoSetorAuthorization::canModifyProcess($db, $actor, $id)) {
        $db->rollBack();
        $responder(403, ['error' => 'Acesso negado ao processo.']);
        return;
    }
    $stmt = $db->prepare('SELECT sigiloso FROM processos WHERE id = :id');
    if (!$stmt->execute([':id' => $id])) throw new RuntimeException('Processo indisponível.');
    $sigiloso = (bool) $stmt->fetchColumn();
    $stmt->closeCursor();
    if (!$sigiloso) {
        $db->rollBack();
        $responder(409, ['error' => 'Só é possível registrar o nível de um processo já marcado como sigiloso.']);
        return;
    }
    $alterado = $repo->registrar($id, $nivel);
    if ($alterado) {
        AuditLogger::log($db, $actor, 'processo_nivel_sigilo_registrado', ['processo_id' => $id]);
    }
    if (!$db->commit()) throw new RuntimeException('Atualização não confirmada.');
    $responder(200, ['success' => true, 'alterado' => $alterado]);
} catch (Throwable $erro) {
    if ($db !== null && $db->inTransaction()) $db->rollBack();
    ApiErrorReporter::logUnexpected($erro, 'public/api/processos/nivel-sigilo.php');
    $responder(500, ['error' => 'Não foi possível registrar o nível.']);
}
