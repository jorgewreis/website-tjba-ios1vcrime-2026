<?php
declare(strict_types=1);
require_once __DIR__ . '/../../app/services/DataJudService.php';
$numero = '00000016920208050001';
function fetchSynthetic(string $numero, array $source): array
{
    $response = ['curl_error'=>'', 'http_code'=>200, 'decoded'=>['hits'=>['hits'=>[['_source'=>$source]]]]];
    $service = new DataJudService(['base_url'=>'https://example.invalid', 'default_alias'=>'tjba', 'api_key'=>'synthetic'], static fn() => $response);
    return $service->searchProcesso($numero);
}
function assertPayload(bool $ok, string $message): void { if (!$ok) throw new RuntimeException($message); }
$valid = ['codigo'=>123, 'nome'=>'Assunto válido'];
foreach ([0, '0', ' 00 '] as $nivel) {
    $result = fetchSynthetic($numero, ['numeroProcesso'=>$numero, 'nivelSigilo'=>$nivel, 'assuntos'=>[$valid]]);
    assertPayload($result['fonte_confirmada_para_sincronizacao'] === true, 'Fonte pública identificada deveria ser aceita.');
}
foreach ([null, '', false, true, 0.0, '0.5', 'sem sigilo', 1, '1', 'inválido'] as $nivel) {
    $result = fetchSynthetic($numero, ['numeroProcesso'=>$numero, 'nivelSigilo'=>$nivel, 'assuntos'=>[$valid]]);
    assertPayload($result['fonte_confirmada_para_sincronizacao'] === false, 'Nível ausente, ambíguo ou restrito não pode autorizar escrita.');
    assertPayload(count($result['assuntos']) === 1, 'Leitura parcial deve permanecer disponível.');
}
$withoutId = fetchSynthetic($numero, ['nivelSigilo'=>0, 'assuntos'=>[$valid]]);
assertPayload($withoutId['fonte_confirmada_para_sincronizacao'] === false && count($withoutId['assuntos']) === 1, 'Número sintetizado não comprova identidade.');
$mismatch = DataJudResponseMapper::mapSourceToPayload(['numeroProcesso'=>'00000000000000000000', 'nivelSigilo'=>0, 'assuntos'=>[$valid]], $numero, false);
assertPayload($mismatch['fonte_confirmada_para_sincronizacao'] === false, 'Número diferente não pode autorizar sincronização.');
$withoutLevel = fetchSynthetic($numero, ['numeroProcesso'=>$numero, 'assuntos'=>[$valid]]);
assertPayload($withoutLevel['fonte_confirmada_para_sincronizacao'] === false, 'Sigilo ausente não comprova ausência de restrição.');
$mixed = fetchSynthetic($numero, ['numeroProcesso'=>$numero, 'assuntos'=>[$valid, ['nome'=>'Sem código']]]);
assertPayload($mixed['assuntos_completos'] === false, 'Lote parcial não pode sincronizar.');
assertPayload(count($mixed['assuntos']) === 2, 'Dados disponíveis devem continuar legíveis.');
$complete = fetchSynthetic($numero, ['numeroProcesso'=>$numero, 'assuntos'=>[$valid]]);
assertPayload($complete['assuntos_completos'] === true, 'Lote completo deveria ser aceito.');
set_error_handler(static function (int $severity, string $message): never { throw new ErrorException($message, 0, $severity); });
foreach ([[], null, 'inválido', [$valid, null], [['codigo'=>'---','nome'=>'X']], [['codigo'=>123]], [['codigo'=>[], 'nome'=>'Legível']]] as $bad) {
    $result = fetchSynthetic($numero, ['numeroProcesso'=>$numero, 'assuntos'=>$bad]);
    assertPayload($result['assuntos_completos'] === false, 'Lote incompleto aceito.');
}
restore_error_handler();
$secret = fetchSynthetic($numero, ['numeroProcesso'=>$numero, 'nivelSigilo'=>1, 'assuntos'=>[$valid]]);
assertPayload($secret['assuntos_completos'] === false, 'Sigilo não pode sincronizar dados públicos incompletos.');
assertPayload(DataJudResponseMapper::mapEmptyPublicSearch($numero)['assuntos_completos'] === false, 'Ausência de hit não é lote completo.');
echo "PASS sinal de completude do DataJud (transporte sintético, sem rede)\n";
