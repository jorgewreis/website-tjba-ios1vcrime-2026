<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=UTF-8');

/**
 * Regista 1 ponto de produtividade por consulta de CEP bem-sucedida (8 dígitos).
 * Silencioso em falta de sessão, regra inexistente ou erro de BD (não afeta a resposta JSON).
 *
 * Requer linha em `produtividade_regras` (ex.: docs/database/seed-interno-produtividade-regras.sql
 * ou docs/database/seed-consulta-cep-produtividade-regra.sql). Sem regra, não há lançamento.
 *
 * @return string|null chave do catálogo toast quando o lançamento é criado
 */
function consultaCepRegistrarProdutividade(string $cep8Digitos): ?string
{
    $basePathCepApi = dirname(__DIR__, 3);
    require_once $basePathCepApi . '/app/helpers/Conection.php';
    require_once $basePathCepApi . '/app/helpers/BootstrapHelper.php';

    $usuarioId = isset($_SESSION['id_usuario']) ? trim((string) $_SESSION['id_usuario']) : '';
    if ($usuarioId === '') {
        return null;
    }

    require_once $basePathCepApi . '/app/services/ProdutividadeService.php';

    $cfgPath = $basePathCepApi . '/app/config/consulta-cep-produtividade.php';
    if (!is_file($cfgPath)) {
        return null;
    }

    /** @var array{regra_id?:string,tipo_ato?:string,tipo_documento?:string,sistema_modulo?:string,referencia_sistema?:string} $cfg */
    $cfg = require $cfgPath;
    $regraId = trim((string) ($cfg['regra_id'] ?? ''));
    if ($regraId === '') {
        return null;
    }

    $cepFmt = strlen($cep8Digitos) === 8
        ? substr($cep8Digitos, 0, 5) . '-' . substr($cep8Digitos, 5, 3)
        : $cep8Digitos;

    try {
        $connection = Conection::getInstance()->getConnection();
        $service = new ProdutividadeService($connection);
        $result = $service->registrarAuto($usuarioId, [
            'regra_id' => $regraId,
            'tipo_ato' => trim((string) ($cfg['tipo_ato'] ?? 'Consulta')),
            'tipo_documento' => trim((string) ($cfg['tipo_documento'] ?? 'CEP')),
            'sistema_modulo' => trim((string) ($cfg['sistema_modulo'] ?? 'consultas-cep')),
            'quantidade' => 1,
            'referencia_item_id' => 'cep:' . $cep8Digitos,
            'referencia_sistema' => trim((string) ($cfg['referencia_sistema'] ?? 'consultas-cep')),
            'observacoes' => 'Consulta de CEP (' . $cepFmt . ').',
        ]);
        if (!empty($result['created'])) {
            return 'interno.produtividade.cep.ponto';
        }
        if (($result['reason'] ?? '') === 'regra_inexistente_ou_inativa') {
            error_log(
                'CEP produtividade: regra inativa ou ausente no BD. Rode docs/database/seed-interno-produtividade-regras.sql '
                . '(ou seed-consulta-cep-produtividade-regra.sql). regra_id=' . $regraId
            );
        }
    } catch (Throwable $ignored) {
        // Não interromper a consulta CEP por falha de produtividade.
    }

    return null;
}

function respond(array $payload, int $statusCode = 200): void
{
    http_response_code($statusCode);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

function readInputQuery(): string
{
    $rawInput = file_get_contents('php://input') ?: '';
    $payload = json_decode($rawInput, true);

    if (is_array($payload) && isset($payload['q'])) {
        return trim((string) $payload['q']);
    }

    return trim((string) ($_GET['q'] ?? $_POST['q'] ?? ''));
}

function onlyDigits(string $value): string
{
    return preg_replace('/\D+/', '', $value) ?? '';
}

function isNumericLikeQuery(string $value): bool
{
    return $value !== '' && preg_match('/^[0-9.\-\s]+$/', $value) === 1;
}

function normalizeTextSegment(string $value): string
{
    $value = trim(preg_replace('/\s+/', ' ', $value) ?? '');
    if ($value === '') {
        return '';
    }

    $ascii = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
    if (is_string($ascii) && $ascii !== '') {
        $value = $ascii;
    }

    return rawurlencode($value);
}

function fetchJson(string $url): array
{
    if (function_exists('curl_init')) {
        $curl = curl_init($url);
        curl_setopt_array($curl, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_HTTPGET => true,
            CURLOPT_USERAGENT => 'TJBA-Consulta-CEP/1.0',
            CURLOPT_FAILONERROR => false,
        ]);

        $response = curl_exec($curl);
        $httpCode = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $error = curl_error($curl);
        curl_close($curl);

        if ($response === false) {
            throw new RuntimeException('Falha ao consultar o serviço externo: ' . $error);
        }

        if ($httpCode >= 400) {
            throw new RuntimeException('Serviço externo retornou HTTP ' . $httpCode . '.');
        }
    } elseif (function_exists('exec')) {
        $output = [];
        $exitCode = 0;
        $command = 'curl.exe -L --silent --show-error --max-time 10 --user-agent "TJBA-Consulta-CEP/1.0" ' . escapeshellarg($url);
        @exec($command, $output, $exitCode);

        $response = implode("\n", $output);
        if ($exitCode !== 0 || trim($response) === '') {
            throw new RuntimeException('Falha ao consultar o serviço externo.');
        }
    } else {
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => 10,
                'ignore_errors' => true,
                'header' => "User-Agent: TJBA-Consulta-CEP/1.0\r\n",
            ],
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
            ],
        ]);

        $response = @file_get_contents($url, false, $context);
        if ($response === false) {
            throw new RuntimeException('Falha ao consultar o serviço externo.');
        }

        $headers = isset($http_response_header) && is_array($http_response_header) ? $http_response_header : [];
        $statusLine = $headers[0] ?? '';
        if (preg_match('/\s(\d{3})\s/', $statusLine, $matches) === 1) {
            $httpCode = (int) $matches[1];
            if ($httpCode >= 400) {
                throw new RuntimeException('Serviço externo retornou HTTP ' . $httpCode . '.');
            }
        }
    }

    $decoded = json_decode((string) $response, true);
    if (!is_array($decoded)) {
        throw new RuntimeException('Resposta inválida do serviço externo.');
    }

    return $decoded;
}

