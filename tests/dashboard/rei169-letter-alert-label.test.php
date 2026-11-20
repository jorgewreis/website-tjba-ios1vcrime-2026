<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/services/DashboardResumoService.php';

function rei169Check(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function rei169SelectOldest(ReflectionMethod $selection, array $candidates): array
{
    $oldest = null;
    $label = null;
    foreach ($candidates as [$date, $candidateLabel]) {
        [$oldest, $label] = $selection->invoke(null, $oldest, $label, $date, $candidateLabel);
    }
    return [$oldest, $label];
}

$service = (new ReflectionClass(DashboardResumoService::class))->newInstanceWithoutConstructor();
$selectOldest = new ReflectionMethod(DashboardResumoService::class, 'selectOldestQueue');
$selectOldest->setAccessible(true);
$letterAlerts = new ReflectionMethod(DashboardResumoService::class, 'letterAlerts');
$letterAlerts->setAccessible(true);
$today = new DateTimeImmutable('today');
$sentDate = $today->modify('-5 days')->setTime(10, 0)->format('Y-m-d H:i:s');
$receivedDate = $today->modify('-2 days')->setTime(10, 0)->format('Y-m-d H:i:s');
$sentNewerDate = $today->modify('-2 days')->setTime(10, 0)->format('Y-m-d H:i:s');
$receivedOlderDate = $today->modify('-5 days')->setTime(10, 0)->format('Y-m-d H:i:s');
$sentLabel = 'Cartas Expedidas aguardando envio';
$receivedStage = "aguardando devolu\u{00e7}\u{00e3}o";
$receivedLabel = 'Cartas Recebidas ' . $receivedStage;

$cases = [
    ['only received return', [[$receivedDate, $receivedLabel]], $receivedDate, 'Cartas Recebidas', $receivedStage],
    ['only dispatched send', [[$sentDate, $sentLabel]], $sentDate, 'Cartas Expedidas', 'aguardando envio'],
    [
        'both queues: dispatched is older',
        [[$sentDate, $sentLabel], [$receivedDate, $receivedLabel]],
        $sentDate,
        'Cartas Expedidas',
        'aguardando envio',
    ],
    [
        'both queues: received is older',
        [[$sentNewerDate, $sentLabel], [$receivedOlderDate, $receivedLabel]],
        $receivedOlderDate,
        'Cartas Recebidas',
        $receivedStage,
    ],
    [
        'both queues: equal timestamps preserve first queue',
        [[$sentDate, $sentLabel], [$sentDate, $receivedLabel]],
        $sentDate,
        'Cartas Expedidas',
        'aguardando envio',
    ],
];

foreach ($cases as [$scenario, $candidates, $expectedDate, $expectedLabel, $expectedDetail]) {
    [$selectedDate, $selectedLabel] = rei169SelectOldest($selectOldest, $candidates);
    rei169Check(
        $selectedDate === $expectedDate && $selectedLabel === ($expectedLabel . ' ' . $expectedDetail),
        'wrong oldest queue selection: ' . $scenario
    );

    $alerts = $letterAlerts->invoke($service, $selectedDate, $selectedLabel, null, null, null, null, null);
    $expectedDays = max(
        0,
        (int)(new DateTimeImmutable($expectedDate))->setTime(0, 0)->diff(new DateTimeImmutable('today'))->format('%r%a')
    );
    rei169Check($alerts === [[
        'key' => 'fila_mais_antiga',
        'label' => $expectedLabel,
        'detail' => $expectedDetail,
        'value' => $expectedDays,
        'unit' => 'days',
        'color' => 'purple',
    ]], 'wrong alert or JSON contract: ' . $scenario);
}

[$emptyDate, $emptyLabel] = rei169SelectOldest($selectOldest, []);
rei169Check($emptyDate === null && $emptyLabel === null, 'no candidates must leave the selection empty');
$empty = $letterAlerts->invoke($service, $emptyDate, $emptyLabel, null, null, null, null, null);
rei169Check($empty === [], 'no queue must produce no alert');

echo "REI-169: timestamp-based oldest queue selection, alert labels, and empty queue passed.\n";
