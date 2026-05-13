<?php
declare(strict_types=1);

/**
 * Instala no crontab do usuario atual: quinta-feira 18:00 (Linux).
 * Nao use `php install-....sh` — isso nao executa Bash; use este arquivo .php.
 *
 * Layout canonico (raiz = pasta com config/ e scripts/):
 *   cd /path/apps && php scripts/install-cron-gabinete-reus-presos-revisao-email.php
 *
 * Deploy legado (scripts dentro de public/scripts):
 *   cd /path/apps/public/scripts && php install-cron-gabinete-reus-presos-revisao-email.php
 */

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Execute apenas em linha de comando (php CLI).\n");
    exit(1);
}

$installDir = __DIR__;
$parentDir = dirname($installDir);
$isPublicScripts = basename($installDir) === 'scripts' && basename($parentDir) === 'public';
$root = $isPublicScripts ? dirname($parentDir) : $parentDir;

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
    fwrite(STDERR, "Aviso: instalador em public/scripts. Recomendado mover scripts/ para fora de public/ (seguranca).\n");
}

if (!is_file($config)) {
    fwrite(STDERR, "Erro: raiz invalida (nao achei config/config.php em {$root}).\n");
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

$existing = shell_exec('crontab -l 2>/dev/null');
$existing = is_string($existing) ? $existing : '';

$outLines = [];
foreach (preg_split("/\r\n|\n|\r/", trim($existing)) as $ln) {
    if ($ln === '') {
        continue;
    }
    if (str_contains($ln, $marker)) {
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
