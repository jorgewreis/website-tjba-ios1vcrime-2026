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
    'consultacep'          => $PAGES . '/consulta-cep.php',
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
    'medidasprotetivas'     => $PAGES . '/cartorio-medidas-protetivas-list.php',
    'medidasprotetivasedit' => $PAGES . '/cartorio-medidas-protetivas-edit.php',
    'medidasprotetivascreate' => $PAGES . '/cartorio-medidas-protetivas-create.php',
    'medidasprotetivasrules' => $PAGES . '/cartorio-medidas-protetivas-rules.php',
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
    'usuarioslist'          => $PAGES . '/usuarios-list.php',
    'usuarioscreate'        => $PAGES . '/usuarios-create.php',
    'usuariosedit'          => $PAGES . '/usuarios-edit.php',
    'feriadoslist'          => $PAGES . '/feriados-list.php',
    'feriadoscreate'        => $PAGES . '/feriados-create.php',
    'feriadosedit'          => $PAGES . '/feriados-edit.php',
    'configuracoes'         => $PAGES . '/configuracoes.php',
    'documentos'            => $PAGES . '/documentos.php',
    'alterardados'          => $PAGES . '/alterar-dados-pessoais.php',
    'consultapartes'        => $PAGES . '/consulta-partes-list.php',
    'consultapartescreate'  => $PAGES . '/consulta-partes-create.php',
    'consultapartesedit'    => $PAGES . '/consulta-partes-edit.php',
    'consultaprocessos'     => $PAGES . '/consulta-processos-list.php',
    'consultaprocessosedit' => $PAGES . '/consulta-processos-edit.php',
    'instituicoespublicas'  => $PAGES . '/instituicoes-publicas-list.php',
    'instituicoespublicascreate' => $PAGES . '/instituicoes-publicas-create.php',
    'instituicoespublicasedit'   => $PAGES . '/instituicoes-publicas-edit.php',
    'processospartescreate'     => $PAGES . '/processos-partes-create.php',
    'processosparteslist'       => $PAGES . '/processos-partes-list.php',
    'processospartesedit'      => $PAGES . '/processos-partes-edit.php',
    'partesinstituicoeslist'    => $PAGES . '/partes-instituicoes-list.php',
    'partesinstituicoescreate'  => $PAGES . '/partes-instituicoes-create.php',
    'partesinstituicoesedit'    => $PAGES . '/partes-instituicoes-edit.php',

    '401'=> $ERRORS . '/401.php',
    '403'=> $ERRORS . '/403.php',
    '404'=> $ERRORS . '/404.php',
    '500'=> $ERRORS . '/500.php',
    '503'=> $ERRORS . '/503.php'
];


