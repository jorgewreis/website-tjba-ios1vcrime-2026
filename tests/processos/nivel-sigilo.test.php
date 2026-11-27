<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/helpers/ProcessoNivelSigilo.php';

function verificar(mixed $esperado, mixed $atual): void
{
    if ($atual !== $esperado) {
        throw new RuntimeException(var_export(['esperado' => $esperado, 'atual' => $atual], true));
    }
}

$rotulos = [1 => 'Segredo de Justiça', 2 => 'Sigilo Mínimo', 3 => 'Sigilo Médio', 4 => 'Sigilo Intenso', 5 => 'Sigilo Absoluto'];
foreach ($rotulos as $nivel => $rotulo) {
    verificar($rotulo, ProcessoNivelSigilo::rotulo(true, $nivel));
    verificar($rotulo, ProcessoNivelSigilo::rotulo(true, (string) $nivel));
    verificar(null, ProcessoNivelSigilo::rotulo(false, $nivel));
}
foreach ([null, 0, 6, '0', '5.0', true, [], ' 3', '<svg>'] as $nivel) {
    verificar(null, ProcessoNivelSigilo::classificado($nivel));
    verificar('Segredo de Justiça', ProcessoNivelSigilo::rotulo(true, $nivel));
    verificar(null, ProcessoNivelSigilo::rotulo(false, $nivel));
}
echo "Níveis, fallback legado e casos sem tag: OK\n";

require_once __DIR__ . '/../../app/repositories/ProcessoNivelSigiloRepository.php';
$db = class_exists(\Pdo\Sqlite::class) ? new \Pdo\Sqlite('sqlite::memory:') : new PDO('sqlite::memory:');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
// Catálogo sintético com o contrato do MySQL, sem dependência do servidor de produção.
$funcaoCatalogo = static fn (): string => 'teste';
if (method_exists($db, 'createFunction')) $db->createFunction('DATABASE', $funcaoCatalogo, 0);
else $db->sqliteCreateFunction('DATABASE', $funcaoCatalogo, 0);
$db->exec("ATTACH DATABASE ':memory:' AS information_schema");
$db->exec('CREATE TABLE information_schema.COLUMNS(TABLE_SCHEMA TEXT, TABLE_NAME TEXT, COLUMN_NAME TEXT)');
$db->exec('CREATE TABLE processos (id TEXT PRIMARY KEY, sigiloso INT)');
$db->exec("INSERT INTO processos VALUES ('PUBLICO', 0), ('LEGADO', 1), ('EXPLICITO', 1)");
verificar([], (new ProcessoNivelSigiloRepository($db))->porIds(['LEGADO']));
$db->exec('ALTER TABLE processos ADD COLUMN nivel_sigilo INT CHECK (nivel_sigilo BETWEEN 1 AND 5)');
$db->exec("INSERT INTO information_schema.COLUMNS VALUES ('teste', 'processos', 'nivel_sigilo')");
$repo = new ProcessoNivelSigiloRepository($db);
verificar([], $repo->porIds(['PUBLICO', 'LEGADO']));
$db->beginTransaction();
verificar(false, $repo->registrar('PUBLICO', 5));
verificar(true, $repo->registrar('EXPLICITO', 4));
verificar(false, $repo->registrar('EXPLICITO', 4));
verificar(['EXPLICITO' => 4], $repo->porIds(['PUBLICO', 'LEGADO', 'EXPLICITO']));
verificar([0,1,1], array_map('intval', $db->query('SELECT sigiloso FROM processos ORDER BY rowid')->fetchAll(PDO::FETCH_COLUMN)));
$db->rollBack();
verificar([], $repo->porIds(['EXPLICITO']));
echo "Banco sintético: migração aditiva, nível explícito, zero alterações no flag, rollback: OK\n";
