<?php
declare(strict_types=1);
require_once __DIR__ . '/../app/repositories/CpfIdentidadeRepository.php';
$db = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$db->exec('CREATE TABLE partes (id TEXT PRIMARY KEY, cpf TEXT); CREATE TABLE partes_documentos (parte_id TEXT, tipo TEXT, numero TEXT)');
$r = new CpfIdentidadeRepository($db);
$db->exec("INSERT INTO partes VALUES ('A','529.982.247-25'), ('B',NULL)");
if ($r->findOwner('52998224725') !== 'A') throw new RuntimeException('legado pontuado');
$db->exec("INSERT INTO partes_documentos VALUES ('A',' cpf ','529 982 247 25')");
if ($r->findOwner('52998224725') !== 'A') throw new RuntimeException('mesmo dono');
$db->exec("INSERT INTO partes_documentos VALUES ('B','CPF','52998224725')");
try { $r->findOwner('52998224725'); throw new RuntimeException('conflito nao identificado'); } catch (InvalidArgumentException $e) {}
$db->exec("DELETE FROM partes_documentos WHERE parte_id='B'; UPDATE partes SET cpf='11111111111' WHERE id='A'");
try { $r->findOwner('52998224725'); throw new RuntimeException('divergencia nao identificada'); } catch (InvalidArgumentException $e) {}
$db->exec("DELETE FROM partes_documentos; INSERT INTO partes_documentos VALUES ('ORFA','CPF','52998224725')");
try { $r->findOwner('52998224725'); throw new RuntimeException('orfao nao identificado'); } catch (InvalidArgumentException $e) {}
echo "REI-152 CPF identidade sintetica: OK\n";

// Tabelas alternativas coexistem com dados conflitantes nas tabelas padrão.
require_once __DIR__ . '/../app/repositories/PartesRepository.php';
require_once __DIR__ . '/../app/repositories/PartesDocumentosRepository.php';
$db->sqliteCreateFunction('NOW', fn() => '2026-01-01', 0);
$db->sqliteCreateFunction('FIELD', fn(...$args) => 1, -1);
$db->exec('CREATE TABLE partes_alt (id TEXT PRIMARY KEY, cpf TEXT, nome_completo TEXT, sexo TEXT, data_nascimento TEXT, profissao TEXT, created_at TEXT, updated_at TEXT); CREATE TABLE docs_alt (id TEXT PRIMARY KEY, parte_id TEXT, tipo TEXT, numero TEXT, created_at TEXT, updated_at TEXT)');
$db->exec("INSERT INTO partes_alt (id,cpf,nome_completo) VALUES ('ALT','529.982.247-25','Sintetico'), ('ALT2',NULL,'Outro'); INSERT INTO partes (id,cpf) VALUES ('PADRAO','52998224725')");
$alt = new CpfIdentidadeRepository($db, 'partes_alt', 'docs_alt');
if ($alt->findOwner('52998224725') !== 'ALT') throw new RuntimeException('tabelas alternativas ignoradas');
$parts = new PartesRepository($db, 'partes_alt');
// Documentos padrão pertencem ao contrato do repositório de partes.
$db->exec("DELETE FROM partes_documentos");
if (($parts->findByCpf('52998224725')['id'] ?? null) !== 'ALT') throw new RuntimeException('repositório ignorou tabela alternativa');
$docs = new PartesDocumentosRepository($db, 'docs_alt', 'partes_alt');
$db->beginTransaction();
try { $docs->insert(['id'=>'DOCX','parte_id'=>'ALT2','tipo'=>'CPF','numero'=>'52998224725']); throw new RuntimeException('conflito em tabela alternativa aceito'); } catch (InvalidArgumentException $e) {}
$db->rollBack();
$db->beginTransaction();
$docs->insert(['id'=>'DOCY','parte_id'=>'ALT','tipo'=>'CPF','numero'=>'52998224725']);
$db->commit();
if ((int) $db->query('SELECT COUNT(*) FROM docs_alt')->fetchColumn() !== 1) throw new RuntimeException('tabela de documentos alternativa ignorada');
// Ao passar o par simultâneo, a consulta e a criação documental usam docs_alt.
$pair = new PartesRepository($db, 'partes_alt', 'docs_alt');
$db->exec("INSERT INTO partes_alt (id,cpf,nome_completo) VALUES ('DOC_ONLY',NULL,'Pessoa documental'); INSERT INTO docs_alt (id,parte_id,tipo,numero) VALUES ('DOC_ONLY_ID','DOC_ONLY','CPF','111.444.777-35')");
if (($pair->findByCpf('11144477735')['id'] ?? null) !== 'DOC_ONLY') throw new RuntimeException('busca ignorou documentos alternativos');
$db->exec("DELETE FROM docs_alt WHERE id='DOC_ONLY_ID'");
$pair->insert(['id'=>'CRIADO','cpf'=>'11144477735','nome_completo'=>'Pessoa Criada']);
if ((int) $db->query("SELECT COUNT(*) FROM docs_alt WHERE parte_id='CRIADO' AND numero='11144477735'")->fetchColumn() !== 1) throw new RuntimeException('cadastro ignorou documentos alternativos');
if ((int) $db->query("SELECT COUNT(*) FROM partes_documentos WHERE parte_id='CRIADO'")->fetchColumn() !== 0) throw new RuntimeException('cadastro escreveu na tabela padrao');
echo "REI-152 tabelas alternativas: OK\n";

// Verifica contrato de lock com driver simulado; não comprova concorrência MySQL.
class FakeMysqlLock152 extends PDO {
    public function getAttribute(int $attribute): mixed {
        return $attribute === PDO::ATTR_DRIVER_NAME ? 'mysql' : parent::getAttribute($attribute);
    }
}
$lockDb = new FakeMysqlLock152('sqlite::memory:');
$lockDb->sqliteCreateFunction('DATABASE', fn() => 'synthetic', 0);
$lockDb->sqliteCreateFunction('GET_LOCK', fn($name, $seconds) => 1, 2);
$lockDb->sqliteCreateFunction('RELEASE_LOCK', fn($name) => 1, 1);
try { ParteDocumentoLock::assertHeld($lockDb, 'CPF', '52998224725'); throw new RuntimeException('lock ausente aceito'); } catch (LogicException $e) {}
ParteDocumentoLock::run($lockDb, 'CPF', '52998224725', function () use ($lockDb): void {
    $lockDb->beginTransaction();
    ParteDocumentoLock::assertHeld($lockDb, 'CPF', '529.982.247-25');
    ParteDocumentoLock::run($lockDb, 'CPF', '52998224725', function () use ($lockDb): void { ParteDocumentoLock::assertHeld($lockDb, 'CPF', '52998224725'); });
    try { ParteDocumentoLock::assertHeld($lockDb, 'CPF', '11144477735'); throw new RuntimeException('lock diferente aceito'); } catch (LogicException $e) {}
    $lockDb->rollBack();
});
try { ParteDocumentoLock::assertHeld($lockDb, 'CPF', '52998224725'); throw new RuntimeException('lock liberado aceito'); } catch (LogicException $e) {}
$lockDb->beginTransaction();
try { ParteDocumentoLock::run($lockDb, 'CPF', '52998224725', fn() => null); throw new RuntimeException('transacao sem lock aceita'); } catch (LogicException $e) {}
$lockDb->rollBack();
echo "REI-152 contrato de lock simulado: OK\n";
