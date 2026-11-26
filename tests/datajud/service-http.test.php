<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/services/DataJudService.php';

$number = '0000001-69.2020.8.05.0001';
$response = static fn(int $status, $body, string $error = ''): array => ['http_code' => $status, 'decoded' => $body, 'curl_error' => $error, 'raw' => ''];
$empty = ['timed_out' => false, '_shards' => ['total' => 1, 'successful' => 1, 'failed' => 0, 'skipped' => 0], 'hits' => ['total' => ['value' => 0, 'relation' => 'eq'], 'hits' => []]];
$make = static function (array $responses, int &$calls): DataJudService {
    return new DataJudService(['default_alias' => 'tjsp'], static function () use (&$responses, &$calls): array {
        $calls++;
        if (!$responses) throw new LogicException('Unexpected request');
        return array_shift($responses);
    });
};
$cnjFor = static function (string $segment, string $court): string {
    $base = '0000000' . '2020' . $segment . $court . '0000';
    $remainder = 0;
    foreach (str_split($base . '00') as $digit) $remainder = (($remainder * 10) + (int) $digit) % 97;
    $checkDigits = str_pad((string) (98 - $remainder), 2, '0', STR_PAD_LEFT);
    return substr($base, 0, 7) . $checkDigits . substr($base, 7);
};
foreach ([400, 401, 403, 404, 429, 500, 302, 0] as $status) {
    $calls = 0;
    $service = $make([$response($status, $empty)], $calls);
    try { $service->searchProcesso($number); throw new LogicException("HTTP $status interpreted as absence"); }
    catch (RuntimeException $e) { if ($calls !== 1) throw new LogicException('Failure must not trigger fallback'); }
}
$calls = 0;
try {
    $make([], $calls)->searchProcesso($cnjFor('1', '00'));
    throw new LogicException('Unsupported STF route was accepted');
} catch (InvalidArgumentException $e) {
    if ($calls !== 0) throw new LogicException('Unsupported tribunal triggered an external request');
}
foreach ([null, [], ['hits' => ['hits' => 'invalid']], ['hits' => ['hits' => [[]]]],
    ['hits' => ['hits' => [['_source' => []]]]],
    ['hits' => ['hits' => [['_source' => ['numeroProcesso' => '00000025420208050001']]]]],
    ['timed_out' => true, 'hits' => ['hits' => []]],
    ['timed_out' => 'false', 'hits' => ['hits' => []]],
    ['_shards' => ['failed' => 1], 'hits' => ['hits' => []]],
    ['_shards' => [], 'hits' => ['hits' => []]],
] as $invalid) {
    $calls = 0;
    try { $make([$response(200, $invalid)], $calls)->searchProcesso($number); throw new LogicException('Invalid response interpreted as absence'); }
    catch (RuntimeException $e) { if ($calls !== 1) throw new LogicException('Invalid response triggered fallback'); }
}
$calls = 0;
$result = $make([$response(200, $empty)], $calls)->searchProcesso($number);
if ($calls !== 1 || empty($result['indisponivel_na_base_publica'])) throw new LogicException('Valid empty result failed');
$calls = 0;
$targetUrl = '';
$service = new DataJudService(['default_alias' => 'tjba'], static function (string $url) use (&$calls, &$targetUrl, $response, $empty): array {
    $calls++; $targetUrl = $url; return $response(200, $empty);
});
$service->searchProcesso($number);
if ($calls !== 1 || !str_contains($targetUrl, '/api_publica_tjba/')) throw new LogicException('Consulta local usou tribunal incorreto ou fallback');
$calls = 0;
try { $make([$response(0, null, 'synthetic-sensitive-marker')], $calls)->searchProcesso($number); throw new LogicException('Transport error interpreted as absence'); }
catch (RuntimeException $e) { if ($calls !== 1 || str_contains($e->getMessage(), 'synthetic-sensitive-marker')) throw new LogicException('Transport error triggered fallback or exposed details'); }
$calls = 0;
$source = ['numeroProcesso' => '00000016920208050001', 'tribunal' => 'TJBA', 'nivelSigilo' => 0];
$found = $make([$response(200, ['timed_out' => false, '_shards' => ['total' => 2, 'successful' => 2, 'failed' => 0, 'skipped' => 1], 'hits' => ['total' => ['value' => 1, 'relation' => 'eq'], 'hits' => [['_source' => $source]]]])], $calls)->searchProcesso($number);
if ($calls !== 1 || ($found['tribunal'] ?? null) !== 'TJBA' || !empty($found['indisponivel_na_base_publica']) || ($found['resultado_unico_verificado'] ?? false) !== true) throw new LogicException('Valid hit failed');
if (($found['sigilo_ausencia_verificada'] ?? null) !== true) throw new LogicException('Explicit public sigilo marker was not preserved.');
if (($found['fonte_confirmada_para_sincronizacao'] ?? false) !== true || ($found['resultado_unico_verificado'] ?? false) !== true) throw new LogicException('Validated DataJud result lost sync evidence.');
$sourceWithoutSigilo = ['numeroProcesso' => '00000016920208050001', 'tribunal' => 'TJBA'];
$missingSigilo = $make([$response(200, ['timed_out' => false, '_shards' => ['total' => 1, 'successful' => 1, 'failed' => 0, 'skipped' => 0], 'hits' => ['total' => ['value' => 1, 'relation' => 'eq'], 'hits' => [['_source' => $sourceWithoutSigilo]]]])], $calls)->searchProcesso($number);
if (($missingSigilo['resultado_unico_verificado'] ?? null) !== true || ($missingSigilo['sigilo_ausencia_verificada'] ?? null) !== false) throw new LogicException('Missing sigilo marker must not authorize synchronization.');
$calls = 0;
$partialMetadata = $make([$response(200, ['hits' => ['total' => ['value' => 1, 'relation' => 'eq'], 'hits' => [['_source' => $source]]]])], $calls)->searchProcesso($number);
if (($partialMetadata['resultado_unico_verificado'] ?? null) !== false) throw new LogicException('Missing completeness metadata must not authorize sync');
foreach ([
    ['failed' => 0],
    ['total' => 2, 'successful' => 1, 'failed' => 0, 'skipped' => 0],
    ['total' => 1, 'successful' => 1, 'failed' => 0, 'skipped' => 2],
    ['total' => 0, 'successful' => 0, 'failed' => 0, 'skipped' => 0],
] as $incompleteShards) {
    $body = ['timed_out' => false, '_shards' => $incompleteShards, 'hits' => ['total' => ['value' => 1, 'relation' => 'eq'], 'hits' => [['_source' => $source]]]];
    $calls = 0;
    if (($make([$response(200, $body)], $calls)->searchProcesso($number)['resultado_unico_verificado'] ?? null) !== false) throw new LogicException('Incomplete shards must not authorize sync');
    $body['hits'] = ['total' => ['value' => 0, 'relation' => 'eq'], 'hits' => []];
    try { $make([$response(200, $body)], $calls)->searchProcesso($number); throw new LogicException('Incomplete empty search accepted'); }
    catch (RuntimeException $e) {}
}
$missingMetaEmpty = ['hits' => ['total' => ['value' => 0, 'relation' => 'eq'], 'hits' => []]];
try { $make([$response(200, $missingMetaEmpty)], $calls)->searchProcesso($number); throw new LogicException('Missing metadata empty search accepted'); }
catch (RuntimeException $e) {}
$hit = ['_source' => $source];
$calls = 0;
try {
    $make([$response(200, ['hits' => ['total' => ['value' => 2, 'relation' => 'eq'], 'hits' => [$hit, $hit]]])], $calls)->searchProcesso($number);
    throw new LogicException('Ambiguous result accepted');
} catch (DataJudAmbiguousResponseException $e) {
    if ($calls !== 1) throw new LogicException('Ambiguous result triggered another request');
}
foreach ([
    ['hits' => ['total' => ['value' => 2, 'relation' => 'eq'], 'hits' => [$hit, $hit]]],
    ['hits' => ['total' => ['value' => 2, 'relation' => 'eq'], 'hits' => [$hit]]],
    ['hits' => ['total' => ['value' => 1, 'relation' => 'gte'], 'hits' => [$hit]]],
    ['hits' => ['hits' => [$hit]]],
    ['hits' => ['total' => ['value' => 0, 'relation' => 'eq'], 'hits' => [$hit]]],
    ['hits' => ['total' => ['value' => 1, 'relation' => 'eq'], 'hits' => [['_source' => []]]]],
    ['hits' => ['total' => ['value' => 1, 'relation' => 'eq'], 'hits' => [['_source' => ['numeroProcesso' => '00000025420208050001']]]]],
] as $ambiguous) {
    $calls = 0;
    try { $make([$response(200, $ambiguous)], $calls)->searchProcesso($number); throw new LogicException('Ambiguous result accepted'); }
    catch (RuntimeException $e) { if ($calls !== 1) throw new LogicException('Ambiguous result triggered another request'); }
}
$captured = null;
$service = new DataJudService([], static function (string $url, array $body) use (&$captured, $response, $empty): array {
    $captured = $body; return $response(200, $empty);
});
$service->searchProcesso($number);
if (($captured['size'] ?? null) !== 2 || ($captured['track_total_hits'] ?? null) !== true) throw new LogicException('Exact total not requested');
$expectedFields = ['numeroProcesso', 'tribunal', 'classe', 'classeProcessual', 'assuntos', 'prioridade', 'polo', 'poloAtivo', 'poloPassivo', 'movimentos', 'orgaoJulgador', 'nivelSigilo', 'grau', 'formato', 'sistema', 'dataAjuizamento', 'dataHoraUltimaAtualizacao', 'id'];
if (($captured['_source'] ?? null) !== $expectedFields) throw new LogicException('Query requested source fields outside the institutional mapper contract.');
echo "PASS: falhas HTTP e respostas inválidas não viram ausência; consulta restrita ao tribunal.\n";
