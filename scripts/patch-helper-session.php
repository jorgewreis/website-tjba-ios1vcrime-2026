<?php
$p = dirname(__DIR__) . '/app/helpers/CartorioProdutividade.php';
$c = file_get_contents($p);
$c = str_replace("\r\n", "\n", $c);
if (strpos($c, 'mirrorEstornoSaldoSession') !== false) {
    echo "already\n";
    exit(0);
}
$ins = <<<'PHP'

    public static function mirrorEstornoSaldoSession(PDO $connection, string $referenciaItemId, string $referenciaSistema, string $motivoObs = ''): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return;
        }
        $uid = isset($_SESSION['id_usuario']) ? trim((string) $_SESSION['id_usuario']) : '';
        self::mirrorEstornoSaldo($connection, $uid, $referenciaItemId, $referenciaSistema, $motivoObs);
    }

PHP;
$c = str_replace(
    "    public static function loadRegistry(): array\n    {",
    rtrim($ins) . "\n\n    public static function loadRegistry(): array\n    {",
    $c,
    $n
);
if ($n !== 1) exit("n=$n");
file_put_contents($p, $c);
echo "helper session ok\n";
