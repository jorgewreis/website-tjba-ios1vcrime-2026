<?php
// Dependências base do sistema (configuração, segurança e acesso a dados).
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/security.php';
require_once __DIR__ . '/../app/helpers/bootstrap.php';
require_once __DIR__ . '/../app/helpers/UserAccessHelper.php';
require_once __DIR__ . '/../app/models/Usuario.php';
require_once __DIR__ . '/../app/helpers/Conection.php';
require_once __DIR__ . '/../app/access/AccessEvaluator.php';
require_once __DIR__ . '/../app/access/AccessPolicyVersion.php';
require_once __DIR__ . '/../app/helpers/UsuarioPerfisAutorizacao.php';

// Página solicitada (padrão: dashboard).
$page = $_GET['page'] ?? 'dashboard';
$page = is_string($page) ? trim($page) : 'dashboard';

Security::requireAuthentication();

// Extrai o ID do usuário do retorno do validarToken.
$userId = null;

// Diretórios base para resolução de views internas.
$PAGES = realpath(__DIR__ . '/../app/views/internal');
$ERRORS = $PAGES . '/errors';

// Lista de páginas permitidas e seus caminhos.
$allowed_pages = [
    'dashboard'             => $PAGES . '/dashboard.php',
    'dashboardexterno'      => $PAGES . '/dashboard-externo.php',
    'cejusc'                => $PAGES . '/cartorio-cejusc-list.php',
    'cejusccreate'          => $PAGES . '/cartorio-cejusc-create.php',
    'cejuscedit'            => $PAGES . '/cartorio-cejusc-edit.php',
    'editais'               => $PAGES . '/cartorio-editais-list.php',
    'editaisedit'           => $PAGES . '/cartorio-editais-edit.php',
    'editaiscreate'         => $PAGES . '/cartorio-editais-create.php',
    'editaisrules'          => $PAGES . '/cartorio-editais-rules.php',
    'guias'                 => $PAGES . '/cartorio-guias-list.php',
    'guiasedit'             => $PAGES . '/cartorio-guias-edit.php',
    'guiascreate'           => $PAGES . '/cartorio-guias-create.php',
    'guiasrules'             => $PAGES . '/cartorio-guias-rules.php',
    'oficioscartorio'       => $PAGES . '/cartorio-oficios-list.php',
    'oficioscartorioedit'   => $PAGES . '/cartorio-oficios-edit.php',
    'oficiosrules'          => $PAGES . '/cartorio-oficios-rules.php',
    'oficiosgabinete'       => $PAGES . '/gabinete-oficios-list.php',
    'oficiosgabineteedit'   => $PAGES . '/gabinete-oficios-edit.php',
    'monitoramentogabinete' => $PAGES . '/gabinete-monitoramento-eletronico-list.php',
    'monitoramentogabinetecreate' => $PAGES . '/gabinete-monitoramento-eletronico-create.php',
    'monitoramentogabineteedit'   => $PAGES . '/gabinete-monitoramento-eletronico-edit.php',
    'salapauta'             => $PAGES . '/sala-audiencias-pauta-list.php',
    'estudapauta'           => $PAGES . '/sala-audiencias-pauta-list-estudantes.php',
    'salapautacreate'       => $PAGES . '/sala-audiencias-pauta-create.php',
    'salapautaedit'         => $PAGES . '/sala-audiencias-pauta-edit.php',
    'salapautaparteedit'    => $PAGES . '/sala-audiencias-pauta-parte-edit.php',
    'salaata'               => $PAGES . '/sala-audiencias-atas-list.php',
    'salapainel'            => $PAGES . '/sala-audiencias-pauta-list.php',
    'salapaineldia'         => $PAGES . '/sala-audiencias-painel-dia.php',
    'estudapainel'          => $PAGES . '/sala-audiencias-painel-estudantes.php',
    'reuspresos'            => $PAGES . '/gabinete-reus-presos-list.php',
    'reuspresoscreate'      => $PAGES . '/gabinete-reus-presos-create.php',
    'reuspresosedit'        => $PAGES . '/gabinete-reus-presos-edit.php',
    'cartasexpedidas'       => $PAGES . '/cartorio-cartas-expedidas-list.php',
    'cartasexpedidasedit'   => $PAGES . '/cartorio-cartas-expedidas-edit.php',
    'cartasexpedidascreate' => $PAGES . '/cartorio-cartas-expedidas-create.php',
    'cartasexpedidasrules'  => $PAGES . '/cartorio-cartas-expedidas-rules.php',
    'cartasrecebidas'       => $PAGES . '/cartorio-cartas-recebidas-list.php',
    'cartasrecebidasedit'   => $PAGES . '/cartorio-cartas-recebidas-edit.php',
    'cartasrecebidascreate' => $PAGES . '/cartorio-cartas-recebidas-create.php',
    'cartasrecebidasrules'  => $PAGES . '/cartorio-cartas-recebidas-rules.php',
    'notificacoes'          => $PAGES . '/notificacoes.php',
    'usuarioslist'          => $PAGES . '/usuarios-list.php',
    'usuarioscreate'        => $PAGES . '/usuarios-create.php',
    'usuariosedit'          => $PAGES . '/usuarios-edit.php',
    'feriadoslist'          => $PAGES . '/feriados-list.php',
    'feriadoscreate'        => $PAGES . '/feriados-create.php',
    'feriadosedit'          => $PAGES . '/feriados-edit.php',
    'configuracoes'         => $PAGES . '/configuracoes.php',
    'skills'                => $PAGES . '/skills.php',
    'skillspdfmarkdown'     => $PAGES . '/skills-pdf-markdown.php',
    'documentos'            => $PAGES . '/documentos.php',
    'alterardados'          => $PAGES . '/alterar-dados-pessoais.php',
    'consultapartes'        => $PAGES . '/consulta-partes-list.php',
    'consultapartescreate'  => $PAGES . '/consulta-partes-create.php',
    'consultapartesedit'    => $PAGES . '/consulta-partes-edit.php',
    'consultaprocessos'     => $PAGES . '/consulta-processos-list.php',
    'consultaprocessosedit' => $PAGES . '/consulta-processos-edit.php',
    'consultapjepublicoteste' => $PAGES . '/consulta-pje-publico-teste.php',
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

/**
 * Responde com página de erro correspondente ao status HTTP.
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

    $user = $usuario->validarToken($_SESSION['token']);

    if (!$user) {
        Security::clearAuthenticatedSession();
        $validationStatus = 401;
        $validationMessage = 'Sessão expirada';
        break;
    }

    if (is_array($user)) {
        $userId = $user['id_usuario'] ?? ($user['id'] ?? null);
    } elseif (is_object($user)) {
        $userId = $user->id_usuario ?? ($user->id ?? null);
    } else {
        $userId = null;
    }

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

    if ($page === 'consultapjepublicoteste') {
        $allowPjePublicTest = strtolower((string) envValue('PJE_PUBLIC_TEST_PAGE_ENABLED', '0'));
        if (!in_array($allowPjePublicTest, ['1', 'true', 'yes', 'on'], true)) {
            $validationStatus = 404;
            $validationMessage = 'Página não encontrada';
            break;
        }
    }

    $userPerfils = UsuarioPerfisAutorizacao::perfisDoUsuario($userId);

    $isExterno = UserAccessHelper::isUsuarioExterno($userPerfils);
    if ($isExterno && $page === 'dashboard') {
        header('Location: /frame.php?page=dashboardexterno', true, 302);
        exit;
    }
    if (!$isExterno && $page === 'dashboardexterno') {
        header('Location: /frame.php?page=dashboard', true, 302);
        exit;
    }

    if (!AccessEvaluator::canAccessPage($page, $userPerfils)) {
        error_log(
            'Acesso negado v' . AccessPolicyVersion::VERSION
            . ': userId=' . $userId . ' perfis=' . implode(',', $userPerfils)
            . ' page=' . $page
        );
        $validationStatus = 403;
        $validationMessage = 'Acesso negado. Seu perfil não tem permissão para acessar este conteúdo.';
        break;
    }

    $pagePath = $allowed_pages[$page];
    $resolved = realpath($pagePath);

    if ($resolved === false || !is_file($resolved)) {
        error_log('Arquivo de página não encontrado. page=' . $page . ' path=' . $pagePath);
        $validationStatus = 500;
        $validationMessage = 'Erro interno: página não localizada no servidor.';
        break;
    }
} while (false);

if ($validationStatus !== null) {
    respondWithError($validationStatus, $validationMessage, $ERRORS);
}

require $resolved;
