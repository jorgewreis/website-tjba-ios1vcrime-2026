<?php
declare(strict_types=1);

/** Classificação somente leitura. Não completa nem escolhe arbitrariamente etapas legadas. */
final class CartasExpedidasEstadoFluxo
{
    public static function normalizar(string $value): string
    {
        return str_replace('concluída', 'concluida', mb_strtolower(trim($value), 'UTF-8'));
    }

    public static function classificar(array $rows, string $statusPrincipal = ''): array
    {
        $result = ['etapa'=>'indeterminada', 'diagnostico'=>null, 'subtarefa'=>null, 'envio_em'=>null];
        if (in_array(self::normalizar($statusPrincipal), ['concluida','cancelada','finalizada'], true)) {
            $result['etapa'] = 'encerrada';
            return $result;
        }
        $steps = ['aguardando_envio'=>[], 'aguardando_devolucao'=>[]];
        foreach ($rows as $row) {
            $type = mb_strtolower(trim((string) ($row['tipo'] ?? '')), 'UTF-8');
            if (isset($steps[$type])) {
                // Consumidores recebem o mesmo contrato canônico usado na classificação.
                $row['tipo'] = $type;
                $row['status'] = self::normalizar((string) ($row['status'] ?? ''));
                $steps[$type][] = $row;
            }
        }
        $envios = $steps['aguardando_envio']; $devolucoes = $steps['aguardando_devolucao'];
        if (count($envios)>1 || count($devolucoes)>1) {
            $result['diagnostico']='etapas_duplicadas'; return $result;
        }
        foreach (['aguardando_envio'=>1,'aguardando_devolucao'=>2] as $type=>$order) {
            foreach ($steps[$type] as $step) {
                if ((string)($step['ordem']??'') !== (string)$order || !in_array(self::normalizar((string)($step['status']??'')), ['pendente','em_andamento','concluida'], true)) {
                    $result['diagnostico']='etapa_invalida'; return $result;
                }
            }
        }
        if (!$envios) { $result['diagnostico']='envio_ausente'; return $result; }
        $envio=$envios[0]; $devolucao=$devolucoes[0]??null;
        $enviado=self::normalizar((string)$envio['status'])==='concluida';
        $devolvido=$devolucao!==null && self::normalizar((string)$devolucao['status'])==='concluida';
        $result['envio_em']=$enviado ? ($envio['updated_at']??null) : null;
        if (!$enviado && $devolvido) { $result['diagnostico']='sequencia_invalida'; return $result; }
        if (!$enviado) {
            $result['etapa']='envio'; $result['subtarefa']=$envio;
            if ($devolucao===null) $result['diagnostico']='devolucao_ausente';
        } elseif ($devolucao===null) {
            $result['diagnostico']='devolucao_ausente';
        } elseif (!$devolvido) {
            $result['etapa']='devolucao'; $result['subtarefa']=$devolucao;
        } else {
            $result['etapa']='encerrada';
        }
        return $result;
    }

    /** Uma linha por carta, compatível com ONLY_FULL_GROUP_BY. Mesmo contrato de classificar(). */
    public static function consultaEstados(bool $somenteAbertas = false): string
    {
        $status="REPLACE(LOWER(TRIM(COALESCE(s.status,''))),'concluída','concluida')";
        $type="LOWER(TRIM(s.tipo))";
        $invalid="SUM(CASE WHEN {$type} IN ('aguardando_envio','aguardando_devolucao') AND ({$status} NOT IN ('pendente','em_andamento','concluida') OR s.ordem IS NULL OR ({$type}='aguardando_envio' AND s.ordem<>1) OR ({$type}='aguardando_devolucao' AND s.ordem<>2)) THEN 1 ELSE 0 END)";
        $where = $somenteAbertas ? "WHERE REPLACE(LOWER(TRIM(COALESCE(p.status,''))),'concluída','concluida') NOT IN ('concluida','cancelada','finalizada')" : '';
        $aggregate="SELECT p.id,p.status,p.prazo_dias,
            SUM(CASE WHEN {$type}='aguardando_envio' THEN 1 ELSE 0 END) envios,
            SUM(CASE WHEN {$type}='aguardando_devolucao' THEN 1 ELSE 0 END) devolucoes,
            {$invalid} invalidos,
            MAX(CASE WHEN {$type}='aguardando_envio' THEN {$status} END) envio_status,
            MAX(CASE WHEN {$type}='aguardando_devolucao' THEN {$status} END) devolucao_status,
            MAX(CASE WHEN {$type}='aguardando_envio' THEN s.updated_at END) envio_em,
            MAX(CASE WHEN {$type}='aguardando_devolucao' THEN s.updated_at END) devolucao_em
            FROM cartas_expedidas p LEFT JOIN cartas_expedidas_subtarefas s ON s.carta_expedida_id=p.id
            {$where}
            GROUP BY p.id,p.status,p.prazo_dias";
        $closed="REPLACE(LOWER(TRIM(COALESCE(f.status,''))),'concluída','concluida') IN ('concluida','cancelada','finalizada')";
        return "SELECT f.*,CURRENT_DATE hoje,
            CASE WHEN {$closed} THEN 'encerrada'
              WHEN envios>1 OR devolucoes>1 OR invalidos>0 OR envios=0 THEN 'indeterminada'
              WHEN envio_status<>'concluida' AND devolucao_status='concluida' THEN 'indeterminada'
              WHEN envio_status<>'concluida' THEN 'envio'
              WHEN devolucoes=0 THEN 'indeterminada'
              WHEN devolucao_status<>'concluida' THEN 'devolucao'
              ELSE 'encerrada' END etapa,
            CASE WHEN {$closed} THEN NULL
              WHEN envios>1 OR devolucoes>1 THEN 'etapas_duplicadas'
              WHEN invalidos>0 THEN 'etapa_invalida'
              WHEN envios=0 THEN 'envio_ausente'
              WHEN envio_status<>'concluida' AND devolucao_status='concluida' THEN 'sequencia_invalida'
              WHEN devolucoes=0 THEN 'devolucao_ausente' ELSE NULL END diagnostico
            FROM ({$aggregate}) f";
    }

