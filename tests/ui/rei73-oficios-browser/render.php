<?php
declare(strict_types=1);
// Any accidental application DB access fails before creating a connection.
final class Conection { public static function getInstance(): never { throw new RuntimeException('Database forbidden in UI fixture'); } }
$root=dirname(__DIR__,3);
require $root.'/app/repositories/OficiosCartorioSubtarefasRepository.php';
require $root.'/app/helpers/CartorioAlocacaoChipHelper.php';
require $root.'/app/helpers/OficioRegistroMetadataHelper.php';
$viewDir=$root.'/app/views/internal';
$source=file_get_contents($viewDir.'/cartorio-oficios-list.php');
$source=str_replace("\r\n","\n",$source);
$startMarker='<?php foreach ($rows as $row):';
$endMarker="                <?php endforeach; ?>\n            <?php endif; ?>";
if(substr_count($source,$startMarker)!==1||substr_count($source,$endMarker)!==1)throw new RuntimeException('View row boundary changed; review fixture extraction');
$start=strpos($source,$startMarker);$end=strpos($source,$endMarker,$start)+strlen('                <?php endforeach; ?>');
$fragment=substr($source,$start,$end-$start);
if(!str_contains($fragment,'btn-concluir-subtarefa-oficio'))throw new RuntimeException('Real action markup missing');
$fragmentHash=hash('sha256',$fragment);
$fragment=str_replace('__DIR__','$viewDir',$fragment);
preg_match_all('~<script>(.*?)</script>~s',$source,$scripts);
$inline=implode("\n",$scripts[0]);
if(!str_contains($inline,"fetch('/api/cartorio-oficios/concluir-subtarefa.php'"))throw new RuntimeException('Real completion script missing');
$routePage='oficioscartorio';$oficioFilter='';
$labelsStart=strpos($source,'$subtarefaLabels = [');$labelsEnd=strpos($source,'$baseQueryParams = []',$labelsStart);
if($labelsStart===false||$labelsEnd===false)throw new RuntimeException('View labels boundary missing');
eval(substr($source,$labelsStart,$labelsEnd-$labelsStart));
$oficioListViewerContext=['usuario_id'=>'SYNTHETIC','perfis'=>['SERVIDOR-CARTORIO']];
$isGestao=false;$currentUserId='SYNTHETIC';
$result=[];
foreach(['envio','juntada','finalizada'] as $stage){
 $steps=[['id'=>'SYN-SEND','tipo'=>'enviar_oficio','ordem'=>1,'status'=>$stage==='envio'?'em_andamento':'concluida'],['id'=>'SYN-JOIN','tipo'=>'juntar_termo_envio','ordem'=>2,'status'=>$stage==='envio'?'pendente':($stage==='juntada'?'em_andamento':'concluida')]];
 $rows=[['IdOficio'=>'SYN-OFFICIO','NumeroSeq'=>1,'Ano'=>2026,'TipoOficio'=>'Ofício fictício de auditoria','FormaCumprimento'=>'cartorio','Status'=>$stage==='finalizada'?'finalizada':'pendente','SubtarefasAll'=>$steps,'Subtarefas'=>array_values(array_filter($steps,fn($s)=>$s['status']==='em_andamento')),'Alocados'=>[]]];
 ob_start();eval('?>'.$fragment);$row=ob_get_clean();
 ob_start();eval('?>'.$inline);$js=ob_get_clean();
 $result[$stage]=['row'=>$row,'scripts'=>$js];
}
$result['_provenance']=['row_fragment_sha256'=>$fragmentHash,'inline_scripts_sha256'=>hash('sha256',$inline),'included_php_sha256'=>[],'scope'=>'Real list row, labels, nested partials and inline scripts; synthetic rows; no bootstrap'];
foreach(get_included_files() as $file){$result['_provenance']['included_php_sha256'][str_replace($root.'/','',str_replace('\\','/',$file))]=hash_file('sha256',$file);}
echo json_encode($result,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);
