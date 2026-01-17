<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/security.php';
require_once __DIR__ . '/../../bd/Usuario.php';

header('Content-Type: application/json; charset=utf-8');

$token = Security::getToken();

if (empty($token)) {
    http_response_code(401);
    echo json_encode(['valid' => false, 'message' => 'Token não fornecido.']);
    exit;
}

$usuario = new Usuario();
$user = $usuario->validarToken($token);

if ($user) {
    echo json_encode([
        'valid' => true,
        'user' => [
            'id_usuario' => $user['id_usuario'],
            'nome' => $user['nome']
        ]
    ]);
} else {
    http_response_code(401);
    echo json_encode(['valid' => false, 'message' => 'Token inválido ou expirado.']);
}
