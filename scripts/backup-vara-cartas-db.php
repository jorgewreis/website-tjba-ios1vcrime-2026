<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit;
}

final class VaraBackupFailure extends RuntimeException
{
    public function __construct(public readonly string $reason, string $message = 'Backup não confirmado.')
    {
        parent::__construct($message);
    }
}

function varaDumpErrno(string $stderr): string
{
    return preg_match('/(?:Got error:|\bError)\s+(1[0-9]{3}|2[0-9]{3}|3[0-9]{3})\b|\((1[0-9]{3}|2[0-9]{3}|3[0-9]{3})\)\s*(?:when |$)/im', $stderr, $match)
        ? (($match[1] ?? '') !== '' ? $match[1] : $match[2])
        : 'none';
}

function classifyVaraDumpFailure(string $stderr): string
{
    $errno = varaDumpErrno($stderr);
    return match (true) {
        in_array($errno, ['1044', '1142', '1227', '1370'], true) => 'DUMP_PRIVILEGE',
        in_array($errno, ['1045', '2002', '2003', '2005', '2013'], true) => 'DUMP_CONNECTION',
        in_array($errno, ['1049', '1146'], true) => 'DUMP_SCHEMA',
        $errno === '1356' => 'DUMP_VIEW',
        preg_match('/PROCESS privilege|SHOW VIEW|SHOW CREATE|mysql[.]proc|access denied.*(table|routine)|need.*privilege/i', $stderr) === 1 => 'DUMP_PRIVILEGE',
        preg_match('/access denied for user|cannot connect|can.t connect|unknown server host|connection refused|lost connection/i', $stderr) === 1 => 'DUMP_CONNECTION',
        preg_match('/unknown (variable|option)|unrecognized option/i', $stderr) === 1 => 'DUMP_CLIENT_OPTIONS',
        default => 'DUMP_INCOMPLETE',
    };
}

function varaDumpClientOptions(string $dump): array
{
    $proc = proc_open([$dump, '--no-defaults', '--version'],
        [0=>['file','/dev/null','r'], 1=>['pipe','w'], 2=>['file','/dev/null','w']], $pipes);
    if (!is_resource($proc)) throw new VaraBackupFailure('CLIENT_VERSION');
    $version = stream_get_contents($pipes[1], 2048);
    fclose($pipes[1]);
    if (proc_close($proc) !== 0) throw new VaraBackupFailure('CLIENT_VERSION');
    if (preg_match('/Mariadb/i', (string)$version)) return [];
    if (preg_match('/mysqldump\s+Ver\s+8[.]4[.]/i', (string)$version)) {
        // Logical recovery of one database, not restoration of replication state.
        return ['--set-gtid-purged=OFF', '--column-statistics=0'];
    }
    return [];
}

