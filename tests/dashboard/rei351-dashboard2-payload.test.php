<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/helpers/Dashboard2Payload.php';

$summary = [
    'agenda' => ['audiencias_7d' => 0, 'dias_nao_uteis_30d' => 2],
    'geral' => ['tarefas_abertas' => 4],
];
$payload = Dashboard2Payload::fromResumo($summary);
if ($payload['agenda']['audiencias_7d'] !== null) {
    throw new RuntimeException('A contagem inclusiva D0..D+7 não pode preencher a faixa de sete dias D0..D+6.');
}
if ($payload['agenda']['dias_nao_uteis_30d'] !== 2 || $payload['geral']['tarefas_abertas'] !== 4) {
    throw new RuntimeException('O adaptador deve preservar os campos que não altera.');
}

echo "PASS Dashboard 2 bloqueia a contagem legada de agenda e preserva o restante do resumo\n";