    /** Status de apresentação/filtro derivado do mesmo estado, preservando terminais explícitos. */
    public static function expressaoStatus(string $alias = 'fluxo'): string
    {
        if (!preg_match('/^[a-z_]+$/i', $alias)) throw new InvalidArgumentException('Alias inválido.');
        return "CASE WHEN {$alias}.etapa='envio' THEN 'em_andamento'
            WHEN {$alias}.etapa='devolucao' THEN 'aguardando cumprimento'
            WHEN {$alias}.etapa='indeterminada' THEN 'indeterminada'
            WHEN LOWER(TRIM({$alias}.status)) IN ('cancelada','finalizada') THEN LOWER(TRIM({$alias}.status))
            ELSE 'concluída' END";
    }

    /** Agrega o snapshot da consulta sem confundir ausência de data com ausência de tarefa. */
    public static function resumir(array $rows, ?DateTimeImmutable $hoje = null): array
    {
        $hoje=($hoje??new DateTimeImmutable($rows[0]['hoje']??'today'))->setTime(0,0);
        $result=['envios'=>0,'devolucoes'=>0,'envio_mais_antigo'=>null,'proximos'=>0,'proximo_vencimento'=>null,'vencidos'=>0,'maior_atraso'=>null,'inconsistencias'=>0];
        $filaIndeterminada=false; $prazoIndeterminado=false; $antiguidadeIndeterminada=false;
        foreach ($rows as $row) {
            if (($row['diagnostico']??null)!==null) $result['inconsistencias']++;
            $etapa=$row['etapa'];
            if ($etapa==='indeterminada') { $filaIndeterminada=true; $prazoIndeterminado=true; continue; }
            if ($etapa==='envio') {
                $result['envios']++;
                $date=$row['envio_em']??null;
                if (!$date) $antiguidadeIndeterminada=true;
                if ($date && ($result['envio_mais_antigo']===null || $date<$result['envio_mais_antigo'])) $result['envio_mais_antigo']=$date;
            }
            if ($etapa!=='devolucao') continue;
            $result['devolucoes']++;
            $days=(int)($row['prazo_dias']??0);
            if ($days<=0) continue;
            $date=substr((string)($row['envio_em']??''),0,10);
            $sent=DateTimeImmutable::createFromFormat('!Y-m-d',$date,$hoje->getTimezone());
            if (!$sent || $sent->format('Y-m-d')!==$date) { $prazoIndeterminado=true; continue; }
            $due=$sent->modify('+'.$days.' days');
            if ($due<$hoje) {
                $result['vencidos']++;
                $result['maior_atraso']=max($result['maior_atraso']??0,(int)$due->diff($hoje)->days);
            } elseif ($due<=$hoje->modify('+7 days')) {
                $result['proximos']++;
                $iso=$due->format('Y-m-d');
                if ($result['proximo_vencimento']===null || $iso<$result['proximo_vencimento']) $result['proximo_vencimento']=$iso;
            }
        }
        if ($filaIndeterminada) foreach (['envios','devolucoes','envio_mais_antigo'] as $key) $result[$key]=null;
        if ($antiguidadeIndeterminada) $result['envio_mais_antigo']=null;
        if ($prazoIndeterminado) foreach (['proximos','proximo_vencimento','vencidos','maior_atraso'] as $key) $result[$key]=null;
        return $result;
    }
}
