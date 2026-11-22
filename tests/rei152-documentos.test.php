<?php
declare(strict_types=1);
require_once __DIR__ . '/../app/services/PessoasService.php';
class TestPdo152 extends PDO {
 public bool $failRollback = false;
 public function rollBack(): bool { if ($this->failRollback) throw new RuntimeException("rollback simulado"); return parent::rollBack(); }
 public function prepare(string $query, array $options = []): PDOStatement|false {
  $query = preg_replace('/ (LIMIT 1)$/i', '', $query);
  return parent::prepare($query, $options);
 }
}
$p = new TestPdo152('sqlite::memory:'); $p->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$p->sqliteCreateFunction('NOW', fn() => '2026-01-01', 0);
$p->sqliteCreateFunction('FIELD', fn(...$args) => 1, -1);
$p->exec('CREATE TABLE partes (id TEXT PRIMARY KEY, cpf TEXT, updated_at TEXT); CREATE TABLE partes_documentos (id TEXT PRIMARY KEY, parte_id TEXT, tipo TEXT, numero TEXT, created_at TEXT, updated_at TEXT); INSERT INTO partes VALUES (\'P1\',NULL,NULL)');
$s = new PessoasService($p);
$p->exec("CREATE TRIGGER fail_cpf BEFORE UPDATE OF cpf ON partes BEGIN SELECT RAISE(ABORT, 'falha simulada'); END");
try { $s->addDocumento('P1', ['tipo'=>'CPF','numero'=>'52998224725']); throw new Exception('falha esperada'); } catch (PDOException $e) {}
assert((int)$p->query('SELECT COUNT(*) FROM partes_documentos')->fetchColumn() === 0);
$p->exec('DROP TRIGGER fail_cpf'); $id=$s->addDocumento('P1', ['tipo'=>'CPF','numero'=>'52998224725']);
assert($p->query('SELECT cpf FROM partes')->fetchColumn() === '52998224725');
$p->exec("CREATE TRIGGER fail_cpf BEFORE UPDATE OF cpf ON partes BEGIN SELECT RAISE(ABORT, 'falha simulada'); END");
try { $s->deleteDocumento($id); throw new Exception('falha esperada'); } catch (PDOException $e) {}
assert((int)$p->query('SELECT COUNT(*) FROM partes_documentos')->fetchColumn() === 1);
$p->exec('DROP TRIGGER fail_cpf'); assert($s->deleteDocumento($id)); assert(!$s->deleteDocumento($id));
assert((int)$p->query('SELECT COUNT(*) FROM partes_documentos')->fetchColumn() === 0); assert($p->query('SELECT cpf FROM partes')->fetchColumn() === null);
$p->beginTransaction();
try { $s->addDocumento('P1', ['tipo'=>'CPF','numero'=>'52998224725']); throw new Exception('recusa esperada'); } catch (LogicException $e) {}
assert($p->inTransaction()); assert((int)$p->query('SELECT COUNT(*) FROM partes_documentos')->fetchColumn() === 0); $p->rollBack();
$p->exec("CREATE TRIGGER fail_cpf BEFORE UPDATE OF cpf ON partes BEGIN SELECT RAISE(ABORT, 'falha simulada'); END");
$p->failRollback = true;
try { $s->addDocumento('P1', ['tipo'=>'CPF','numero'=>'52998224725']); throw new Exception('falha de rollback esperada'); } catch (RuntimeException $e) { assert(str_contains($e->getMessage(), 'estado da conexão incerto')); assert($e->getPrevious() instanceof PDOException); }
assert($p->inTransaction()); $p->failRollback = false; $p->rollBack();
echo "REI-152: testes SQLite passaram\n";
