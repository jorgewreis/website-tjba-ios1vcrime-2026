<?php
declare(strict_types=1);

/**
 * Bootstrap compartilhado pelos scripts CLI em tests-mni/.
 *
 * - Garante que estamos no CLI (não expõe credenciais por HTTP por acidente).
 * - Faz require_once dos arquivos da Fase 1.
 * - Expõe helpers simples de print/saída (CLI-friendly, sem dependências externas).
 *
 * Uso (a partir da raiz do repo): php tests-mni/01-conectividade.php
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    echo "tests-mni/* só pode ser executado via CLI (PHP_SAPI=" . PHP_SAPI . ").\n";
    exit(1);
}

require_once __DIR__ . '/../app/Mni/MniClient.php';
require_once __DIR__ . '/../app/helpers/CnjValidator.php';

function tmni_section(string $title): void
{
    echo "\n";
    echo "================================================================================\n";
    echo "  " . $title . "\n";
    echo "================================================================================\n";
}

function tmni_kv(string $label, $value, int $indent = 2): void
{
    $pad = str_repeat(' ', $indent);
    if (is_bool($value)) {
        $value = $value ? 'true' : 'false';
    } elseif (is_array($value) || is_object($value)) {
        $value = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    } elseif ($value === null) {
        $value = '(null)';
    } else {
        $value = (string) $value;
    }
    echo $pad . $label . ': ' . $value . "\n";
}

function tmni_ok(string $msg): void   { echo "  [OK]    " . $msg . "\n"; }
function tmni_warn(string $msg): void { echo "  [WARN]  " . $msg . "\n"; }
function tmni_fail(string $msg): void { echo "  [FAIL]  " . $msg . "\n"; }

/**
 * Lê os CNJs reais informados via argv ou variável de ambiente MNI_TEST_CNJS (separados por vírgula).
 * Aborta se nenhum CNJ for fornecido — não usamos fallback hardcoded para evitar consultar processo de outras pessoas.
 *
 * @param array<int, string> $argv
 * @return list<string>
 */
function tmni_read_cnjs(array $argv): array
{
    $raw = '';
    if (isset($argv[1]) && $argv[1] !== '') {
        $raw = (string) $argv[1];
    } else {
        $env = getenv('MNI_TEST_CNJS');
        if ($env !== false) {
            $raw = (string) $env;
        }
    }
    if ($raw === '') {
        return [];
    }
    $items = preg_split('/[,;\s]+/', $raw, -1, PREG_SPLIT_NO_EMPTY) ?: [];
    $out = [];
    foreach ($items as $item) {
        $digits = preg_replace('/\D+/', '', (string) $item) ?? '';
        if (strlen($digits) === 20) {
            $out[] = $digits;
        }
    }
    return $out;
}

function tmni_credentials_summary(MniCredentials $c): void
{
    tmni_kv('endpoint', $c->endpoint());
    tmni_kv('version', $c->version());
    tmni_kv('id_consultante', $c->idConsultante() === '' ? '(vazio)' : (mb_substr($c->idConsultante(), 0, 4) . '*** (mascarado)'));
    tmni_kv('senha_consultante', $c->senhaConsultante() === '' ? '(vazio)' : '*** (mascarado)');
    tmni_kv('timeout_ms', $c->timeoutMs());
    tmni_kv('connect_timeout_ms', $c->connectTimeoutMs());
}
