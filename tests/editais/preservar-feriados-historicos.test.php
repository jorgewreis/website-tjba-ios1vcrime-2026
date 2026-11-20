<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/services/EditaisService.php';
final class EditalListSqlite extends PDO {
 public function prepare(string $query, array $options = []): PDOStatement|false {
  // SQLite deste ambiente não aceita LIMIT em UPDATE; a cláusula WHERE identifica o edital.
  if (str_starts_with(ltrim($query), 'UPDATE ')) $query = preg_replace('/ LIMIT 1$/', '', $query);
  $query = preg_replace('/ FOR UPDATE\s*$/i', '', $query);
  return parent::prepare($query, $options);
 }
 public function __construct() {
  parent::__construct('sqlite::memory:'); $this->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
  $this->sqliteCreateCollation('utf8mb4_unicode_ci', fn($a,$b)=>strcmp($a,$b));
  $this->sqliteCreateFunction('NOW', fn()=> '2026-01-01 00:00:00');
  $this->sqliteCreateFunction('FIELD', fn(...$args)=>1, -1);
  $this->sqliteCreateFunction('CONCAT', fn(...$args)=>implode('', $args), -1);
  foreach (['CREATE TABLE processos (id TEXT, numero_processo TEXT, classe_id TEXT)', 'CREATE TABLE classes (id TEXT, nome TEXT, codigo TEXT)', 'CREATE TABLE usuarios (id_usuario TEXT, nome TEXT, sobrenome TEXT)', 'CREATE TABLE feriados (ativo INTEGER, data_inicio TEXT, data_fim TEXT, regra_data TEXT)', 'CREATE TABLE editais (id TEXT, processo_id TEXT, tipo_edital TEXT, prazo_dias INTEGER, data_publicacao TEXT, data_inicio_contagem TEXT, data_decurso_prazo TEXT, status TEXT, created_at TEXT, updated_at TEXT, observacoes TEXT, criado_por TEXT, atualizado_por TEXT)', 'CREATE TABLE editais_subtarefas (id TEXT, edital_id TEXT, tipo TEXT, ordem INTEGER, status TEXT, dados_especificos TEXT, created_at TEXT, updated_at TEXT)'] as $sql) $this->exec($sql);
 }
}
$db=new EditalListSqlite();
$db->exec("INSERT INTO feriados VALUES (1,'2024-01-02','2024-01-02','unica')");
$db->exec("INSERT INTO feriados VALUES (1,'2024-02-13','2024-02-13','movel')");
$db->exec("INSERT INTO editais (id,prazo_dias,data_publicacao,status) VALUES ('E',1,'2024-01-01','aguardando decurso')");
$before=[]; foreach (['feriados','editais','editais_subtarefas'] as $table) $before[$table]=$db->query("SELECT * FROM $table")->fetchAll(PDO::FETCH_ASSOC);
$result=(new EditaisService($db))->listEditais([],20,0);
$info=$result['rows'][0]['DecursoInfo'];
if ($result['total'] !== 1 || $info['data_inicio_contagem'] !== '2024-01-03' || $info['data_limite_decurso'] !== '2024-01-04') throw new RuntimeException('Feriado histórico ignorado');
foreach ($before as $table=>$rows) if ($rows !== $db->query("SELECT * FROM $table")->fetchAll(PDO::FETCH_ASSOC)) throw new RuntimeException("Listagem alterou $table");
$service = new EditaisService($db);
$created = $service->reconciliarEditais(['E']);
if ($created !== ['solicitados'=>1,'editais_alterados'=>1,'editais_com_etapas_criadas'=>1,'etapas_criadas'=>2,'editais_com_datas_calculadas'=>1,'inalterados'=>0,'nao_encontrados'=>0,'finalizados'=>0,'falhas'=>0]) throw new RuntimeException('Totais da criação de etapas divergentes');
if ((int) $db->query("SELECT COUNT(*) FROM editais_subtarefas WHERE edital_id='E'")->fetchColumn() !== 2) throw new RuntimeException('A ação explícita não criou as etapas ausentes');
$unchanged = $service->reconciliarEditais(['E']);
if ($unchanged !== ['solicitados'=>1,'editais_alterados'=>0,'editais_com_etapas_criadas'=>0,'etapas_criadas'=>0,'editais_com_datas_calculadas'=>0,'inalterados'=>1,'nao_encontrados'=>0,'finalizados'=>0,'falhas'=>0]) throw new RuntimeException('Retry não foi idempotente ou não reportou edital inalterado');
$missing = $service->reconciliarEditais(['MISSING']);
if ($missing !== ['solicitados'=>1,'editais_alterados'=>0,'editais_com_etapas_criadas'=>0,'etapas_criadas'=>0,'editais_com_datas_calculadas'=>0,'inalterados'=>0,'nao_encontrados'=>1,'finalizados'=>0,'falhas'=>0]) throw new RuntimeException('ID inexistente não foi contabilizado separadamente');
$db->exec("INSERT INTO editais (id,prazo_dias,data_publicacao,status) VALUES ('DONE',1,'2024-01-01','concluída'),('CANCEL',1,'2024-01-01','cancelada')");
if ($service->reconciliarEditais(['DONE','CANCEL']) !== ['solicitados'=>2,'editais_alterados'=>0,'editais_com_etapas_criadas'=>0,'etapas_criadas'=>0,'editais_com_datas_calculadas'=>0,'inalterados'=>2,'nao_encontrados'=>0,'finalizados'=>0,'falhas'=>0]) throw new RuntimeException('Editais terminais não foram reportados como inalterados');
if ((int) $db->query("SELECT COUNT(*) FROM editais_subtarefas WHERE edital_id IN ('DONE','CANCEL')")->fetchColumn() !== 0) throw new RuntimeException('Reconciliação recriou etapas em edital terminal');
$db->exec("INSERT INTO editais (id,prazo_dias,data_publicacao,status) VALUES ('A',1,'2024-01-01','pendente'),('Z',1,'2024-01-01','pendente')");
$db->exec("CREATE TRIGGER falhar_segundo_edital BEFORE INSERT ON editais_subtarefas WHEN NEW.edital_id='Z' BEGIN SELECT RAISE(ABORT, 'falha sintética'); END");
try {
 $service->reconciliarEditais(['A','Z']);
 throw new RuntimeException('Falha sintética da segunda linha não foi propagada');
} catch (Throwable $expected) {
 if (!$expected instanceof PDOException) throw $expected;
}
if ((int) $db->query("SELECT COUNT(*) FROM editais_subtarefas WHERE edital_id IN ('A','Z')")->fetchColumn() !== 0) throw new RuntimeException('Falha no lote deixou etapas parcialmente criadas');
$db->exec('DROP TRIGGER falhar_segundo_edital');
$recovered = $service->reconciliarEditais(['A','Z']);
if ($recovered !== ['solicitados'=>2,'editais_alterados'=>2,'editais_com_etapas_criadas'=>2,'etapas_criadas'=>4,'editais_com_datas_calculadas'=>2,'inalterados'=>0,'nao_encontrados'=>0,'finalizados'=>0,'falhas'=>0]) throw new RuntimeException('Retry após rollback global não reportou a recuperação completa');
$retry = $service->reconciliarEditais(['A','Z']);
if ($retry !== ['solicitados'=>2,'editais_alterados'=>0,'editais_com_etapas_criadas'=>0,'etapas_criadas'=>0,'editais_com_datas_calculadas'=>0,'inalterados'=>2,'nao_encontrados'=>0,'finalizados'=>0,'falhas'=>0]) throw new RuntimeException('Retry repetido criou duplicatas ou retornou totais incorretos');

