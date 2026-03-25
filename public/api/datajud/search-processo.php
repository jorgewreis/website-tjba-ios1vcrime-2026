<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');
$config = require __DIR__ . '/config.php';

set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
    throw new ErrorException($message, 0, $severity, $file, $line);
});

set_exception_handler(static function (Throwable $e): void {
    @file_put_contents(
        __DIR__ . '/../../../debug-b44cef.log',
        json_encode([
            'sessionId' => 'b44cef',
            'runId' => 'run-datajud-1',
            'hypothesisId' => 'H6',
            'location' => 'search-processo.php:exception',
            'message' => 'Excecao no endpoint DataJud',
            'data' => [
                'type' => get_class($e),
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ],
            'timestamp' => (int) round(microtime(true) * 1000),
        ], JSON_UNESCAPED_UNICODE) . PHP_EOL,
        FILE_APPEND
    );
    respond([
        'error' => 'Falha interna no endpoint DataJud.',
        '_debug' => [
            'type' => get_class($e),
            'message' => $e->getMessage(),
            'file' => basename($e->getFile()),
            'line' => $e->getLine(),
        ],
    ], 500);
});

register_shutdown_function(static function (): void {
    $lastError = error_get_last();
    if (!is_array($lastError)) {
        return;
    }
    $fatalTypes = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR];
    if (!in_array((int) ($lastError['type'] ?? 0), $fatalTypes, true)) {
        return;
    }
    @file_put_contents(
        __DIR__ . '/../../../debug-b44cef.log',
        json_encode([
            'sessionId' => 'b44cef',
            'runId' => 'run-datajud-1',
            'hypothesisId' => 'H6',
            'location' => 'search-processo.php:shutdown-fatal',
            'message' => 'Fatal error no endpoint DataJud',
            'data' => $lastError,
            'timestamp' => (int) round(microtime(true) * 1000),
        ], JSON_UNESCAPED_UNICODE) . PHP_EOL,
        FILE_APPEND
    );
    if (!headers_sent()) {
        http_response_code(500);
        echo json_encode([
            'error' => 'Falha fatal no endpoint DataJud.',
            '_debug' => [
                'message' => (string) ($lastError['message'] ?? ''),
                'file' => basename((string) ($lastError['file'] ?? '')),
                'line' => (int) ($lastError['line'] ?? 0),
            ],
        ], JSON_UNESCAPED_UNICODE);
    }
});

$rawInput = file_get_contents('php://input') ?: '';
$payload = json_decode($rawInput, true);
if (!is_array($payload)) {
    $payload = $_POST;
}

$numero = isset($payload['numero']) ? (string) $payload['numero'] : '';
$numero = trim(preg_replace('/\D+/', '', $numero));

if ($numero === '' || strlen($numero) !== 20) {
    respond(['error' => 'Número do processo inválido. Forneça 20 dígitos sem pontuação.'], 400);
}

$body = [
    'query' => [
        'bool' => [
            'must' => [
                ['term' => ['numeroProcesso' => $numero]],
            ],
        ],
    ],
    'size' => 1,
    // Retorna _source completo para suportar variações de schema entre tribunais.
    // A resposta segue size=1, então o impacto é baixo.
    '_source' => true,
];

// #region agent log H1/H2 backend request payload
$sourceConfig = $body['_source'] ?? null;
@file_put_contents(
    __DIR__ . '/../../../debug-b44cef.log',
    json_encode([
        'sessionId' => 'b44cef',
        'runId' => 'run-datajud-1',
        'hypothesisId' => 'H1',
        'location' => 'search-processo.php:request',
        'message' => 'Consulta DataJud iniciada',
        'data' => [
            'numeroLen' => strlen($numero),
            'queryMode' => 'term',
            'sourceFieldCount' => is_array($sourceConfig) ? count($sourceConfig) : null,
            'sourceMode' => is_bool($sourceConfig) ? ($sourceConfig ? 'all' : 'none') : 'subset',
        ],
        'timestamp' => (int) round(microtime(true) * 1000),
    ], JSON_UNESCAPED_UNICODE) . PHP_EOL,
    FILE_APPEND
);
// #endregion

$ch = curl_init($config['endpoint']);
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'Authorization: APIKey ' . $config['api_key'],
    ],
    CURLOPT_POSTFIELDS => json_encode($body, JSON_UNESCAPED_UNICODE),
    CURLOPT_TIMEOUT => $config['timeout'],
    CURLOPT_FAILONERROR => false,
]);

