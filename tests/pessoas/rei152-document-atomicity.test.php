<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/services/PessoasService.php';

function rei152Assert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$db = new PDO('sqlite::memory:');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$db->sqliteCreateFunction('NOW', static fn (): string => date('Y-m-d H:i:s'));
$db->sqliteCreateFunction('FIELD', static function (string ...$values): int {
    $value = array_shift($values);
    $position = array_search($value, $values, true);
    return $position === false ? 0 : $position + 1;
}, -1);
$db->exec('CREATE TABLE partes (id TEXT PRIMARY KEY, cpf TEXT, nome_completo TEXT, sexo TEXT, data_nascimento TEXT, profissao TEXT, created_at TEXT, updated_at TEXT)');
$db->exec('CREATE TABLE partes_documentos (id TEXT PRIMARY KEY, parte_id TEXT, tipo TEXT, numero TEXT, created_at TEXT, updated_at TEXT)');
$db->exec("INSERT INTO partes VALUES ('p1', NULL, 'Pessoa sintetica', NULL, NULL, NULL, NULL, NULL)");
$service = new PessoasService($db);

$db->exec("CREATE TRIGGER falha_cpf BEFORE UPDATE OF cpf ON partes BEGIN SELECT RAISE(ABORT, 'falha sintetica sincronizacao CPF'); END");
try {
    $service->addDocumento('p1', ['tipo' => 'CPF', 'numero' => '52998224725']);
    throw new RuntimeException('Falha sintética de sincronização não ocorreu.');
} catch (PDOException $exception) {
    rei152Assert(str_contains($exception->getMessage(), 'falha sintetica sincronizacao CPF'), 'Falha inesperada ao incluir documento');
}
rei152Assert((int) $db->query("SELECT COUNT(*) FROM partes_documentos WHERE parte_id='p1'")->fetchColumn() === 0, 'Falha na inclusão deixou documento sem sync do CPF');
rei152Assert($db->query("SELECT cpf FROM partes WHERE id='p1'")->fetchColumn() === null, 'Falha na inclusão alterou CPF legado');
$db->exec('DROP TRIGGER falha_cpf');

$docId = $service->addDocumento('p1', ['tipo' => 'CPF', 'numero' => '52998224725']);
rei152Assert($db->query("SELECT cpf FROM partes WHERE id='p1'")->fetchColumn() === '52998224725', 'Inclusão não sincronizou CPF legado');
try {
    $service->addDocumento('p1', ['tipo' => 'CPF', 'numero' => '52998224725']);
    throw new RuntimeException('Inclusão repetida de CPF foi aceita.');
} catch (InvalidArgumentException) {
    rei152Assert((int) $db->query("SELECT COUNT(*) FROM partes_documentos WHERE parte_id='p1'")->fetchColumn() === 1, 'Retry duplicou documento');
}

$db->exec("CREATE TRIGGER falha_exclusao_cpf BEFORE UPDATE OF cpf ON partes BEGIN SELECT RAISE(ABORT, 'falha sintetica sync exclusao'); END");
try {
    $service->deleteDocumento($docId);
    throw new RuntimeException('Falha sintética da exclusão não ocorreu.');
} catch (PDOException $exception) {
    rei152Assert(str_contains($exception->getMessage(), 'falha sintetica sync exclusao'), 'Falha inesperada ao excluir documento');
}
rei152Assert((int) $db->query("SELECT COUNT(*) FROM partes_documentos WHERE id='" . $docId . "'")->fetchColumn() === 1, 'Falha na sincronização deixou exclusão parcial');
rei152Assert($db->query("SELECT cpf FROM partes WHERE id='p1'")->fetchColumn() === '52998224725', 'Falha na exclusão alterou CPF legado');
$db->exec('DROP TRIGGER falha_exclusao_cpf');
rei152Assert($service->deleteDocumento($docId), 'Exclusão válida falhou');
rei152Assert((int) $db->query("SELECT COUNT(*) FROM partes_documentos WHERE id='" . $docId . "'")->fetchColumn() === 0, 'Exclusão válida manteve documento');
rei152Assert($db->query("SELECT cpf FROM partes WHERE id='p1'")->fetchColumn() === null, 'Exclusão válida não limpou CPF legado');

echo "PASS REI-152: inclusão, exclusão, rollback e repetição (SQLite em memória)\n";
