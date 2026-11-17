<?php
declare(strict_types=1);

// This test is runnable only inside the dedicated, disposable runner. The
// marker is checked before connecting or creating any table/data.
$runId = (string) getenv('REI77_TEST_RUN_ID');
$marker = (string) getenv('REI77_TEST_DISPOSABLE_MARKER');
$dsn = (string) getenv('REI77_MYSQL_DSN');
$user = (string) getenv('REI77_MYSQL_USER');
if (getenv('REI77_TEST_DISPOSABLE') !== '1'
    || !preg_match('/^[a-f0-9]{32}$/', $runId)
    || !preg_match('/^[a-f0-9]{64}$/', $marker)
    || $dsn !== 'mysql:unix_socket=/var/run/mysqld/mysqld.sock;dbname=rei77_test;charset=utf8mb4'
    || $user !== 'root'
) {
    fwrite(STDERR, "FAIL CLOSED: runner disposable marker/configuration missing or invalid\n");
    exit(90);
}

require_once '/repo/app/services/SalaAudienciasAtaService.php';

$connect = static function (): PDO {
    return new PDO(
        (string) getenv('REI77_MYSQL_DSN'),
        (string) getenv('REI77_MYSQL_USER'),
        '',
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 5]
    );
};
$db = $connect();
$serverVersion = (string) $db->query('SELECT VERSION()')->fetchColumn();
if ((string) $db->query('SELECT DATABASE()')->fetchColumn() !== 'rei77_test'
    || !str_starts_with($serverVersion, '8.4.')
) {
    fwrite(STDERR, "FAIL CLOSED: expected MySQL 8.4 database rei77_test\n");
    exit(93);
}
$markerCheck = $db->prepare('SELECT COUNT(*) FROM rei77_disposable_guard WHERE run_id = :run_id AND marker = :marker');
$markerCheck->execute([':run_id' => $runId, ':marker' => $marker]);
if ((int) $markerCheck->fetchColumn() !== 1) {
    fwrite(STDERR, "FAIL CLOSED: disposable database marker was not found\n");
    exit(91);
}
$isolation = strtoupper((string) $db->query('SELECT @@transaction_isolation')->fetchColumn());
if ($isolation !== 'REPEATABLE-READ') {
    fwrite(STDERR, "FAIL CLOSED: fixture requires REPEATABLE-READ\n");
    exit(92);
}

foreach ([
    'CREATE TABLE sala_audiencias_pauta (id_pauta VARCHAR(64) NOT NULL PRIMARY KEY, ativo TINYINT NOT NULL, processo VARCHAR(64) NULL, data_audiencia DATE NOT NULL, hora_audiencia VARCHAR(8) NOT NULL, status VARCHAR(32) NOT NULL) ENGINE=InnoDB',
    'CREATE TABLE processos (id VARCHAR(64) NOT NULL PRIMARY KEY, numero_processo VARCHAR(32) NULL, classe_id VARCHAR(64) NULL, sigiloso TINYINT NULL) ENGINE=InnoDB',
    'CREATE TABLE classes (id VARCHAR(64) NOT NULL PRIMARY KEY, nome VARCHAR(128) NULL) ENGINE=InnoDB',
    'CREATE TABLE processo_prioridades (id VARCHAR(64) NOT NULL PRIMARY KEY, processo_id VARCHAR(64) NOT NULL, codigo VARCHAR(64) NOT NULL, origem VARCHAR(32) NULL, regra_detalhe TEXT NULL, created_at DATETIME NULL) ENGINE=InnoDB',
    'CREATE TABLE sala_audiencias_pauta_parte (pauta VARCHAR(64) NOT NULL, ativo TINYINT NOT NULL, maioridade VARCHAR(16) NULL) ENGINE=InnoDB',
    'CREATE TABLE sala_audiencias_estudante_inscricao (id VARCHAR(64) NOT NULL PRIMARY KEY, pauta VARCHAR(64) NOT NULL, usuario VARCHAR(64) NOT NULL, created_at DATETIME NOT NULL) ENGINE=InnoDB',
] as $sql) {
    $db->exec($sql);
}
$engineCheck = $db->query("SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='sala_audiencias_pauta'")->fetchColumn();
if (strtoupper((string) $engineCheck) !== 'INNODB') {
    throw new RuntimeException('Synthetic pauta table is not InnoDB.');
}

