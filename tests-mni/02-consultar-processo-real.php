<?php
declare(strict_types=1);

/**
 * Teste 02 — Consulta real de processos do TJBA via MNI.
 *
 * Uso:
 *   php tests-mni/02-consultar-processo-real.php 0000000-00.0000.8.05.0103
 *   php tests-mni/02-consultar-processo-real.php 00000000000000000000,00000000000000000000
 *   MNI_TEST_CNJS="0000000-00.0000.8.05.0103,..." php tests-mni/02-consultar-processo-real.php
 *
 * O script:
 *   - Não usa CNJ hardcoded (evita expor processo alheio em fixtures versionadas).
 *   - Para cada CNJ, faz consultarProcesso(incluir_movimentos=true, incluir_documentos=false).
 *   - Imprime resumo: classe, assuntos, partes, qtd movimentos.
 *   - Salva resposta XML bruta (SANITIZADA — sem nome de parte/documento) em fixtures/.
 *
 * Saída: 0 se TODOS os CNJs retornaram shape válido OU erro traduzido conhecido.
 *        1 se algum erro inesperado (network/timeout/auth).
 */

require_once __DIR__ . '/_bootstrap.php';

$cnjs = tmni_read_cnjs($argv);
if (empty($cnjs)) {
    tmni_section('Uso');
    echo "  Forneça CNJs como argumento ou em MNI_TEST_CNJS (separados por vírgula/espaço).\n";
    echo "  Exemplo: php tests-mni/02-consultar-processo-real.php 0000000-00.0000.8.05.0103\n";
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
    $result = $client->consultarProcesso($cnj, [
        'incluir_cabecalho'  => true,
        'incluir_movimentos' => true,
        'incluir_documentos' => false,
    ]);

    tmni_kv('http_code',  $result['meta']['http_code']);
    tmni_kv('elapsed_ms', $result['meta']['elapsed_ms']);
    tmni_kv('version',    $result['meta']['version']);

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

    $shape = $result['shape'];
    tmni_ok('Resposta MNI OK.');
    tmni_kv('numero',          $shape['numero']);
    tmni_kv('classe',          $shape['classe']);
    tmni_kv('assuntos (qtd)',  count($shape['assuntos']));
    tmni_kv('assuntos',        array_map(fn($a) => $a['descricao'], $shape['assuntos']));
    tmni_kv('polo_passivo',    $shape['polo_passivo']);
    tmni_kv('orgao_julgador',  $shape['orgao_julgador']);
    tmni_kv('movimentos (qtd)',$shape['mni']['totalMovimentos']);

    $resultados[] = [
        'cnj' => $cnj,
        'ok' => true,
        'kind' => 'ok',
        'classe' => $shape['classe']['descricao'] ?? null,
        'assuntos' => count($shape['assuntos']),
        'movimentos' => $shape['mni']['totalMovimentos'],
    ];

    if (is_string($result['raw']) && $result['raw'] !== '') {
        $fixtureDir = __DIR__ . '/fixtures/raw';
        if (!is_dir($fixtureDir)) {
            @mkdir($fixtureDir, 0775, true);
        }
        $sanitized = preg_replace('/(<[^>]*senhaConsultante[^>]*>).*?(<\/[^>]+>)/i', '$1***$2', $result['raw']) ?? $result['raw'];
        $base = $fixtureDir . '/cnj-' . substr($cnj, 0, 7) . '-' . date('Ymd-His') . '.xml';
        if (@file_put_contents($base, $sanitized) !== false) {
            tmni_kv('xml_salvo_em', $base);
        }
    }
}

tmni_section('Resumo');
foreach ($resultados as $r) {
    $line = $r['cnj'] . ' -> ' . ($r['ok'] ? 'OK' : 'FAIL/' . $r['kind']);
    if ($r['ok']) {
        $line .= ' | classe=' . ($r['classe'] ?? '?') . ' | assuntos=' . $r['assuntos'] . ' | movimentos=' . $r['movimentos'];
    }
    echo '  ' . $line . "\n";
}

exit($globalOk ? 0 : 1);
