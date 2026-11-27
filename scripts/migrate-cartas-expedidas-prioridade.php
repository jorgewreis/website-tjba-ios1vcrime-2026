<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit;
}

/** Verify the private artifact linked to the operator's isolated restore before any DDL. */
function verifyVaraPriorityBackup(string $home, string $id, string $sha256): void
{
    if (!preg_match('/^vara-[0-9]{8}T[0-9]{6}Z-[a-f0-9]{12}$/D', $id)
        || !preg_match('/^[a-f0-9]{64}$/D', $sha256)) {
        throw new RuntimeException('BACKUP_REFERENCE');
    }
    $dir = rtrim($home, '/').'/.vara-db-backups';
    $file = $dir.'/'.$id.'.sql.gz';
    if (!is_dir($dir) || is_link($dir) || (fileperms($dir) & 0777) !== 0700
        || !is_file($file) || is_link($file) || (fileperms($file) & 0777) !== 0600
        || fileowner($file) !== fileowner($dir) || fileowner($dir) !== fileowner($home)) {
        throw new RuntimeException('BACKUP_PRIVATE_ARTIFACT');
    }
    $modified = filemtime($file);
    if ($modified === false || $modified > time() || $modified < time() - 86400) {
        throw new RuntimeException('BACKUP_FRESHNESS');
    }
    $actual = hash_file('sha256', $file);
    if ($actual === false || !hash_equals($sha256, $actual)) {
        throw new RuntimeException('BACKUP_DIGEST');
    }
}

/** Execute only with an exclusive table lock, on the project's InnoDB table. */
function migrateCartasExpedidasPrioridade(PDO $db): void
{
    $db->exec('SET SESSION lock_wait_timeout = 5');
    $db->exec('SET SESSION innodb_lock_wait_timeout = 5');
    $engine = $db->query("SELECT ENGINE FROM information_schema.TABLES
        WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='cartas_expedidas'")->fetchColumn();
    if ($engine !== 'InnoDB' || (int) $db->query('SELECT @@innodb_table_locks')->fetchColumn() !== 1) {
        throw new RuntimeException('Tabela InnoDB ou configuração de bloqueio incompatível.');
    }
    $column = static fn () => $db->query("SHOW COLUMNS FROM cartas_expedidas WHERE Field = 'prioridade_tarefa'")->fetch(PDO::FETCH_ASSOC);
    $valid = static fn (array $field): bool => $field['Type'] === "enum('baixo','medio','alto','urgente','critico')" && $field['Null'] === 'YES';
    $current = $column();
    if ($current && $valid($current) && $current['Default'] === 'medio') {
        return;
    }

    // Hold the explicit lock across both DDL changes so legacy writers cannot slip between them.
    $db->exec('LOCK TABLES cartas_expedidas WRITE');
    try {
        $current = $column();
        if ($current && (!$valid($current) || !in_array($current['Default'], [null, 'medio'], true))) {
            throw new RuntimeException('Coluna existente incompatível; nenhuma correção automática.');
        }
        if (!$current) {
            $db->exec("ALTER TABLE cartas_expedidas ADD COLUMN prioridade_tarefa
                ENUM('baixo','medio','alto','urgente','critico') NULL DEFAULT NULL, ALGORITHM=INSTANT");
        }
        if (!$current || $current['Default'] !== 'medio') {
            $db->exec("ALTER TABLE cartas_expedidas ALTER COLUMN prioridade_tarefa SET DEFAULT 'medio', ALGORITHM=INSTANT");
        }
        $verified = $column();
        if (!$verified || !$valid($verified) || $verified['Default'] !== 'medio') {
            throw new RuntimeException('Definição final da prioridade não confirmada.');
        }
    } finally {
        $db->exec('UNLOCK TABLES');
    }
}

if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    if (($argv[1] ?? '') !== '--apply' || !is_file(getcwd().'/.env')) {
        fwrite(STDERR, "Use --apply ID SHA256 --restore-confirmed na raiz da aplicação após validar alvo e recuperação.\n");
        exit(1);
    }
    try {
        $home = realpath((string) getenv('HOME'));
        $root = realpath((string) getcwd());
        if (!$home || !$root || $home === $root || str_starts_with($home.'/', $root.'/')
            || ($argv[4] ?? '') !== '--restore-confirmed') {
            throw new RuntimeException('BACKUP_RESTORE_CONFIRMATION');
        }
        verifyVaraPriorityBackup($home, (string) ($argv[2] ?? ''), (string) ($argv[3] ?? ''));
        require_once getcwd().'/app/helpers/Conection.php';
        migrateCartasExpedidasPrioridade(Conection::getInstance()->getConnection());
        fwrite(STDOUT, "OK cartas_expedidas: prioridade nullable, padrão medio verificado.\n");
    } catch (Throwable $e) {
        $code = preg_match('/^[A-Z0-9]{5}$/', (string) $e->getCode()) ? ' SQLSTATE '.$e->getCode() : '';
        $gate = in_array($e->getMessage(), ['BACKUP_RESTORE_CONFIRMATION', 'BACKUP_REFERENCE', 'BACKUP_PRIVATE_ARTIFACT', 'BACKUP_FRESHNESS', 'BACKUP_DIGEST'], true);
        fwrite(STDERR, $gate ? 'Migração bloqueada antes de abrir o banco: '.$e->getMessage()."\n"
            : "Migração não confirmada; validar estrutura antes de repetir.$code\n");
        exit(1);
    }
}