$insertPauta = $db->prepare('INSERT INTO sala_audiencias_pauta (id_pauta,ativo,processo,data_audiencia,hora_audiencia,status) VALUES (:id,1,NULL,:today,\'10:00\',\'designada\')');
$insertInscricao = $db->prepare('INSERT INTO sala_audiencias_estudante_inscricao (id,pauta,usuario,created_at) VALUES (:id,:pauta,:usuario,NOW())');
$makePauta = static function (string $id) use ($insertPauta): void {
    $insertPauta->execute([':id' => $id, ':today' => date('Y-m-d')]);
};
$countFor = static function (string $pauta) use ($db): int {
    $st = $db->prepare('SELECT COUNT(*) FROM sala_audiencias_estudante_inscricao WHERE pauta=:pauta');
    $st->execute([':pauta' => $pauta]);
    return (int) $st->fetchColumn();
};
$seed = static function (string $pauta, string $user, string $suffix) use ($insertInscricao): void {
    $insertInscricao->execute([':id' => 'seed-' . $suffix, ':pauta' => $pauta, ':usuario' => $user]);
};

/** Run two independent PHP/PDO workers behind a filesystem start barrier. */
$race = static function (string $pauta, array $users, string $expectedRefusal) use ($db, $runId, $marker): array {
    $barrier = '/tmp/rei77-' . $runId . '-' . bin2hex(random_bytes(8));
    if (!mkdir($barrier, 0700)) {
        throw new RuntimeException('Could not create race barrier.');
    }
    $processes = [];
    $lockOwned = false;
        $checkWorkers = static function (array &$workers, string $stage): ?string {
            foreach ($workers as &$worker) {
                $worker['stdoutText'] .= (string) stream_get_contents($worker['stdout']);
                $worker['stderrText'] .= (string) stream_get_contents($worker['stderr']);
                $status = proc_get_status($worker['process']);
                if (!$status['running']) {
                    $worker['exitCode'] = (int) $status['exitcode'];
                    $worker['stdoutText'] .= (string) stream_get_contents($worker['stdout']);
                    $worker['stderrText'] .= (string) stream_get_contents($worker['stderr']);
                    return 'Worker index=' . $worker['index']
                        . ' exited during ' . $stage
                        . ' (exit=' . $worker['exitCode']
                        . ', stdout=' . trim($worker['stdoutText'])
                        . ', stderr=' . trim($worker['stderrText']) . ').';
                }
            }
            unset($worker);
            return null;
        };
    try {
        // Hold the pauta row before launching workers. The test will release
        // it only after both independent service transactions are observed in
        // InnoDB's LOCK WAIT state, making the race deterministic.
        $db->beginTransaction();
        $lock = $db->prepare('SELECT id_pauta FROM sala_audiencias_pauta WHERE id_pauta=:pauta FOR UPDATE');
        $lock->execute([':pauta' => $pauta]);
        if ($lock->fetchColumn() === false) {
            throw new RuntimeException('Race pauta row was not found for lock barrier.');
        }
        $lockOwned = true;

        foreach (array_values($users) as $index => $usuario) {
            $command = [PHP_BINARY, '/test/rei77-inscricao-mysql-worker.php', $pauta, $usuario, $barrier, (string) $index, $runId, $marker];
            $pipes = [];
            $process = proc_open($command, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
            if (!is_resource($process)) {
                throw new RuntimeException('Could not start independent registration worker.');
            }
            fclose($pipes[0]);
            stream_set_blocking($pipes[1], false);
            stream_set_blocking($pipes[2], false);
            $processes[] = [
                'process' => $process,
                'stdout' => $pipes[1],
                'stderr' => $pipes[2],
                'stdoutText' => '',
                'stderrText' => '',
                'index' => $index,
            ];
        }

        $deadline = microtime(true) + 20;
        while (microtime(true) < $deadline) {
            $earlyExit = $checkWorkers($processes, 'ready barrier');
            if ($earlyExit !== null) {
                throw new RuntimeException($earlyExit);
            }
            if (is_file($barrier . '/ready-0') && is_file($barrier . '/ready-1')) {
                break;
            }
            usleep(20000);
        }
        if (!is_file($barrier . '/ready-0') || !is_file($barrier . '/ready-1')) {
            throw new RuntimeException('Workers did not reach the race barrier in time.');
        }
        file_put_contents($barrier . '/go', $runId . ':' . $marker, LOCK_EX);
        $deadline = microtime(true) + 20;
        while (microtime(true) < $deadline && (!is_file($barrier . '/started-0') || !is_file($barrier . '/started-1'))) {
            $earlyExit = $checkWorkers($processes, 'MySQL session announcement');
            if ($earlyExit !== null) {
                throw new RuntimeException($earlyExit);
            }
            usleep(10000);
        }
        if (!is_file($barrier . '/started-0') || !is_file($barrier . '/started-1')) {
            throw new RuntimeException('Workers did not announce their MySQL sessions.');
        }
        $threadIds = [(int) file_get_contents($barrier . '/started-0'), (int) file_get_contents($barrier . '/started-1')];
        if ($threadIds[0] < 1 || $threadIds[1] < 1 || $threadIds[0] === $threadIds[1]) {
            throw new RuntimeException('Workers do not have distinct MySQL connection IDs.');
        }
        $waitDeadline = microtime(true) + 20;
        $bothWaiting = false;
        $observedTransactions = [];
        while (microtime(true) < $waitDeadline) {
            $earlyExit = $checkWorkers($processes, 'InnoDB lock observation');
            if ($earlyExit !== null) {
                throw new RuntimeException(
                    $earlyExit . ' Worker thread IDs=[' . implode(',', $threadIds) . ']; '
                    . 'last InnoDB rows=[' . json_encode($observedTransactions, JSON_UNESCAPED_SLASHES) . '].'
                );
            }
            $wait = $db->prepare('SELECT trx_mysql_thread_id, trx_state FROM information_schema.innodb_trx WHERE trx_mysql_thread_id IN (?, ?)');
            $wait->execute($threadIds);
            $observedTransactions = $wait->fetchAll(PDO::FETCH_ASSOC) ?: [];
            $wait->closeCursor();
            $waitingIds = [];
            foreach ($observedTransactions as $transaction) {
                if (strtoupper((string) ($transaction['trx_state'] ?? '')) === 'LOCK WAIT') {
                    $waitingIds[] = (int) $transaction['trx_mysql_thread_id'];
                }
            }
            if (count(array_unique($waitingIds)) === 2
                && in_array($threadIds[0], $waitingIds, true)
                && in_array($threadIds[1], $waitingIds, true)
            ) {
                $bothWaiting = true;
                break;
            }
            usleep(10000);
        }
        if (!$bothWaiting) {
            // Diagnostic is intentionally restricted to the two synthetic
            // worker connection IDs announced through this run's barrier.
            $processlistStatement = $db->query('SHOW FULL PROCESSLIST');
            $allProcessRows = $processlistStatement->fetchAll(PDO::FETCH_ASSOC) ?: [];
            $processlistStatement->closeCursor();
            $workerIdSet = array_fill_keys($threadIds, true);
            $observedProcesses = array_values(array_filter(
                $allProcessRows,
                static fn (array $row): bool => isset($workerIdSet[(int) ($row['Id'] ?? 0)])
            ));
            foreach ($observedProcesses as &$processRow) {
                $info = preg_replace('/\s+/', ' ', trim((string) ($processRow['Info'] ?? '')));
                if (preg_match('/^SELECT\s+`?id_pauta`?\s+FROM\s+`?sala_audiencias_pauta`?\s+WHERE\s+`?id_pauta`?\s*=.+\s+FOR\s+UPDATE$/i', $info)) {
                    $processRow['Statement'] = 'pauta-row-lock-select';
                } elseif (preg_match('/^SELECT\s+.+\s+FROM\s+`?sala_audiencias_pauta`?/i', $info)) {
                    $processRow['Statement'] = 'other-pauta-select';
                } elseif ($info === '') {
                    $processRow['Statement'] = 'none';
                } else {
                    $processRow['Statement'] = 'other-statement-redacted';
                }
                // These rows are already restricted to the two CONNECTION_IDs
                // announced by workers that validated the disposable marker,
                // exact fixture DSN/database, and synthetic run IDs. Retain a
                // short SQL prefix for diagnosing the current fixture query.
                $processRow['Info'] = substr($info, 0, 300);
            }
            unset($processRow);
            $describe = static function (array $rows, array $fields): string {
                $out = [];
                foreach ($rows as $row) {
                    $parts = [];
                    foreach ($fields as $field) {
                        $parts[] = $field . '=' . (string) ($row[$field] ?? 'NULL');
                    }
                    $out[] = implode(',', $parts);
                }
                return $out === [] ? 'none' : implode('; ', $out);
            };
            throw new RuntimeException(
                'Both worker transactions were not observed waiting on the held pauta lock. '
                . 'Worker thread IDs=[' . implode(',', $threadIds) . ']; '
                . 'InnoDB=[' . $describe($observedTransactions, ['trx_mysql_thread_id', 'trx_state']) . ']; '
                . 'processlist=[' . $describe($observedProcesses, ['Id', 'Command', 'State', 'Time', 'Statement', 'Info']) . '].'
            );
        }
        $db->commit();
        $lockOwned = false;

        $results = [];
        $deadline = microtime(true) + 25;
        $finished = [false, false];
        while (microtime(true) < $deadline && (!$finished[0] || !$finished[1])) {
            foreach ($processes as $slot => &$item) {
                $item['stdoutText'] .= stream_get_contents($item['stdout']);
                $item['stderrText'] .= stream_get_contents($item['stderr']);
                $status = proc_get_status($item['process']);
                if (!$status['running'] && !$finished[$slot]) {
                    $finished[$slot] = true;
                    $item['exitCode'] = (int) $status['exitcode'];
                }
            }
            unset($item);
            if (!$finished[0] || !$finished[1]) { usleep(10000); }
        }
        if (!$finished[0] || !$finished[1]) {
            throw new RuntimeException('Race workers did not finish before timeout.');
        }
        foreach ($processes as &$item) {
            $item['stdoutText'] .= stream_get_contents($item['stdout']);
            $item['stderrText'] .= stream_get_contents($item['stderr']);
            fclose($item['stdout']);
            fclose($item['stderr']);
            proc_close($item['process']);
            if ($item['exitCode'] !== 0 || trim($item['stderrText']) !== '') {
                throw new RuntimeException('Race worker failed: ' . trim($item['stderrText']));
            }
            $result = json_decode(trim($item['stdoutText']), true, 512, JSON_THROW_ON_ERROR);
            if (!is_array($result) || !isset($result['result'])) {
                throw new RuntimeException('Race worker returned malformed output.');
            }
            $results[] = $result;
        }
        unset($item);
        $successes = array_values(array_filter($results, static fn (array $r): bool => $r['result'] === 'success'));
        $refusals = array_values(array_filter($results, static fn (array $r): bool => $r['result'] === 'refused' && $r['message'] === $expectedRefusal));
        if (count($results) !== 2 || count($successes) !== 1 || count($refusals) !== 1) {
            throw new RuntimeException('Race did not produce exactly one success and the expected single refusal.');
        }
        return $results;
    } finally {
        if ($lockOwned && $db->inTransaction()) {
            $db->rollBack();
        }
        foreach ($processes as $item) {
            if (is_resource($item['process'])) {
                $status = proc_get_status($item['process']);
                if ($status['running']) {
                    proc_terminate($item['process']);
                    $reapDeadline = microtime(true) + 2;
                    do {
                        usleep(20000);
                        $status = proc_get_status($item['process']);
                    } while ($status['running'] && microtime(true) < $reapDeadline);
                    if ($status['running']) {
                        proc_terminate($item['process'], 9);
                    }
                }
            }
            foreach (['stdout', 'stderr'] as $stream) {
                if (isset($item[$stream]) && is_resource($item[$stream])) {
                    fclose($item[$stream]);
                }
            }
            if (isset($item['process']) && is_resource($item['process'])) {
                proc_close($item['process']);
            }
        }
        foreach (glob($barrier . '/*') ?: [] as $path) {
            if (is_file($path) && !is_link($path)) {
                unlink($path);
            }
        }
        if (is_dir($barrier) && !is_link($barrier)) {
            rmdir($barrier);
        }
        if (file_exists($barrier)) {
            throw new RuntimeException('Could not prove barrier cleanup.');
        }
    }
};

$capacityPauta = 'rei77-cap-' . $runId;
$makePauta($capacityPauta);
for ($i = 1; $i <= 4; $i++) {
    $seed($capacityPauta, 'rei77-existing-' . $i, 'cap-' . $i . '-' . $runId);
}
if ($countFor($capacityPauta) !== 4) {
    throw new RuntimeException('Capacity fixture did not start with exactly four registrations.');
}
$capacityMessage = 'O limite de 5 estudantes por audiência foi atingido. Não é possível efetuar novas inscrições.';
$race($capacityPauta, ['rei77-contender-a-' . $runId, 'rei77-contender-b-' . $runId], $capacityMessage);
if ($countFor($capacityPauta) !== 5) {
    throw new RuntimeException('Capacity race did not leave exactly five registrations.');
}

$duplicatePauta = 'rei77-dup-' . $runId;
$makePauta($duplicatePauta);
if ($countFor($duplicatePauta) !== 0) {
    throw new RuntimeException('Duplicate fixture must start empty.');
}
$duplicateMessage = 'Você já está inscrito nesta audiência.';
$race($duplicatePauta, ['rei77-same-user-' . $runId, 'rei77-same-user-' . $runId], $duplicateMessage);
if ($countFor($duplicatePauta) !== 1) {
    throw new RuntimeException('Duplicate race did not leave exactly one registration.');
}

echo "PASS: InnoDB RR serializes capacity and duplicate registration races; synthetic disposable fixture only\n";