function normalizeAddressRow(array $row): array
{
    return [
        'cep' => (string) ($row['cep'] ?? ''),
        'logradouro' => (string) ($row['logradouro'] ?? ''),
        'complemento' => (string) ($row['complemento'] ?? ''),
        'bairro' => (string) ($row['bairro'] ?? ''),
        'cidade' => (string) ($row['localidade'] ?? ''),
        'uf' => (string) ($row['uf'] ?? ''),
        'ibge' => (string) ($row['ibge'] ?? ''),
    ];
}

function parseAddressQuery(string $query): array
{
    $parts = array_values(array_filter(array_map('trim', explode(',', $query)), static function ($part) {
        return $part !== '';
    }));

    $uf = 'BA';
    $cidade = 'Ilheus';
    $bairro = '';
    $logradouro = '';

    if (count($parts) === 0) {
        return ['uf' => $uf, 'cidade' => $cidade, 'bairro' => $bairro, 'logradouro' => ''];
    }

    if (count($parts) === 1) {
        $logradouro = $parts[0];
        return ['uf' => $uf, 'cidade' => $cidade, 'bairro' => $bairro, 'logradouro' => $logradouro];
    }

    $lastPart = strtoupper((string) end($parts));
    if (preg_match('/^[A-Z]{2}$/', $lastPart) === 1 && count($parts) >= 3) {
        $uf = $lastPart;
        $cidade = $parts[count($parts) - 2];
        if (count($parts) >= 4) {
            $bairro = $parts[count($parts) - 3];
            $logradouro = implode(' ', array_slice($parts, 0, -3));
        } else {
            $logradouro = implode(' ', array_slice($parts, 0, -2));
        }
        return ['uf' => $uf, 'cidade' => $cidade, 'bairro' => $bairro, 'logradouro' => $logradouro];
    }

    if (count($parts) >= 3) {
        $cidade = $parts[count($parts) - 1];
        $bairro = $parts[count($parts) - 2];
        $logradouro = implode(' ', array_slice($parts, 0, -2));
        return ['uf' => $uf, 'cidade' => $cidade, 'bairro' => $bairro, 'logradouro' => $logradouro];
    }

    $cidade = $parts[count($parts) - 1];
    $logradouro = implode(' ', array_slice($parts, 0, -1));

    return ['uf' => $uf, 'cidade' => $cidade, 'bairro' => $bairro, 'logradouro' => $logradouro];
}

function substringStart(string $value, int $length): string
{
    if (function_exists('mb_substr')) {
        return (string) mb_substr($value, 0, $length, 'UTF-8');
    }

    return substr($value, 0, $length);
}

function stringLength(string $value): int
{
    if (function_exists('mb_strlen')) {
        return (int) mb_strlen($value, 'UTF-8');
    }

    return strlen($value);
}