function dumpVaraDatabase(string $dump, string $home, string $host, int $port, string $name, string $user, string $pass): array
{
    if (!preg_match('/^[a-zA-Z0-9_][a-zA-Z0-9_-]*$/D', $name)) {
        throw new VaraBackupFailure('DATABASE_NAME');
    }
    $dir = rtrim($home, '/').'/.vara-db-backups';
    umask(0077);
    if ((!is_dir($dir) && !mkdir($dir, 0700)) || is_link($dir) || (fileperms($dir) & 0777) !== 0700
        || fileowner($dir) !== fileowner($home)) {
        throw new VaraBackupFailure('PRIVATE_DIRECTORY');
    }
    $id = 'vara-'.gmdate('Ymd\THis\Z').'-'.bin2hex(random_bytes(6));
    $cnf = $dir.'/.client-'.$id;
    $err = $dir.'/.error-'.$id;
    $partial = $dir.'/'.$id.'.partial';
    $final = $dir.'/'.$id.'.sql.gz';
    $quote = static fn(string $v): string => '"'.strtr($v, ["\\"=>"\\\\",'"'=>'\\"',"\n"=>"\\n","\r"=>"\\r","\t"=>"\\t"]).'"';
    try {
        $opts = "[client]\nprotocol=".($host === 'localhost' ? 'socket' : 'tcp')."\nhost=".$quote($host)."\nport=".$port."\nuser=".$quote($user)."\npassword=".$quote($pass)."\n";
        if (file_put_contents($cnf, $opts) !== strlen($opts)) throw new VaraBackupFailure('CLIENT_CONFIG');
        unset($opts);
        $extra = varaDumpClientOptions($dump);
        $proc = proc_open(
            [$dump, '--defaults-file='.$cnf, '--single-transaction', '--quick', '--skip-lock-tables',
                '--routines', '--events', '--triggers', '--hex-blob', '--no-tablespaces', ...$extra, '--', $name],
            [0=>['file','/dev/null','r'], 1=>['pipe','w'], 2=>['file',$err,'w']], $pipes, null, ['PATH'=>'/usr/bin:/bin','MYSQL_TEST_LOGIN_FILE'=>$cnf.'.no-login']
        );
        if (!is_resource($proc)) throw new VaraBackupFailure('DUMP_START');
        $bytes = 0;
        $tail = '';
        $valid = true;
        $out = gzopen($partial, 'wb6');
        try {
            if (!$out) throw new VaraBackupFailure('BACKUP_OPEN');
            while (!feof($pipes[1])) {
                $chunk = fread($pipes[1], 65536);
                if ($chunk === false || gzwrite($out, $chunk) !== strlen($chunk)) {
                    $valid = false;
                    break;
                }
                $bytes += strlen($chunk);
                $tail = substr($tail.$chunk, -512);
            }
        } finally {
            fclose($pipes[1]);
            if ($out && !gzclose($out)) $valid = false;
            $exit = proc_close($proc);
        }
        if (!$valid || $exit !== 0 || $bytes === 0 || !str_contains($tail, '-- Dump completed on')) {
            $stderr = (string) @file_get_contents($err, false, null, 0, 8192);
            $reason = match (true) {
                !$valid => 'DUMP_WRITE',
                $exit === 0 && $bytes === 0 => 'DUMP_EMPTY',
                $exit === 0 => 'DUMP_FOOTER',
                default => classifyVaraDumpFailure($stderr),
            };
            $errno = varaDumpErrno($stderr);
            throw new VaraBackupFailure($reason . ' exit=' . (int)$exit . ' errno=' . $errno,
                'A exportação não terminou íntegra; nenhum backup foi publicado.');
        }
        $check = proc_open(['/usr/bin/gzip','-t',$partial], [0=>['file','/dev/null','r'],1=>['file','/dev/null','w'],2=>['file','/dev/null','w']], $unused);
        if (!is_resource($check) || proc_close($check) !== 0) throw new VaraBackupFailure('GZIP_CHECK');
        $sha = hash_file('sha256', $partial);
        if ($sha === false || !rename($partial, $final)) throw new VaraBackupFailure('BACKUP_FINALIZE');
        return ['id'=>$id, 'bytes'=>filesize($final), 'sha256'=>$sha];
    } finally {
        if (is_file($cnf)) unlink($cnf);
        if (is_file($err)) unlink($err);
        if (is_file($partial)) unlink($partial);
    }
}

if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    if (($argv[1] ?? '') !== '--backup' || !is_file(getcwd().'/.env')) exit(1);
    try {
        $home = realpath((string)getenv('HOME'));
        $root = realpath((string)getcwd());
        if (!$home || !$root || $home === $root || str_starts_with($home.'/', $root.'/')) {
            throw new VaraBackupFailure('HOME_PATH');
        }
        $dump = '/usr/bin/mariadb-dump';
        if (!is_executable($dump)) $dump = '/usr/bin/mysqldump';
        if (!is_executable($dump) || !is_executable('/usr/bin/gzip')) throw new VaraBackupFailure('CLIENT_MISSING');
        require_once $root.'/app/helpers/Conection.php';
        $db = Conection::getInstance()->getConnection();
        $count = (int)$db->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE()
          AND TABLE_NAME='cartas_expedidas' AND TABLE_TYPE='BASE TABLE' AND ENGINE='InnoDB'")->fetchColumn();
        $other = (int)$db->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE()
          AND TABLE_TYPE='BASE TABLE' AND (ENGINE IS NULL OR ENGINE<>'InnoDB')")->fetchColumn();
        if ($count !== 1) throw new VaraBackupFailure('TARGET_TABLE');
        if ($other !== 0) throw new VaraBackupFailure('NON_TRANSACTIONAL_TABLES');
        unset($db);
        $backup = dumpVaraDatabase($dump, $home, DB_HOST, DB_PORT, DB_NAME, DB_USER, DB_PASS);
        echo "BACKUP OK {$backup['id']} bytes={$backup['bytes']} sha256={$backup['sha256']}\n";
    } catch (Throwable $e) {
        // Never emit SQL, credentials, records or database names into Actions logs.
        $reason = $e instanceof VaraBackupFailure ? $e->reason : 'DATABASE_PREFLIGHT';
        fwrite(STDERR, "Backup não confirmado. Código: {$reason}\n");
        exit(1);
    }
}
