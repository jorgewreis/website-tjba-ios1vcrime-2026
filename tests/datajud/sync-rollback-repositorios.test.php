<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/services/ProcessoDataJudSyncService.php';
$db = new PDO('sqlite::memory:');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$db->sqliteCreateFunction('NOW', static fn(): string => '2026-01-01 00:00:00');
$db->exec('CREATE TABLE processos (id TEXT PRIMARY KEY, numero_processo TEXT, classe_id TEXT, data_fato TEXT, sigiloso INTEGER, created_at TEXT, updated_at TEXT)');
$db->exec('CREATE TABLE classes (id TEXT PRIMARY KEY, codigo TEXT UNIQUE, nome TEXT, created_at TEXT, updated_at TEXT)');
$db->exec('CREATE TABLE assuntos (id TEXT PRIMARY KEY, codigo TEXT UNIQUE, descricao TEXT, created_at TEXT, updated_at TEXT)');
$db->exec('CREATE TABLE processo_assuntos (id TEXT PRIMARY KEY, processo_id TEXT NOT NULL, assunto_id TEXT NOT NULL, origem TEXT NOT NULL, created_at TEXT, updated_at TEXT, UNIQUE(processo_id, assunto_id))');
$db->exec("INSERT INTO processos VALUES ('p1','00008323520184013202','c1',NULL,0,NULL,NULL)");
$db->exec("INSERT INTO classes VALUES ('c1','1','Anterior',NULL,NULL),('c2','2','Nova',NULL,NULL)");
$db->exec("INSERT INTO assuntos VALUES ('a1','42','Teste',NULL,NULL)");
$db->exec("CREATE TRIGGER rejeitar_vinculo BEFORE INSERT ON processo_assuntos BEGIN SELECT RAISE(ABORT, 'falha sintética'); END");
try {
    (new ProcessoDataJudSyncService($db))->sync('00008323520184013202', ['codigo'=>'2','descricao'=>'Nova'], [['codigo'=>'42','descricao'=>'Teste']]);
    throw new LogicException('Falha esperada não ocorreu.');
} catch (PDOException $expected) {}
if ($db->query("SELECT classe_id FROM processos WHERE id='p1'")->fetchColumn() !== 'c1') throw new RuntimeException('Classe não revertida.');
if ($db->query('SELECT COUNT(*) FROM processo_assuntos')->fetchColumn() != 0) throw new RuntimeException('Vínculo indevido.');
if ($db->inTransaction()) throw new RuntimeException('Transação permaneceu aberta.');
echo "PASS: falha de vínculo reverte classe usando repositórios reais em SQLite.\n";
