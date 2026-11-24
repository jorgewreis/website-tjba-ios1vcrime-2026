<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/services/DashboardResumoService.php';

// SQLite em memória; adapta apenas a descoberta de tabelas, sem banco externo.
final class DashboardSqlite extends PDO
{
    public bool $falharGuias = false;
    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        if (str_contains($query, 'INFORMATION_SCHEMA.TABLES')) {
            $query = "SELECT 1 FROM sqlite_master WHERE type='table' AND name=?";
        }
        if ($this->falharGuias && str_contains($query, 'FROM `guias_subtarefas`')) {
            throw new PDOException('falha sintética');
        }
        return parent::prepare($query, $options);
    }
}
function verificar(bool $condition, string $message): void { if (!$condition) throw new RuntimeException($message); }
$db = new DashboardSqlite('sqlite::memory:');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$db->sqliteCreateCollation('utf8mb4_unicode_ci', static fn($a, $b) => strcmp($a, $b));
$modules = [
    ['cartas_expedidas','cartas_expedidas_subtarefas','carta_expedida_id'],
    ['cartas_recebidas','cartas_recebidas_subtarefas','carta_recebida_id'],
    ['guias','guias_subtarefas','guia_id'],
    ['editais','editais_subtarefas','edital_id'],
    ['oficios_cartorio','oficios_cartorio_subtarefas','oficio_cartorio_id'],
];
foreach ($modules as [$parent, $child, $fk]) {
    $extra = $parent === 'cartas_expedidas' ? ', prazo_dias INTEGER' : '';
    $db->exec("CREATE TABLE `$parent` (id TEXT PRIMARY KEY, status TEXT, created_at TEXT{$extra})");
    $db->exec("CREATE TABLE `$child` (id TEXT PRIMARY KEY, `$fk` TEXT, tipo TEXT, ordem INTEGER, status TEXT, updated_at TEXT)");
}
$db->exec("INSERT INTO guias VALUES ('aberta','em_andamento','2026-01-01'),('concluida','concluída','2026-01-01'),('cancelada','cancelada','2026-01-01')");
$insert = $db->prepare('INSERT INTO guias_subtarefas VALUES (?,?,?,?,?,?)');
$steps = ['detalhamento_informacoes','calculo_pena_multa','expedicao_guia','aguardando_assinatura','juntada_automatica','gerar_pdf','envio_guia','juntada_termo_envio'];
foreach ($steps as $i => $type) {
    $insert->execute(['a'.$i,'aberta',$type,$i+1,$i === 0 ? 'em_andamento' : 'pendente','2026-01-01']);
    $insert->execute(['c'.$i,'concluida',$type,$i+1,'concluida','2026-01-01']);
}
$insert->execute(['x','cancelada','detalhamento_informacoes',1,'em_andamento','2026-01-01']);
$service = new DashboardResumoService($db);
$db->exec("INSERT INTO guias VALUES ('futura-invalida','em_andamento','2026-01-01')");
$insert->execute(['f1','futura-invalida','detalhamento_informacoes',1,'em_andamento','2026-01-01']);
$insert->execute(['f2','futura-invalida','etapa_inexistente',2,'pendente','2026-01-01']);
$result = $service->resumo('usuario', ['CARTORIO']);
$queue = array_values(array_filter($result['geral']['filas'], static fn($q) => ($q['key'] ?? '') === 'guias'));
verificar($result['geral']['tarefas_abertas'] === null && $result['fila_parcial'] && ($queue[0]['error'] ?? false), 'Tipo desconhecido em etapa futura deve tornar fila indisponível');
$db->exec("DELETE FROM guias_subtarefas WHERE guia_id='futura-invalida'");
$db->exec("DELETE FROM guias WHERE id='futura-invalida'");
$db->exec("INSERT INTO guias VALUES ('futura-duplicada','em_andamento','2026-01-01')");
$insert->execute(['d1','futura-duplicada','detalhamento_informacoes',1,'em_andamento','2026-01-01']);
$insert->execute(['d2','futura-duplicada','calculo_pena_multa',2,'pendente','2026-01-01']);
$insert->execute(['d3','futura-duplicada','calculo_pena_multa',2,'pendente','2026-01-01']);
$result = $service->resumo('usuario', ['CARTORIO']);
$queue = array_values(array_filter($result['geral']['filas'], static fn($q) => ($q['key'] ?? '') === 'guias'));
verificar($result['geral']['tarefas_abertas'] === null && $result['fila_parcial'] && ($queue[0]['error'] ?? false), 'Empate em etapa futura deve tornar fila indisponível');
$db->exec("DELETE FROM guias_subtarefas WHERE guia_id='futura-duplicada'");
$db->exec("DELETE FROM guias WHERE id='futura-duplicada'");
foreach ($steps as $i => $type) {
    if ($i > 0) $db->exec("UPDATE guias_subtarefas SET status='concluida' WHERE id='a" . ($i-1) . "'");
    $result = $service->resumo('usuario', ['CARTORIO']);
    $queue = array_values(array_filter($result['geral']['filas'], static fn($q) => ($q['key'] ?? '') === 'guias'));
    verificar(count($queue) === 1 && $queue[0]['type'] === $type && $queue[0]['count'] === 1, 'Etapa executável incorreta: '.$type);
    verificar($result['geral']['tarefas_abertas'] === 1 && !$result['fila_parcial'], 'Total não reflete a etapa aberta');
}
$db->exec("UPDATE guias_subtarefas SET status='concluida' WHERE guia_id='aberta'");
$db->exec("UPDATE guias SET status='concluída' WHERE id='aberta'");
$result = $service->resumo('usuario', ['CARTORIO']);
verificar($result['geral']['tarefas_abertas'] === 0 && !$result['fila_parcial'], 'Guia concluída deve sair da fila');
$db->exec("INSERT INTO guias VALUES ('pulada','em_andamento','2026-01-01')");
$insert->execute(['p1','pulada','detalhamento_informacoes',1,'pulada','2026-01-01']);
$insert->execute(['p2','pulada','calculo_pena_multa',2,'pendente','2026-01-01']);
$result = $service->resumo('usuario', ['CARTORIO']);
$queue = array_values(array_filter($result['geral']['filas'], static fn($q) => ($q['key'] ?? '') === 'guias'));
verificar(count($queue) === 1 && $queue[0]['type'] === 'calculo_pena_multa' && $queue[0]['count'] === 1, 'Etapas puladas não bloqueiam a próxima');
$insert->execute(['p3','pulada','calculo_pena_multa',2,'em_andamento','2026-01-01']);
$result = $service->resumo('usuario', ['CARTORIO']);
$queue = array_values(array_filter($result['geral']['filas'], static fn($q) => ($q['key'] ?? '') === 'guias'));
verificar($result['geral']['tarefas_abertas'] === null && $result['fila_parcial'] && ($queue[0]['error'] ?? false), 'Empate na ordem deve tornar fila indisponível');
$db->exec("DELETE FROM guias_subtarefas WHERE id='p3'");
$db->exec("UPDATE guias_subtarefas SET tipo='etapa_inexistente' WHERE id='p2'");
$result = $service->resumo('usuario', ['CARTORIO']);
$queue = array_values(array_filter($result['geral']['filas'], static fn($q) => ($q['key'] ?? '') === 'guias'));
verificar($result['geral']['tarefas_abertas'] === null && $result['fila_parcial'] && ($queue[0]['error'] ?? false), 'Tipo ativo desconhecido deve tornar fila indisponível');
$db->exec("UPDATE guias_subtarefas SET tipo='calculo_pena_multa' WHERE id='p2'");
$db->exec("UPDATE guias_subtarefas SET ordem=NULL WHERE id='p2'");
$result = $service->resumo('usuario', ['CARTORIO']);
$queue = array_values(array_filter($result['geral']['filas'], static fn($q) => ($q['key'] ?? '') === 'guias'));
verificar($result['geral']['tarefas_abertas'] === null && $result['fila_parcial'] && ($queue[0]['error'] ?? false), 'Etapa ativa sem ordem deve tornar fila indisponível');
$db->exec("UPDATE guias_subtarefas SET ordem=2 WHERE id='p2'");
$db->exec("INSERT INTO guias VALUES ('ordem-invalida','em_andamento','2026-01-01')");
$insert->execute(['o1','ordem-invalida','expedicao_guia',2,'em_andamento','2026-01-01']);
$result = $service->resumo('usuario', ['CARTORIO']);
$queue = array_values(array_filter($result['geral']['filas'], static fn($q) => ($q['key'] ?? '') === 'guias'));
verificar($result['geral']['tarefas_abertas'] === null && $result['fila_parcial'] && ($queue[0]['error'] ?? false), 'Tipo e ordem incompatíveis devem tornar fila indisponível');
$db->exec("DELETE FROM guias_subtarefas WHERE guia_id='ordem-invalida'");
$db->exec("DELETE FROM guias WHERE id='ordem-invalida'");
$db->exec("INSERT INTO guias VALUES ('status-invalido','em_andamento','2026-01-01')");
$insert->execute(['s1','status-invalido','expedicao_guia',3,'em_revisao','2026-01-01']);
$result = $service->resumo('usuario', ['CARTORIO']);
$queue = array_values(array_filter($result['geral']['filas'], static fn($q) => ($q['key'] ?? '') === 'guias'));
verificar($result['geral']['tarefas_abertas'] === null && $result['fila_parcial'] && ($queue[0]['error'] ?? false), 'Status desconhecido deve tornar fila indisponível');
$db->exec("DELETE FROM guias_subtarefas WHERE guia_id='status-invalido'");
$db->exec("DELETE FROM guias WHERE id='status-invalido'");
$db->falharGuias = true;
$result = $service->resumo('usuario', ['CARTORIO']);
$queue = array_values(array_filter($result['geral']['filas'], static fn($q) => ($q['key'] ?? '') === 'guias'));
verificar($result['geral']['tarefas_abertas'] === null && $result['fila_parcial'] && ($queue[0]['error'] ?? false) && $queue[0]['count'] === null, 'Falha da fila deve tornar total indisponível');
echo "PASS: oito etapas, conclusão, total e falha de guias em SQLite sintético\n";
