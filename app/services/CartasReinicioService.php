<?php
declare(strict_types=1);

require_once __DIR__.'/../repositories/CartasExpedidasSubtarefasRepository.php';
require_once __DIR__.'/../repositories/CartasRecebidasSubtarefasRepository.php';

final class CartasReinicioService
{
    public static function reiniciar(PDO $pdo,string $module,string $cartaId,string $userId): void
    {
        $definitions=[
            'expedidas'=>['parent'=>'cartas_expedidas','table'=>'cartas_expedidas_subtarefas','fk'=>'carta_expedida_id','first'=>'aguardando_envio','second'=>'aguardando_devolucao','status'=>'em_andamento'],
            'recebidas'=>['parent'=>'cartas_recebidas','table'=>'cartas_recebidas_subtarefas','fk'=>'carta_recebida_id','first'=>'cumprimento','second'=>'devolucao','status'=>'aguardando cumprimento'],
        ];
        if(!isset($definitions[$module])||trim($cartaId)===''||trim($userId)==='')throw new InvalidArgumentException('Carta ou usuário inválido.');
        $meta=$definitions[$module];$ownsTransaction=!$pdo->inTransaction();
        try {
            if($ownsTransaction)$pdo->beginTransaction();
            $st=$pdo->prepare("SELECT id,status FROM `{$meta['parent']}` WHERE id=? FOR UPDATE");$st->execute([$cartaId]);$carta=$st->fetch(PDO::FETCH_ASSOC);
            if(!$carta)throw new InvalidArgumentException('Carta não encontrada.');
            $status=mb_strtolower(trim((string)$carta['status']),'UTF-8');
            if($status===$meta['status']||($module==='expedidas'&&$status==='aguardando envio'))throw new InvalidArgumentException('A carta já está na etapa inicial.');
            $repo=$module==='expedidas'?new CartasExpedidasSubtarefasRepository($pdo):new CartasRecebidasSubtarefasRepository($pdo);
            $existing=$repo->findByCartaId($cartaId);
            foreach([$meta['first'],$meta['second']] as $i=>$type){
                $matches=array_filter($existing,static fn(array $row):bool=>($row['tipo']??'')===$type&&(int)($row['ordem']??0)===$i+1);
                if(!$matches)$repo->create($cartaId,$type,$i+1,$i===0?'em_andamento':'pendente',null);
            }
            $st=$pdo->prepare("UPDATE `{$meta['table']}` SET status=CASE WHEN tipo=? THEN 'em_andamento' ELSE 'pendente' END,dados_especificos=NULL,updated_at=NOW() WHERE `{$meta['fk']}`=? AND tipo IN (?,?)");
            $st->execute([$meta['first'],$cartaId,$meta['first'],$meta['second']]);
            $st=$pdo->prepare("UPDATE `{$meta['parent']}` SET status=?,atualizado_por=?,updated_at=NOW() WHERE id=?");$st->execute([$meta['status'],$userId,$cartaId]);
            if($ownsTransaction)$pdo->commit();
        } catch(Throwable $e) {
            if($ownsTransaction&&$pdo->inTransaction())$pdo->rollBack();
            throw $e;
        }
    }
}