$db->exec("INSERT INTO editais (id,prazo_dias,data_publicacao,status) VALUES ('DATES',2,'2030-01-01','aguardando decurso')");
$db->exec("INSERT INTO editais_subtarefas (id,edital_id,tipo,ordem,status) VALUES ('DATES-PUB','DATES','certificacao_publicacao',3,'em andamento')");
$dates = $service->reconciliarEditais(['DATES']);
if ($dates !== ['solicitados'=>1,'editais_alterados'=>1,'editais_com_etapas_criadas'=>0,'etapas_criadas'=>0,'editais_com_datas_calculadas'=>1,'inalterados'=>0,'nao_encontrados'=>0,'finalizados'=>0,'falhas'=>0]) throw new RuntimeException('Persistência das datas calculadas não foi incluída nos totais');
$datesAfter = $db->query("SELECT data_inicio_contagem,data_decurso_prazo FROM editais WHERE id='DATES'")->fetch(PDO::FETCH_ASSOC);
if (empty($datesAfter['data_inicio_contagem']) || empty($datesAfter['data_decurso_prazo'])) throw new RuntimeException('Datas calculadas não foram persistidas');
$datesRetry = $service->reconciliarEditais(['DATES']);
if ($datesRetry['editais_alterados'] !== 0 || $datesRetry['editais_com_datas_calculadas'] !== 0 || $datesRetry['inalterados'] !== 1) throw new RuntimeException('Repetição das datas calculadas não foi classificada como inalterada');
try {
 $service->reconciliarEditais(array_map(static fn($n) => 'E' . $n, range(1, 16)));
 throw new RuntimeException('A reconciliação aceitou mais de 15 editais');
} catch (InvalidArgumentException $expected) {}
echo "PASS preservação de feriado histórico, GET sem alterações e manutenção somente por reconciliação explícita (SQLite adaptado)\n";
