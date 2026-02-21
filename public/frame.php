<?php
// Dependências base do sistema (configuração, segurança e acesso a dados).
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/security.php';
require_once __DIR__ . '/../app/helpers/bootstrap.php';
require_once __DIR__ . '/../app/models/Usuario.php';
require_once __DIR__ . '/../app/helpers/Conection.php';

// Garante que a sessão esteja ativa antes de acessar dados do usuário.
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// Página solicitada (padrão: dashboard).
$page = $_GET['page'] ?? 'dashboard';

// Extrai o ID do usuário do retorno do validarToken.
$userId = null;

// Diretórios base para resolução de views internas.
$PAGES = realpath(__DIR__ . '/../app/views/internal');
$ERRORS = $PAGES . '/errors';

// Lista de páginas permitidas e seus caminhos.
$allowed_pages = [
    'dashboard'         => $PAGES . '/dashboard.php',
    'cejusc'            => $PAGES . '/cejusc.php',
    'editais'           => $PAGES . '/editais.php',
    'guias'             => $PAGES . '/guias.php',
    'medidasprotetivas' => $PAGES . '/medidas_protetivas.php',
    'oficioscartorio'   => $PAGES . '/oficios_cartorio.php',
    'oficiosgabinete'   => $PAGES . '/oficios_gabinete.php',
    'cartasexpedidas'   => $PAGES . '/cartas_expedidas.php',
    'cartasrecebidas'   => $PAGES . '/cartas_recebidas.php',
    'produtividade'     => $PAGES . '/produtividade.php',
    'notificacoes'      => $PAGES . '/notificacoes.php',
    'usuarios'          => $PAGES . '/usuarios.php',
    'configuracoes'     => $PAGES . '/configuracoes.php',
    'documentos'        => $PAGES . '/documentos.php',

    '401'=> $ERRORS . '/401.php',
    '403'=> $ERRORS . '/403.php',
    '404'=> $ERRORS . '/401.php', // testar - original 404
    '500'=> $ERRORS . '/500.php',
    '503'=> $ERRORS . '/503.php'
];


// Mapeia páginas para suas seções, usadas no controle de acesso.
$page_to_section = [
    'dashboard'         => 'All',
    'cartasexpedidas'   => 'Cartório',
    'cartasrecebidas'   => 'Cartório',
    'cejusc'            => 'Cartório',
    'editais'           => 'Cartório',
    'guias'             => 'Cartório',
    'medidasprotetivas' => 'Cartório',
    'oficioscartorio'   => 'Cartório',
    'oficiosgabinete'   => 'Gabinete',
    'produtividade'     => 'Cartório',
    'reuspresos'        => 'Gabinete' // 'reuspresos' não está em $allowed_pages, será ignorado.
];

/**
 * Verifica se o usuário possui perfil compatível com a seção exigida.
 *
 * @param array  $userPerfils     Array de perfis do usuário logado.
 * @param string|null $requiredSection Seção necessária para acesso.
 * @return bool True se o usuário tem acesso, false caso contrário.
 */
function hasAccessToSection(array $userPerfils, ?string $requiredSection): bool
{
    // De acordo com as memórias do usuário, "Estudante não tem acesso à área interna em nenhuma hipótese."
    // Portanto, se o usuário tiver o perfil 'ESTUDANTE', o acesso é negado imediatamente.
    if (in_array('ESTUDANTE', $userPerfils, true)) {
        return false;
    }

    if ($requiredSection === null) {
        return true; // Se nenhuma seção é requerida, o acesso é permitido (após a verificação de ESTUDANTE).
    }

    // Mapeamento de seções para perfis permitidos.
    $sectionMap = [
        // Removido 'ESTUDANTE' de 'All' para cumprir a restrição.
        'All' => ['CONSULTOR', 'CARTORIO', 'AUDIENCIA', 'GABINETE', 'ADMIN', 'SERVIDOR', 'CHEFIA'],
        'Cartório' => ['CARTORIO', 'AUDIENCIA', 'GABINETE', 'ADMIN', 'SERVIDOR', 'CHEFIA'],
        'Gabinete' => ['GABINETE', 'ADMIN', 'CHEFIA'],
        'Sala de Audiências' => ['AUDIENCIA', 'GABINETE', 'ADMIN', 'CHEFIA'],
        // 'Estudantes' e 'Consultor' podem ser removidos se não houver páginas mapeadas para eles.
        'Estudantes' => ['ADMIN'], // Estudante não tem acesso, mas Admin pode gerenciar.
        'Consultor'  => ['CONSULTOR', 'ADMIN'],
        'Admin' => ['ADMIN'],
        'Servidor' => ['SERVIDOR', 'CHEFIA', 'ADMIN'], // Adicionado para clareza, se houver páginas específicas.
        'Chefia' => ['CHEFIA', 'ADMIN'] // Adicionado para clareza, se houver páginas específicas.
    ];

    $requiredPerfils = $sectionMap[$requiredSection] ?? [];

    // Se algum perfil do usuário está na lista permitida, libera acesso.
    foreach ($userPerfils as $userPerfil) {
        if (in_array($userPerfil, $requiredPerfils, true)) {
            return true;
        }
    }

    return false;
}

