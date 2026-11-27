<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit;
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
        fwrite(STDERR, "Use --apply na raiz da aplicação após validar alvo e recuperação.\n");
        exit(1);
    }
    try {
        require_once getcwd().'/app/helpers/Conection.php';
        migrateCartasExpedidasPrioridade(Conection::getInstance()->getConnection());
        fwrite(STDOUT, "OK cartas_expedidas: prioridade nullable, padrão medio verificado.\n");
    } catch (Throwable $e) {
        $code = preg_match('/^[A-Z0-9]{5}$/', (string) $e->getCode()) ? ' SQLSTATE '.$e->getCode() : '';
        fwrite(STDERR, "Migração não confirmada; validar estrutura antes de repetir.$code\n");
        exit(1);
    }
}
