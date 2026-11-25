<?php
declare(strict_types=1);
require_once __DIR__ . '/../app/services/PessoasService.php';
class DivergenciaPdo152 extends PDO {
    public function prepare(string $query, array $options = []): PDOStatement|false {
        return parent::prepare(preg_replace('/ (LIMIT 1)$/i', '', $query), $options);
    }
}
function fixtureDivergencia(): DivergenciaPdo152 {
    $p = new DivergenciaPdo152('sqlite::memory:');
    $p->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $p->sqliteCreateFunction('NOW', fn() => '2026-01-01', 0);
    $p->sqliteCreateFunction('FIELD', fn(...$args) => 1, -1);
    $p->exec("CREATE TABLE partes (id TEXT PRIMARY KEY, cpf TEXT, nome_completo TEXT, sexo TEXT, data_nascimento TEXT, profissao TEXT, created_at TEXT, updated_at TEXT); CREATE TABLE partes_documentos (id TEXT PRIMARY KEY, parte_id TEXT, tipo TEXT, numero TEXT, created_at TEXT, updated_at TEXT); INSERT INTO partes (id,cpf) VALUES ('P1','52998224725'); INSERT INTO partes_documentos (id,parte_id,tipo,numero) VALUES ('D1','P1','CPF','11144477735')");
    return $p;
}
$p = fixtureDivergencia(); $service = new PessoasService($p);
$id = $service->addDocumento('P1', ['tipo'=>'RG','numero'=>'RG-123']);
if ($p->query("SELECT cpf FROM partes WHERE id='P1'")->fetchColumn() !== '52998224725') throw new RuntimeException('Adicionar RG alterou legado');
if (!$service->deleteDocumento($id)) throw new RuntimeException('Exclusao RG recusada');
if ($p->query("SELECT cpf FROM partes WHERE id='P1'")->fetchColumn() !== '52998224725' || (int)$p->query('SELECT COUNT(*) FROM partes_documentos')->fetchColumn() !== 1) throw new RuntimeException('Exclusao RG alterou estado documental');
try { $service->deleteDocumento('D1'); throw new RuntimeException('Divergencia aceita'); } catch (InvalidArgumentException $e) {}
if ($p->query("SELECT cpf FROM partes WHERE id='P1'")->fetchColumn() !== '52998224725' || (int)$p->query("SELECT COUNT(*) FROM partes_documentos WHERE id='D1' AND numero='11144477735'")->fetchColumn() !== 1) throw new RuntimeException('Recusa alterou estado divergente');
$p = fixtureDivergencia(); $p->exec("DELETE FROM partes_documentos"); $service = new PessoasService($p);
$id = $service->addDocumento('P1', ['tipo'=>'RG','numero'=>'RG-123']);
if ($p->query("SELECT cpf FROM partes WHERE id='P1'")->fetchColumn() !== '52998224725') throw new RuntimeException('RG apagou CPF somente legado');
try { $service->addDocumento('P1', ['tipo'=>'CPF','numero'=>'11144477735']); throw new RuntimeException('CPF divergente aceito'); } catch (InvalidArgumentException $e) {}
if ($p->query("SELECT cpf FROM partes WHERE id='P1'")->fetchColumn() !== '52998224725' || (int)$p->query("SELECT COUNT(*) FROM partes_documentos WHERE tipo='CPF'")->fetchColumn() !== 0) throw new RuntimeException('CPF divergente alterou estado');
$p = fixtureDivergencia(); $p->exec("UPDATE partes SET cpf='11144477735' WHERE id='P1'"); $service = new PessoasService($p);
if (!$service->deleteDocumento('D1')) throw new RuntimeException('CPF consistente nao removido');
if ($p->query("SELECT cpf FROM partes WHERE id='P1'")->fetchColumn() !== null || (int)$p->query('SELECT COUNT(*) FROM partes_documentos')->fetchColumn() !== 0) throw new RuntimeException('Exclusao consistente nao sincronizou');
$p = fixtureDivergencia(); $p->exec("UPDATE partes SET cpf='11144477735' WHERE id='P1'; INSERT INTO partes_documentos (id,parte_id,tipo,numero) VALUES ('D2','P1','cpf','11144477735')"); $service = new PessoasService($p);
try { $service->deleteDocumento('D1'); throw new RuntimeException('CPF duplicado aceito'); } catch (InvalidArgumentException $e) {}
if ($p->query("SELECT cpf FROM partes WHERE id='P1'")->fetchColumn() !== '11144477735' || (int)$p->query('SELECT COUNT(*) FROM partes_documentos')->fetchColumn() !== 2) throw new RuntimeException('Recusa de duplicata alterou estado');
$p = fixtureDivergencia(); $p->beginTransaction();
try { (new PartesRepository($p))->setCpfLegado('P1', null, '11144477735'); throw new RuntimeException('valor anterior incorreto aceito'); } catch (RuntimeException $e) { if (!str_contains($e->getMessage(), 'alterado durante')) throw $e; }
$p->rollBack();
if ($p->query("SELECT cpf FROM partes WHERE id='P1'")->fetchColumn() !== '52998224725' || (int)$p->query('SELECT COUNT(*) FROM partes_documentos')->fetchColumn() !== 1) throw new RuntimeException('precondicao alterou estado');
$p = fixtureDivergencia(); $p->exec("UPDATE partes SET cpf='11144477735' WHERE id='P1'"); $p->beginTransaction();
try { (new PartesRepository($p))->setCpfLegado('P1', null, '11144477735'); throw new RuntimeException('limpeza com documento presente aceita'); } catch (InvalidArgumentException $e) { if (!str_contains($e->getMessage(), 'ainda presente')) throw $e; }
$p->rollBack();
if ($p->query("SELECT cpf FROM partes WHERE id='P1'")->fetchColumn() !== '11144477735' || (int)$p->query('SELECT COUNT(*) FROM partes_documentos')->fetchColumn() !== 1) throw new RuntimeException('limpeza recusada alterou estado');
// Setter direto não pode escolher documento distinto do legado de outra pessoa.
$p = fixtureDivergencia(); $p->beginTransaction();
try { (new PartesRepository($p))->setCpfLegado('P1', '11144477735', '52998224725'); throw new RuntimeException('troca divergente aceita'); } catch (InvalidArgumentException $e) {}
$p->rollBack();
if ($p->query("SELECT cpf FROM partes WHERE id='P1'")->fetchColumn() !== '52998224725' || (int)$p->query("SELECT COUNT(*) FROM partes_documentos WHERE id='D1' AND numero='11144477735'")->fetchColumn() !== 1) throw new RuntimeException('troca recusada alterou estado');
$p = fixtureDivergencia(); $p->exec("UPDATE partes SET cpf=NULL WHERE id='P1'; INSERT INTO partes (id,cpf) VALUES ('P2','111.444.777-35')"); $p->beginTransaction();
try { (new PartesRepository($p))->setCpfLegado('P1', '11144477735', null); throw new RuntimeException('CPF de terceiro aceito'); } catch (InvalidArgumentException $e) {}
$p->rollBack();
if ($p->query("SELECT cpf FROM partes WHERE id='P1'")->fetchColumn() !== null || $p->query("SELECT cpf FROM partes WHERE id='P2'")->fetchColumn() !== '111.444.777-35' || (int)$p->query("SELECT COUNT(*) FROM partes_documentos WHERE id='D1'")->fetchColumn() !== 1) throw new RuntimeException('conflito recusado alterou estado');
$p = fixtureDivergencia(); $p->exec("UPDATE partes SET cpf=NULL WHERE id='P1'"); $p->beginTransaction();
(new PartesRepository($p))->setCpfLegado('P1', '11144477735', null); $p->commit();
if ($p->query("SELECT cpf FROM partes WHERE id='P1'")->fetchColumn() !== '11144477735') throw new RuntimeException('sincronizacao consistente recusada');
$p = fixtureDivergencia(); $p->exec("UPDATE partes SET cpf=NULL WHERE id='P1'; UPDATE partes_documentos SET numero='12345678900' WHERE id='D1'"); $p->beginTransaction();
try { (new PartesRepository($p))->setCpfLegado('P1', '12345678900', null); throw new RuntimeException('CPF invalido aceito'); } catch (InvalidArgumentException $e) { if (!str_contains($e->getMessage(), 'CPF inválido')) throw $e; }
$p->rollBack();
if ($p->query("SELECT cpf FROM partes WHERE id='P1'")->fetchColumn() !== null || (int)$p->query("SELECT COUNT(*) FROM partes_documentos WHERE id='D1' AND numero='12345678900'")->fetchColumn() !== 1) throw new RuntimeException('CPF invalido alterou estado');
echo "REI-152 divergencia e preservacao: OK\n";
