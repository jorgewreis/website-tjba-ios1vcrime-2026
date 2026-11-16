<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/services/EditaisService.php';
final class EditalListSqlite extends PDO {
 public function prepare(string $query, array $options = []): PDOStatement|false {
  // SQLite deste ambiente não aceita LIMIT em UPDATE; a cláusula WHERE identifica o edital.
  if (str_starts_with(ltrim($query), 'UPDATE ')) $query = preg_replace('/ LIMIT 1$/', '', $query);
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
$before=[]; foreach (['feriados'] as $table) $before[$table]=$db->query("SELECT * FROM $table")->fetchAll(PDO::FETCH_ASSOC);
$result=(new EditaisService($db))->listEditais([],20,0);
$info=$result['rows'][0]['DecursoInfo'];
if ($result['total'] !== 1 || $info['data_inicio_contagem'] !== '2024-01-03' || $info['data_limite_decurso'] !== '2024-01-04') throw new RuntimeException('Feriado histórico ignorado');
foreach ($before as $table=>$rows) if ($rows !== $db->query("SELECT * FROM $table")->fetchAll(PDO::FETCH_ASSOC)) throw new RuntimeException("Listagem alterou $table");
echo "PASS preservação de feriado histórico e cálculo retroativo (SQLite adaptado; GET pode alterar editais)\n";



