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
    'dashboard'             => $PAGES . '/dashboard.php',
    'cejusc'                => $PAGES . '/cartorio-cejusc-list.php',
    'cejuscedit'            => $PAGES . '/cartorio-cejusc-edit.php',
    'cejusccreate'          => $PAGES . '/cartorio-cejusc-create.php',
    'cejuscrules'           => $PAGES . '/cartorio-cejusc-rules.php',
    'editais'               => $PAGES . '/cartorio-editais-list.php',
    'editaisedit'           => $PAGES . '/cartorio-editais-edit.php',
    'editaiscreate'         => $PAGES . '/cartorio-editais-create.php',
    'editaisrules'          => $PAGES . '/cartorio-editais-rules.php',
    'guias'                 => $PAGES . '/cartorio-guias-list.php',
    'guiasedit'             => $PAGES . '/cartorio-guias-edit.php',
    'guiascreate'           => $PAGES . '/cartorio-guias-create.php',
    'guiasrules'             => $PAGES . '/cartorio-guias-rules.php',
    'medidasprotetivas'     => $PAGES . '/medidas_protetivas.php',
    'oficioscartorio'       => $PAGES . '/oficios_cartorio.php',
    'oficiosgabinete'       => $PAGES . '/oficios_gabinete.php',
    'cartasexpedidas'       => $PAGES . '/cartorio-cartas-expedidas-list.php',
    'cartasexpedidasedit'   => $PAGES . '/cartorio-cartas-expedidas-edit.php',
    'cartasexpedidascreate' => $PAGES . '/cartorio-cartas-expedidas-create.php',    
    'cartasexpedidasrules'  => $PAGES . '/cartorio-cartas-expedidas-rules.php',
    'cartasrecebidas'       => $PAGES . '/cartorio-cartas-recebidas-list.php',
    'cartasrecebidasedit'   => $PAGES . '/cartorio-cartas-recebidas-edit.php',
    'cartasrecebidascreate' => $PAGES . '/cartorio-cartas-recebidas-create.php',
    'cartasrecebidasrules'  => $PAGES . '/cartorio-cartas-recebidas-rules.php',
    'produtividade'         => $PAGES . '/produtividade.php',
    'notificacoes'          => $PAGES . '/notificacoes.php',
    'usuarios'              => $PAGES . '/usuarios.php',
    'configuracoes'         => $PAGES . '/configuracoes.php',
    'documentos'            => $PAGES . '/documentos.php',
    'alterardados'          => $PAGES . '/alterar-dados-pessoais.php',

    '401'=> $ERRORS . '/401.php',
    '403'=> $ERRORS . '/403.php',
    '404'=> $ERRORS . '/404.php',
    '500'=> $ERRORS . '/500.php',
    '503'=> $ERRORS . '/503.php'
];


// Mapeia páginas para suas seções, usadas no controle de acesso.
$page_to_section = [
    'dashboard'         => 'All',
    'cartasexpedidas'   => 'Cartório',
    'cartasexpedidasedit'   => 'Cartório',
    'cartasexpedidascreate' => 'Cartório',
    'cartasexpedidasrules'  => 'Cartório',
    'cartasrecebidas'       => 'Cartório',
    'cartasrecebidasedit'   => 'Cartório',
    'cartasrecebidascreate' => 'Cartório',
    'cartasrecebidasrules'  => 'Cartório',
    'cejusc'            => 'Cartório',
    'cejuscedit'        => 'Cartório',
    'cejusccreate'      => 'Cartório',
    'cejuscrules'       => 'Cartório',
    'editais'           => 'Cartório',
    'editaisedit'       => 'Cartório',
    'editaiscreate'     => 'Cartório',
    'editaisrules'      => 'Cartório',
    'guias'             => 'Cartório',
    'guiasedit'         => 'Cartório',
    'guiascreate'       => 'Cartório',
    'guiasrules'        => 'Cartório',
    'medidasprotetivas' => 'Cartório',
    'oficioscartorio'   => 'Cartório',
    'oficiosgabinete'   => 'Gabinete',
    'produtividade'     => 'Cartório',
    'reuspresos'        => 'Gabinete',
    'alterardados'      => 'Perfil'
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
    // Estudante não tem acesso à área interna, exceto à seção "Perfil" (alterar dados pessoais / alterar senha).
    $perfilSectionsAllowedForEstudante = ['Perfil'];
    if (in_array('ESTUDANTE', $userPerfils, true)) {
        if ($requiredSection === null || !in_array($requiredSection, $perfilSectionsAllowedForEstudante, true)) {
            return false;
        }
    }

    if ($requiredSection === null) {
        return true;
    }

    // Mapeamento de seções para perfis permitidos.
    $sectionMap = [
        'All' => ['CONSULTOR', 'CARTORIO', 'AUDIENCIA', 'GABINETE', 'ADMIN', 'SERVIDOR', 'CHEFIA'],
        'Perfil' => ['ESTUDANTE', 'CONSULTOR', 'CARTORIO', 'AUDIENCIA', 'GABINETE', 'ADMIN', 'SERVIDOR', 'CHEFIA', 'DIRETORA', 'MAGISTRADA'],
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
