<?php
declare(strict_types=1);

$source = file_get_contents(__DIR__ . '/../../app/services/CartasExpedidasService.php');
if (!is_string($source)) {
    throw new RuntimeException('Não foi possível ler CartasExpedidasService.');
}

$start = strpos($source, 'public function listCartas(');
$end = strpos($source, 'public function getStatusCounts(', $start === false ? 0 : $start);
if ($start === false || $end === false) {
    throw new RuntimeException('Não foi possível localizar listCartas().');
}

$method = substr($source, $start, $end - $start);
foreach (['garantirSubtarefasFluxo(', 'inferirStatusTarefaPrincipal(', '->update('] as $writeOperation) {
    if (str_contains($method, $writeOperation)) {
        throw new RuntimeException('listCartas() contém operação de reparo/escrita: ' . $writeOperation);
    }
}

foreach (['fetchList(', 'findPendenteOuEmAndamentoByCartaId(', 'findByCartaId(', 'count('] as $readOperation) {
    if (!str_contains($method, $readOperation)) {
        throw new RuntimeException('listCartas() perdeu operação de leitura esperada: ' . $readOperation);
    }
}

echo "PASS listCartas permanece leitura sem reparos/escritas implícitos.\n";
