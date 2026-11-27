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

/** Called only by the existing guarded disposable MySQL and MariaDB fixtures. */
function testNivelSigiloMigrationContract(PDO $db, string $processoId): void
{
    if (!in_array($db->query('SELECT DATABASE()')->fetchColumn(), ['rei69_pauta_test', 'datajud_test'], true)) {
        throw new RuntimeException('Migration requires a disposable test database.');
    }
    $sql = file_get_contents(__DIR__ . '/../../database/migrations/20261127_add_processos_nivel_sigilo.sql');
    if (!is_string($sql)) throw new RuntimeException('Secrecy-level migration is missing.');
    $commands = array_filter(array_map('trim', explode(';', (string) preg_replace('/^--.*$/m', '', $sql))));
    $apply = static function () use ($db, $commands): void {
        $previous = (bool) $db->getAttribute(PDO::ATTR_EMULATE_PREPARES);
        $db->setAttribute(PDO::ATTR_EMULATE_PREPARES, true);
        try {
            foreach ($commands as $statement) $db->query($statement)->closeCursor();
        } finally {
            $db->setAttribute(PDO::ATTR_EMULATE_PREPARES, $previous);
        }
    };
    $estado = $db->prepare('SELECT sigiloso FROM processos WHERE id = :id');
    $estado->execute([':id' => $processoId]);
    $sigiloso = $estado->fetchColumn();
    if ($sigiloso === false) throw new RuntimeException('Process fixture not found.');
    $estado->closeCursor();
    $get = static function () use ($db, $processoId): array {
        $query = $db->prepare('SELECT sigiloso, nivel_sigilo FROM processos WHERE id = :id');
        $query->execute([':id' => $processoId]);
        return $query->fetch(PDO::FETCH_ASSOC);
    };
    $coluna = static fn (): bool => (bool) $db->query("SELECT COUNT(*) FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'processos' AND COLUMN_NAME = 'nivel_sigilo'")->fetchColumn();
    $db->exec('SET @ficha_nivel_permitir_copia = 0');
    $modalidade = 'INSTANT';
    try {
        $apply();
    } catch (PDOException $erro) {
        if (!in_array((int) ($erro->errorInfo[1] ?? 0), [1845, 1846, 1847, 1848, 4092], true)
            || $coluna()) {
            throw $erro;
        }
        // A falha deve ser explícita e não deixar a coluna parcialmente aplicada.
        $modalidade = 'COPY explicitamente autorizado (INSTANT recusado)';
        $db->exec('SET @ficha_nivel_permitir_copia = 1');
        $apply();
    }
    if (!$coluna() || $get() !== ['sigiloso' => $sigiloso, 'nivel_sigilo' => null]) {
        throw new RuntimeException('Additive secrecy-level migration modified existing classification.');
    }
    $set = $db->prepare('UPDATE processos SET nivel_sigilo = :nivel WHERE id = :id');
    foreach ([0, 6] as $invalid) {
        try {
            $set->execute([':id' => $processoId, ':nivel' => $invalid]);
            throw new RuntimeException('Invalid secrecy level was accepted.');
        } catch (PDOException $erro) {
            if (!in_array((int) ($erro->errorInfo[1] ?? 0), [3819, 4025], true)) throw $erro;
        }
    }
    foreach ([1, 5] as $valid) {
        $set->execute([':id' => $processoId, ':nivel' => $valid]);
        if ((int) ($get()['nivel_sigilo'] ?? 0) !== $valid) throw new RuntimeException('Valid secrecy level did not persist.');
    }
    // Existing protection means the second pass is a no-op even without COPY permission.
    $db->exec('SET @ficha_nivel_permitir_copia = 0');
    $apply();
    $preservado = $get();
    if ((int) ($preservado['nivel_sigilo'] ?? 0) !== 5 || $preservado['sigiloso'] !== $sigiloso) {
        throw new RuntimeException('Repeated secrecy-level migration changed the existing classification.');
    }
    $set->execute([':id' => $processoId, ':nivel' => null]);
    echo "PASS: secrecy-level migration, database constraint and reapply ($modalidade).\n";
}
