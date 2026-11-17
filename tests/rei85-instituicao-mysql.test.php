<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/services/PessoasService.php';

$dsn = getenv('REI85_TEST_DSN');
if (getenv('REI85_TEST_ISOLATION') !== 'isolated-rei85-test'
    || !is_string($dsn)
    || !preg_match('/^mysql:host=127\.0\.0\.1;dbname=rei85_[a-f0-9]{32};charset=utf8mb4$/', $dsn)
    || getenv('REI85_TEST_USER') !== 'root'
    || !preg_match('/^[a-f0-9]{32}$/', (string) getenv('REI85_TEST_PASSWORD'))) {
    throw new RuntimeException('Teste permitido somente pelo runner MySQL descartável REI-85.');
}

function expectRei85(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$db = new PDO($dsn, (string) getenv('REI85_TEST_USER'), (string) getenv('REI85_TEST_PASSWORD'), [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$engine = ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';
$db->exec('DROP TABLE IF EXISTS partes_instituicoes, instituicoes_publicas, partes');
$db->exec('CREATE TABLE partes (id VARCHAR(64) PRIMARY KEY, cpf VARCHAR(32) NULL, nome_completo VARCHAR(255), sexo VARCHAR(32) NULL, data_nascimento DATE NULL, profissao VARCHAR(120) NULL, created_at DATETIME NULL, updated_at DATETIME NULL)' . $engine);
$db->exec('CREATE TABLE instituicoes_publicas (id VARCHAR(64) PRIMARY KEY, nome VARCHAR(255), nome_fantasia VARCHAR(255) NULL)' . $engine);
$db->exec('CREATE TABLE partes_instituicoes (id VARCHAR(64) PRIMARY KEY, parte_id VARCHAR(64), instituicao_id VARCHAR(64), setor_id VARCHAR(64) NULL, profissao VARCHAR(120) NULL, cargo_funcao VARCHAR(120) NULL, data_inclusao DATE, data_inicio DATE NULL, data_fim DATE NULL, ativo TINYINT NOT NULL DEFAULT 1, observacoes TEXT NULL, created_at DATETIME NULL, updated_at DATETIME NULL, UNIQUE KEY uq_pessoa_instituicao (parte_id, instituicao_id))' . $engine);
$db->exec("INSERT INTO instituicoes_publicas VALUES ('I1','Instituição 1',NULL),('I2','Instituição 2',NULL)");

$snapshot = static function () use ($db): array {
    return [
        $db->query('SELECT id, nome_completo, profissao FROM partes ORDER BY id')->fetchAll(PDO::FETCH_ASSOC),
        $db->query('SELECT id, parte_id, instituicao_id, profissao FROM partes_instituicoes ORDER BY id')->fetchAll(PDO::FETCH_ASSOC),
    ];
};
$seedPerson = static function (string $id, ?string $profession) use ($db): void {
    $db->prepare('INSERT INTO partes VALUES (?, NULL, ?, NULL, NULL, ?, NOW(), NOW())')->execute([$id, 'Pessoa ' . $id, $profession]);
};
$seedLink = static function (string $id, string $person, string $institution, ?string $profession) use ($db): void {
    $db->prepare('INSERT INTO partes_instituicoes VALUES (?, ?, ?, NULL, ?, NULL, CURRENT_DATE, NULL, NULL, 1, NULL, NOW(), NOW())')->execute([$id, $person, $institution, $profession]);
};

foreach (['add', 'update', 'delete'] as $operation) {
    $personId = 'P-' . strtoupper($operation);
    $seedPerson($personId, $operation === 'add' ? null : 'Advogado');
    if ($operation !== 'add') {
        $seedLink('V-' . strtoupper($operation), $personId, 'I1', $operation === 'delete' ? 'Advogado' : 'Outra');
    }
    if ($operation === 'delete') {
        $seedLink('V-KEEP', $personId, 'I2', 'Outra');
        $db->exec("CREATE TRIGGER fail_rei85_reconcile BEFORE UPDATE ON partes_instituicoes FOR EACH ROW BEGIN IF OLD.id = 'V-KEEP' AND NEW.profissao = 'Advogado' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'synthetic reconciliation failure'; END IF; END");
    } elseif ($operation === 'update') {
        $db->exec("CREATE TRIGGER fail_rei85_reconcile BEFORE UPDATE ON partes_instituicoes FOR EACH ROW BEGIN IF NEW.profissao = 'Advogado' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'synthetic reconciliation failure'; END IF; END");
    } else {
        $db->exec("CREATE TRIGGER fail_rei85_reconcile BEFORE UPDATE ON partes FOR EACH ROW BEGIN IF NEW.profissao = 'Medico' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'synthetic reconciliation failure'; END IF; END");
    }
    $before = $snapshot();
    $service = new PessoasService($db);
    if ($operation === 'delete') {
        expectRei85($service->deleteVinculoInstituicao('V-DELETE'), 'Exclusão válida não foi concluída quando a reconciliação global estaria bloqueada.');
        expectRei85((int) $db->query("SELECT COUNT(*) FROM partes_instituicoes WHERE id='V-DELETE'")->fetchColumn() === 0, 'Vínculo escolhido não foi removido.');
        expectRei85($db->query("SELECT profissao FROM partes WHERE id='P-DELETE'")->fetchColumn() === 'Advogado', 'Exclusão alterou a profissão global da pessoa.');
        expectRei85($db->query("SELECT profissao FROM partes_instituicoes WHERE id='V-KEEP'")->fetchColumn() === 'Outra', 'Exclusão recalculou profissão de outro vínculo.');
        expectRei85(!$db->inTransaction(), 'Transação permaneceu aberta após excluir vínculo.');
        $db->exec('DROP TRIGGER fail_rei85_reconcile');
        continue;
    }
    try {
        if ($operation === 'add') {
            $service->addVinculoInstituicao($personId, ['instituicao_id' => 'I1', 'profissao' => 'Medico']);
        } elseif ($operation === 'update') {
            $service->updateVinculoInstituicao('V-UPDATE', ['profissao' => 'Medico']);
        }
        throw new RuntimeException('Falha de sincronização não foi propagada: ' . $operation);
    } catch (PDOException $error) {
        expectRei85(str_contains($error->getMessage(), 'synthetic reconciliation failure'), 'Erro inesperado: ' . $error->getMessage());
    }
    expectRei85($snapshot() === $before, 'Operação parcial persistiu após falha: ' . $operation);
    expectRei85(!$db->inTransaction(), 'Transação permaneceu aberta: ' . $operation);
    $db->exec('DROP TRIGGER fail_rei85_reconcile');
}

$seedPerson('P-SUCCESS', null);
$service = new PessoasService($db);
$linkId = $service->addVinculoInstituicao('P-SUCCESS', ['instituicao_id' => 'I1', 'profissao' => 'Medico']);
expectRei85($linkId !== '' && $db->query("SELECT profissao FROM partes WHERE id='P-SUCCESS'")->fetchColumn() === 'Medico', 'Criação atômica não sincronizou a profissão.');
expectRei85($service->updateVinculoInstituicao($linkId, ['cargo_funcao' => 'Perito']), 'Atualização válida foi rejeitada.');
expectRei85($service->deleteVinculoInstituicao($linkId), 'Exclusão válida foi rejeitada.');
expectRei85(!$service->deleteVinculoInstituicao($linkId), 'Exclusão repetida deveria permanecer idempotente.');

$seedPerson('P-RACE', null);
$db->beginTransaction();
expectRei85($db->query("SELECT profissao FROM partes WHERE id='P-RACE'")->fetchColumn() === null, 'Falha ao fixar snapshot de profissão anterior.');
$other = new PDO($dsn, (string) getenv('REI85_TEST_USER'), (string) getenv('REI85_TEST_PASSWORD'), [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
(new PessoasService($other))->addVinculoInstituicao('P-RACE', ['instituicao_id' => 'I1', 'profissao' => 'Medico']);
$service->addVinculoInstituicao('P-RACE', ['instituicao_id' => 'I2', 'profissao' => 'Advogado']);
$db->commit();
$race = $db->query("SELECT p.profissao AS pessoa_profissao, GROUP_CONCAT(DISTINCT pi.profissao ORDER BY pi.profissao) AS vinculo_profissoes FROM partes p JOIN partes_instituicoes pi ON pi.parte_id=p.id AND pi.ativo=1 WHERE p.id='P-RACE' GROUP BY p.id")->fetch(PDO::FETCH_ASSOC);
expectRei85(($race['pessoa_profissao'] ?? null) === 'Medico' && ($race['vinculo_profissoes'] ?? null) === 'Medico', 'Reconciliação concorrente leu snapshot antigo e deixou profissões divergentes.');

$seedPerson('P-OUTER', null);
$db->exec("CREATE TRIGGER fail_rei85_outer BEFORE UPDATE ON partes FOR EACH ROW BEGIN IF NEW.profissao = 'Medico' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'synthetic outer failure'; END IF; END");
$db->beginTransaction();
$db->exec("INSERT INTO partes VALUES ('P-UNRELATED',NULL,'Preservar',NULL,NULL,NULL,NOW(),NOW())");
try {
    $service->addVinculoInstituicao('P-OUTER', ['instituicao_id' => 'I1', 'profissao' => 'Medico']);
    throw new RuntimeException('Falha externa não foi propagada.');
} catch (PDOException $error) {
    expectRei85(str_contains($error->getMessage(), 'synthetic outer failure'), 'Erro externo inesperado.');
}
expectRei85($db->inTransaction(), 'Operação fechou a transação que não iniciou.');
expectRei85((int) $db->query("SELECT COUNT(*) FROM partes_instituicoes WHERE parte_id='P-OUTER'")->fetchColumn() === 0, 'Savepoint preservou vínculo parcial.');
expectRei85((int) $db->query("SELECT COUNT(*) FROM partes WHERE id='P-UNRELATED'")->fetchColumn() === 1, 'Rollback da operação removeu escrita externa anterior.');
$db->rollBack();
$db->exec('DROP TRIGGER fail_rei85_outer');

echo "PASS REI-85 instituição: add/update/delete rollback, sucesso, repetição e savepoint externo em MySQL/InnoDB.\n";