$response = curl_exec($ch);
$error = curl_error($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

// #region agent log H1 backend http result
@file_put_contents(
    __DIR__ . '/../../../debug-b44cef.log',
    json_encode([
        'sessionId' => 'b44cef',
        'runId' => 'run-datajud-1',
        'hypothesisId' => 'H1',
        'location' => 'search-processo.php:http-result',
        'message' => 'Resposta HTTP do DataJud',
        'data' => [
            'httpCode' => (int) $httpCode,
            'curlError' => $error !== '' ? 1 : 0,
            'responseLen' => is_string($response) ? strlen($response) : 0,
        ],
        'timestamp' => (int) round(microtime(true) * 1000),
    ], JSON_UNESCAPED_UNICODE) . PHP_EOL,
    FILE_APPEND
);
// #endregion

if ($response === false) {
    respond(['error' => 'Falha ao contatar o DataJud: ' . $error], 502);
}

$decoded = json_decode($response, true);
// #region agent log H2 backend decoded shape
@file_put_contents(
    __DIR__ . '/../../../debug-b44cef.log',
    json_encode([
        'sessionId' => 'b44cef',
        'runId' => 'run-datajud-1',
        'hypothesisId' => 'H2',
        'location' => 'search-processo.php:decoded-shape',
        'message' => 'Estrutura decodificada do DataJud',
        'data' => [
            'decodedIsArray' => is_array($decoded) ? 1 : 0,
            'hasHits' => (is_array($decoded) && isset($decoded['hits'])) ? 1 : 0,
            'hitsCount' => (int) (is_array($decoded) ? count((array) ($decoded['hits']['hits'] ?? [])) : 0),
        ],
        'timestamp' => (int) round(microtime(true) * 1000),
    ], JSON_UNESCAPED_UNICODE) . PHP_EOL,
    FILE_APPEND
);
// #endregion
if (!is_array($decoded) || !isset($decoded['hits']['hits'][0]['_source'])) {
    respond(['error' => 'Processo não encontrado na base do DataJud.'], 404);
}

$source = $decoded['hits']['hits'][0]['_source'];

$poloRaw = $source['polo'] ?? [];
$poloPassivo = formatarPolo($poloRaw);
if (count($poloPassivo) === 0) {
    $poloPassivo = extractPartNamesFromSource($source);
}

// #region agent log H2/H3 source parts shape
$partesDiretas = is_array($source['partes'] ?? null) ? count((array) $source['partes']) : 0;
$poloAtivoRaw = is_array($source['poloAtivo'] ?? null) ? count((array) $source['poloAtivo']) : 0;
$poloPassivoRaw = is_array($source['poloPassivo'] ?? null) ? count((array) $source['poloPassivo']) : 0;
@file_put_contents(
    __DIR__ . '/../../../debug-b44cef.log',
    json_encode([
        'sessionId' => 'b44cef',
        'runId' => 'run-datajud-1',
        'hypothesisId' => 'H2',
        'location' => 'search-processo.php:source-parts-shape',
        'message' => 'Campos de partes no _source',
        'data' => [
            'sourceKeyCount' => count((array) $source),
            'hasPolo' => isset($source['polo']) ? 1 : 0,
            'poloRawCount' => is_array($poloRaw) ? count($poloRaw) : 0,
            'poloPassivoCountOut' => is_array($poloPassivo) ? count($poloPassivo) : 0,
            'partesDiretasCount' => $partesDiretas,
            'poloAtivoCount' => $poloAtivoRaw,
            'poloPassivoRawCount' => $poloPassivoRaw,
            'nivelSigilo' => isset($source['nivelSigilo']) ? (int) $source['nivelSigilo'] : null,
        ],
        'timestamp' => (int) round(microtime(true) * 1000),
    ], JSON_UNESCAPED_UNICODE) . PHP_EOL,
    FILE_APPEND
);
// #endregion

$result = [
    'numero' => $source['numeroProcesso'] ?? $numero,
    'tribunal' => $source['tribunal'] ?? 'TJBA',
    'classe' => formatarClasse($source),
    'assuntos' => formatarAssuntos($source['assuntos'] ?? []),
    'prioridades' => formatarPrioridades($source['prioridade'] ?? []),
    'polo_passivo' => $poloPassivo,
    'movimentos' => formatarMovimentos($source['movimentos'] ?? []),
    'orgao_julgador' => formatarOrgao($source['orgaoJulgador'] ?? []),
    'nivel_sigilo' => $source['nivelSigilo'] ?? null,
    'grau' => $source['grau'] ?? null,
    'formato' => formatarCodigoNome($source['formato'] ?? []),
    'sistema' => formatarCodigoNome($source['sistema'] ?? []),
    'data_ajuizamento' => $source['dataAjuizamento'] ?? null,
    'ultima_atualizacao' => $source['dataHoraUltimaAtualizacao'] ?? null,
    'id' => $source['id'] ?? null,
    '_debug_datajud' => [
        'source_keys' => array_slice(array_keys((array) $source), 0, 40),
        'has_polo' => isset($source['polo']),
        'polo_type' => gettype($source['polo'] ?? null),
        'polo_count' => is_array($source['polo'] ?? null) ? count((array) $source['polo']) : null,
        'has_poloAtivo' => isset($source['poloAtivo']),
        'poloAtivo_type' => gettype($source['poloAtivo'] ?? null),
        'poloAtivo_count' => is_array($source['poloAtivo'] ?? null) ? count((array) $source['poloAtivo']) : null,
        'has_poloPassivo' => isset($source['poloPassivo']),
        'poloPassivo_type' => gettype($source['poloPassivo'] ?? null),
        'poloPassivo_count' => is_array($source['poloPassivo'] ?? null) ? count((array) $source['poloPassivo']) : null,
        'has_partes' => isset($source['partes']),
        'partes_type' => gettype($source['partes'] ?? null),
        'partes_count' => is_array($source['partes'] ?? null) ? count((array) $source['partes']) : null,
        'nivel_sigilo' => $source['nivelSigilo'] ?? null,
        'polo_passivo_out_count' => count($poloPassivo),
        'polo_first_item' => is_array($source['polo'] ?? null) ? (($source['polo'][0] ?? null)) : null,
        'poloAtivo_first_item' => is_array($source['poloAtivo'] ?? null) ? (($source['poloAtivo'][0] ?? null)) : null,
        'poloPassivo_first_item' => is_array($source['poloPassivo'] ?? null) ? (($source['poloPassivo'][0] ?? null)) : null,
        'partes_first_item' => is_array($source['partes'] ?? null) ? (($source['partes'][0] ?? null)) : null,
    ],
];

respond(['data' => $result]);

function formatarClasse(array $source): array
{
    $classe = $source['classe'] ?? $source['classeProcessual'] ?? null;
    if (is_string($classe) && $classe !== '') {
        return ['codigo' => $classe, 'descricao' => $classe];
    }
    if (is_array($classe)) {
        return [
            'codigo' => $classe['codigo'] ?? $classe['numClasse'] ?? $classe['codigoClasse'] ?? null,
            'descricao' => $classe['descricao'] ?? $classe['nome'] ?? $classe['descricaoClasse'] ?? null,
        ];
    }
    return ['codigo' => null, 'descricao' => null];
}

function formatarAssuntos($assuntos): array
{
    if (empty($assuntos)) {
        return [];
    }
    $lista = [];
    foreach ($assuntos as $item) {
        if (is_string($item)) {
            $lista[] = $item;
            continue;
        }
        if (is_array($item)) {
            $desc = $item['descricaoAssunto'] ?? $item['descricao'] ?? $item['nome'] ?? null;
            if ($desc) {
                $lista[] = $desc;
                continue;
            }
            foreach ($item as $sub) {
                if (is_string($sub)) {
                    $lista[] = $sub;
                    continue;
                }
                if (is_array($sub)) {
                    $nested = $sub['descricaoAssunto'] ?? $sub['descricao'] ?? $sub['nome'] ?? null;
                    if ($nested) {
                        $lista[] = $nested;
                    }
                }
            }
        }
    }
    return array_values(array_unique($lista));
}

function formatarMovimentos($movimentos): array
{
    if (empty($movimentos) || !is_array($movimentos)) {
        return [];
    }

    $resultado = [];
    foreach ($movimentos as $movimento) {
        if (!is_array($movimento)) {
            continue;
        }
        $resultado[] = [
            'codigo' => $movimento['codigo'] ?? null,
            'nome' => $movimento['nome'] ?? null,
            'dataHora' => $movimento['dataHora'] ?? $movimento['data_hora'] ?? null,
            'complementos' => formatarComplementos($movimento['complementosTabelados'] ?? []),
        ];
    }
    return $resultado;
}

function formatarComplementos($complementos): array
{
    $lista = [];
    foreach ((array) $complementos as $item) {
        if (!is_array($item)) {
            continue;
        }
        $lista[] = [
            'codigo' => $item['codigo'] ?? null,
            'nome' => $item['nome'] ?? null,
            'valor' => $item['valor'] ?? null,
            'descricao' => $item['descricao'] ?? null,
        ];
    }
    return $lista;
}

function formatarOrgao($orgao): array
{
    if (!is_array($orgao)) {
        return [];
    }
    return [
        'codigo' => $orgao['codigo'] ?? null,
        'nome' => $orgao['nome'] ?? null,
        'codigoMunicipioIBGE' => $orgao['codigoMunicipioIBGE'] ?? null,
    ];
}

function formatarCodigoNome($obj): array
{
    if (!is_array($obj)) {
        return [];
    }
    return [
        'codigo' => $obj['codigo'] ?? null,
        'descricao' => $obj['nome'] ?? $obj['descricao'] ?? null,
    ];
}

function formatarPrioridades($prioridades): array
{
    if (empty($prioridades)) {
        return [];
    }
    $lista = [];
    foreach ((array) $prioridades as $item) {
        if (is_string($item)) {
            $lista[] = $item;
        } elseif (is_array($item)) {
            $lista[] = $item['descricaoPrioridade'] ?? $item['descricao'] ?? implode(', ', $item);
        }
    }
    return array_values(array_filter($lista));
}

function formatarPolo($polos): array
{
    $nomes = [];
    foreach ((array) $polos as $polo) {
        $partes = (array) ($polo['parte'] ?? $polo['partes'] ?? []);
        foreach ($partes as $parte) {
            if (is_string($parte)) {
                $nome = $parte;
            } elseif (is_array($parte)) {
                $nome = $parte['nome'] ?? ($parte['descricao'] ?? null);
            } else {
                $nome = null;
            }
            if ($nome) {
                $nomes[] = trim((string) $nome);
            }
        }
    }
    // Observação: mantemos o nome da chave "polo_passivo" na resposta,
    // mas aqui retornamos os nomes de todas as partes encontradas no processo.
    $nomes = array_values(array_filter($nomes, static function ($v) {
        return trim((string) $v) !== '';
    }));
    return array_values(array_unique($nomes));
}

function extractPartNamesFromSource(array $source): array
{
    $nomes = [];
    $collections = [
        $source['polo'] ?? null,
        $source['poloAtivo'] ?? null,
        $source['poloPassivo'] ?? null,
        $source['partes'] ?? null,
        $source['participantes'] ?? null,
    ];

    foreach ($collections as $collection) {
        collectPartNames($collection, $nomes);
    }

    return array_values(array_keys($nomes));
}

function collectPartNames($node, array &$nomes): void
{
    if (is_string($node)) {
        $v = trim($node);
        if ($v !== '' && looksLikePersonName($v)) {
            $nomes[$v] = true;
        }
        return;
    }

    if (!is_array($node)) {
        return;
    }

    // Captura nomes em chaves comuns.
    foreach (['nome', 'descricao', 'nomeParte', 'nomePessoa'] as $k) {
        if (isset($node[$k]) && is_string($node[$k])) {
            $v = trim((string) $node[$k]);
            if ($v !== '' && looksLikePersonName($v)) {
                $nomes[$v] = true;
            }
        }
    }

    // Percorre recursivamente estruturas típicas de partes.
    foreach ($node as $key => $value) {
        $keyStr = '';
        if (is_string($key)) {
            $keyStr = function_exists('mb_strtolower') ? mb_strtolower($key, 'UTF-8') : strtolower($key);
        }
        if (
            $keyStr === '' ||
            strpos($keyStr, 'polo') !== false ||
            strpos($keyStr, 'parte') !== false ||
            strpos($keyStr, 'particip') !== false ||
            strpos($keyStr, 'represent') !== false ||
            strpos($keyStr, 'advog') !== false ||
            is_numeric($key)
        ) {
            collectPartNames($value, $nomes);
        }
    }
}

function looksLikePersonName(string $v): bool
{
    // Evita coletar textos curtos/códigos e rótulos do processo.
    $s = trim(preg_replace('/\s+/u', ' ', $v) ?: $v);
    $len = function_exists('mb_strlen') ? mb_strlen($s, 'UTF-8') : strlen($s);
    if ($s === '' || $len < 5) {
        return false;
    }
    $tokens = preg_split('/\s+/u', $s) ?: [];
    return count($tokens) >= 2;
}

function respond(array $payload, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}
