<?php
$path = dirname(__DIR__) . '/app/services/ProdutividadeService.php';
$c = file_get_contents($path);
$c = str_replace("\r\n", "\n", $c);
if (strpos($c, 'contexto_hash_forced') !== false) {
    echo "already patched\n";
    exit(0);
}

$c = str_replace(
    '        if ($referenciaItemId !== \'\') {
            $contextoHash = hash(\'sha256\', implode(\'|\', [
                $actorUserId,
                $tipoAto,
                $tipoDocumento,
                $sistemaModulo,
                $referenciaItemId,
                date(\'Y-m-d H:i\', strtotime($dataHoraAto)),
            ]));
        }',
    '        if ($referenciaItemId !== \'\') {
            $forcedHash = trim((string) ($payload[\'contexto_hash_forced\'] ?? \'\'));
            if ($forcedHash !== \'\') {
                $contextoHash = $forcedHash;
            } else {
                $contextoHash = hash(\'sha256\', implode(\'|\', [
                    $actorUserId,
                    $tipoAto,
                    $tipoDocumento,
                    $sistemaModulo,
                    $referenciaItemId,
                    date(\'Y-m-d H:i\', strtotime($dataHoraAto)),
                ]));
            }
        }',
    $c,
    $n1
);
var_dump($n1);