function searchAddressRows(array $parsed): array
{
    $logradouro = trim((string) ($parsed['logradouro'] ?? ''));
    $cidade = (string) ($parsed['cidade'] ?? '');
    $uf = (string) ($parsed['uf'] ?? 'BA');

    $attempts = [];
    if ($logradouro !== '') {
        $attempts[] = $logradouro;
    }

    $firstWord = preg_split('/\s+/', $logradouro)[0] ?? '';
    if ($firstWord !== '' && stringLength($firstWord) >= 3) {
        $attempts[] = substringStart($firstWord, 3);
    } elseif ($logradouro !== '' && stringLength($logradouro) >= 3) {
        $attempts[] = substringStart($logradouro, 3);
    }

    $attempts = array_values(array_unique(array_filter($attempts, static function ($attempt) {
        return trim((string) $attempt) !== '';
    })));

    foreach ($attempts as $attempt) {
        $url = sprintf(
            'https://viacep.com.br/ws/%s/%s/%s/json/',
            normalizeTextSegment($uf),
            normalizeTextSegment($cidade),
            normalizeTextSegment($attempt)
        );

        $rows = fetchJson($url);
        if (isset($rows['erro']) && $rows['erro'] === true) {
            continue;
        }

        if (is_array($rows) && $rows !== []) {
            return $rows;
        }
    }

    return [];
}

function normalizeComparable(string $value): string
{
    $value = trim(preg_replace('/\s+/', ' ', $value) ?? '');
    if ($value === '') {
        return '';
    }

    $ascii = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
    if (is_string($ascii) && $ascii !== '') {
        $value = $ascii;
    }

    $value = strtolower($value);
    $value = preg_replace('/[^a-z0-9\s]/', ' ', $value) ?? '';
    return trim(preg_replace('/\s+/', ' ', $value) ?? '');
}

function matchesAddressSearch(array $row, string $logradouroBusca): bool
{
    $needle = normalizeComparable($logradouroBusca);
    if ($needle === '') {
        return true;
    }

    $primaryToken = preg_split('/\s+/', $needle)[0] ?? '';
    $prefix = $primaryToken !== '' && stringLength($primaryToken) >= 3 ? substringStart($primaryToken, 3) : '';
    $haystack = normalizeComparable(
        (string) ($row['logradouro'] ?? '') . ' ' .
        (string) ($row['bairro'] ?? '') . ' ' .
        (string) ($row['localidade'] ?? '')
    );
    $haystackTokens = array_values(array_filter(explode(' ', $haystack), static function ($token) {
        return $token !== '';
    }));

    foreach ($haystackTokens as $token) {
        if ($primaryToken !== '' && str_starts_with($token, $primaryToken)) {
            return true;
        }
        if ($prefix !== '' && str_starts_with($token, $prefix)) {
            return true;
        }
    }

    return false;
}

function comparableTokens(string $value): array
{
    return array_values(array_filter(explode(' ', normalizeComparable($value)), static function ($token) {
        return $token !== '';
    }));
}

function tokenDistance(string $left, string $right): int
{
    if ($left === '' || $right === '') {
        return 99;
    }

    if (function_exists('levenshtein')) {
        return levenshtein($left, $right);
    }

    return $left === $right ? 0 : 99;
}

function scoreAddressRow(array $row, array $parsed): int
{
    $queryLogradouro = normalizeComparable((string) ($parsed['logradouro'] ?? ''));
    $queryBairro = normalizeComparable((string) ($parsed['bairro'] ?? ''));
    $queryCidade = normalizeComparable((string) ($parsed['cidade'] ?? ''));
    $rowLogradouro = normalizeComparable((string) ($row['logradouro'] ?? ''));
    $rowBairro = normalizeComparable((string) ($row['bairro'] ?? ''));
    $rowCidade = normalizeComparable((string) ($row['localidade'] ?? ''));

    $score = 0;

    if ($queryCidade !== '' && $queryCidade === $rowCidade) {
        $score += 200;
    }

    if ($queryBairro !== '') {
        if ($rowBairro === $queryBairro) {
            $score += 1000;
        } elseif (str_contains($rowBairro, $queryBairro)) {
            $score += 700;
        }

        $bairroTokens = comparableTokens($queryBairro);
        $rowBairroTokens = comparableTokens($rowBairro);
        foreach ($bairroTokens as $bairroToken) {
            $bestBairroScore = 0;
            foreach ($rowBairroTokens as $rowBairroToken) {
                if ($rowBairroToken === $bairroToken) {
                    $bestBairroScore = max($bestBairroScore, 350);
                    continue;
                }

                if (str_starts_with($rowBairroToken, $bairroToken) || str_starts_with($bairroToken, $rowBairroToken)) {
                    $bestBairroScore = max($bestBairroScore, 240);
                }

                $distance = tokenDistance($bairroToken, $rowBairroToken);
                if ($distance === 1) {
                    $bestBairroScore = max($bestBairroScore, 180);
                } elseif ($distance === 2) {
                    $bestBairroScore = max($bestBairroScore, 80);
                }
            }
            $score += $bestBairroScore;
        }
    }

    if ($queryLogradouro !== '') {
        if ($rowLogradouro === $queryLogradouro) {
            $score += 1200;
        } elseif (str_contains($rowLogradouro, $queryLogradouro)) {
            $score += 900;
        }

        $queryTokens = comparableTokens($queryLogradouro);
        $rowTokens = comparableTokens($rowLogradouro);
        $significantRowTokens = array_values(array_filter($rowTokens, static function ($token) {
            return !in_array($token, ['rua', 'avenida', 'travessa', 'alameda', 'praca', 'rodovia'], true);
        }));
        if ($significantRowTokens !== []) {
            $rowTokens = $significantRowTokens;
        }

        foreach ($queryTokens as $queryToken) {
            $bestTokenScore = 0;
            foreach ($rowTokens as $rowToken) {
                if ($rowToken === $queryToken) {
                    $bestTokenScore = max($bestTokenScore, 500);
                    continue;
                }

                if (str_starts_with($rowToken, $queryToken) || str_starts_with($queryToken, $rowToken)) {
                    $bestTokenScore = max($bestTokenScore, 350);
                }

                $distance = tokenDistance($queryToken, $rowToken);
                if ($distance === 1) {
                    $bestTokenScore = max($bestTokenScore, 320);
                } elseif ($distance === 2) {
                    $bestTokenScore = max($bestTokenScore, 180);
                } elseif ($distance === 3) {
                    $bestTokenScore = max($bestTokenScore, 80);
                }
            }

            $score += $bestTokenScore;
        }

        if ($rowBairro !== '' && str_contains($rowBairro, $queryLogradouro)) {
            $score += 120;
        }
    }

    return $score;
}

