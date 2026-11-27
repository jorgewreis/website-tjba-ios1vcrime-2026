<?php
declare(strict_types=1);

require_once __DIR__.'/../../scripts/backup-vara-cartas-db.php';
require_once __DIR__.'/../../scripts/migrate-cartas-expedidas-prioridade.php';

$home = sys_get_temp_dir().'/vara-backup-fixture-'.bin2hex(random_bytes(8));
mkdir($home, 0700);
$dump = $home.'/dump';
$dir = $home.'/.vara-db-backups';
file_put_contents($dump, "#!/bin/sh\nprintf '%s\\n' 'CREATE TABLE fixture (id INT);' '-- Dump completed on fixture'\n");
chmod($dump, 0700);
try {
    $setDump = static function (string $script) use ($dump): void {
        $version = 'if [ "${2:-}" = --version ]; then echo "mysqldump  Ver 8.4.11-12 for Linux"; exit 0; fi';
        file_put_contents($dump, "#!/bin/sh\n".$version."\n".$script."\n");
    };
    $setDump('case " $* " in *" --set-gtid-purged=OFF "*) ;; *) exit 8;; esac' . "\n" .
        "printf '%s\\n' 'CREATE TABLE fixture (id INT);' '-- Dump completed on fixture'");
    $result = dumpVaraDatabase($dump, $home, '127.0.0.1', 3306, 'isolated', 'fixture', 'fake');
    $file = $dir.'/'.$result['id'].'.sql.gz';
    $data = gzdecode((string)file_get_contents($file));
    if ((fileperms($file) & 0777) !== 0600 || hash_file('sha256',$file) !== $result['sha256']
        || !str_contains((string)$data,'CREATE TABLE fixture') || count(scandir($dir)) !== 3) {
        throw new RuntimeException('Backup sintético sem integridade, privacidade ou limpeza.');
    }
    verifyVaraPriorityBackup($home, $result['id'], $result['sha256']);
    foreach ([['../other', $result['sha256'], 'BACKUP_REFERENCE'], [$result['id'], str_repeat('0',64), 'BACKUP_DIGEST']] as [$id, $hash, $reason]) {
        try {
            verifyVaraPriorityBackup($home, $id, $hash);
            throw new LogicException('Referência de backup indevida aceita.');
        } catch (RuntimeException $e) {
            if ($e->getMessage() !== $reason) throw $e;
        }
    }
    touch($file, time() - 90000);
    clearstatcache(true, $file);
    try {
        verifyVaraPriorityBackup($home, $result['id'], $result['sha256']);
        throw new LogicException('Backup antigo aceito.');
    } catch (RuntimeException $e) {
        if ($e->getMessage() !== 'BACKUP_FRESHNESS') throw $e;
    }
    if (varaDumpErrno("mysqldump: Couldn't execute 'statement': denied (1227)") !== '1227'
        || classifyVaraDumpFailure("mysqldump: Couldn't execute 'statement': invalid view (1356)") !== 'DUMP_VIEW'
        || classifyVaraDumpFailure("mysqldump: Got error: 1049: Unknown database") !== 'DUMP_SCHEMA') {
        throw new RuntimeException('Classificação sanitizada incorreta.');
    }
    $setDump("printf 'conteudo incompleto'\nexit 2");
    try {
        dumpVaraDatabase($dump, $home, '127.0.0.1', 3306, 'isolated', 'fixture', 'fake');
        throw new LogicException('Exportação incompleta foi aceita.');
    } catch (RuntimeException $e) {
        if (!str_contains($e->getMessage(),'não terminou íntegra') || count(scandir($dir)) !== 3) throw $e;
    }
    $setDump("printf '%s' 'Access denied for user SECRET_REDACT_TEST' >&2\nexit 2");
    try {
        dumpVaraDatabase($dump, $home, 'localhost', 3306, 'isolated', 'fixture', 'fake');
        throw new LogicException('Erro de conexão foi aceito.');
    } catch (VaraBackupFailure $e) {
        if (!str_starts_with($e->reason, 'DUMP_CONNECTION exit=2') || str_contains($e->getMessage(), 'SECRET_REDACT_TEST') || count(scandir($dir)) !== 3) throw $e;
    }
    echo "PASS: arquivo privado, checksum e recusa/limpeza de dump incompleto.\n";
} finally {
    if (is_dir($dir)) {
        foreach (scandir($dir) as $name) if ($name !== '.' && $name !== '..') unlink($dir.'/'.$name);
        rmdir($dir);
    }
    unlink($dump);
    rmdir($home);
}