/**
 * Responde com página de erro correspondente ao status HTTP.
 *
 * @param int    $status    Código de status HTTP.
 * @param string $message   Mensagem de erro a ser exibida se a página de erro não for encontrada.
 * @param string $errorsDir Caminho para o diretório das páginas de erro.
 */
function respondWithError(int $status, string $message, string $errorsDir): void
{
    http_response_code($status);
    $errorPage = $errorsDir . '/' . $status . '.php';

    if (is_file($errorPage)) {
        require $errorPage;
        exit;
    }

    echo $message;
    exit;
}

// Bloco centralizado de validações para sessão, usuário, página e acesso.
$usuario = new Usuario();
$user = null;
$userPerfils = [];
$pagePath = null;
$resolved = null;
$validationStatus = null;
$validationMessage = '';

do {
    if (empty($_SESSION['token'])) {
        $validationStatus = 401;
        $validationMessage = 'Acesso negado';
        break;
    }

    // O método validarToken deve retornar um objeto ou array consistente com o ID do usuário.
    $user = $usuario->validarToken($_SESSION['token']);

    if (!$user) {
        $_SESSION = [];
        session_destroy();
        $validationStatus = 401;
        $validationMessage = 'Sessão expirada';
        break;
    }

    // Tenta extrair o ID do usuário de forma mais robusta.
    if (is_array($user)) {
        $userId = $user['id_usuario'] ?? ($user['id'] ?? null);
    } elseif (is_object($user)) {
        $userId = $user->id_usuario ?? ($user->id ?? null);
    } else {
        // Se $user não é array nem objeto, pode ser um erro ou um ID direto.
        // Considerar que validarToken deve retornar um formato esperado.
        $userId = null;
    }

    // Fallback para a sessão, mas o ideal é que validarToken seja a fonte primária.
    if ($userId === null) {
        $userId = $_SESSION['id_usuario'] ?? null;
    }

    $userId = is_scalar($userId) ? trim((string)$userId) : '';

    if ($userId === '') {
        $validationStatus = 401;
        $validationMessage = 'Sessão inválida: ID do usuário não encontrado.';
        break;
    }

    if (!preg_match('/^[A-Za-z0-9_-]{1,80}$/', $userId)) {
        error_log('Sessão inválida: userId com formato inesperado: ' . var_export($userId, true));
        $validationStatus = 401;
        $validationMessage = 'Sessão inválida: formato de ID de usuário incorreto.';
        break;
    }

    if (!isset($allowed_pages[$page])) {
        $validationStatus = 404;
        $validationMessage = 'Página não encontrada';
        break;
    }

    $userPerfils = $usuario->listarPerfisUsuario($userId);
    // Garante que $userPerfils é um array, mesmo que listarPerfisUsuario retorne null/false.
    if (!is_array($userPerfils)) {
        $userPerfils = [];
    }

    $requiredSection = $page_to_section[$page] ?? null;

    if (!hasAccessToSection($userPerfils, $requiredSection)) {
        error_log('Acesso negado: userId=' . $userId . ' perfis=' . implode(',', $userPerfils) . ' page=' . $page);
        $validationStatus = 403;
        $validationMessage = 'Acesso negado. Seu perfil não tem permissão para acessar este conteúdo.';
        break;
    }

    $pagePath = $allowed_pages[$page];
    $resolved = realpath($pagePath);

    if ($resolved === false || !is_file($resolved)) {
        // Removida a referência a $BASE, que não estava definida.
        error_log('Arquivo de página não encontrado. page=' . $page . ' path=' . $pagePath);
        $validationStatus = 500;
        $validationMessage = 'Erro interno: página não localizada no servidor.';
        break;
    }
} while (false);

if ($validationStatus !== null) {
    respondWithError($validationStatus, $validationMessage, $ERRORS);
}

// Inclui a página solicitada.
require $resolved;
