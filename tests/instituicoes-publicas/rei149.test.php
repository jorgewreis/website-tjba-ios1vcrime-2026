<?php
declare(strict_types=1);
require __DIR__ . '/../../app/services/InstituicoesPublicasService.php';
require __DIR__ . '/../../app/repositories/PartesInstituicoesRepository.php';
class Sqlite149 extends PDO {
    public function prepare(string $query, array $options = []): PDOStatement|false {
        $query = preg_replace('/ LIMIT 1$/', '', $query);
        return parent::prepare(str_replace('NOW()', "'2026-01-01'", $query), $options);
    }
}
$db = new Sqlite149('sqlite::memory:');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$db->exec('CREATE TABLE instituicoes_publicas (id TEXT PRIMARY KEY,nome TEXT,nome_fantasia TEXT,cnpj TEXT,observacoes TEXT,ativo INTEGER,created_at TEXT,updated_at TEXT)');
$db->exec('CREATE TABLE partes_instituicoes (id TEXT PRIMARY KEY,parte_id TEXT,instituicao_id TEXT,setor_id TEXT,profissao TEXT,cargo_funcao TEXT,data_inclusao TEXT,data_inicio TEXT,data_fim TEXT,ativo INTEGER,observacoes TEXT,created_at TEXT,updated_at TEXT)');
$db->exec("INSERT INTO instituicoes_publicas VALUES ('i1','Instituicao',NULL,NULL,NULL,1,'2026-01-01','2026-01-01')");
$db->exec("INSERT INTO partes_instituicoes VALUES ('v1','p1','i1',NULL,NULL,NULL,'2026-01-01',NULL,NULL,1,NULL,'2026-01-01','2026-01-01')");
$service = new InstituicoesPublicasService($db);
assert($service->update('missing', ['nome'=>'X']) === false);
assert($service->update('i1', ['nome'=>'Instituicao']) === true);
try { $service->update('i1', ['nome'=>'   ']); throw new RuntimeException('nome vazio aceito'); } catch (InvalidArgumentException $e) { assert(str_contains($e->getMessage(),'obrigatório')); }
assert($db->query("SELECT nome FROM instituicoes_publicas WHERE id='i1'")->fetchColumn() === 'Instituicao');
$repo = new PartesInstituicoesRepository($db);
assert($repo->update('missing', ['ativo'=>1]) === false);
assert($repo->update('v1', ['ativo'=>1]) === true);
assert($repo->update('missing', []) === false);
assert($repo->update('v1', []) === true);
echo "REI-149 repository tests passed\n";
