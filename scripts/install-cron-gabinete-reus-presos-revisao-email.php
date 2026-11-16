<?php
declare(strict_types=1);

/**
 * Instala no crontab do usuario atual: quinta-feira 18:00 (Linux).
 * Nao use `php install-....sh` — isso nao executa Bash; use este arquivo .php.
 *
 * Layout canonico (config e scripts na mesma raiz, ex. .../apps):
 *   cd /path/apps && php scripts/install-cron-gabinete-reus-presos-revisao-email.php
 *
 * Deploy com um ou dois niveis de pasta public (docroot HTTP em .../public/public/):
 *   .../public/scripts ou .../public/public/scripts (config em .../public/config).
 *   cd .../public/scripts && php install-cron-gabinete-reus-presos-revisao-email.php
 *
 * Se public/scripts e public/config forem copias e git pull nao atualizar: na raiz do repo rode
 *   bash scripts/link-public-overlays-to-repo.sh
 * (cria symlinks public/scripts -> ../scripts e public/config -> ../config).
 */

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Execute apenas em linha de comando (php CLI).\n");
    exit(1);
}

require_once __DIR__ . '/resolve_project_root_cli.php';

$installDir = __DIR__;
$parentDir = dirname($installDir);
$root = resolveProjectRootFromCliScriptsDir($installDir);
$isPublicScripts = basename($installDir) === 'scripts' && basename($parentDir) === 'public';

$config = $root . '/config/config.php';
$sendScript = $installDir . '/send-gabinete-reus-presos-revisao-email.php';
if (!is_file($sendScript)) {
    $sendScript = $root . '/scripts/send-gabinete-reus-presos-revisao-email.php';
}
if (!is_file($sendScript)) {
    $sendScript = $root . '/public/scripts/send-gabinete-reus-presos-revisao-email.php';
}

$logFile = $root . '/storage/logs/reus-presos-revisao-cron.log';
$marker = '#tjba-reus-presos-revisao-email';

if ($isPublicScripts) {
    fwrite(STDERR, "Aviso: scripts sob pasta public (um ou dois niveis). Prefira nao expor scripts ao docroot.\n");
}

if (!is_file($config)) {
    fwrite(STDERR, "Erro: nao achei config/config.php em {$root} (raiz = pasta pai de scripts/; confira permissoes de leitura).\n");
    exit(1);
}
if (!is_file($sendScript)) {
    fwrite(STDERR, "Erro: nao encontrei send-gabinete-reus-presos-revisao-email.php (instalador em {$installDir}).\n");
    exit(1);
}

if (!is_dir($root . '/storage/logs')) {
    if (!mkdir($root . '/storage/logs', 0755, true) && !is_dir($root . '/storage/logs')) {
        fwrite(STDERR, "Erro: nao foi possivel criar storage/logs.\n");
        exit(1);
    }
}

$phpBin = PHP_BINARY;
if ($phpBin === '' || !is_file($phpBin)) {
    fwrite(STDERR, "Erro: PHP_BINARY invalido. Rode com o php da CLI (ex.: /usr/bin/php ...).\n");
    exit(1);
}

$line = sprintf(
    '0 18 * * 4 cd %s && %s %s >> %s 2>&1 %s',
    escapeshellarg($root),
    escapeshellarg($phpBin),
    escapeshellarg($sendScript),
    escapeshellarg($logFile),
    $marker
);

$previousLocale = getenv('LC_ALL');
putenv('LC_ALL=C');
$stdout = tmpfile();
$stderr = tmpfile();
if ($stdout === false || $stderr === false) {
    fwrite(STDERR, "Erro: nao foi possivel criar arquivos temporarios.\n");
    exit(1);
}
$process = proc_open('crontab -l', [0 => ['pipe', 'r'], 1 => $stdout, 2 => $stderr], $pipes);
if (!is_resource($process)) {
    fwrite(STDERR, "Erro: nao foi possivel iniciar leitura do crontab.\n");
    exit(1);
}
fclose($pipes[0]);
$readStatus = proc_close($process);
rewind($stdout);
rewind($stderr);
$existing = stream_get_contents($stdout);
$readError = stream_get_contents($stderr);
fclose($stdout);
fclose($stderr);
$previousLocale === false ? putenv('LC_ALL') : putenv('LC_ALL=' . $previousLocale);
if ($existing === false || $readError === false || ($readStatus !== 0 && !(
    $readStatus === 1 && $existing === '' && preg_match('/\\Ano crontab for [^\\r\\n]+\\r?\\n\\z/', $readError) === 1
))) {
    fwrite(STDERR, "Erro: nao foi possivel ler o crontab; nenhuma alteracao foi instalada.\n");
    exit(1);
}
if ($readStatus !== 0) {
    $existing = '';
}

$outLines = [];
foreach (preg_split("/\r\n|\n|\r/", rtrim($existing, "\r\n")) as $ln) {
    if ($ln === '' && $existing === '') continue;
    if (preg_match('~^0[\\t ]+18[\\t ]+\\*[\\t ]+\\*[\\t ]+4[\\t ]+cd[\\t ]+[^#]*&&[^#]*send-gabinete-reus-presos-revisao-email\\.php[^#]*>>[^#]*2>&1[\\t ]+#tjba-reus-presos-revisao-email[\\t ]*$~', $ln) === 1) {
        continue;
    }
    $outLines[] = $ln;
}
$outLines[] = $line;

$tmp = tempnam(sys_get_temp_dir(), 'tjba-cron');
if ($tmp === false) {
    fwrite(STDERR, "Erro: tempnam falhou.\n");
    exit(1);
}

$content = implode("\n", $outLines) . "\n";
if (file_put_contents($tmp, $content) === false) {
    @unlink($tmp);
    fwrite(STDERR, "Erro: nao foi possivel gravar arquivo temporario.\n");
    exit(1);
}

$ret = 0;
passthru('crontab ' . escapeshellarg($tmp) . ' 2>&1', $ret);
@unlink($tmp);

if ($ret !== 0) {
    fwrite(STDERR, "Erro: crontab retornou codigo {$ret}.\n");
    exit($ret);
}

echo "OK: crontab atualizado (quinta 18:00) -> {$sendScript}\n";
echo "Log: {$logFile}\n";
exit(0);
