<?php
declare(strict_types=1);

/**
 * Teste 03 — Comparativo MNI x DataJud para os mesmos CNJs.
 *
 * Faz, para cada CNJ:
 *   - Consulta DataJud via DataJudService (com config existente).
 *   - Consulta MNI via MniClient.
 *   - Compara campos sobrepostos (classe, assuntos, polo_passivo) e lista
 *     os campos que SÓ o MNI fornece (movimentos, partes detalhadas, documentos).
 *
 * Saída: relatório textual no stdout + JSON em fixtures/comparativo-<timestamp>.json
 *
 * Uso:
 *   php tests-mni/03-comparar-datajud.php "CNJ1,CNJ2,CNJ3"
 *   MNI_TEST_CNJS="CNJ1,CNJ2" php tests-mni/03-comparar-datajud.php
 */

require_once __DIR__ . '/_bootstrap.php';
require_once __DIR__ . '/../app/services/DataJudService.php';

/* Helpers internos do comparativo (classe local para encapsulamento). */
final class Cmp03
{
    public static function comparePairs(?array $a, ?array $b): string
    {
        if ($a === null && $b === null) return 'ambos_null';
        if ($a === null) return 'so_mni';
        if ($b === null) return 'so_datajud';
        $cA = trim((string) ($a['codigo'] ?? ''));
        $cB = trim((string) ($b['codigo'] ?? ''));
        if ($cA === $cB && $cA !== '') return 'codigo_igual';
        $dA = mb_strtolower(trim((string) ($a['descricao'] ?? '')), 'UTF-8');
        $dB = mb_strtolower(trim((string) ($b['descricao'] ?? '')), 'UTF-8');
        if ($dA !== '' && $dA === $dB) return 'descricao_igual';
        return 'diferentes';
    }

    public static function compareAssuntos(array $a, array $b): string
    {
        if (empty($a) && empty($b)) return 'ambos_vazio';
        if (empty($a)) return 'so_mni';
        if (empty($b)) return 'so_datajud';
        $codigosA = array_filter(array_map(static fn($x) => $x['codigo'] ?? null, $a));
        $codigosB = array_filter(array_map(static fn($x) => $x['codigo'] ?? null, $b));
        if (!empty(array_intersect($codigosA, $codigosB))) return 'algum_codigo_comum';
        return 'disjuntos';
    }

    public static function comparePolo(array $a, array $b): string
    {
        if (empty($a) && empty($b)) return 'ambos_vazio';
        if (empty($a)) return 'so_mni';
        if (empty($b)) return 'so_datajud';
        $lowA = array_map(static fn($n) => mb_strtolower((string) $n, 'UTF-8'), $a);
        $lowB = array_map(static fn($n) => mb_strtolower((string) $n, 'UTF-8'), $b);
        if (!empty(array_intersect($lowA, $lowB))) return 'algum_nome_comum';
        return 'disjuntos';
    }

    /** @return list<string> */
    public static function camposExclusivosMni(?array $shape): array
    {
        if ($shape === null) return [];
        $tags = [];
        if (!empty($shape['mni']['partes']['ativo']))      $tags[] = 'partes.ativo';
        if (!empty($shape['mni']['partes']['outros']))     $tags[] = 'partes.outros';
        if (!empty($shape['mni']['orgaoJulgador']))        $tags[] = 'orgaoJulgador.detalhado';
        if (($shape['mni']['totalMovimentos'] ?? 0) > 0)   $tags[] = 'movimentos.estruturados';
        if (!empty($shape['mni']['documentos']))           $tags[] = 'documentos.metadados';
        if (!empty($shape['mni']['assuntosHierarquicos'])) $tags[] = 'assuntos.hierarquia';
        return $tags;
    }
}

$cnjs = tmni_read_cnjs($argv);
if (empty($cnjs)) {
    tmni_section('Uso');
    echo "  Forneça CNJs como argumento ou em MNI_TEST_CNJS.\n";
    echo "  Exemplo: php tests-mni/03-comparar-datajud.php 0000000-00.0000.8.05.0103\n";
    exit(1);
}

$creds = MniCredentials::loadForTjba();
if (!$creds->isComplete()) {
    tmni_fail('MNI nao configurado: ' . implode(', ', $creds->missingVarNames()));
    exit(1);
}

$datajudConfig = require __DIR__ . '/../public/api/datajud/config.php';
if (($datajudConfig['api_key'] ?? '') === '') {
    tmni_fail('DataJud nao configurado: variavel DATAJUD_API_KEY ausente.');
    exit(1);
}

$mniClient = new MniClient($creds);
$datajudService = new DataJudService($datajudConfig);

$relatorio = [
    'gerado_em' => date('c'),
    'cnjs' => [],
];

