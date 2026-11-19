<?php
declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/app/helpers/ApiErrorReporter.php';
ApiErrorReporter::register('public/api/pessoas/search-by-name.php');
require_once dirname(__DIR__, 3) . '/app/helpers/ApiAccessGuard.php';
ApiAccessGuard::requireAccess(['consultapartes'], false);


header('Content-Type: application/json; charset=UTF-8');

$basePath = dirname(__DIR__, 3);
require_once $basePath . '/app/helpers/Conection.php';
require_once $basePath . '/app/services/PessoasService.php';

$connection = Conection::getInstance()->getConnection();

$nome = trim((string) ($_GET['nome'] ?? ''));
$limit = isset($_GET['limit']) ? min(50, max(1, (int) $_GET['limit'])) : 20;

if ($nome === '' || strlen($nome) < 2) {
    echo json_encode(['pessoas' => []], JSON_UNESCAPED_UNICODE);
    exit;
}

function pessoasSearchNormalize(string $value): string
{
    $value = trim($value);
    if ($value === '') {
        return '';
    }

    $value = function_exists('mb_strtolower') ? mb_strtolower($value, 'UTF-8') : strtolower($value);
    try {
        if (class_exists('Normalizer')) {
            $value = \Normalizer::normalize($value, \Normalizer::FORM_D);
            $value = preg_replace('/\p{Mn}/u', '', $value) ?: $value;
        }
    } catch (Throwable $e) {
        // Fallback silencioso.
    }
    if (function_exists('iconv')) {
        $ascii = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
        if (is_string($ascii) && $ascii !== '') {
            $value = $ascii;
        }
    }

    $value = preg_replace('/[^\p{L}\p{N} ]/u', ' ', $value) ?: $value;
    $value = preg_replace('/\s+/u', ' ', $value) ?: $value;

    return trim($value);
}

function pessoasSearchFormatCpf(string $cpf): string
{
    $digits = preg_replace('/\D/', '', $cpf) ?: '';
    if (strlen($digits) !== 11) {
        return trim($cpf);
    }

    return substr($digits, 0, 3) . '.'
        . substr($digits, 3, 3) . '.'
        . substr($digits, 6, 3) . '-'
        . substr($digits, 9, 2);
}

function pessoasSearchResolveProfissao(array $row): string
{
    $instituicoes = is_array($row['instituicoes'] ?? null) ? $row['instituicoes'] : [];
    foreach ($instituicoes as $inst) {
        if (!is_array($inst)) {
            continue;
        }
        $cargo = trim((string) ($inst['cargo_funcao'] ?? ''));
        if ($cargo !== '') {
            return $cargo;
        }
        $profissao = trim((string) ($inst['profissao'] ?? ''));
        if ($profissao !== '') {
            return $profissao;
        }
    }

    return trim((string) ($row['profissao'] ?? ''));
}

function pessoasSearchResolveLotacao(array $row): string
{
    $instituicoes = is_array($row['instituicoes'] ?? null) ? $row['instituicoes'] : [];
    foreach ($instituicoes as $inst) {
        if (!is_array($inst)) {
            continue;
        }
        $sigla = trim((string) ($inst['sigla'] ?? ''));
        if ($sigla !== '') {
            return $sigla;
        }
        $nome = trim((string) ($inst['nome'] ?? ''));
        if ($nome !== '') {
            return $nome;
        }
    }

    return '';
}

function pessoasSearchBuildLabel(array $pessoa): string
{
    $prefixo = trim((string) ($pessoa['cpf_formatado'] ?? ''));
    $nome = trim((string) ($pessoa['nome_completo'] ?? ''));
    $profissao = trim((string) ($pessoa['profissao_exibicao'] ?? ''));
    $lotacao = trim((string) ($pessoa['lotacao_exibicao'] ?? ''));

    $label = $nome !== '' ? $nome : trim((string) ($pessoa['id'] ?? ''));
    if ($prefixo !== '') {
        $label = $prefixo . ' - ' . $label;
    }

    $sufixo = [];
    if ($profissao !== '') {
        $sufixo[] = $profissao;
    }
    if ($lotacao !== '') {
        $sufixo[] = $lotacao;
    }
    if ($sufixo !== []) {
        $label .= ' (' . implode(' - ', $sufixo) . ')';
    }

    return $label;
}

function pessoasSearchScore(string $nomeBuscado, array $row): float
{
    $nomePessoa = pessoasSearchNormalize((string) ($row['nome_completo'] ?? ''));
    if ($nomePessoa === '') {
        return -INF;
    }

    $score = 0.0;
    if ($nomePessoa === $nomeBuscado) {
        $score += 1000;
    }
    if (strpos($nomePessoa, $nomeBuscado) === 0) {
        $score += 400;
    } elseif (strpos($nomePessoa, $nomeBuscado) !== false) {
        $score += 250;
    }

    $tokens = array_values(array_filter(explode(' ', $nomeBuscado), static function (string $token): bool {
        return $token !== '';
    }));
    foreach ($tokens as $token) {
        if ($token !== '' && strpos($nomePessoa, $token) !== false) {
            $score += 40;
        }
    }

    if (function_exists('similar_text')) {
        $percent = 0.0;
        similar_text($nomeBuscado, $nomePessoa, $percent);
        $score += $percent;
    }

    return $score;
}

try {
    $service = new PessoasService($connection);
    $queryLimit = min(50, max($limit * 5, 20));
    $result = $service->list(['nome' => $nome], $queryLimit, 0);
    $rows = $result['rows'] ?? [];
    $nomeBuscado = pessoasSearchNormalize($nome);

    usort($rows, static function (array $a, array $b) use ($nomeBuscado): int {
        $scoreA = pessoasSearchScore($nomeBuscado, $a);
        $scoreB = pessoasSearchScore($nomeBuscado, $b);
        if ($scoreA === $scoreB) {
            return strcasecmp((string) ($a['nome_completo'] ?? ''), (string) ($b['nome_completo'] ?? ''));
        }

        return $scoreB <=> $scoreA;
    });

    $pessoas = [];
    foreach (array_slice($rows, 0, $limit) as $row) {
        $cpfFormatado = pessoasSearchFormatCpf((string) ($row['cpf'] ?? ''));
        $pessoa = [
            'id' => (string) ($row['id'] ?? ''),
            'nome_completo' => (string) ($row['nome_completo'] ?? ''),
            'cpf' => (string) ($row['cpf'] ?? ''),
            'cpf_formatado' => $cpfFormatado,
            'profissao_exibicao' => pessoasSearchResolveProfissao($row),
            'lotacao_exibicao' => pessoasSearchResolveLotacao($row),
        ];
        $pessoa['label'] = pessoasSearchBuildLabel($pessoa);
        $pessoas[] = $pessoa;
    }

    echo json_encode(['pessoas' => $pessoas], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    ApiErrorReporter::logUnexpected($e, 'public/api/pessoas/search-by-name.php');
    http_response_code(500);
    echo json_encode(['error' => 'Erro ao buscar.', 'pessoas' => []], JSON_UNESCAPED_UNICODE);
}
