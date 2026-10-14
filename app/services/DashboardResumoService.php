<?php
declare(strict_types=1);

/** Consultas agregadas usadas pelo dashboard interno. Filas contam somente subtarefas executáveis. */
final class DashboardResumoService
{
    private PDO $pdo;
    private const MODULES = [
        'cartas_expedidas' => ['label'=>'Cartas expedidas','table' => 'cartas_expedidas_subtarefas', 'parent' => 'cartas_expedidas', 'fk' => 'carta_expedida_id', 'route' => 'cartasexpedidas', 'types'=>['aguardando_envio'=>'Envio de carta expedida','aguardando_devolucao'=>'Cumprimento de carta expedida']],
        'cartas_recebidas' => ['label'=>'Cartas recebidas','table' => 'cartas_recebidas_subtarefas', 'parent' => 'cartas_recebidas', 'fk' => 'carta_recebida_id', 'route' => 'cartasrecebidas', 'types'=>['cumprimento'=>'Cumprimento de carta recebida','devolucao'=>'Devolução de carta recebida']],
        'guias' => ['label'=>'Guias','table' => 'guias_subtarefas', 'parent' => 'guias', 'fk' => 'guia_id', 'route' => 'guias', 'types'=>['expedicao_guia'=>'Expedição de guia']],
        'editais' => ['label'=>'Editais','table' => 'editais_subtarefas', 'parent' => 'editais', 'fk' => 'edital_id', 'route' => 'editais', 'types'=>['certificacao_publicacao'=>'Publicação de edital','certificacao_decurso_prazo'=>'Decurso de prazo']],
        'oficios' => ['label'=>'Ofícios','table' => 'oficios_cartorio_subtarefas', 'parent' => 'oficios_cartorio', 'fk' => 'oficio_cartorio_id', 'route' => 'oficioscartorio', 'types'=>['enviar_oficio'=>'Envio de ofício']],
    ];

    public function __construct(PDO $pdo) { $this->pdo = $pdo; }

    public static function areasAutorizadas(array $profiles): array
    {
        $areas = [];
        foreach ($profiles as $profile) {
            $p = strtoupper(trim((string) $profile));
            if (preg_match('/^(?:ESTAGIARIO|SERVIDOR)-(CARTORIO|GABINETE|AUDIENCIAS)$/', $p, $m)) {
                $areas[] = $m[1];
            }
            if (in_array($p, ['ADMIN','CHEFIA','DIRETORA','MAGISTRADA'], true)) $areas = ['CARTORIO','GABINETE','AUDIENCIAS'];
        }
        return array_values(array_unique($areas));
    }

