<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/services/ProcessoDataJudSyncService.php';
require_once __DIR__ . '/../../app/DataJud/DataJudOrgaoMetadata.php';

final class FichaSQLite extends PDO
{
    public function prepare(string $query, array $options = []): PDOStatement|false
    {
        // Simulate SQL supported by SQLite; this fixture does not test MySQL locks.
        $query = preg_replace('/ FOR UPDATE$/', '', $query);
        if (preg_match('/^UPDATE\s/i', $query)) $query = preg_replace('/ LIMIT 1$/', '', $query);
        return parent::prepare($query, $options);
    }
}

function expectFicha(bool $fact, string $message): void
{
    if (!$fact) throw new RuntimeException($message);
}

$db = new FichaSQLite('sqlite::memory:', options: [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$db->sqliteCreateFunction('NOW', static fn () => '2026-01-01 00:00:00');
$db->exec('CREATE TABLE processos(id TEXT PRIMARY KEY, numero_processo TEXT, classe_id TEXT, data_fato TEXT, sigiloso INT, created_at TEXT, updated_at TEXT, classe_origem TEXT)');
$db->exec('CREATE TABLE classes(id TEXT PRIMARY KEY, codigo TEXT UNIQUE, nome TEXT, created_at TEXT, updated_at TEXT)');
$db->exec('CREATE TABLE assuntos(id TEXT PRIMARY KEY, codigo TEXT UNIQUE, descricao TEXT, created_at TEXT, updated_at TEXT)');
$db->exec('CREATE TABLE processo_assuntos(id TEXT PRIMARY KEY, processo_id TEXT, assunto_id TEXT, origem TEXT, created_at TEXT, updated_at TEXT, UNIQUE(processo_id, assunto_id))');
$db->exec("INSERT INTO processos VALUES ('P','00008323520184013202','C1',NULL,0,NULL,NULL,'datajud')");
$db->exec("INSERT INTO classes VALUES ('C1','1268','Classe local',NULL,NULL),('C2','2','Outra classe',NULL,NULL)");
$db->exec("INSERT INTO assuntos VALUES ('A1','42','Assunto local',NULL,NULL),('A2','44','Manual',NULL,NULL),('A3','45','Substituto',NULL,NULL)");
$db->exec("INSERT INTO processo_assuntos VALUES ('L1','P','A1','datajud',NULL,NULL),('L2','P','A2','manual',NULL,NULL)");
$service = new ProcessoDataJudSyncService($db, onlyChangedFields: true);

$before = (int) $db->query('SELECT total_changes()')->fetchColumn();
$same = $service->sync('00008323520184013202', ['codigo'=>1268,'descricao'=>'Classe remota'], [['codigo'=>42,'descricao'=>'Rótulo remoto']]);
expectFicha($same['classe']['action'] === 'noop' && $same['assuntos']['action'] === 'noop', 'Mesmos códigos devem retornar noop.');
expectFicha((int) $db->query('SELECT total_changes()')->fetchColumn() === $before, 'Sincronização igual escreveu dados.');
$newClass = $service->sync('00008323520184013202', ['codigo'=>2,'descricao'=>'Outra classe'], null);
expectFicha($newClass['classe']['action'] === 'updated' && $newClass['assuntos'] === null, 'Ausência de assunto não deve apagar dados.');
expectFicha($db->query('SELECT COUNT(*) FROM processo_assuntos')->fetchColumn() == 2, 'Lista existente foi apagada.');
$newSubject = $service->sync('00008323520184013202', null, [['codigo'=>45,'descricao'=>'Substituto']]);
expectFicha($newSubject['assuntos']['action'] === 'updated', 'Lista diferente deve atualizar.');
expectFicha($db->query('SELECT assunto_id,origem FROM processo_assuntos ORDER BY assunto_id')->fetchAll(PDO::FETCH_KEY_PAIR) === ['A2'=>'manual','A3'=>'datajud'], 'Deve substituir só vínculos DataJud e preservar os manuais.');
$db->exec("UPDATE processos SET classe_id=NULL, classe_origem=NULL WHERE id='P'; DELETE FROM processo_assuntos WHERE processo_id='P'");
$fill = $service->sync('00008323520184013202', ['codigo'=>2,'descricao'=>'Outra classe'], [['codigo'=>45,'descricao'=>'Substituto']]);
expectFicha($fill['classe']['action'] === 'updated' && $fill['assuntos']['action'] === 'updated', 'Campos vazios devem ser preenchidos.');

$repo = new ProcessosRepository($db);
$classeSvc = new ProcessoClasseSyncService($db);
$cnj = '00008323520184013202';
$blocked = static function (callable $operation, string $kind): void {
    try {
        $operation();
        throw new RuntimeException('Escrita manual não foi bloqueada.');
    } catch (ProcessoDadoProtegidoException $e) {
        expectFicha(str_contains($e->getMessage(), $kind), 'Motivo do bloqueio incorreto.');
    }
};
$blocked(static fn() => $classeSvc->updateClasseManual('P', '3', 'Não persistir no catálogo'), 'sincronizada');
$blocked(static fn() => $repo->updateClasseForProcesso('P', 'C1'), 'sincronizada');
$repo->ensureProcess($cnj, 'C1');
expectFicha($repo->findById('P')['classe_id'] === 'C2', 'Uma operação de outro módulo substituiu a classe DataJud.');
expectFicha($db->query("SELECT COUNT(*) FROM classes WHERE codigo='3'")->fetchColumn() == 0, 'Operação bloqueada alterou o catálogo.');

$db->exec("UPDATE processos SET classe_origem=NULL WHERE id='P'");
$blocked(static fn() => $classeSvc->updateClasseManual('P', '1268', null), 'registro de sua origem');
$repo->ensureProcess($cnj, 'C1');
expectFicha($repo->findById('P')['classe_id'] === 'C2' && $repo->findById('P')['classe_origem'] === null, 'Uma operação de outro módulo substituiu a classe legada ou inventou origem.');
$confirmed = $service->sync($cnj, ['codigo'=>2,'descricao'=>'Outra classe'], null);
expectFicha($confirmed['classe']['action'] === 'updated' && $repo->findById('P')['classe_origem'] === 'datajud', 'Confirmação da classe igual não registrou sua origem.');

$db->exec("UPDATE processos SET classe_id=NULL, classe_origem=NULL WHERE id='P'");
$classeSvc->updateClasseManual('P', '2', null);
expectFicha($repo->findById('P')['classe_origem'] === 'manual', 'Preenchimento manual não indicou origem.');
$repo->ensureProcess($cnj, 'C1');
expectFicha($repo->findById('P')['classe_id'] === 'C1', 'Outra operação local não conseguiu atualizar uma classe manual.');
$classeSvc->updateClasseManual('P', '2', null);
expectFicha($repo->findById('P')['classe_id'] === 'C2', 'Edição manual válida não atualizou a classe local.');
$service->sync($cnj, ['codigo'=>2,'descricao'=>'Outra classe'], null);
expectFicha($repo->findById('P')['classe_origem'] === 'datajud', 'Sincronização igual não assumiu classe antes manual.');
$service->sync($cnj, ['codigo'=>1,'descricao'=>'Classe sintética substituta'], null);
expectFicha($repo->findById('P')['classe_id'] !== 'C2' && $repo->findById('P')['classe_origem'] === 'datajud', 'Nova sincronização não conseguiu substituir a classe protegida.');

$db->exec('ALTER TABLE processos DROP COLUMN classe_origem');
$unmigrated = new ProcessosRepository($db);
$beforeMigration = $unmigrated->findById('P');
expectFicha(!$unmigrated->classeOrigemDisponivel() && $beforeMigration['classe_origem'] === null, 'Leitura sem migração deveria apresentar origem desconhecida.');
try {
    $service->sync($cnj, ['codigo'=>2,'descricao'=>'Outra classe'], [['codigo'=>44,'descricao'=>'Manual']]);
    throw new RuntimeException('Sincronização de classe permitida sem proveniência.');
} catch (ProcessoClasseOrigemIndisponivelException) {}
expectFicha($unmigrated->findById('P')['classe_id'] === $beforeMigration['classe_id'], 'Esquema antigo permitiu alteração desprotegida.');
expectFicha($service->sync($cnj, null, [['codigo'=>44,'descricao'=>'Manual']])['assuntos']['action'] === 'updated', 'Esquema antigo impediu sincronização exclusiva de assuntos.');
$db->exec('ALTER TABLE processos ADD COLUMN vara_nome TEXT');
$db->exec('ALTER TABLE processos ADD COLUMN vara_nome_datajud TEXT');
$db->exec('ALTER TABLE processos ADD COLUMN orgao_municipio_ibge_datajud TEXT');
$db->exec("UPDATE processos SET vara_nome='Vara local sintética' WHERE id='P'");
$repoVara = new ProcessoVaraRepository($db);
$meta = DataJudOrgaoMetadata::fromMapped(['nome'=>' Vara sintética A ', 'codigoMunicipioIBGE'=>9999999]);
expectFicha($meta === ['nome'=>'Vara sintética A', 'municipio_ibge'=>'9999999'], 'Metadado não normalizado.');
expectFicha(DataJudOrgaoMetadata::fromMapped(['nome'=>" inválida\n", 'codigoMunicipioIBGE'=>[1]]) === [], 'Metadado inválido entrou no sync.');
expectFicha($service->sync($cnj, null, null, orgao: $meta)['orgao']['action'] === 'updated', 'Alteração exclusiva de órgão não confirmada.');
expectFicha($service->sync($cnj, null, null, orgao: $meta)['orgao']['action'] === 'noop', 'Repetição escreveu órgão novamente.');
expectFicha($repoVara->porProcesso('P') === 'Vara local sintética', 'A origem DataJud substituiu a coluna manual.');
$incomplete = DataJudOrgaoMetadata::fromMapped(['nome'=>'Vara sintética B']);
$service->sync($cnj, null, null, orgao: $incomplete);
expectFicha($repoVara->dadosDataJudPorProcesso('P') === ['nome'=>'Vara sintética B', 'municipio_ibge'=>'9999999'], 'Campo ausente apagou o dado anterior.');
$db->beginTransaction();
try {
    $repoVara->registrar('P', null);
    throw new RuntimeException('Vara sincronizada aceitou remoção manual.');
} catch (ProcessoVaraDataJudProtegidaException) {
    $db->rollBack();
}
try {
    $service->sync($cnj, null, null, beforeWrite: static fn() => false, orgao: ['nome'=>'Não autorizado']);
    throw new LogicException('Setor recusado gravou órgão.');
} catch (RuntimeException) {}
expectFicha($repoVara->dadosDataJudPorProcesso('P')['nome'] === 'Vara sintética B', 'Recusa setorial alterou órgão.');
$db->exec("UPDATE processos SET sigiloso=1 WHERE id='P'");
try {
    $service->sync($cnj, null, null, orgao: ['nome'=>'Não público']);
    throw new LogicException('Sigilo local permitiu gravar órgão.');
} catch (DataJudLocalSigiloException) {}
$db->exec("UPDATE processos SET sigiloso=0 WHERE id='P'");
$falhaAssunto = new ProcessoDataJudSyncService($db, assuntoSync: static fn() => throw new RuntimeException('Falha sintética de assunto.'));
try {
    $falhaAssunto->sync($cnj, null, [['codigo'=>44,'descricao'=>'Manual']], orgao: ['nome'=>'Não confirmar']);
    throw new LogicException('Falha de assunto não interrompeu a transação.');
} catch (RuntimeException $erro) {
    expectFicha($erro->getMessage() === 'Falha sintética de assunto.', 'Falha inesperada.');
}
expectFicha($repoVara->dadosDataJudPorProcesso('P')['nome'] === 'Vara sintética B', 'Órgão persistiu apesar do rollback de assunto.');
echo "PASS: metadados do órgão, fallback, proteção, permissões, idempotência e rollback sintéticos.\n";
echo "PASS: precedência DataJud, idempotência, classe legada protegida e leitura sem migração (SQLite sintético).\n";
