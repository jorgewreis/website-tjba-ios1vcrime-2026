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
        $registrations = array_fill(0,6,0);
        $registrationLabels=[];
        for($i=5;$i>=0;$i--){$d=(new DateTimeImmutable('first day of this month'))->modify('-'.$i.' months');$registrationLabels[]=$d->format('m/Y');}
        {
            foreach (self::MODULES as $key => $meta) {
                try {
                    if (!$this->tableExists($meta['table']) || !$this->tableExists($meta['parent'])) { $hasQueueErrors=true; $queues[]=['key'=>$key,'label'=>$meta['label'],'count'=>null,'route'=>$meta['route'],'error'=>true]; continue; }
                    $types=array_keys($meta['types']);$marks=implode(',',array_fill(0,count($types),'?'));
                    $stmt = $this->pdo->prepare("SELECT s.tipo,COUNT(*) total,MIN(s.created_at) oldest FROM `{$meta['table']}` s JOIN `{$meta['parent']}` p ON p.id=s.`{$meta['fk']}` WHERE s.status='em_andamento' AND s.tipo IN ({$marks}) AND LOWER(COALESCE(p.status,'')) NOT IN ('concluída','concluida','cancelada','finalizada') GROUP BY s.tipo");
                    $stmt->execute($types);$moduleCount=0;
                    foreach($stmt->fetchAll(PDO::FETCH_ASSOC) as $row){$count=(int)$row['total'];$moduleCount+=$count;$date=$row['oldest']??null;if($date&&($oldest===null||$date<$oldest))$oldest=$date;$queues[]=['key'=>$key,'type'=>$row['tipo'],'label'=>$meta['types'][$row['tipo']]??$key,'count'=>$count,'route'=>$meta['route']];}
                    $total += $moduleCount;
                    try { $monthStmt=$this->pdo->query("SELECT DATE_FORMAT(p.created_at,'%m/%Y') mes,COUNT(*) total FROM `{$meta['parent']}` p WHERE p.created_at >= DATE_SUB(DATE_FORMAT(CURDATE(),'%Y-%m-01'), INTERVAL 5 MONTH) GROUP BY DATE_FORMAT(p.created_at,'%Y-%m') ORDER BY DATE_FORMAT(p.created_at,'%Y-%m')"); foreach($monthStmt->fetchAll(PDO::FETCH_ASSOC) as $monthRow){$idx=array_search((string)$monthRow['mes'],$registrationLabels,true);if($idx!==false)$registrations[$idx]+=(int)$monthRow['total'];} } catch (Throwable $ignored) {}
                } catch (Throwable $e) { $hasQueueErrors=true; $queues[] = ['key'=>$key,'label'=>$meta['label'],'count'=>null,'route'=>$meta['route'],'error'=>true]; }
            }
        }
        $completed = ['7d'=>0,'30d'=>0,'weekly'=>array_fill(0,7,0),'modules'=>[]];
        if ($this->tableExists('dashboard_conclusoes')) {
            $st=$this->pdo->prepare("SELECT COUNT(*) FROM dashboard_conclusoes WHERE usuario_id=? AND concluido_em >= DATE_SUB(NOW(), INTERVAL 7 DAY)"); $st->execute([$userId]); $completed['7d']=(int)$st->fetchColumn();
            $st=$this->pdo->prepare("SELECT COUNT(*) FROM dashboard_conclusoes WHERE usuario_id=? AND concluido_em >= DATE_SUB(NOW(), INTERVAL 30 DAY)"); $st->execute([$userId]); $completed['30d']=(int)$st->fetchColumn();
            $st=$this->pdo->prepare("SELECT modulo, COUNT(*) total FROM dashboard_conclusoes WHERE usuario_id=? AND concluido_em >= DATE_SUB(NOW(), INTERVAL 30 DAY) GROUP BY modulo"); $st->execute([$userId]); $completed['modules']=$st->fetchAll(PDO::FETCH_ASSOC);
            $st=$this->pdo->prepare("SELECT DATEDIFF(CURDATE(), DATE(concluido_em)) age, COUNT(*) total FROM dashboard_conclusoes WHERE usuario_id=? AND concluido_em >= DATE_SUB(CURDATE(), INTERVAL 6 DAY) GROUP BY DATE(concluido_em)"); $st->execute([$userId]);
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) { $i=6-(int)$row['age']; if ($i>=0 && $i<7) $completed['weekly'][$i]=(int)$row['total']; }
        }
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
        if($this->tableExists('dashboard_conclusoes')){ $st=$this->pdo->query("SELECT DATEDIFF(CURDATE(),DATE(concluido_em)) age,COUNT(*) total FROM dashboard_conclusoes WHERE concluido_em>=DATE_SUB(CURDATE(),INTERVAL 6 DAY) GROUP BY DATE(concluido_em)"); foreach($st->fetchAll(PDO::FETCH_ASSOC) as $r){$idx=6-(int)$r['age'];if($idx>=0&&$idx<7)$globalCompletions[$idx]=(int)$r['total'];} }
        $sectorQueues=$includeCartorio?$queues:[];$reportedTotal=$hasQueueErrors?null:$total;$sectorTotal=$includeCartorio?$reportedTotal:null;
        return ['geral'=>['tarefas_abertas'=>$reportedTotal,'filas'=>$queues,'cadastros_mensais'=>['labels'=>$registrationLabels,'values'=>$registrations],'conclusoes_semanais'=>$globalCompletions], 'setor'=>['areas'=>$areas,'selecionado'=>$selectedArea,'conectado'=>$includeCartorio,'tarefas_abertas'=>$sectorTotal,'filas'=>$sectorQueues], 'usuario'=>$completed, 'agenda'=>$calendar,'fila_mais_antiga_em'=>$oldest,'atualizado_em'=>date(DATE_ATOM),'fila_parcial'=>$hasQueueErrors];
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
