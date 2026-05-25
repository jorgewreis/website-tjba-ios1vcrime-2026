<?php
declare(strict_types=1);

/**
 * Teste 01 — Conectividade básica ao MNI do TJBA.
 *
 * Valida em sequência:
 *   1) Variáveis MNI_* no .env (endpoint, idConsultante, senha).
 *   2) DNS + abertura de conexão TCP ao host do endpoint.
 *   3) Disponibilidade do WSDL (GET ao endpoint + '?wsdl').
 *   4) Envio de um consultarProcesso DUMMY (CNJ 0...0 inválido) só para confirmar que o servidor
 *      responde com SOAP Fault esperado (autenticação rejeitada / numeroProcesso inválido) —
 *      isso prova autenticação/roteamento sem precisar de processo real.
 *
 * Uso:
 *   php tests-mni/01-conectividade.php
 *
 * Saída:
 *   0 = todas as etapas passaram (ou se etapa 4 retornou Fault esperado).
 *   1 = algum bloqueador (config faltando, host inacessível, HTTP 5xx persistente).
 */

require_once __DIR__ . '/_bootstrap.php';

$creds = MniCredentials::loadForTjba();
tmni_section('1/4  Variaveis .env (MNI_TJBA_*)');
tmni_credentials_summary($creds);
if (!$creds->isComplete()) {
    tmni_fail('Variaveis ausentes: ' . implode(', ', $creds->missingVarNames()));
    tmni_fail('Configure-as em .env (template em config/mni.example.php) e tente novamente.');
    exit(1);
}
tmni_ok('Variaveis MNI_TJBA_* presentes.');

$endpoint = $creds->endpoint();
$parsed = parse_url($endpoint);
if (!is_array($parsed) || empty($parsed['host'])) {
    tmni_fail('MNI_TJBA_ENDPOINT invalido: ' . $endpoint);
    exit(1);
}
$host = (string) $parsed['host'];
$scheme = (string) ($parsed['scheme'] ?? 'https');
$port = (int) ($parsed['port'] ?? ($scheme === 'https' ? 443 : 80));

tmni_section('2/4  DNS + TCP ao host MNI');
tmni_kv('host', $host);
tmni_kv('porta', $port);

$ip = gethostbyname($host);
if ($ip === $host) {
    tmni_fail('Falha de DNS para ' . $host);
    exit(1);
}
tmni_ok('DNS OK (' . $ip . ').');

$errno = 0; $errstr = '';
$sock = @fsockopen(($scheme === 'https' ? 'tls://' : 'tcp://') . $host, $port, $errno, $errstr, 5.0);
if ($sock === false) {
    tmni_fail('Nao conectou em ' . $host . ':' . $port . ' (errno=' . $errno . ' ' . $errstr . ')');
    exit(1);
}
fclose($sock);
tmni_ok('Conexao TCP/TLS estabelecida.');

tmni_section('3/4  WSDL HTTP GET');
$wsdlUrl = rtrim($endpoint, '/') . '?wsdl';
tmni_kv('wsdl_url', $wsdlUrl);
$ch = curl_init($wsdlUrl);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 15,
    CURLOPT_CONNECTTIMEOUT => 5,
    CURLOPT_SSL_VERIFYPEER => true,
    CURLOPT_FOLLOWLOCATION => true,
]);
$wsdl = curl_exec($ch);
$wsdlCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
$wsdlErr = (string) curl_error($ch);
curl_close($ch);

tmni_kv('http_code', $wsdlCode);
if ($wsdlCode !== 200 || !is_string($wsdl) || $wsdl === '') {
    tmni_warn('WSDL nao retornou 200 (pode ser que o servidor exija auth ou rotas diferentes).');
    if ($wsdlErr !== '') {
        tmni_warn('cURL: ' . $wsdlErr);
    }
} else {
    $isWsdl = stripos($wsdl, '<wsdl:definitions') !== false || stripos($wsdl, '<definitions') !== false;
    if ($isWsdl) {
        tmni_ok('WSDL recebido (' . strlen($wsdl) . ' bytes).');
        $fixtureDir = __DIR__ . '/fixtures';
        if (!is_dir($fixtureDir)) {
            @mkdir($fixtureDir, 0775, true);
        }
        $fixturePath = $fixtureDir . '/wsdl-tjba-' . date('Ymd-His') . '.xml';
        if (@file_put_contents($fixturePath, $wsdl) !== false) {
            tmni_ok('WSDL salvo em ' . $fixturePath);
        }
    } else {
        tmni_warn('Resposta 200 mas conteudo nao parece WSDL (pode ser pagina HTML de erro).');
    }
}

tmni_section('4/4  consultarProcesso DUMMY (autenticacao)');
$client = new MniClient($creds, ['include_raw' => true]);
$result = $client->consultarProcesso('00000000000000000000', ['incluir_movimentos' => false]);

tmni_kv('http_code', $result['meta']['http_code']);
tmni_kv('elapsed_ms', $result['meta']['elapsed_ms']);
tmni_kv('endpoint', $result['meta']['endpoint']);
tmni_kv('version', $result['meta']['version']);

if ($result['ok'] === false && is_array($result['error'])) {
    $kind = (string) $result['error']['kind'];
    $msg = (string) $result['error']['message'];
    if (in_array($kind, ['fault', 'not_found', 'invalid_response'], true)) {
        tmni_ok('Servidor respondeu (SOAP Fault ou not_found esperado para CNJ invalido). kind=' . $kind);
        tmni_kv('mensagem_servidor', $msg);
        exit(0);
    }
    if ($kind === 'auth') {
        tmni_fail('Autenticacao rejeitada pelo servidor MNI. Reveja idConsultante/senha.');
        tmni_kv('mensagem', $msg);
        exit(1);
    }
    if ($kind === 'sigilo') {
        tmni_ok('Servidor respondeu negando por sigilo (resposta valida; conectividade OK).');
        exit(0);
    }
    tmni_fail('Erro: ' . $msg . ' (kind=' . $kind . ')');
    exit(1);
}

tmni_ok('Resposta inesperadamente bem-sucedida para CNJ dummy (servidor deve estar com mock?). Tudo bem para conectividade.');
exit(0);
