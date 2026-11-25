<?php
declare(strict_types=1);
// CI synthetic schema test only. No application configuration or TCP connection.
$stage='guard';
set_exception_handler(static function(Throwable $e) use (&$stage): void {
    $allowed=['guard','connect','identity','sentinel','second_schema','fixture_ddl','cross_fk','migration','idempotence','full_ddl'];
    $code=in_array($stage,$allowed,true)?$stage:'unknown';
    fwrite(STDERR,"REI69_FIXTURE_FAILURE code=$code\n");
    exit(1);
});
$schema=(string)getenv('REI69PAUTA_SCHEMA');
$other=(string)getenv('REI69PAUTA_OTHER_SCHEMA');
$marker=(string)getenv('REI69PAUTA_TEST_DISPOSABLE_MARKER');
$dsn="mysql:unix_socket=/var/run/mysqld/mysqld.sock;dbname=$schema;charset=utf8mb4";
if (getenv('REI69PAUTA_TEST_DISPOSABLE')!=='1' || getenv('REI69PAUTA_TEST_REPO_ROOT')!=='/repo'
    || !preg_match('/^rei69m_[a-f0-9]{32}$/',$schema) || !preg_match('/^rei69x_[a-f0-9]{32}$/',$other)
    || substr($schema,7)!==substr($other,7) || !preg_match('/^[a-f0-9]{64}$/',$marker)
    || getenv('REI69PAUTA_MYSQL_DSN')!==$dsn) throw new RuntimeException('Runner identity invalid');
$stage='connect';
$pdo=new PDO($dsn,'root','',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_EMULATE_PREPARES=>false]);
$stage='identity';
if ($pdo->query('SELECT DATABASE()')->fetchColumn()!==$schema
    || !str_starts_with((string)$pdo->query('SELECT VERSION()')->fetchColumn(),'8.4.')
    || (string)$pdo->query('SELECT @@lower_case_table_names')->fetchColumn()!==getenv('REI69PAUTA_EXPECT_LOWER_CASE')
    || $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN)!==['rei69_pauta_test_guard']) throw new RuntimeException('Unexpected schema identity');