foreach ($cnjs as $cnj) {
    tmni_section('CNJ: ' . $cnj);

    $dataDatajud = null;
    $erroDatajud = null;
    try {
        $dataDatajud = $datajudService->searchProcesso($cnj);
    } catch (Throwable $e) {
        $erroDatajud = $e->getMessage();
    }

    $resMni = $mniClient->consultarProcesso($cnj, ['incluir_movimentos' => true]);
    $shapeMni = $resMni['shape'];

    $itemRelatorio = [
        'cnj' => $cnj,
        'datajud' => [
            'erro' => $erroDatajud,
            'classe' => $dataDatajud['classe'] ?? null,
            'assuntos_qtd' => is_array($dataDatajud['assuntos'] ?? null) ? count($dataDatajud['assuntos']) : 0,
            'polo_passivo_qtd' => is_array($dataDatajud['polo_passivo'] ?? null) ? count($dataDatajud['polo_passivo']) : 0,
            'movimentos_qtd' => is_array($dataDatajud['movimentos'] ?? null) ? count($dataDatajud['movimentos']) : 0,
            'sigilo_restrito' => $dataDatajud['sigilo_restrito'] ?? null,
            'indisponivel_na_base_publica' => $dataDatajud['indisponivel_na_base_publica'] ?? null,
        ],
        'mni' => [
            'erro' => $resMni['ok'] ? null : ($resMni['error']['message'] ?? 'erro'),
            'kind' => $resMni['ok'] ? 'ok' : ($resMni['error']['kind'] ?? '?'),
            'classe' => $shapeMni['classe'] ?? null,
            'assuntos_qtd' => $shapeMni ? count($shapeMni['assuntos']) : 0,
            'polo_passivo_qtd' => $shapeMni ? count($shapeMni['polo_passivo']) : 0,
            'movimentos_qtd' => $shapeMni['mni']['totalMovimentos'] ?? 0,
            'partes_ativo_qtd' => $shapeMni ? count($shapeMni['mni']['partes']['ativo']) : 0,
            'partes_outros_qtd' => $shapeMni ? count($shapeMni['mni']['partes']['outros']) : 0,
            'documentos_qtd' => $shapeMni ? count($shapeMni['mni']['documentos']) : 0,
            'elapsed_ms' => $resMni['meta']['elapsed_ms'] ?? null,
        ],
        'diff' => [
            'classe_igual'         => Cmp03::comparePairs($dataDatajud['classe'] ?? null, $shapeMni['classe'] ?? null),
            'assuntos_iguais'      => Cmp03::compareAssuntos($dataDatajud['assuntos'] ?? [], $shapeMni['assuntos'] ?? []),
            'polo_passivo_iguais'  => Cmp03::comparePolo($dataDatajud['polo_passivo'] ?? [], $shapeMni['polo_passivo'] ?? []),
            'mni_adiciona'         => Cmp03::camposExclusivosMni($shapeMni),
        ],
    ];

    tmni_kv('DataJud classe', $itemRelatorio['datajud']['classe']);
    tmni_kv('MNI    classe',  $itemRelatorio['mni']['classe']);
    tmni_kv('classe_igual',   $itemRelatorio['diff']['classe_igual']);
    tmni_kv('assuntos DJ x MNI', $itemRelatorio['datajud']['assuntos_qtd'] . ' x ' . $itemRelatorio['mni']['assuntos_qtd']);
    tmni_kv('assuntos_iguais',   $itemRelatorio['diff']['assuntos_iguais']);
    tmni_kv('polo_passivo DJ x MNI', $itemRelatorio['datajud']['polo_passivo_qtd'] . ' x ' . $itemRelatorio['mni']['polo_passivo_qtd']);
    tmni_kv('movimentos DJ x MNI',   $itemRelatorio['datajud']['movimentos_qtd'] . ' x ' . $itemRelatorio['mni']['movimentos_qtd']);
    tmni_kv('MNI adiciona', $itemRelatorio['diff']['mni_adiciona']);

    $relatorio['cnjs'][] = $itemRelatorio;
}

$fixtureDir = __DIR__ . '/fixtures';
if (!is_dir($fixtureDir)) {
    @mkdir($fixtureDir, 0775, true);
}
$dest = $fixtureDir . '/comparativo-' . date('Ymd-His') . '.json';
@file_put_contents($dest, json_encode($relatorio, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));

tmni_section('Resumo');
tmni_kv('cnjs_testados', count($relatorio['cnjs']));
tmni_kv('relatorio_json', $dest);

echo "\n  Preencha tests-mni/relatorio-fase3.md (template) com a decisao de quais campos do MNI vamos persistir/expor.\n";

exit(0);
