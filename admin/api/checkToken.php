<?php
require_once __DIR__ . '/../bd/Usuario.php';

header('Content-Type: application/json');

$token = $_GET['token'] ?? '';

if (empty($token)) {
    echo json_encode(['valid' => false, 'message' => 'Token não fornecido.']);
    exit;
}

$usuario = new Usuario();
$user = $usuario->verifyToken($token);

if ($user) {
    echo json_encode([
        'valid' => true,
        'user' => [
            'id' => $user['id'],
            'nome' => $user['nome'],
            'perfil' => $user['perfil']
        ]
    ]);
} else {
    echo json_encode(['valid' => false, 'message' => 'Token inválido ou expirado.']);
}
