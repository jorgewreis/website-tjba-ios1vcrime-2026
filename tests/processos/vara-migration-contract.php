<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/repositories/ProcessoVaraRepository.php';

/** Called only by the existing guarded disposable MySQL and MariaDB fixtures. */
function testVaraMigrationContract(PDO $db, string $processoId): void
{
    if (!in_array($db->query('SELECT DATABASE()')->fetchColumn(), ['rei69_pauta_test', 'datajud_test'], true)) {
        throw new RuntimeException('Migration requires a disposable test database.');
    }
    if ((new ProcessoVaraRepository($db))->disponivel()) throw new RuntimeException('Fixture already migrated.');
    $sql = file_get_contents(__DIR__ . '/../../database/migrations/20261127_add_processos_vara_nome.sql');
    if (!is_string($sql)) throw new RuntimeException('Vara migration file missing.');
    $commands = array_filter(array_map('trim', explode(';', (string) preg_replace('/^--.*$/m', '', $sql))));
    $apply = static function () use ($db, $commands): void {
        // SQL PREPARE/EXECUTE containers must use the text protocol, not
        // another nested binary prepared statement.
        $previous = (bool) $db->getAttribute(PDO::ATTR_EMULATE_PREPARES);
        $db->setAttribute(PDO::ATTR_EMULATE_PREPARES, true);
        try {
            foreach ($commands as $statement) $db->query($statement)->closeCursor();
        } finally {
            $db->setAttribute(PDO::ATTR_EMULATE_PREPARES, $previous);
        }
    };

    $apply();
    $repo = new ProcessoVaraRepository($db);
    if (!$repo->disponivel() || $repo->porProcesso($processoId) !== null) {
        throw new RuntimeException('Migration changed existing Vara information.');
    }
    if (!$db->beginTransaction()) throw new RuntimeException('Cannot begin fixture transaction.');
    $nome = ProcessoVaraRepository::normalizar('  Vara sintética  ');
    if (!$repo->registrar($processoId, $nome) || $repo->registrar($processoId, $nome)) {
        throw new RuntimeException('Local Vara save is not idempotent.');
    }
    $db->commit();
    $apply();
    if ($repo->porProcesso($processoId) !== 'Vara sintética') {
        throw new RuntimeException('Reapplying migration modified local Vara.');
    }
    if (!$db->beginTransaction()) throw new RuntimeException('Cannot begin cleanup transaction.');
    if (!$repo->registrar($processoId, ProcessoVaraRepository::normalizar(''))) {
        throw new RuntimeException('Local Vara could not be cleared.');
    }
    $db->commit();
    if ($repo->porProcesso($processoId) !== null) throw new RuntimeException('Local Vara is not nullable.');
    echo "PASS: fresh and repeated Vara migration on an isolated database.\n";
}
