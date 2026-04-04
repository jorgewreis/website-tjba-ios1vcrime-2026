<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../config/config.php';
require_once __DIR__ . '/../../../config/security.php';
require_once __DIR__ . '/../../../app/helpers/Conection.php';
require_once __DIR__ . '/../../../app/models/Usuario.php';
require_once __DIR__ . '/../../../app/helpers/UsuarioPerfisAutorizacao.php';
require_once __DIR__ . '/../../../app/helpers/EffectiveAccessProfiles.php';

header('Content-Type: application/json; charset=utf-8');

try {
    if (!Security::isAuthenticated()) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Não autenticado']);
        exit;
    }

    $token = Security::getToken();
    $usuario = new Usuario();
    $user = ($token !== null && $token !== '') ? $usuario->validarToken($token) : null;
    if ($user === null) {
        Security::clearAuthenticatedSession();
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Sessão expirada']);
        exit;
    }

    $userId = Security::getUserId();
    if (!$userId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'ID de usuário inválido']);
        exit;
    }

    $usuario = new Usuario();
    $perfisCadastro = $usuario->listarPerfisUsuario($userId);
    if (!is_array($perfisCadastro)) {
        $perfisCadastro = [];
    }
    $perfis = UsuarioPerfisAutorizacao::perfisDoUsuario($userId);

    http_response_code(200);
    echo json_encode([
        'success' => true,
        'perfis' => array_values($perfis),
        'perfis_cadastro' => array_values($perfisCadastro),
        'admin_suprimido_sessao' => EffectiveAccessProfiles::isAdminSuppressed(),
        'pode_alternar_modo_admin' => EffectiveAccessProfiles::canOfferAdminModeToggle($perfisCadastro),
    ]);
    
} catch (Exception $e) {
    error_log('Erro ao buscar perfis: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro interno do servidor']);
}
