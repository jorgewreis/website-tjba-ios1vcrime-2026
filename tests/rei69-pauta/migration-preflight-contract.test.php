<?php
declare(strict_types=1);
// Pure metadata contract checks: no PDO driver, DB connection, DDL or migration.
require_once __DIR__ . '/../../scripts/migrate-process-responsible-sector.php';
final class MetadataStatement extends PDOStatement {
    public function __construct(private int $mode) {}
    public function fetchColumn(int $column = 0): mixed { return $this->mode; }
}
final class MetadataPDO extends PDO {
    public function __construct(public int $mode) {}
    public function query(string $query, ?int $fetchMode = null, mixed ...$fetchModeArgs): PDOStatement|false {
        if ($query !== 'SELECT @@lower_case_table_names') throw new RuntimeException('Unexpected query');
        return new MetadataStatement($this->mode);
    }
}
function expect69migration(?string $expectedError, callable $fn): void {
    try { $fn(); } catch (RuntimeException $e) {
        if ($expectedError !== null && $e->getMessage() === $expectedError) return;
        throw new RuntimeException('Unexpected preflight failure', 0, $e);
    }
    if ($expectedError !== null) throw new RuntimeException('Expected preflight failure was not raised');
}
$fk = ['REFERENCED_TABLE_SCHEMA'=>'synthetic','REFERENCED_TABLE_NAME'=>'setores','REFERENCED_COLUMN_NAME'=>'ID_SETOR','DELETE_RULE'=>'RESTRICT','UPDATE_RULE'=>'NO ACTION'];
$assertFk = new ReflectionMethod(ProcessResponsibleSectorMigration::class, 'assertCompatibleForeignKey');
$incompatible = 'A referência existente de setor responsável não corresponde à política esperada.';
foreach ([0,1,2] as $mode) {
    $migration = new ProcessResponsibleSectorMigration(new MetadataPDO($mode));
    expect69migration(null, fn()=> $assertFk->invoke($migration, $fk, 'synthetic'));
    expect69migration($incompatible, fn()=> $assertFk->invoke($migration, array_replace($fk,['REFERENCED_TABLE_SCHEMA'=>'other']), 'synthetic'));
    expect69migration($mode === 0 ? $incompatible : null, fn()=> $assertFk->invoke($migration, array_replace($fk,['REFERENCED_TABLE_SCHEMA'=>'SYNTHETIC','REFERENCED_TABLE_NAME'=>'SETORES']), 'synthetic'));
    expect69migration($incompatible, fn()=> $assertFk->invoke($migration, array_replace($fk,['REFERENCED_COLUMN_NAME'=>'wrong']), 'synthetic'));
    expect69migration($incompatible, fn()=> $assertFk->invoke($migration, array_replace($fk,['DELETE_RULE'=>'CASCADE']), 'synthetic'));
}
$unknownModeMigration = new ProcessResponsibleSectorMigration(new MetadataPDO(3));
expect69migration('Modo de nomes de tabelas não reconhecido.', fn()=> $assertFk->invoke($unknownModeMigration, $fk, 'synthetic'));
$migration = new ProcessResponsibleSectorMigration(new MetadataPDO(0));
foreach (['isUsableIndex','isExactTargetIndex'] as $method) {
    $check = new ReflectionMethod(ProcessResponsibleSectorMigration::class, $method);
    if (!$check->invoke($migration, ['non_unique'=>1,'columns'=>['ID_SETOR_RESPONSAVEL'],'sub_parts'=>[null]])) throw new RuntimeException('Case-insensitive index was rejected');
    if ($check->invoke($migration, ['non_unique'=>1,'columns'=>['OTHER'],'sub_parts'=>[null]])) throw new RuntimeException('Wrong index was accepted');
}
echo "PASS metadata FK and index contract (simulated PDO)\n";
