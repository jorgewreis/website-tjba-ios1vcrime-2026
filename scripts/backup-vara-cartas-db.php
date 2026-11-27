<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit;
}

function dumpVaraDatabase(string $dump, string $home, string $host, int $port, string $name, string $user, string $pass): array
{
    if (!preg_match('/^[a-zA-Z0-9_][a-zA-Z0-9_-]*$/D', $name)) {
        throw new RuntimeException('Nome do banco fora do formato suportado.');
    }
    $dir = rtrim($home, '/').'/.vara-db-backups';
    umask(0077);
    if ((!is_dir($dir) && !mkdir($dir, 0700)) || is_link($dir) || (fileperms($dir) & 0777) !== 0700
        || fileowner($dir) !== fileowner($home)) {
        throw new RuntimeException('Diretório de backup não é privado.');
    }
    $id = 'vara-'.gmdate('Ymd\THis\Z').'-'.bin2hex(random_bytes(6));
    $cnf = $dir.'/.client-'.$id;
    $partial = $dir.'/'.$id.'.partial';
    $final = $dir.'/'.$id.'.sql.gz';
    $quote = static fn(string $v): string => '"'.strtr($v, ["\\"=>"\\\\",'"'=>'\\"',"\n"=>"\\n","\r"=>"\\r","\t"=>"\\t"]).'"';
    try {
        $opts = "[client]\nprotocol=tcp\nhost=".$quote($host)."\nport=".$port."\nuser=".$quote($user)."\npassword=".$quote($pass)."\n";
        if (file_put_contents($cnf, $opts) !== strlen($opts)) throw new RuntimeException('Não foi possível preparar a conexão.');
        unset($opts);
        $proc = proc_open(
            [$dump, '--defaults-file='.$cnf, '--single-transaction', '--quick', '--skip-lock-tables',
                '--routines', '--events', '--triggers', '--hex-blob', '--', $name],
            [0=>['file','/dev/null','r'], 1=>['pipe','w'], 2=>['file','/dev/null','w']], $pipes, null, ['PATH'=>'/usr/bin:/bin']
        );
        if (!is_resource($proc)) throw new RuntimeException('Não foi possível iniciar a exportação.');
        $bytes = 0;
        $tail = '';
        $valid = true;
        $out = gzopen($partial, 'wb6');
        try {
            if (!$out) throw new RuntimeException('Não foi possível abrir o arquivo de backup.');
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
            throw new RuntimeException('A exportação não terminou íntegra; nenhum backup foi publicado.');
        }
        $check = proc_open(['/usr/bin/gzip','-t',$partial], [0=>['file','/dev/null','r'],1=>['file','/dev/null','w'],2=>['file','/dev/null','w']], $unused);
        if (!is_resource($check) || proc_close($check) !== 0) throw new RuntimeException('Falha na verificação do gzip.');
        $sha = hash_file('sha256', $partial);
        if ($sha === false || !rename($partial, $final)) throw new RuntimeException('Não foi possível finalizar a cópia.');
        return ['id'=>$id, 'bytes'=>filesize($final), 'sha256'=>$sha];
    } finally {
        if (is_file($cnf)) unlink($cnf);
        if (is_file($partial)) unlink($partial);
    }
}

if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    if (($argv[1] ?? '') !== '--backup' || !is_file(getcwd().'/.env')) exit(1);
    try {
        $home = realpath((string)getenv('HOME'));
        $root = realpath((string)getcwd());
        if (!$home || !$root || $home === $root || str_starts_with($home.'/', $root.'/')) {
            throw new RuntimeException('Home privado fora da raiz publicada não identificado.');
        }
        $dump = '/usr/bin/mariadb-dump';
        if (!is_executable($dump)) $dump = '/usr/bin/mysqldump';
        if (!is_executable($dump) || !is_executable('/usr/bin/gzip')) throw new RuntimeException('Cliente de exportação ou gzip indisponível.');
        require_once $root.'/app/helpers/Conection.php';
        $db = Conection::getInstance()->getConnection();
        $count = (int)$db->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE()
          AND TABLE_NAME='cartas_expedidas' AND TABLE_TYPE='BASE TABLE' AND ENGINE='InnoDB'")->fetchColumn();
        $other = (int)$db->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE()
          AND TABLE_TYPE='BASE TABLE' AND (ENGINE IS NULL OR ENGINE<>'InnoDB')")->fetchColumn();
        if ($count !== 1 || $other !== 0) throw new RuntimeException('Alvo ou consistência transacional não confirmados.');
        unset($db);
        $backup = dumpVaraDatabase($dump, $home, DB_HOST, DB_PORT, DB_NAME, DB_USER, DB_PASS);
        echo "BACKUP OK {$backup['id']} bytes={$backup['bytes']} sha256={$backup['sha256']}\n";
    } catch (Throwable) {
        // Never emit SQL, credentials, records or database names into Actions logs.
        fwrite(STDERR, "Backup não confirmado; verificar pré-requisitos e permissões no servidor.\n");
        exit(1);
    }
}
