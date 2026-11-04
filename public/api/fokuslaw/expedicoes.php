<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=UTF-8');
$base = dirname(__DIR__, 3);
require_once $base . '/config/security.php';
require_once $base . '/app/access/AccessEvaluator.php';
require_once $base . '/app/helpers/UsuarioPerfisAutorizacao.php';
require_once $base . '/app/helpers/BootstrapHelper.php';
require_once $base . '/app/helpers/ApiJsonResponse.php';
require_once $base . '/app/helpers/Conection.php';
require_once $base . '/app/helpers/CnjValidator.php';
require_once $base . '/app/helpers/IdGenerator.php';
require_once $base . '/app/repositories/ProcessosRepository.php';
require_once $base . '/app/services/ProcessoLookupService.php';
require_once $base . '/app/services/ProcessoClasseSyncService.php';
require_once $base . '/app/services/ProcessoAssuntoSyncService.php';
if (!Security::isAuthenticated()) { ApiJsonResponse::send(['error'=>'Sessão expirada.'],401); exit; }
$user = trim((string)($_SESSION['id_usuario'] ?? ''));
if (!AccessEvaluator::canAccessPage('fokusexpedicoes', UsuarioPerfisAutorizacao::perfisDoUsuario($user))) { ApiJsonResponse::send(['error'=>'Acesso negado.'],403); exit; }
$db = Conection::getInstance()->getConnection();
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$action = trim((string)($_GET['action'] ?? 'list'));
try {
 if ($method === 'GET' && $action === 'list') {
   $q='%'.trim((string)($_GET['q']??'')).'%';
   $s=$db->prepare("SELECT e.*, u.nome AS responsavel_nome FROM fokuslaw_expedicoes e LEFT JOIN usuarios u ON u.id_usuario=e.responsavel_id WHERE e.numero_processo LIKE :q OR e.destinatario LIKE :q OR e.tipo_documento LIKE :q ORDER BY e.created_at DESC LIMIT 300"); $s->execute([':q'=>$q]);
   $users=$db->query("SELECT id_usuario, TRIM(CONCAT(COALESCE(nome,''),' ',COALESCE(sobrenome,''))) AS nome FROM usuarios WHERE COALESCE(cadastro_ativo,1)=1 ORDER BY nome")->fetchAll(PDO::FETCH_ASSOC);
   ApiJsonResponse::send(['data'=>$s->fetchAll(PDO::FETCH_ASSOC),'users'=>$users]); exit;
 }
 if ($method !== 'POST' || !bootstrapRequireTrustedPostRequest()) { ApiJsonResponse::send(['error'=>'Requisição inválida.'],403); exit; }
 $p=ApiJsonResponse::readJsonPayload(); $id=trim((string)($p['id']??''));
 if ($action==='create') {
   $digits=CnjValidator::digits20OrThrow((string)($p['numero_processo']??''));
   $cnj=sprintf('%s-%s.%s.%s.%s.%s',substr($digits,0,7),substr($digits,7,2),substr($digits,9,4),substr($digits,13,1),substr($digits,14,2),substr($digits,16,4));
   foreach (['destinatario'=>'Destinatário','tipo_documento'=>'Tipo de documento'] as $k=>$label) if (trim((string)($p[$k]??''))==='') throw new InvalidArgumentException("Informe {$label}.");
   $processRepo=new ProcessosRepository($db); $local=$processRepo->findByNumero($cnj); $processId=$local['id']??null; $sigiloso=(int)($local['sigiloso']??0);
   $lookupError=null;
   if (!$local) {
     try {
       $dj=require $base.'/public/api/datajud/config.php'; $lookup=(new ProcessoLookupService($dj))->lookup($digits,['mni'=>true]); $data=$lookup['data']??[];
       $sigiloso=!empty($data['sigilo_restrito'])?1:0;
       if (!$sigiloso && empty($data['indisponivel_na_base_publica'])) {
         $processId=$processRepo->ensureProcess($cnj);
         $class=$data['classe']??[]; if (trim((string)($class['codigo']??''))!=='' && !(new ProcessoClasseSyncService($db))->readByNumero20($digits)) (new ProcessoClasseSyncService($db))->syncFromDataJud($digits,(string)$class['codigo'],(string)($class['descricao']??''));
         $subjects=$data['assuntos']??[]; if (is_array($subjects)&&$subjects&&!(new ProcessoAssuntoSyncService($db))->readByNumero20($digits)) (new ProcessoAssuntoSyncService($db))->syncFromDataJud($digits,$subjects);
       }
     } catch (Throwable $e) { $lookupError=$e->getMessage(); }
   }
   $rid=IdGenerator::generateUniquePrefixedId($db,'fokuslaw_expedicoes','id','FEX');
   $responsavel=trim((string)($p['responsavel_id']??$user)); if ($responsavel==='') $responsavel=$user;
   $stmt=$db->prepare("INSERT INTO fokuslaw_expedicoes (id,numero_processo,processo_id,destinatario,tipo_documento,descricao_documento,status,responsavel_id,sigiloso,is_teste,criado_por,atualizado_por) VALUES (:id,:cnj,:pid,:dest,:tipo,:desc,'a_fazer',:resp,:sig,:test,:user,:user)");
   $stmt->execute([':id'=>$rid,':cnj'=>$cnj,':pid'=>$processId,':dest'=>mb_substr(trim((string)$p['destinatario']),0,180),':tipo'=>mb_substr(trim((string)$p['tipo_documento']),0,120),':desc'=>mb_substr(trim((string)($p['descricao_documento']??'')),0,255)?:null,':resp'=>$responsavel,':sig'=>$sigiloso,':test'=>!empty($p['is_teste'])?1:0,':user'=>$user]);
   $h=$db->prepare("INSERT INTO fokuslaw_historico (id,modulo,registro_id,acao,detalhes,usuario_id) VALUES (:id,'expedicao',:rid,'criada',:det,:user)"); $h->execute([':id'=>IdGenerator::generateUniquePrefixedId($db,'fokuslaw_historico','id','FHI'),':rid'=>$rid,':det'=>json_encode(['is_teste'=>!empty($p['is_teste'])],JSON_UNESCAPED_UNICODE),':user'=>$user]);
   ApiJsonResponse::send(['id'=>$rid,'lookup_error'=>$lookupError],201); exit;
 }
 $st=$db->prepare('SELECT * FROM fokuslaw_expedicoes WHERE id=:id'); $st->execute([':id'=>$id]); $row=$st->fetch(PDO::FETCH_ASSOC); if (!$row) throw new InvalidArgumentException('Expedição não encontrada.');
 if ($action==='delete-test') { if ((int)$row['is_teste']!==1) throw new InvalidArgumentException('Somente registros fictícios podem ser excluídos nesta ação.'); $db->prepare('DELETE FROM fokuslaw_expedicoes WHERE id=:id')->execute([':id'=>$id]); ApiJsonResponse::send(['ok'=>true]); exit; }
 if ($action==='update') { if (trim((string)($p['responsavel_id']??$row['responsavel_id']))!==$user && (string)$row['criado_por']!==$user) throw new RuntimeException('Somente o responsável pode editar esta expedição.'); $st=$db->prepare("UPDATE fokuslaw_expedicoes SET destinatario=:dest,tipo_documento=:tipo,descricao_documento=:descr,status=:status,atualizado_por=:user,updated_at=NOW() WHERE id=:id"); $st->execute([':dest'=>mb_substr(trim((string)$p['destinatario']),0,180),':tipo'=>mb_substr(trim((string)$p['tipo_documento']),0,120),':descr'=>mb_substr(trim((string)($p['descricao_documento']??'')),0,255)?:null,':status'=>in_array(($p['status']??''),['a_fazer','em_andamento','pendente'],true)?$p['status']:'a_fazer',':user'=>$user,':id'=>$id]); ApiJsonResponse::send(['ok'=>true]); exit; }
 if ($action==='send') { if ((string)$row['responsavel_id']!==$user) throw new RuntimeException('Somente o responsável pode registrar o envio.'); $date=trim((string)($p['data_envio']??date('Y-m-d'))); $st=$db->prepare("UPDATE fokuslaw_expedicoes SET status='concluida',data_envio=:date,concluido_por=:user,concluido_em=NOW(),atualizado_por=:user,updated_at=NOW() WHERE id=:id"); $st->execute([':date'=>$date,':user'=>$user,':id'=>$id]); ApiJsonResponse::send(['ok'=>true]); exit; }
 if ($action==='cancel') { if ((string)$row['responsavel_id']!==$user && (string)$row['criado_por']!==$user) throw new RuntimeException('Somente o responsável ou criador pode cancelar.'); $db->prepare("UPDATE fokuslaw_expedicoes SET status='cancelada',atualizado_por=:user,updated_at=NOW() WHERE id=:id")->execute([':user'=>$user,':id'=>$id]); ApiJsonResponse::send(['ok'=>true]); exit; }
 ApiJsonResponse::send(['error'=>'Ação inválida.'],400);
} catch (InvalidArgumentException $e) { ApiJsonResponse::send(['error'=>$e->getMessage()],400); } catch (Throwable $e) { ApiJsonResponse::send(['error'=>$e->getMessage(),'technical'=>$e->getMessage()],500); }
