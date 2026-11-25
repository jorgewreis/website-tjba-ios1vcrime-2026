<?php
declare(strict_types=1);


require_once dirname(__DIR__, 3) . '/app/helpers/ApiErrorReporter.php';
ApiErrorReporter::register('public/api/cartorio-cartas/reiniciar.php');
$basePath=dirname(__DIR__,3);
require_once $basePath.'/config/config.php';
require_once $basePath.'/config/security.php';
require_once $basePath.'/app/helpers/BootstrapHelper.php';
require_once $basePath.'/app/helpers/UsuarioPerfisAutorizacao.php';
require_once $basePath.'/app/services/CartasReinicioService.php';
header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');
if(strtoupper((string)($_SERVER['REQUEST_METHOD']??''))!=='POST'){
    http_response_code(405);header('Allow: POST');echo json_encode(['error'=>'Método não permitido.'],JSON_UNESCAPED_UNICODE);exit;
}
Security::requireAuthentication();
$userId=trim((string)($_SESSION['id_usuario']??''));
$profiles=UsuarioPerfisAutorizacao::perfisDoUsuario($userId);
$allowed=['ADMIN','CHEFIA','DIRETORA','MAGISTRADA','SERVIDOR-CARTORIO','ESTAGIARIO-CARTORIO'];
if($userId===''||!array_intersect($profiles,$allowed)||!bootstrapRequireTrustedPostRequest()){
    http_response_code(403);echo json_encode(['error'=>'Acesso não autorizado.'],JSON_UNESCAPED_UNICODE);exit;
}
$payload=json_decode(file_get_contents('php://input')?:'',true);
if(!is_array($payload))$payload=$_POST;
try {
    CartasReinicioService::reiniciar(Conection::getInstance()->getConnection(),trim((string)($payload['modulo']??'')),trim((string)($payload['carta_id']??'')),$userId);
    echo json_encode(['success'=>true],JSON_UNESCAPED_UNICODE);
} catch(InvalidArgumentException $e){http_response_code(400);echo json_encode(['error'=>$e->getMessage()],JSON_UNESCAPED_UNICODE);}
catch(Throwable $e){error_log('reiniciar carta: '.ApiErrorReporter::genericMessage($e, 'public/api/cartorio-cartas/reiniciar.php'));http_response_code(500);echo json_encode(['error'=>'Não foi possível reiniciar a carta.'],JSON_UNESCAPED_UNICODE);}
