<?php
declare(strict_types=1);

/**
 * Teste 05 — Consulta de alteracao (polling barato) via MNI.
 *
 * Uso:
 *   php tests-mni/05-consultar-alteracao.php 0000000-00.0000.8.05.0103
 *   php tests-mni/05-consultar-alteracao.php "CNJ1,CNJ2"
 *   MNI_TEST_CNJS="CNJ1,..." php tests-mni/05-consultar-alteracao.php
 *
 * Para cada CNJ:
 *   - Faz consultarAlteracao() — devolve hashes de cabecalho/movimentacoes/documentos.
 *   - Imprime resumo: ok, sucesso, hashCabecalho, hashMovimentacoes, hashDocumentos.
 *   - Salva resposta XML bruta (sanitizada) em fixtures/raw/.
 *
 * Critico para validar:
 *   - O endpoint v3 do tribunal aceita a operacao.
 *   - O envelope v3 com <consultante> + <numeroProcesso> e' suficiente.
 *   - O parser extrai os hashes do shape oficial.
 *
 * Saida: 0 se TODOS os CNJs retornarem shape valido OU erro traduzido conhecido.
 *        1 se algum erro inesperado (network/timeout/auth).
 */

require_once __DIR__ . '/_bootstrap.php';

$cnjs = tmni_read_cnjs($argv);
if (empty($cnjs)) {
    tmni_section('Uso');
    echo "  Forneca CNJs como argumento ou em MNI_TEST_CNJS (separados por virgula/espaco).\n";
    echo "  Exemplo: php tests-mni/05-consultar-alteracao.php 0000000-00.0000.8.05.0103\n";
    exit(1);
}

$creds = MniCredentials::loadForTjba();
if (!$creds->isComplete()) {
    tmni_section('Pre-requisito');
    tmni_fail('Variaveis MNI_TJBA_* ausentes: ' . implode(', ', $creds->missingVarNames()));
    exit(1);
}

$client = new MniClient($creds, ['include_raw' => true]);
$globalOk = true;
$resultados = [];

foreach ($cnjs as $idx => $cnj) {
    tmni_section('CNJ #' . ($idx + 1) . ' — ' . $cnj);
    $result = $client->consultarAlteracao($cnj);

    tmni_kv('http_code',  $result['meta']['http_code']);
    tmni_kv('elapsed_ms', $result['meta']['elapsed_ms']);
    tmni_kv('version',    $result['meta']['version']);
    tmni_kv('used_token', $result['meta']['used_token'] ?? false);

    if (!$result['ok']) {
        $err = $result['error'] ?? ['kind' => 'unknown', 'message' => 'sem detalhes'];
        if (in_array($err['kind'], ['sigilo', 'not_found'], true)) {
            tmni_warn('Esperado/aceito: ' . $err['kind'] . ' - ' . $err['message']);
            $resultados[] = ['cnj' => $cnj, 'ok' => false, 'kind' => $err['kind']];
            continue;
        }
        tmni_fail($err['kind'] . ' - ' . $err['message']);
        $globalOk = false;
        $resultados[] = ['cnj' => $cnj, 'ok' => false, 'kind' => $err['kind']];
        continue;
    }

    $alteracoes = $result['alteracoes'] ?? [];
    tmni_ok('Resposta consultarAlteracao OK.');
    tmni_kv('hashCabecalho',     $alteracoes['hashCabecalho']     ?? '(ausente)');
    tmni_kv('hashMovimentacoes', $alteracoes['hashMovimentacoes'] ?? '(ausente)');
    tmni_kv('hashDocumentos',    $alteracoes['hashDocumentos']    ?? '(ausente)');
    if (isset($alteracoes['dataReferencia'])) {
        tmni_kv('dataReferencia', $alteracoes['dataReferencia']);
    }

    $resultados[] = [
        'cnj' => $cnj,
        'ok' => true,
        'kind' => 'ok',
        'hashCabecalho'     => $alteracoes['hashCabecalho']     ?? null,
        'hashMovimentacoes' => $alteracoes['hashMovimentacoes'] ?? null,
        'hashDocumentos'    => $alteracoes['hashDocumentos']    ?? null,
    ];

    if (is_string($result['raw']) && $result['raw'] !== '') {
        $fixtureDir = __DIR__ . '/fixtures/raw';
        if (!is_dir($fixtureDir)) {
            @mkdir($fixtureDir, 0775, true);
        }
        $sanitized = preg_replace('/(<[^>]*senha[^>]*>).*?(<\/[^>]+>)/i', '$1***$2', $result['raw']) ?? $result['raw'];
        $sanitized = preg_replace('/(<[^>]*token[^>]*>).*?(<\/[^>]+>)/i', '$1***$2', $sanitized) ?? $sanitized;
        $base = $fixtureDir . '/alteracao-' . substr($cnj, 0, 7) . '-' . date('Ymd-His') . '.xml';
        if (@file_put_contents($base, $sanitized) !== false) {
            tmni_kv('xml_salvo_em', $base);
        }
    }
}

tmni_section('Resumo');
foreach ($resultados as $r) {
    $line = $r['cnj'] . ' -> ' . ($r['ok'] ? 'OK' : 'FAIL/' . $r['kind']);
    if ($r['ok']) {
        $hc = $r['hashCabecalho']     ? substr((string) $r['hashCabecalho'], 0, 12)     . '..' : 'n/a';
        $hm = $r['hashMovimentacoes'] ? substr((string) $r['hashMovimentacoes'], 0, 12) . '..' : 'n/a';
        $hd = $r['hashDocumentos']    ? substr((string) $r['hashDocumentos'], 0, 12)    . '..' : 'n/a';
        $line .= ' | cabec=' . $hc . ' | movim=' . $hm . ' | docs=' . $hd;
    }
    echo '  ' . $line . "\n";
}

exit($globalOk ? 0 : 1);
