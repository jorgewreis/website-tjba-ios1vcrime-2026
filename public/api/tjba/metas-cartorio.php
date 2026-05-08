<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=UTF-8');

const COD_CNJ_COMARCA = 2146;
const COD_CNJ_UNIDADE = 4701;
const ENDPOINT_BASE = 'https://transparencia-service-api.tjba.jus.br/metas/filtro';

$query = http_build_query([
    'codCnjComarca' => COD_CNJ_COMARCA,
    'codCnjUnidade' => COD_CNJ_UNIDADE,
]);

$url = ENDPOINT_BASE . '?' . $query;

try {
    $decoded = fetchJson($url);
    $items = normalizeItems($decoded);
    if ($items === []) {
        respond(['error' => 'Nenhum dado de metas retornado pela API do TJBA.'], 502);
    }

    $selected = selectFirstUnique($items);
    $metas = collectMetas($selected);

    respond([
        'comarca' => (string) ($selected['comarca'] ?? ''),
        'vara' => (string) ($selected['unidade'] ?? ''),
        'codCnjComarca' => (string) ($selected['codCnjComarca'] ?? ''),
        'codCnjUnidade' => (string) ($selected['codCnjUnidade'] ?? ''),
        'ano' => (string) ($selected['ano'] ?? ''),
        'metas' => $metas,
    ]);
} catch (RuntimeException $e) {
    respond(['error' => $e->getMessage()], 502);
}

function fetchJson(string $url): array
{
    if (!function_exists('curl_init')) {
        throw new RuntimeException('cURL não está habilitado no servidor.');
    }

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_HTTPHEADER => ['Accept: application/json'],
    ]);

    $raw = curl_exec($ch);
    $err = curl_error($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if (!is_string($raw) || $raw === '') {
        throw new RuntimeException($err !== '' ? 'Falha ao consultar API do TJBA: ' . $err : 'Resposta vazia da API do TJBA.');
    }
    if ($status >= 400) {
        throw new RuntimeException('API do TJBA retornou erro HTTP ' . $status . '.');
    }

    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        throw new RuntimeException('Resposta inválida da API do TJBA.');
    }

    return $decoded;
}

function normalizeItems(array $decoded): array
{
    if (isset($decoded['value']) && is_array($decoded['value'])) {
        return array_values(array_filter($decoded['value'], 'is_array'));
    }
    if (array_is_list($decoded)) {
        return array_values(array_filter($decoded, 'is_array'));
    }

    return [];
}

function selectFirstUnique(array $items): array
{
    $seen = [];
    foreach ($items as $item) {
        $key = implode('|', [
            (string) ($item['codCnjComarca'] ?? ''),
            (string) ($item['codCnjUnidade'] ?? ''),
            (string) ($item['ano'] ?? ''),
        ]);

        if (isset($seen[$key])) {
            continue;
        }

        $seen[$key] = true;
        return $item;
    }

    return $items[0];
}

function collectMetas(array $item): array
{
    $metaOrder = [
        'meta1',
        'meta2_1Grau',
        'meta2_2Grau',
        'meta2Jeetr',
        'meta2Antigos',
        'meta4',
        'meta6',
        'meta7Indigenas',
        'meta7Quilombolas',
        'meta8Vd',
        'meta8Feminicidio',
        'meta10_1Grau',
        'meta10_2Grau',
    ];

    $metas = [];
    foreach ($metaOrder as $metaKey) {
        $metas[$metaKey] = $item[$metaKey] ?? null;
    }

    return $metas;
}

function respond(array $payload, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}
