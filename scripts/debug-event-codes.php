<?php
declare(strict_types=1);

$registry = require __DIR__ . '/../app/config/cartorio-produtividade-registry.php';
$missing = [];

$maps = [
    'cartorio-oficios.subtarefa.' => __DIR__ . '/../app/repositories/OficiosCartorioSubtarefasRepository.php',
    'cartorio-medidas-protetivas.subtarefa.' => __DIR__ . '/../app/repositories/MedidasProtetivasSubtarefasRepository.php',
    'cartorio-editais.subtarefa.' => __DIR__ . '/../app/repositories/EditaisSubtarefasRepository.php',
    'cartorio-cejusc.subtarefa.' => __DIR__ . '/../app/repositories/CejuscSubtarefasRepository.php',
    'cartorio-cartas-recebidas.subtarefa.' => __DIR__ . '/../app/repositories/CartasRecebidasSubtarefasRepository.php',
    'cartorio-cartas-expedidas.subtarefa.' => __DIR__ . '/../app/repositories/CartasExpedidasSubtarefasRepository.php',
    'cartorio-guias.subtarefa.' => __DIR__ . '/../app/repositories/GuiasSubtarefasRepository.php',
];

foreach ($maps as $prefix => $file) {
    require_once $file;
    $class = basename($file, '.php');
    if (!class_exists($class)) {
        continue;
    }
    $ref = new ReflectionClass($class);
    foreach ($ref->getConstants() as $name => $value) {
        if (!is_string($value)) {
            continue;
        }
        if (strpos($name, 'TIPO_') !== 0) {
            continue;
        }
        $event = $prefix . $value;
        if (!isset($registry[$event])) {
            $missing[] = $event;
        }
    }
}

$creates = [
    'cartorio-guias.create',
    'cartorio-oficios.create',
    'cartorio-medidas-protetivas.create',
    'cartorio-editais.create',
    'cartorio-cejusc.create',
    'cartorio-cartas-recebidas.create',
    'cartorio-cartas-expedidas.create',
    'cartorio-ceapa.create',
];
foreach ($creates as $e) {
    if (!isset($registry[$e])) {
        $missing[] = $e;
    }
}

if ($missing === []) {
    echo "event-codes: OK\n";
    exit(0);
}

echo "missing event-codes:\n";
foreach ($missing as $m) {
    echo " - {$m}\n";
}
exit(1);