// Mapeia páginas para suas seções, usadas no controle de acesso.
$page_to_section = [
    'dashboard'         => 'All',
    'consultacep'       => 'Consultas',
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
    'medidasprotetivasedit' => 'Cartório',
    'medidasprotetivascreate' => 'Cartório',
    'medidasprotetivasrules' => 'Cartório',
    'oficioscartorio'   => 'Cartório',
    'oficiosgabinete'   => 'Gabinete',
    'produtividade'     => 'Cartório',
    'reuspresos'        => 'Gabinete',
    'alterardados'      => 'All',
    'consultaparte'     => 'Consultas',
    'consultapartes'    => 'Consultas',
    'consultapartescreate'  => 'Cartório',
    'consultapartesedit'    => 'Cartório',
    'consultaprocessos'     => 'Cartório',
    'consultaprocessosedit' => 'Cartório',
    'instituicoespublicas'  => 'Cartório',
    'instituicoespublicascreate' => 'Cartório',
    'instituicoespublicasedit'   => 'Cartório',
    'processospartescreate'     => 'Cartório',
    'processosparteslist'       => 'Cartório',
    'processospartesedit'       => 'Cartório',
    'partesinstituicoeslist'    => 'Cartório',
    'partesinstituicoescreate'  => 'Cartório',
    'partesinstituicoesedit'    => 'Cartório',
    'usuarios'                  => 'Gestão',
    'usuarioslist'              => 'Gestão',
    'usuarioscreate'            => 'Gestão',
    'usuariosedit'              => 'Gestão',
    'feriadoslist'              => 'All',
    'feriadoscreate'            => 'Gestão',
    'feriadosedit'              => 'Gestão',
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

    // Perfis por setor: CARTORIO, AUDIENCIA, GABINETE
    // Perfis por cargo: CONSULTOR, ESTUDANTE, ESTAGIARIO, SERVIDOR, ADMIN, CHEFIA

    // Mapeamento de seções para perfis permitidos.
    $sectionMap = [
        'All' => ['CONSULTOR', 'ESTUDANTE', 'ESTAGIARIO', 'SERVIDOR', 'ADMIN', 'CHEFIA'],
        'Cartório' => ['CARTORIO', 'ESTAGIARIO', 'SERVIDOR', 'ADMIN', 'CHEFIA'],
        'Gabinete' => ['GABINETE', 'SERVIDOR', 'ADMIN', 'CHEFIA'],
        'Sala de Audiências' => ['AUDIENCIA', 'ESTAGIARIO', 'SERVIDOR', 'ADMIN', 'CHEFIA'],
        'Consultas' => ['CONSULTOR', 'ESTAGIARIO', 'ESTUDANTE', 'CARTORIO', 'AUDIENCIA', 'GABINETE', 'ADMIN', 'SERVIDOR', 'CHEFIA'],
        'Gestão' => ['ADMIN', 'CHEFIA']
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
 * Retorna seções permitidas para estagiário a partir dos setores cadastrados.
 *
 * @param PDO $connection
 * @param string $userId
 * @return array<int, string>
 */
function getEstagiarioAllowedSections(PDO $connection, string $userId): array
{
    $sections = ['Perfil'];
    $setoresNormalizados = [];

    $stmt = $connection->prepare("
        SELECT s.nome
        FROM usuario_setor us
        INNER JOIN setores s ON s.id_setor = us.setor
        WHERE us.usuario = ?
    ");
    $stmt->execute([$userId]);
    $setores = $stmt->fetchAll(PDO::FETCH_COLUMN);

    if (empty($setores)) {
        $stFallback = $connection->prepare("SELECT instituicao FROM usuarios WHERE id_usuario = ? LIMIT 1");
        $stFallback->execute([$userId]);
        $fallback = trim((string) $stFallback->fetchColumn());
        if ($fallback !== '' && $fallback !== '-') {
            $setores = [$fallback];
        }
    }

    foreach ($setores as $setor) {
        $txt = mb_strtolower(trim((string) $setor), 'UTF-8');
        if ($txt !== '') {
            $setoresNormalizados[] = $txt;
        }
    }

    foreach ($setoresNormalizados as $setor) {
        if (strpos($setor, 'cart') !== false) {
            $sections[] = 'Cartório';
        }
        if (strpos($setor, 'gab') !== false) {
            $sections[] = 'Gabinete';
        }
        if (strpos($setor, 'audi') !== false) {
            $sections[] = 'Sala de Audiências';
        }
    }

    $sections[] = 'Consultas';

    return array_values(array_unique($sections));
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
$connection = Conection::getInstance()->getConnection();
$user = null;
$userPerfils = [];
$estagiarioAllowedSections = [];
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

    if (in_array('ESTAGIARIO', $userPerfils, true) && !in_array('ESTUDANTE', $userPerfils, true)) {
        $estagiarioAllowedSections = getEstagiarioAllowedSections($connection, $userId);
    }

    $requiredSection = $page_to_section[$page] ?? null;

    if (!empty($estagiarioAllowedSections) && $requiredSection !== null) {
        $sectionAllowed = in_array($requiredSection, $estagiarioAllowedSections, true);
        // Páginas com seção "All" (dashboard, dados pessoais): liberar só o necessário, sem abrir feriados etc.
        if (!$sectionAllowed && $requiredSection === 'All') {
            $estagiarioPaginasAll = ['dashboard', 'alterardados'];
            $sectionAllowed = in_array($page, $estagiarioPaginasAll, true);
        }
        if (!$sectionAllowed) {
            $validationStatus = 403;
            $validationMessage = 'Acesso negado. Seção não permitida para o setor do estagiário.';
            break;
        }
    }

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