    public function resumo(string $userId, array $areas, ?string $selectedArea = null): array
    {
        if ($selectedArea !== null && !in_array($selectedArea, $areas, true)) {
            throw new InvalidArgumentException('Setor não autorizado.');
        }
        $activeAreas = $selectedArea ? [$selectedArea] : $areas;
        $includeCartorio = in_array('CARTORIO', $activeAreas, true);
        $queues = [];
        $total = 0;
        $hasQueueErrors = false;
        $oldest = null;
        $oldestLabel = null;
        $oldestReturns = null;
        $lettersRegistered = ['expedidas'=>null,'recebidas'=>null];
        $weeklyLabels=[];$weeklyLetters=['expedidas'=>array_fill(0,7,0),'recebidas'=>array_fill(0,7,0)];
        $officesRegistered=['cartorio'=>null,'gabinete'=>null];
        $weeklyOffices=['cartorio'=>array_fill(0,7,0),'gabinete'=>array_fill(0,7,0)];
        for($i=6;$i>=0;$i--){$day=(new DateTimeImmutable('today'))->modify('-'.$i.' days');$weeklyLabels[]=$day->format('d/m');}
        foreach(['expedidas'=>'cartas_expedidas','recebidas'=>'cartas_recebidas'] as $kind=>$table){
            try {
                if (!$this->tableExists($table)) continue;
                // Mesmo recorte padrão das listagens: concluídas e canceladas ficam fora.
                $activeWhere="`status` COLLATE utf8mb4_unicode_ci NOT IN ('concluída','cancelada')";
                $count=$this->pdo->query("SELECT COUNT(*) FROM `{$table}` WHERE {$activeWhere}");$lettersRegistered[$kind]=(int)$count->fetchColumn();
                $weekly=$this->pdo->query("SELECT DATE(created_at) dia, COUNT(*) total FROM `{$table}` WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY) GROUP BY DATE(created_at)");
                foreach($weekly->fetchAll(PDO::FETCH_ASSOC) as $dayRow){$idx=array_search(date('d/m',strtotime((string)$dayRow['dia'])),$weeklyLabels,true);if($idx!==false)$weeklyLetters[$kind][$idx]=(int)$dayRow['total'];}
            } catch(Throwable $e) { error_log('dashboard resumo [cadastros '.$kind.']: '.$e->getMessage()); }
        }
        foreach(['cartorio'=>'oficios_cartorio','gabinete'=>'oficios_gabinete'] as $kind=>$table){
            try {
                if (!$this->tableExists($table)) continue;
                $annual=$this->pdo->query("SELECT COUNT(*) FROM `{$table}` WHERE created_at >= MAKEDATE(YEAR(CURDATE()),1) AND created_at < MAKEDATE(YEAR(CURDATE())+1,1)");
                $officesRegistered[$kind]=(int)$annual->fetchColumn();
                $weekly=$this->pdo->query("SELECT DATE(created_at) dia, COUNT(*) total FROM `{$table}` WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY) GROUP BY DATE(created_at)");
                foreach($weekly->fetchAll(PDO::FETCH_ASSOC) as $dayRow){$idx=array_search(date('d/m',strtotime((string)$dayRow['dia'])),$weeklyLabels,true);if($idx!==false)$weeklyOffices[$kind][$idx]=(int)$dayRow['total'];}
            } catch(Throwable $e) { error_log('dashboard resumo [cadastros oficios '.$kind.']: '.$e->getMessage()); }
        }
        $registrations = array_fill(0,6,0);
        $registrationLabels=[];
        for($i=5;$i>=0;$i--){$d=(new DateTimeImmutable('first day of this month'))->modify('-'.$i.' months');$registrationLabels[]=$d->format('m/Y');}
        {
            foreach (self::MODULES as $key => $meta) {
                try {
                    if (!$this->tableExists($meta['table']) || !$this->tableExists($meta['parent'])) { $hasQueueErrors=true; $queues[]=['key'=>$key,'label'=>$meta['label'],'count'=>null,'route'=>$meta['route'],'error'=>true]; continue; }
                    $types=array_keys($meta['types']);$marks=implode(',',array_fill(0,count($types),'?'));
                    $stmt = $this->pdo->prepare("SELECT s.tipo,COUNT(*) total,MIN(s.updated_at) oldest FROM `{$meta['table']}` s JOIN `{$meta['parent']}` p ON p.id=s.`{$meta['fk']}` WHERE s.status='em_andamento' AND s.tipo IN ({$marks}) AND LOWER(COALESCE(p.status,'')) NOT IN ('concluída','concluida','cancelada','finalizada') GROUP BY s.tipo");
                    $stmt->execute($types);$moduleCount=0;
                    foreach($stmt->fetchAll(PDO::FETCH_ASSOC) as $row){
                        $count=(int)$row['total'];$moduleCount+=$count;$date=$row['oldest']??null;
                        $isSending=$key==='cartas_expedidas'&&$row['tipo']==='aguardando_envio';
                        $isReturning=$key==='cartas_recebidas'&&$row['tipo']==='devolucao';
                        if($count>0&&$date&&($isSending||$isReturning)&&($oldest===null||$date<$oldest)){
                            $oldest=$date;
                            $oldestLabel=$isSending?'Cartas Expedidas aguardando envio':'Cartas Recebidas aguardando devolução';
                        }
                        if($isReturning&&$count>0)$oldestReturns=$date;
                        $queues[]=['key'=>$key,'type'=>$row['tipo'],'label'=>$meta['types'][$row['tipo']]??$key,'count'=>$count,'route'=>$meta['route']];
                    }
                    $total += $moduleCount;
                    try { $monthStmt=$this->pdo->query("SELECT DATE_FORMAT(p.created_at,'%m/%Y') mes,COUNT(*) total FROM `{$meta['parent']}` p WHERE p.created_at >= DATE_SUB(DATE_FORMAT(CURDATE(),'%Y-%m-01'), INTERVAL 5 MONTH) GROUP BY DATE_FORMAT(p.created_at,'%Y-%m') ORDER BY DATE_FORMAT(p.created_at,'%Y-%m')"); foreach($monthStmt->fetchAll(PDO::FETCH_ASSOC) as $monthRow){$idx=array_search((string)$monthRow['mes'],$registrationLabels,true);if($idx!==false)$registrations[$idx]+=(int)$monthRow['total'];} } catch (Throwable $ignored) {}
                } catch (Throwable $e) { $hasQueueErrors=true; $queues[] = ['key'=>$key,'label'=>$meta['label'],'count'=>null,'route'=>$meta['route'],'error'=>true]; }
            }
        }
        $completed = ['7d'=>0,'30d'=>0,'weekly'=>array_fill(0,7,0),'weekly_by_module'=>['cartas_expedidas'=>array_fill(0,7,0),'cartas_recebidas'=>array_fill(0,7,0)],'modules'=>[]];
        try {
            if ($this->tableExists('dashboard_conclusoes')) {
                $scope="usuario_id=? AND modulo IN ('cartas_expedidas','cartas_recebidas')";
                $st=$this->pdo->prepare("SELECT COUNT(*) FROM dashboard_conclusoes WHERE {$scope} AND concluido_em >= DATE_SUB(NOW(), INTERVAL 7 DAY)"); $st->execute([$userId]); $completed['7d']=(int)$st->fetchColumn();
                $st=$this->pdo->prepare("SELECT COUNT(*) FROM dashboard_conclusoes WHERE {$scope} AND concluido_em >= DATE_SUB(NOW(), INTERVAL 30 DAY)"); $st->execute([$userId]); $completed['30d']=(int)$st->fetchColumn();
                $st=$this->pdo->prepare("SELECT modulo, COUNT(*) total FROM dashboard_conclusoes WHERE {$scope} AND concluido_em >= DATE_SUB(NOW(), INTERVAL 30 DAY) GROUP BY modulo"); $st->execute([$userId]); $completed['modules']=$st->fetchAll(PDO::FETCH_ASSOC);
                $st=$this->pdo->prepare("SELECT modulo,DATEDIFF(CURDATE(),DATE(concluido_em)) age,COUNT(*) total FROM dashboard_conclusoes WHERE {$scope} AND concluido_em >= DATE_SUB(CURDATE(), INTERVAL 6 DAY) GROUP BY modulo,DATE(concluido_em)"); $st->execute([$userId]);
                foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) { $i=6-(int)$row['age'];$module=(string)$row['modulo'];if($i>=0&&$i<7){$completed['weekly'][$i]+=(int)$row['total'];if(isset($completed['weekly_by_module'][$module]))$completed['weekly_by_module'][$module][$i]=(int)$row['total'];} }
            }
        } catch (Throwable $e) { error_log('dashboard resumo [produtividade]: '.$e->getMessage()); }
        $calendar=['audiencias_7d'=>null,'dias_nao_uteis_30d'=>null];
        try { if ($this->tableExists('sala_audiencias_pauta')) { $st=$this->pdo->query("SELECT COUNT(*) FROM sala_audiencias_pauta WHERE ativo=1 AND data_audiencia BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY) AND LOWER(COALESCE(status,'')) NOT IN ('cancelada','cancelado')"); $calendar['audiencias_7d']=(int)$st->fetchColumn(); } } catch(Throwable $e) {}
        try {
            if ($this->tableExists('feriados')) {
                $st=$this->pdo->query("SELECT data_inicio,data_fim FROM feriados WHERE ativo=1 AND data_fim>=CURDATE() AND data_inicio<=DATE_ADD(CURDATE(), INTERVAL 30 DAY)");
                $intervals=$st->fetchAll(PDO::FETCH_ASSOC); $days=[]; $start=new DateTimeImmutable('today');
                for($i=0;$i<30;$i++){ $day=$start->modify('+'.$i.' days'); $weekday=(int)$day->format('N'); $iso=$day->format('Y-m-d'); if($weekday>=6) $days[$iso]=true; foreach($intervals as $holiday){if($iso >= (string)$holiday['data_inicio'] && $iso <= (string)$holiday['data_fim']) {$days[$iso]=true;break;}} }
                $calendar['dias_nao_uteis_30d']=count($days);
            }
        } catch(Throwable $e) {}
        $globalCompletions=array_fill(0,7,0);
        try { if($this->tableExists('dashboard_conclusoes')){ $st=$this->pdo->query("SELECT DATEDIFF(CURDATE(),DATE(concluido_em)) age,COUNT(*) total FROM dashboard_conclusoes WHERE concluido_em>=DATE_SUB(CURDATE(),INTERVAL 6 DAY) GROUP BY DATE(concluido_em)"); foreach($st->fetchAll(PDO::FETCH_ASSOC) as $r){$idx=6-(int)$r['age'];if($idx>=0&&$idx<7)$globalCompletions[$idx]=(int)$r['total'];} } } catch(Throwable $e) { error_log('dashboard resumo [conclusoes gerais]: '.$e->getMessage()); }
        $cartasEmDevolucao=$this->queueCount($queues,'cartas_recebidas','devolucao');
        $deadlinesSoon=null;
        $nearestDeadline=null;
        try {
            $deadlinesSoon=0;
            if($this->tableExists('cartas_expedidas')&&$this->tableExists('cartas_expedidas_subtarefas')){
                $st=$this->pdo->query("SELECT COUNT(DISTINCT c.id) total,MIN(DATE_ADD(DATE(envio.updated_at), INTERVAL c.prazo_dias DAY)) proximo FROM cartas_expedidas c JOIN cartas_expedidas_subtarefas s ON s.carta_expedida_id=c.id JOIN cartas_expedidas_subtarefas envio ON envio.carta_expedida_id=c.id AND envio.tipo='aguardando_envio' AND LOWER(envio.status) IN ('concluida','concluída') WHERE s.tipo='aguardando_devolucao' AND s.status='em_andamento' AND c.prazo_dias>0 AND DATE_ADD(DATE(envio.updated_at), INTERVAL c.prazo_dias DAY) BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY) AND LOWER(COALESCE(c.status,'')) NOT IN ('concluída','concluida','cancelada','finalizada')");
                $deadline=$st->fetch(PDO::FETCH_ASSOC);$deadlinesSoon+=(int)($deadline['total']??0);
                if(!empty($deadline['proximo']))$nearestDeadline=(string)$deadline['proximo'];
            }
            if($this->tableExists('cartas_recebidas')&&$this->tableExists('cartas_recebidas_subtarefas')){
                $st=$this->pdo->query("SELECT COUNT(DISTINCT c.id) total,MIN(DATE_ADD(DATE(c.data_recebimento), INTERVAL c.prazo_dias DAY)) proximo FROM cartas_recebidas c JOIN cartas_recebidas_subtarefas s ON s.carta_recebida_id=c.id WHERE s.tipo='cumprimento' AND s.status='em_andamento' AND c.prazo_dias>0 AND c.data_recebimento IS NOT NULL AND DATE_ADD(DATE(c.data_recebimento), INTERVAL c.prazo_dias DAY) BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY) AND LOWER(COALESCE(c.status,'')) NOT IN ('concluída','concluida','cancelada','finalizada')");
                $deadline=$st->fetch(PDO::FETCH_ASSOC);$deadlinesSoon+=(int)($deadline['total']??0);
                if(!empty($deadline['proximo'])&&($nearestDeadline===null||$deadline['proximo']<$nearestDeadline))$nearestDeadline=(string)$deadline['proximo'];
            }
        } catch(Throwable $e) { $deadlinesSoon=null;error_log('dashboard resumo [prazos]: '.$e->getMessage()); }
        $overdueDeadlines=['expedidas'=>null,'recebidas'=>null];
        $mostOverdueExpedidaDays=null;
        $mostOverdueRecebidaDays=null;
        if($includeCartorio){
            try {
                if($this->tableExists('cartas_expedidas')&&$this->tableExists('cartas_expedidas_subtarefas')){
                    $st=$this->pdo->query("SELECT COUNT(DISTINCT c.id) total,MAX(DATEDIFF(CURDATE(),DATE_ADD(DATE(envio.updated_at), INTERVAL c.prazo_dias DAY))) maior_atraso FROM cartas_expedidas c JOIN cartas_expedidas_subtarefas envio ON envio.carta_expedida_id=c.id AND envio.tipo='aguardando_envio' AND LOWER(envio.status) IN ('concluida','concluída') WHERE c.prazo_dias>0 AND DATE_ADD(DATE(envio.updated_at), INTERVAL c.prazo_dias DAY)<CURDATE() AND LOWER(COALESCE(c.status,'')) NOT IN ('concluída','concluida','cancelada','finalizada')");
                    $overdueExpedidas=$st->fetch(PDO::FETCH_ASSOC)?:[];
                    $overdueDeadlines['expedidas']=(int)($overdueExpedidas['total']??0);
                    $mostOverdueExpedidaDays=isset($overdueExpedidas['maior_atraso'])?(int)$overdueExpedidas['maior_atraso']:null;
                }
            } catch(Throwable $e) { error_log('dashboard resumo [prazos vencidos expedidas]: '.$e->getMessage()); }
            try {
                if($this->tableExists('cartas_recebidas')&&$this->tableExists('cartas_recebidas_subtarefas')){
                    $st=$this->pdo->query("SELECT COUNT(DISTINCT c.id) total,MAX(DATEDIFF(CURDATE(),DATE_ADD(DATE(c.data_recebimento), INTERVAL c.prazo_dias DAY))) maior_atraso FROM cartas_recebidas c JOIN cartas_recebidas_subtarefas pendente ON pendente.carta_recebida_id=c.id AND pendente.tipo='cumprimento' AND pendente.status='em_andamento' WHERE c.prazo_dias>0 AND c.data_recebimento IS NOT NULL AND DATE_ADD(DATE(c.data_recebimento), INTERVAL c.prazo_dias DAY)<CURDATE() AND LOWER(COALESCE(c.status,'')) NOT IN ('concluída','concluida','cancelada','finalizada')");
                    $overdueRecebidas=$st->fetch(PDO::FETCH_ASSOC)?:[];
                    $overdueDeadlines['recebidas']=(int)($overdueRecebidas['total']??0);
                    $mostOverdueRecebidaDays=isset($overdueRecebidas['maior_atraso'])?(int)$overdueRecebidas['maior_atraso']:null;
                }
            } catch(Throwable $e) { error_log('dashboard resumo [prazos vencidos recebidas]: '.$e->getMessage()); }
        }
        $sectorQueues=$includeCartorio?$queues:[];$reportedTotal=$hasQueueErrors?null:$total;$sectorTotal=$includeCartorio?$reportedTotal:null;
        $alerts=$this->letterAlerts($oldest,$oldestLabel,$cartasEmDevolucao,$oldestReturns,$deadlinesSoon,$nearestDeadline,$mostOverdueExpedidaDays);
        return ['geral'=>['tarefas_abertas'=>$reportedTotal,'filas'=>$queues,'cartas_cadastradas'=>['expedidas'=>$lettersRegistered['expedidas'],'recebidas'=>$lettersRegistered['recebidas'],'semana'=>['labels'=>$weeklyLabels,'expedidas'=>$weeklyLetters['expedidas'],'recebidas'=>$weeklyLetters['recebidas']]],'oficios_cadastrados'=>['ano'=>['cartorio'=>$officesRegistered['cartorio'],'gabinete'=>$officesRegistered['gabinete']],'semana'=>['labels'=>$weeklyLabels,'cartorio'=>$weeklyOffices['cartorio'],'gabinete'=>$weeklyOffices['gabinete']]],'cadastros_mensais'=>['labels'=>$registrationLabels,'values'=>$registrations],'conclusoes_semanais'=>$globalCompletions], 'setor'=>['areas'=>$areas,'selecionado'=>$selectedArea,'conectado'=>$includeCartorio,'tarefas_abertas'=>$sectorTotal,'filas'=>$sectorQueues,'prazos_vencidos'=>$overdueDeadlines,'prazos_vencidos_dias'=>['expedidas'=>$mostOverdueExpedidaDays,'recebidas'=>$mostOverdueRecebidaDays],'cadastros_mensais'=>$includeCartorio?['labels'=>$registrationLabels,'values'=>$registrations]:['labels'=>[],'values'=>[]]], 'usuario'=>$completed, 'agenda'=>$calendar,'alertas'=>$alerts,'fila_mais_antiga_em'=>$oldest,'fila_mais_antiga_etapa'=>$oldestLabel,'prazos_proximos_7d'=>$deadlinesSoon,'cartas_recebidas_aguardando_devolucao'=>$cartasEmDevolucao,'atualizado_em'=>date(DATE_ATOM),'fila_parcial'=>$hasQueueErrors];
    }

    private function letterAlerts(?string $oldest,?string $label,?int $returns,?string $oldestReturns,?int $deadlines,?string $nearestDeadline,?int $mostOverdueExpedidaDays): array
    {
        $alerts=[];
        if($mostOverdueExpedidaDays!==null&&$mostOverdueExpedidaDays>0){
            $alerts[]=['key'=>'cumprimento_expedido_excedido','label'=>'Prazo de cumprimento excedido','detail'=>'Solicitar devolução de Carta Expedida','value'=>$mostOverdueExpedidaDays,'unit'=>'days','color'=>'danger','priority'=>0,'date'=>'0000-01-01','order'=>0];
        }
        if($deadlines!==null&&$deadlines>0&&$nearestDeadline!==null){
            $alerts[]=['key'=>'prazos_proximos','label'=>'Prazos próximos','detail'=>'Cartas nos próximos 7 dias','value'=>$deadlines,'unit'=>'count','color'=>'warm','priority'=>0,'date'=>$nearestDeadline,'order'=>0];
        }
        if($oldest!==null){
            $days=max(0,(int)(new DateTimeImmutable($oldest))->setTime(0,0)->diff(new DateTimeImmutable('today'))->format('%r%a'));
            $alerts[]=['key'=>'fila_mais_antiga','label'=>'Fila mais antiga','detail'=>$label,'value'=>$days,'unit'=>'days','color'=>'purple','priority'=>1,'date'=>$oldest,'order'=>1];
        }
        if($returns!==null&&$returns>0){
            $alerts[]=['key'=>'devolucoes_aguardando','label'=>'Devoluções aguardando','detail'=>'Cartas Recebidas','value'=>$returns,'unit'=>'count','color'=>'mint','priority'=>1,'date'=>$oldestReturns??'9999-12-31','order'=>2];
        }
        usort($alerts,static fn(array $a,array $b):int=>($a['priority']<=>$b['priority'])?:strcmp($a['date'],$b['date'])?:($a['order']<=>$b['order']));
        return array_map(static function(array $alert):array{unset($alert['priority'],$alert['date'],$alert['order']);return $alert;},$alerts);
    }

    private function queueCount(array $queues,string $key,string $type): ?int
    {
        foreach($queues as $queue){if(($queue['key']??'')===$key&&($queue['error']??false))return null;}
        foreach($queues as $queue){if(($queue['key']??'')===$key&&($queue['type']??'')===$type)return $queue['count']===null?null:(int)$queue['count'];}
        return 0;
    }

    private function tableExists(string $table): bool
    {
        $st=$this->pdo->prepare('SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?'); $st->execute([$table]); return (bool)$st->fetchColumn();
    }

    /** Captura estado anterior ao fluxo; o chamador só persiste após a conclusão bem-sucedida. */
    public static function statusAnterior(PDO $pdo, string $table, string $id): ?array
    {
        $allowed=['cartas_expedidas_subtarefas','cartas_recebidas_subtarefas','guias_subtarefas','editais_subtarefas','oficios_cartorio_subtarefas'];
        if (!in_array($table,$allowed,true) || trim($id)==='') return null;
        $st=$pdo->prepare("SELECT id,status FROM `{$table}` WHERE id=? LIMIT 1"); $st->execute([$id]); $row=$st->fetch(PDO::FETCH_ASSOC); return $row ?: null;
    }

    public static function registrarConclusao(PDO $pdo, ?array $before, string $module, string $area, string $userId): void
    {
        if (!$before || strtolower(trim((string)$before['status']))==='concluida' || strtolower(trim((string)$before['status']))==='concluída' || $userId==='') return;
        $st=$pdo->prepare("INSERT INTO dashboard_conclusoes (id,usuario_id,modulo,area,referencia_interna,concluido_em) VALUES (UUID(),?,?,?,?,NOW())");
        $st->execute([$userId,$module,$area,(string)$before['id']]);
    }
}
