<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/security.php';
require_once __DIR__ . '/../bd/Usuario.php';

if (empty($_SESSION['id_usuario']) || empty($_SESSION['token'])) {
    http_response_code(401);
    die('Acesso negado');
}

$usuario = new Usuario();
$user = $usuario->validarToken($_SESSION['token']);
if (!$user) {
    $_SESSION = [];
    session_destroy();
    http_response_code(401);
    die('Sessão expirada');
}

$perfil = Security::getUserProfile() ?? '';
$page   = $_GET['page'] ?? 'dashboard';

$allowed_pages = [
    'dashboard'         => __DIR__ . '/../admin/dashboard.php',
    'cejusc'            => __DIR__ . '/../admin/cadastros/cejusc.php',
    'editais'           => __DIR__ . '/../admin/cadastros/editais.php',
    'guias'             => __DIR__ . '/../admin/cadastros/guias.php',
    'medidasprotetivas' => __DIR__ . '/../admin/cadastros/medidasprotetivas.php',
    'oficioscartorio'   => __DIR__ . '/../admin/cadastros/oficioscartorio.php',
    'oficiosgabinete'   => __DIR__ . '/../admin/cadastros/oficiosgabinete.php',
    'cartasexpedidas'  => __DIR__ . '/../admin/cadastros/cartasexpedidas.php',
    'cartasrecebidas'  => __DIR__ . '/../admin/cadastros/cartasrecebidas.php',
    'produtividade'     => __DIR__ . '/../admin/cadastros/produtividade.php',
];

// Páginas exclusivas de ADMIN (adicione aqui quando criar no whitelist)
$admin_only = [''];

if (in_array($page, $admin_only, true) && $perfil !== 'ADMIN') {
    http_response_code(403);
    die('Acesso negado');
}

// 4) Carrega a página dentro do iframe
if (isset($allowed_pages[$page])) {
    require $allowed_pages[$page];
} else {
    http_response_code(404);
    echo "Página não encontrada";
}
