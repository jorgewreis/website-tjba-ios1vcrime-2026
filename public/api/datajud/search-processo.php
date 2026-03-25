<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');
$config = require __DIR__ . '/config.php';

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
    '_source' => [
        'numeroProcesso',
        'classeProcessual',
        'classe',
        'assuntos',
        'polo',
        'prioridade',
        'tribunal',
        'movimentos',
        'orgaoJulgador',
        'nivelSigilo',
        'grau',
        'formato',
        'sistema',
        'dataHoraUltimaAtualizacao',
        'dataAjuizamento',
        'id',
    ],
];

// #region agent log H1/H2 backend request payload
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
            'sourceFieldCount' => count($body['_source'] ?? []),
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

function respond(array $payload, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}
