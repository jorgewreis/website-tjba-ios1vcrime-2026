<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../app/services/PasswordResetService.php';

header('Content-Type: application/json; charset=utf-8');

try {
    $body = json_decode(file_get_contents('php://input'), true);

    if (!is_array($body)) {
        throw new RuntimeException('JSON inválido');
    }

    $cpf   = (string)($body['cpf'] ?? '');
    $email = (string)($body['email'] ?? '');

    $service = new PasswordResetService();
    $service->process($cpf, $email);

    echo json_encode([
        'success' => true,
        'message' => 'Se os dados estiverem corretos, você receberá instruções por e-mail.'
    ]);
    exit;

} catch (Throwable $e) {
    error_log('envia_mail.php erro: ' . $e->getMessage());

    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Erro interno ao processar solicitação.'
    ]);
    exit;
}
