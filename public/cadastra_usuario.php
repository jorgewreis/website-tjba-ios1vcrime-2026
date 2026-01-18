<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/security.php';

header('Content-Type: application/json; charset=utf-8');

/* TODO: Implementar endpoint de cadastro de usuários */

http_response_code(501);
echo json_encode([
    'success' => false,
    'message' => 'Endpoint de cadastro não implementado.'
]);
exit;