$query = readInputQuery();

if ($query === '') {
    respond(['error' => 'Informe um CEP ou parte do endereço para consulta.'], 400);
}

try {
    if (isNumericLikeQuery($query)) {
        $cep = onlyDigits($query);
        if (strlen($cep) !== 8) {
            respond(['error' => 'CEP inválido. Informe 8 dígitos.'], 400);
        }

        $data = fetchJson('https://viacep.com.br/ws/' . $cep . '/json/');
        if (($data['erro'] ?? false) === true) {
            respond(['error' => 'CEP não encontrado.'], 404);
        }

        $toastKeyCep = consultaCepRegistrarProdutividade($cep);
        $payloadCep = [
            'mode' => 'cep',
            'query' => $query,
            'data' => normalizeAddressRow($data),
        ];
        if ($toastKeyCep !== null) {
            $payloadCep['produtividade_toast_key'] = $toastKeyCep;
        }
        respond($payloadCep);
    }

    $parsed = parseAddressQuery($query);
    if ($parsed['logradouro'] === '' || $parsed['cidade'] === '') {
        respond(['error' => 'Informe a busca no formato "logradouro, cidade" ou um CEP válido.'], 400);
    }

    $rows = searchAddressRows($parsed);

    $results = [];
    foreach ($rows as $row) {
        if (!is_array($row)) {
            continue;
        }
        if (!matchesAddressSearch($row, (string) $parsed['logradouro'])) {
            continue;
        }
        $normalized = normalizeAddressRow($row);
        if ($normalized['cep'] === '') {
            continue;
        }
        $results[] = $normalized;
    }

    if ($results === []) {
        respond(['results' => [], 'mode' => 'endereco', 'query' => $query], 404);
    }

    usort($results, static function (array $left, array $right) use ($parsed) {
        $leftScore = scoreAddressRow([
            'logradouro' => $left['logradouro'] ?? '',
            'bairro' => $left['bairro'] ?? '',
            'localidade' => $left['cidade'] ?? '',
        ], $parsed);
        $rightScore = scoreAddressRow([
            'logradouro' => $right['logradouro'] ?? '',
            'bairro' => $right['bairro'] ?? '',
            'localidade' => $right['cidade'] ?? '',
        ], $parsed);

        if ($leftScore !== $rightScore) {
            return $rightScore <=> $leftScore;
        }

        $leftLogradouro = normalizeComparable((string) ($left['logradouro'] ?? ''));
        $rightLogradouro = normalizeComparable((string) ($right['logradouro'] ?? ''));
        if ($leftLogradouro !== $rightLogradouro) {
            return $leftLogradouro <=> $rightLogradouro;
        }

        $leftBairro = normalizeComparable((string) ($left['bairro'] ?? ''));
        $rightBairro = normalizeComparable((string) ($right['bairro'] ?? ''));
        if ($leftBairro !== $rightBairro) {
            return $leftBairro <=> $rightBairro;
        }

        return normalizeComparable((string) ($left['cep'] ?? '')) <=> normalizeComparable((string) ($right['cep'] ?? ''));
    });

    respond([
        'mode' => 'endereco',
        'query' => $query,
        'results' => $results,
    ]);
} catch (Throwable $exception) {
    respond(['error' => 'Erro ao consultar CEP: ' . $exception->getMessage()], 500);
}
