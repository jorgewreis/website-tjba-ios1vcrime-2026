<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/services/ProcessoDataJudBackgroundWorker.php';

$valid = [
    ['codigo' => '1268', 'descricao' => 'Medida protetiva'],
    ['codigo' => 1268, 'nome' => 'Medida protetiva'],
    ['codigo' => null, 'descricao' => null],
];
foreach ($valid as $payload) {
    if (!ProcessoDataJudBackgroundWorker::isValidClassPayload($payload)) {
        throw new RuntimeException('A valid class payload was rejected.');
    }
}

$invalid = [
    ['codigo' => true, 'descricao' => 'Medida protetiva'],
    ['codigo' => false, 'descricao' => 'Medida protetiva'],
    ['codigo' => 'MEDIDA', 'descricao' => 'Medida protetiva'],
    ['codigo' => ['1268'], 'descricao' => 'Medida protetiva'],
    ['codigo' => '1268', 'descricao' => ['Medida protetiva']],
    ['codigo' => '1268', 'nome' => ['Medida protetiva']],
];
foreach ($invalid as $payload) {
    if (ProcessoDataJudBackgroundWorker::isValidClassPayload($payload)) {
        throw new RuntimeException('A malformed class payload was accepted.');
    }
}

echo "PASS: DataJud class payload rejects booleans and arrays before casting.\n";