$stage='sentinel';
$markers=$pdo->query('SELECT marker FROM rei69_pauta_test_guard')->fetchAll(PDO::FETCH_COLUMN);
if (count($markers)!==1 || !hash_equals($marker,(string)$markers[0])) throw new RuntimeException('Sentinel mismatch');
$stage='second_schema';
$find=$pdo->prepare('SELECT COUNT(*) FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME=:schema');
$find->execute([':schema'=>$other]);
if ((int)$find->fetchColumn()!==0) throw new RuntimeException('Second schema already exists');
$pdo->exec("CREATE DATABASE `$other` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
$otherMarker=hash('sha256','rei69-other-'.$marker);
$pdo->exec("CREATE TABLE `$other`.`rei69_other_guard` (marker CHAR(64) NOT NULL PRIMARY KEY) ENGINE=InnoDB");
$otherGuard=$pdo->prepare("INSERT INTO `$other`.`rei69_other_guard` VALUES (?)");
$otherGuard->execute([$otherMarker]);
if (!hash_equals($otherMarker,(string)$pdo->query("SELECT marker FROM `$other`.`rei69_other_guard`")->fetchColumn())) throw new RuntimeException('Second sentinel mismatch');
$stage='fixture_ddl';
$pdo->exec("CREATE TABLE `$other`.`setores` (id_setor CHAR(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL PRIMARY KEY) ENGINE=InnoDB");
$pdo->exec('CREATE TABLE setores (ID_SETOR CHAR(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL PRIMARY KEY) ENGINE=InnoDB');
$pdo->exec('CREATE TABLE processos (id CHAR(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL PRIMARY KEY, ID_SETOR_RESPONSAVEL CHAR(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL DEFAULT NULL, KEY IDX_PROCESSOS_ID_SETOR_RESPONSAVEL (ID_SETOR_RESPONSAVEL)) ENGINE=InnoDB');
$pdo->exec("INSERT INTO processos (id,ID_SETOR_RESPONSAVEL) VALUES ('LEGADO-SINTETICO',NULL)");
$pdo->exec("ALTER TABLE processos ADD CONSTRAINT fk_cross_fixture FOREIGN KEY (id_setor_responsavel) REFERENCES `$other`.`setores` (id_setor) ON DELETE RESTRICT ON UPDATE RESTRICT");
require_once '/repo/scripts/migrate-process-responsible-sector.php';
$migration=new ProcessResponsibleSectorMigration($pdo);
$stage='cross_fk';
$beforeCreate=$pdo->query('SHOW CREATE TABLE processos')->fetch(PDO::FETCH_NUM)[1];
$beforeLegacy=$pdo->query('SELECT id,ID_SETOR_RESPONSAVEL FROM processos ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
try {
    $migration->preflight();
    throw new LogicException('Cross-schema FK was accepted');
} catch (RuntimeException $e) {
    if ($e->getMessage()!=='A referência existente de setor responsável não corresponde à política esperada.') throw new LogicException('Unexpected preflight error',0,$e);
}
if ($pdo->query('SHOW CREATE TABLE processos')->fetch(PDO::FETCH_NUM)[1]!==$beforeCreate
    || $pdo->query('SELECT id,ID_SETOR_RESPONSAVEL FROM processos ORDER BY id')->fetchAll(PDO::FETCH_ASSOC)!==$beforeLegacy) throw new RuntimeException('Rejected preflight changed schema or rows');
$pdo->exec('ALTER TABLE processos DROP FOREIGN KEY fk_cross_fixture');
$stage='migration';
$plan=$migration->preflight();
if (($plan['add_foreign_key']??null)!==true) throw new RuntimeException('Expected FK was not planned');
$result=$migration->migrate(false);
if ($result!==['add_column'=>false,'add_index'=>false,'add_foreign_key'=>false]) throw new RuntimeException('Expected FK migration not applied');
$stage='idempotence';
$again=$migration->migrate(false);
if ($again!==['add_column'=>false,'add_index'=>false,'add_foreign_key'=>false]) throw new RuntimeException('Migration not idempotent');
if ($pdo->query('SELECT id,ID_SETOR_RESPONSAVEL FROM processos ORDER BY id')->fetchAll(PDO::FETCH_ASSOC)!==$beforeLegacy) throw new RuntimeException('Migration changed legacy row');
$stage='full_ddl';
$pdo->exec('ALTER TABLE processos DROP FOREIGN KEY fk_processos_setor_responsavel, DROP INDEX IDX_PROCESSOS_ID_SETOR_RESPONSAVEL, DROP COLUMN ID_SETOR_RESPONSAVEL');
$fullPlan=$migration->preflight();
if ($fullPlan!==['add_column'=>true,'add_index'=>true,'add_foreign_key'=>true]) throw new RuntimeException('Full migration was not planned');
$fullResult=$migration->migrate(false);
if ($fullResult!==['add_column'=>false,'add_index'=>false,'add_foreign_key'=>false]
    || $pdo->query('SELECT id,id_setor_responsavel FROM processos ORDER BY id')->fetchAll(PDO::FETCH_ASSOC)!==[['id'=>'LEGADO-SINTETICO','id_setor_responsavel'=>null]]) throw new RuntimeException('Full migration altered legacy row or did not finish');
if ($migration->migrate(false)!==['add_column'=>false,'add_index'=>false,'add_foreign_key'=>false]) throw new RuntimeException('Full migration was not idempotent');
if (!hash_equals($marker,(string)$pdo->query('SELECT marker FROM rei69_pauta_test_guard')->fetchColumn())
    || !hash_equals($otherMarker,(string)$pdo->query("SELECT marker FROM `$other`.`rei69_other_guard`")->fetchColumn())) throw new RuntimeException('Sentinel changed');
echo "PASS REI69 migration synthetic MySQL contract\n";
