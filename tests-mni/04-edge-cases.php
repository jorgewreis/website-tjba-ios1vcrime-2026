<?php
declare(strict_types=1);

/**
 * Teste 04 — Casos de borda do MNI.
 *
 * Casos cobertos (cada um espera um "kind" específico do MniErrorTranslator):
 *   1) CNJ com DV inválido               → kind: 'config' (validação local antes de POST)
 *   2) CNJ válido fora do TJBA (ex.: TJSP J=8 TR=26) → kind: 'config' (resolver retorna null)
 *   3) Credenciais inválidas (sobrescritas) → kind: 'auth' OU 'fault'
 *   4) Timeout artificial (override do timeout) → kind: 'timeout'
 *   5) Endpoint inexistente               → kind: 'network'
 *
 * Uso:
 *   php tests-mni/04-edge-cases.php
 *
 * Variáveis de ambiente úteis:
 *   MNI_TEST_CNJ_TJSP      — um CNJ válido (DV ok) de outro tribunal, para o caso 2.
 *   MNI_TEST_CNJ_VALIDO    — um CNJ válido do TJBA, para o caso 3/4 (qualquer um, mesmo sigiloso).
 *
 * Saída: 0 se todos os casos retornaram o kind esperado; 1 caso contrário.
 */

require_once __DIR__ . '/_bootstrap.php';

$creds = MniCredentials::loadForTjba();
if (!$creds->isComplete()) {
    tmni_section('Pre-requisito');
    tmni_fail('Variaveis MNI_TJBA_* ausentes: ' . implode(', ', $creds->missingVarNames()));
    exit(1);
}

$cnjTjsp    = getenv('MNI_TEST_CNJ_TJSP')   ?: '00000000000000008260000';
$cnjValido  = getenv('MNI_TEST_CNJ_VALIDO') ?: '';

if ($cnjValido === '') {
    tmni_warn('MNI_TEST_CNJ_VALIDO nao definido — casos 3 e 4 usarao um CNJ dummy 0...0103, podem produzir kind=fault em vez de auth/timeout puros.');
    $cnjValido = '00000000000000008050103';
}

$casos = [];

/* 1) CNJ DV invalido */
$casos[] = [
    'titulo' => '1) CNJ DV invalido',
    'esperado' => ['config'],
    'run' => function () use ($creds) {
        $c = new MniClient($creds);
        return $c->consultarProcesso('12345678901234567890');
    },
];

/* 2) CNJ fora do TJBA */
$casos[] = [
    'titulo' => '2) CNJ valido em outro tribunal (resolver retorna null)',
    'esperado' => ['config'],
    'run' => function () use ($creds, $cnjTjsp) {
        $c = new MniClient($creds);
        return $c->consultarProcesso($cnjTjsp);
    },
];

/* 3) Credenciais invalidas — sobrescrevemos a senha em runtime */
$casos[] = [
    'titulo' => '3) Credenciais invalidas (senha errada)',
    'esperado' => ['auth', 'fault'],
    'run' => function () use ($cnjValido) {
        putenv('MNI_TJBA_SENHA_CONSULTANTE=SENHA-INVALIDA-PROPOSITAL');
        $_ENV['MNI_TJBA_SENHA_CONSULTANTE'] = 'SENHA-INVALIDA-PROPOSITAL';
        $bad = MniCredentials::loadForTjba();
        $c = new MniClient($bad);
        return $c->consultarProcesso($cnjValido);
    },
];

/* 4) Timeout artificial — força 1ms */
$casos[] = [
    'titulo' => '4) Timeout artificial (1ms)',
    'esperado' => ['timeout', 'network'],
    'run' => function () use ($cnjValido) {
        putenv('MNI_HTTP_TIMEOUT_MS=1');
        putenv('MNI_HTTP_CONNECT_TIMEOUT_MS=1');
        $_ENV['MNI_HTTP_TIMEOUT_MS'] = '1';
        $_ENV['MNI_HTTP_CONNECT_TIMEOUT_MS'] = '1';
        $cred = MniCredentials::loadForTjba();
        $c = new MniClient($cred);
        return $c->consultarProcesso($cnjValido);
    },
];

/* 5) Endpoint inexistente */
$casos[] = [
    'titulo' => '5) Endpoint inexistente (DNS NXDOMAIN)',
    'esperado' => ['network'],
    'run' => function () use ($cnjValido) {
        putenv('MNI_TJBA_ENDPOINT=https://endpoint-mni-inexistente.invalid/pje/intercomunicacao');
        $_ENV['MNI_TJBA_ENDPOINT'] = 'https://endpoint-mni-inexistente.invalid/pje/intercomunicacao';
        putenv('MNI_HTTP_TIMEOUT_MS=4000');
        putenv('MNI_HTTP_CONNECT_TIMEOUT_MS=2000');
        $_ENV['MNI_HTTP_TIMEOUT_MS'] = '4000';
        $_ENV['MNI_HTTP_CONNECT_TIMEOUT_MS'] = '2000';
        $cred = MniCredentials::loadForTjba();
        $c = new MniClient($cred);
        return $c->consultarProcesso($cnjValido);
    },
];

$globalOk = true;

foreach ($casos as $i => $caso) {
    tmni_section($caso['titulo']);
    $result = $caso['run']();
    $kind = (is_array($result['error']) && isset($result['error']['kind'])) ? (string) $result['error']['kind'] : 'ok';
    tmni_kv('kind_obtido', $kind);
    tmni_kv('mensagem',    $result['error']['message'] ?? '(sem erro)');
    tmni_kv('http_code',   $result['meta']['http_code']);
    tmni_kv('elapsed_ms',  $result['meta']['elapsed_ms']);
    $expected = $caso['esperado'];
    if (in_array($kind, $expected, true)) {
        tmni_ok('Kind dentro do esperado (' . implode('|', $expected) . ').');
    } else {
        tmni_fail('Kind ' . $kind . ' fora do esperado (' . implode('|', $expected) . ').');
        $globalOk = false;
    }
}

tmni_section('Resumo');
echo '  Resultado geral: ' . ($globalOk ? 'OK' : 'COM FALHAS') . "\n";
exit($globalOk ? 0 : 1);
