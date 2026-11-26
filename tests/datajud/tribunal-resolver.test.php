<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/DataJud/DataJudTribunalResolver.php';

$states = [
    '01' => 'ac', '02' => 'al', '03' => 'ap', '04' => 'am', '05' => 'ba', '06' => 'ce', '07' => 'df', '08' => 'es', '09' => 'go', '10' => 'ma',
    '11' => 'mt', '12' => 'ms', '13' => 'mg', '14' => 'pa', '15' => 'pb', '16' => 'pr', '17' => 'pe', '18' => 'pi', '19' => 'rj', '20' => 'rn',
    '21' => 'rs', '22' => 'ro', '23' => 'rr', '24' => 'sc', '25' => 'se', '26' => 'sp', '27' => 'to',
];
$routes = [];
foreach ($states as $code => $uf) {
    $routes[] = ['8', $code, 'tj' . $uf];
    $routes[] = ['6', $code, 'tre-' . ($code === '07' ? 'dft' : $uf)];
}
for ($i = 1; $i <= 6; $i++) $routes[] = ['4', sprintf('%02d', $i), 'trf' . $i];
for ($i = 1; $i <= 24; $i++) $routes[] = ['5', sprintf('%02d', $i), 'trt' . $i];
array_push($routes,
    ['5', '00', 'tst'], ['6', '00', 'tse'], ['3', '00', 'stj'], ['7', '00', 'stm'],
    ['9', '13', 'tjmmg'], ['9', '21', 'tjmrs'], ['9', '26', 'tjmsp']
);
if (count($routes) !== 91) throw new RuntimeException('Expected 91 public tribunal endpoints.');
foreach ($routes as [$segment, $court, $expectedAlias]) {
    $digits = str_repeat('0', 13) . $segment . $court . '0000';
    $actual = DataJudTribunalResolver::resolveAliasFromDigits($digits);
    if ($actual !== $expectedAlias) throw new RuntimeException("Wrong DataJud alias for J=$segment TR=$court: " . (string) $actual);
}
foreach ([['1', '00'], ['2', '00'], ['8', '00']] as [$segment, $court]) {
    $unsupported = str_repeat('0', 13) . $segment . $court . '0000';
    if (DataJudTribunalResolver::resolveAliasFromDigits($unsupported) !== null) throw new RuntimeException('Unsupported CNJ segment was routed to a DataJud endpoint.');
}
echo "PASS: 91 public endpoints route correctly; unsupported CNJ segments are rejected.\n";
