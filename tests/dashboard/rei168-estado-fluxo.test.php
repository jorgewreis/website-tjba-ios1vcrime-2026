<?php
declare(strict_types=1);
require_once __DIR__.'/../../app/helpers/CartasExpedidasEstadoFluxo.php';
require_once __DIR__.'/../../app/repositories/CartasExpedidasSubtarefasRepository.php';
require_once __DIR__.'/../../app/repositories/CartasExpedidasRepository.php';

function check168(bool $ok,string $message): void { if (!$ok) throw new RuntimeException($message); }
function step168(string $type,int $order,string $status): array {
    return ['id'=>$type,'tipo'=>$type,'ordem'=>$order,'status'=>$status,'updated_at'=>'2026-11-20 12:00:00'];
}
$envio=step168('aguardando_envio',1,'em_andamento');
$devolucao=step168('aguardando_devolucao',2,'pendente');
foreach ([
    [[$envio,$devolucao],'envio',null],
    [[$envio],'envio','devolucao_ausente'],
    [[$devolucao],'indeterminada','envio_ausente'],
    [[],'indeterminada','envio_ausente'],
    [[$envio,$envio,$devolucao],'indeterminada','etapas_duplicadas'],
    [[step168('aguardando_envio',2,'em_andamento'),$devolucao],'indeterminada','etapa_invalida'],
    [[step168('aguardando_envio',1,'desconhecido'),$devolucao],'indeterminada','etapa_invalida'],
    [[$envio,step168('aguardando_devolucao',2,'concluida')],'indeterminada','sequencia_invalida'],
    [[step168('aguardando_envio',1,' Concluída '),$devolucao],'devolucao',null],
    [[step168('aguardando_envio',1,'concluida')],'indeterminada','devolucao_ausente'],
    [[step168('aguardando_envio',1,'concluida'),step168('aguardando_devolucao',2,'concluida')],'encerrada',null],
] as [$rows,$stage,$diagnostic]) {
    $result=CartasExpedidasEstadoFluxo::classificar($rows);
    check168($result['etapa']===$stage && $result['diagnostico']===$diagnostic,'Classificação '.$stage.' '.$diagnostic);
    check168(CartasExpedidasSubtarefasRepository::resolverSubtarefaAtivaPorLista($rows)===$result['subtarefa'],'Resolver deve reutilizar contrato');
}
check168(CartasExpedidasEstadoFluxo::classificar([$envio,$envio],' Cancelada ')['etapa']==='encerrada','Pai terminal excluído');
foreach ([[' AGUARDANDO_ENVIO ',1,' EM_ANDAMENTO ','aguardando_envio','em_andamento'],[' AGUARDANDO_DEVOLUCAO ',2,' PENDENTE ','aguardando_devolucao','pendente']] as [$rawType,$order,$rawStatus,$type,$status]) {
    $raw=step168($rawType,$order,$rawStatus)+['dados_especificos'=>'{"origem":"ficticia"}'];
    $rows=$order===1?[$raw,$devolucao]:[step168('aguardando_envio',1,' CONCLUÍDA '),$raw];
    $active=CartasExpedidasEstadoFluxo::classificar($rows)['subtarefa'];
    $expected=$raw; $expected['tipo']=$type; $expected['status']=$status;
    check168($active===$expected,'Subtarefa ativa canônica preserva identidade, datas e dados');
    check168($raw['tipo']===$rawType && $raw['status']===$rawStatus,'Classificação não modifica entrada');
    check168(CartasExpedidasSubtarefasRepository::resolverSubtarefaAtivaPorLista($rows)===$expected,'Repository entrega subtarefa canônica');
}
$today=new DateTimeImmutable('2026-11-24');
$states=[
 ['etapa'=>'envio','diagnostico'=>'devolucao_ausente','envio_em'=>null,'prazo_dias'=>5],
 ['etapa'=>'devolucao','diagnostico'=>null,'envio_em'=>'2026-11-19 12:00:00','prazo_dias'=>5],
 ['etapa'=>'devolucao','diagnostico'=>null,'envio_em'=>'2026-11-18 12:00:00','prazo_dias'=>5],
 ['etapa'=>'devolucao','diagnostico'=>null,'envio_em'=>'2026-11-26 12:00:00','prazo_dias'=>5],
 ['etapa'=>'encerrada','diagnostico'=>null,'envio_em'=>'2026-11-01 12:00:00','prazo_dias'=>5],
];
$summary=CartasExpedidasEstadoFluxo::resumir($states,$today);
check168($summary['envios']===1 && $summary['devolucoes']===3,'Contagens por carta');
check168($summary['proximos']===2 && $summary['vencidos']===1 && $summary['maior_atraso']===1,'Prazos inclusivos hoje e +7');
$states[1]['envio_em']=null;
$summary=CartasExpedidasEstadoFluxo::resumir($states,$today);
check168($summary['envios']===1 && $summary['devolucoes']===3 && $summary['proximos']===null && $summary['vencidos']===null,'Data ausente afeta prazos, não fila');
$states[]=['etapa'=>'indeterminada','diagnostico'=>'envio_ausente'];
$summary=CartasExpedidasEstadoFluxo::resumir($states,$today);
check168($summary['envios']===null && $summary['devolucoes']===null,'Fila ambígua não vira zero');
check168(CartasExpedidasEstadoFluxo::resumir([],$today)['envios']===0,'Vazio conhecido é zero');
// Inspeção do builder comum de fetchList/count sem conexão nem driver de banco.
final class NoConnection168 extends PDO { public function __construct() {} }
$repo=new CartasExpedidasRepository(new NoConnection168());
$filter=new ReflectionMethod($repo,'buildFilterClause');
foreach (['aguardando_envio'=>'em_andamento','aguardando_cumprimento'=>'aguardando cumprimento'] as $status=>$stage) {
    [$sql,$params]=$filter->invoke($repo,['status'=>$status,'processo'=>'ficticio'],'ce',true);
    check168(str_contains($sql,':status_efetivo') && $params[':status_efetivo']===$stage,'Filtro por etapa efetiva');
    check168(($params[':processo']??null)==='%ficticio%','Preservar demais filtros');
}
echo "PASS REI168: classificação pura, resolver, legado, prazos e filtro compartilhado sem conexão.\n";
