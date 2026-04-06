<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/security.php';
require_once __DIR__ . '/../app/services/PasswordResetService.php';

header('Content-Type: application/json; charset=utf-8');

try {
    $body = json_decode(file_get_contents('php://input'), true);

    if (!is_array($body)) {
        echo json_encode([
            'success'   => false,
            'emailSent' => false,
            'message'   => 'Requisição inválida.'
        ]);
        exit;
    }

    $cpf   = (string)($body['cpf'] ?? '');
    $email = (string)($body['email'] ?? '');
    $csrf  = $body['csrf_token'] ?? null;

    if (!Security::verifyCSRFToken(is_string($csrf) ? $csrf : null)) {
        http_response_code(419);
        echo json_encode([
            'success'   => false,
            'emailSent' => false,
            'message'   => 'Sessão inválida. Recarregue a página e tente novamente.',
        ]);
        exit;
    }

    $service = new PasswordResetService();
    $result  = $service->process($cpf, $email);

    echo json_encode([
        'success'   => $result['success'],
        'emailSent' => $result['emailSent'],
        'message'   => $result['message']
    ]);
    exit;

} catch (Throwable $e) {
    error_log('envia_mail.php erro: ' . $e->getMessage());

    http_response_code(500);
    echo json_encode([
        'success'   => false,
        'emailSent' => false,
        'message'   => 'Erro interno ao processar solicitação. Tente novamente mais tarde.'
    ]);
    exit;
}
