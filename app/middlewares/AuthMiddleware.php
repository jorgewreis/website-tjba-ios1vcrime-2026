<?php
require_once __DIR__ . '/../../vendor/autoload.php'; // Se usar biblioteca JWT
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

function verificarAcesso() {
    $headers = apache_request_headers();

    if (!isset($headers['Authorization'])) {
        http_response_code(401);
        echo json_encode(["status" => "error", "message" => "Token não fornecido."]);
        exit;
    }

    $token = str_replace('Bearer ', '', $headers['Authorization']);
    $key = getenv('JWT_SECRET');

    try {
        $decoded = JWT::decode($token, new Key($key, 'HS256'));
        return $decoded->data; // Retorna os dados do usuário (ID, Setor, Perfil)
    } catch (Exception $e) {
        http_response_code(401);
        echo json_encode(["status" => "error", "message" => "Token inválido ou expirado."]);
        exit;
    }
}
