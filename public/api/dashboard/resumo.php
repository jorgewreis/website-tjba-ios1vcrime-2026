<?php
declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/config/config.php';
require_once dirname(__DIR__, 3) . '/config/security.php';
require_once dirname(__DIR__, 3) . '/app/helpers/bootstrap.php';
require_once dirname(__DIR__, 3) . '/app/helpers/Conection.php';
require_once dirname(__DIR__, 3) . '/app/helpers/UserAccessHelper.php';
require_once dirname(__DIR__, 3) . '/app/models/Usuario.php';
require_once dirname(__DIR__, 3) . '/app/helpers/UsuarioPerfisAutorizacao.php';
require_once dirname(__DIR__, 3) . '/app/services/DashboardResumoService.php';

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
if (strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET')) !== 'GET') { http_response_code(405); header('Allow: GET'); echo json_encode(['error'=>'Método não permitido.'],JSON_UNESCAPED_UNICODE); exit; }
Security::requireAuthentication();
$userId=trim((string)($_SESSION['id_usuario']??''));
if ($userId==='') { http_response_code(401); echo json_encode(['error'=>'Autenticação necessária.']); exit; }
try {
    $profiles=UsuarioPerfisAutorizacao::perfisDoUsuario($userId);
    if (!is_array($profiles) || UserAccessHelper::isUsuarioExterno($profiles)) { http_response_code(403); echo json_encode(['error'=>'Acesso restrito ao dashboard interno.'],JSON_UNESCAPED_UNICODE); exit; }
    $areas=DashboardResumoService::areasAutorizadas($profiles);
    if (!$areas) { http_response_code(403); echo json_encode(['error'=>'Perfil sem setor operacional autorizado.'],JSON_UNESCAPED_UNICODE); exit; }
    $selected=isset($_GET['setor']) ? strtoupper(trim((string)$_GET['setor'])) : null;
    $result=(new DashboardResumoService(Conection::getInstance()->getConnection()))->resumo($userId,$areas,$selected);
    echo json_encode($result,JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE);
} catch (InvalidArgumentException $e) { http_response_code(403); echo json_encode(['error'=>$e->getMessage()],JSON_UNESCAPED_UNICODE); }
catch (Throwable $e) { error_log('dashboard resumo: '.$e->getMessage()); http_response_code(500); echo json_encode(['error'=>'Não foi possível carregar os indicadores.'],JSON_UNESCAPED_UNICODE); }
