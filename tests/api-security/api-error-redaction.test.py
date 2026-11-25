#!/usr/bin/env python3
"""Synthetic public and authenticated API failures must not disclose internals."""
import json
import os
from pathlib import Path
import shutil
import re
import subprocess
import tempfile

ROOT = Path(__file__).resolve().parents[2]
GENERIC_ERROR = "".join(map(chr, [78, 227, 111, 32, 102, 111, 105, 32, 112, 111, 115, 115, 237, 118, 101, 108, 32, 99, 111, 110, 99, 108, 117, 105, 114, 32, 97, 32, 111, 112, 101, 114, 97, 231, 227, 111, 46]))


def copy(root: Path, relative: str) -> Path:
    target = root / relative
    target.parent.mkdir(parents=True, exist_ok=True)
    shutil.copyfile(ROOT / relative, target)
    return target


def run_php(script: Path, no_extensions: bool = False) -> subprocess.CompletedProcess:
    env = {"PATH": os.environ.get("PATH", ""), "SystemRoot": os.environ.get("SystemRoot", "")}
    return subprocess.run(
        ["php", *( ["-n"] if no_extensions else []), "-d", "display_errors=1", str(script)],
        text=True,
        encoding="utf-8",
        capture_output=True,
        timeout=15,
        env=env,
    )


def driver(root: Path, name: str, route: Path, prelude: str = "") -> Path:
    path = root / name
    code = "<?php register_shutdown_function(static function (): void { fwrite(STDERR, 'HTTP_STATUS:' . http_response_code()); }); " + prelude + " require " + json.dumps(str(route)) + ";\n"
    path.write_text(code, encoding="utf-8")
    return path


with tempfile.TemporaryDirectory(prefix="rei67-api-errors-") as temp:
    root = Path(temp)
    copy(root, "app/helpers/ApiErrorReporter.php")

    # Actual public DataJud route with an injected synthetic service exception.
    public_route = copy(root, "public/api/datajud/search-processo.php")
    copy(root, "app/helpers/CnjValidator.php")
    (root / "public/api/datajud/config.php").write_text("<?php return ['api_key' => 'synthetic'];\n", encoding="utf-8")
    service = root / "app/services/DataJudService.php"
    service.parent.mkdir(parents=True, exist_ok=True)
    service.write_text("""<?php
final class DataJudService {
    public function __construct(array $config) {}
    public function searchProcesso(string $numero): array {
        throw new RuntimeException('PUBLIC_INTERNAL_SENTINEL');
    }
}
""", encoding="utf-8")

    public_driver = driver(root, "public-driver.php", public_route, "$_POST = ['numero' => '0000001-69.2020.8.05.0001'];")
    result = run_php(public_driver)
    assert result.returncode == 0, (result.stdout, result.stderr)
    assert json.loads(result.stdout)["error"] == GENERIC_ERROR, repr(json.loads(result.stdout)["error"])
    assert "PUBLIC_INTERNAL_SENTINEL" not in result.stdout + result.stderr
    assert "api_internal_error endpoint=public/api/datajud/search-processo.php exception=RuntimeException" in result.stderr
    assert "HTTP_STATUS:502" in result.stderr, result.stderr

    validation_driver = driver(root, "validation-driver.php", public_route, "$_POST = ['numero' => '00000000000000000000'];")
    result = run_php(validation_driver)
    assert result.returncode == 0, (result.stdout, result.stderr)
    assert json.loads(result.stdout)["error"] == "Número do processo inválido. Verifique o dígito verificador.", result.stdout
    assert "HTTP_STATUS:400" in result.stderr, result.stderr
    assert "api_internal_error" not in result.stderr

    # The opt-in public PJe test proxy must not forward curl or upstream details.
    pje_route = copy(root, "public/api/pje-publico/search-processo.php")
    config = root / "config/config.php"
    config.parent.mkdir(parents=True, exist_ok=True)
    config.write_text("""<?php
function envValue(string $key, $default = '') { return $key === 'PJE_PUBLIC_TEST_PAGE_ENABLED' ? '1' : $default; }
""", encoding="utf-8")
    curl_stubs = """$_POST = ['numero' => '00000016920208050001'];
define('CURLOPT_RETURNTRANSFER', 1);
define('CURLOPT_HEADER', 2);
define('CURLOPT_HTTPGET', 3);
define('CURLOPT_TIMEOUT', 4);
define('CURLOPT_CONNECTTIMEOUT', 5);
define('CURLOPT_HTTPHEADER', 6);
define('CURLINFO_HTTP_CODE', 7);
function curl_init(string $url) { return $url; }
function curl_setopt_array($handle, array $options): bool { return true; }
function curl_exec($handle) { return $GLOBALS['pje_test_body']; }
function curl_error($handle): string { return $GLOBALS['pje_test_curl_error']; }
function curl_getinfo($handle, int $option): int { return $GLOBALS['pje_test_status']; }
function curl_close($handle): void {}
"""
    transport_driver = driver(
        root,
        "pje-transport-driver.php",
        pje_route,
        "$GLOBALS['pje_test_body'] = false; $GLOBALS['pje_test_curl_error'] = 'UPSTREAM_TRANSPORT_SENTINEL'; $GLOBALS['pje_test_status'] = 0; " + curl_stubs,
    )
    result = run_php(transport_driver, no_extensions=True)
    assert result.returncode == 0, (result.stdout, result.stderr)
    assert json.loads(result.stdout)["error"] == "Não foi possível consultar o serviço público PJe de teste."
    assert "UPSTREAM_TRANSPORT_SENTINEL" not in result.stdout + result.stderr
    assert "api_internal_error endpoint=public/api/pje-publico/search-processo.php exception=RuntimeException" in result.stderr
    assert "HTTP_STATUS:502" in result.stderr, result.stderr

    upstream_driver = driver(
        root,
        "pje-upstream-driver.php",
        pje_route,
        "$GLOBALS['pje_test_body'] = '{\"detail\":\"UPSTREAM_BODY_SENTINEL\"}'; $GLOBALS['pje_test_curl_error'] = ''; $GLOBALS['pje_test_status'] = 503; " + curl_stubs,
    )
    result = run_php(upstream_driver, no_extensions=True)
    assert result.returncode == 0, (result.stdout, result.stderr)
    assert json.loads(result.stdout)["error"] == "Não foi possível consultar o serviço público PJe de teste."
    assert "UPSTREAM_BODY_SENTINEL" not in result.stdout + result.stderr
    assert "api_internal_error endpoint=public/api/pje-publico/search-processo.php exception=RuntimeException" in result.stderr
    assert "HTTP_STATUS:503" in result.stderr, result.stderr

    # Caught route exceptions must still write restricted diagnostics.
    for relative in ["public/api/sala-audiencias-ata/download.php", "public/api/sala-audiencias-ata/inscrever.php"]:
        source = (ROOT / relative).read_text(encoding="utf-8")
        catch = source.split("catch (Throwable $e) {", 1)[1].split("}", 1)[0]
        assert "ApiErrorReporter::logUnexpected($e," in catch, relative
        assert "$e->getMessage()" not in catch, relative

    # Actual admin route and auth helper; only Security and the DB adapter are
    # stubbed so the helper passes authentication before the synthetic failure.
    admin_route = copy(root, "public/api/admin/feriados/list.php")
    copy(root, "public/api/admin/_auth_admin_diretora_json.php")
    config.write_text("<?php\n", encoding="utf-8")
    (root / "config/security.php").write_text("<?php final class Security { public static function isAuthenticated(): bool { return true; } }\n", encoding="utf-8")
    connection = root / "app/helpers/Conection.php"
    connection.parent.mkdir(parents=True, exist_ok=True)
    connection.write_text("""<?php
final class Conection {
    public static function getInstance(): self { return new self(); }
    public function getConnection(): PDO { throw new PDOException('AUTH_INTERNAL_SENTINEL'); }
}
""", encoding="utf-8")
    admin_helper = root / "app/helpers/AdminDiretoraHelper.php"
    admin_helper.write_text("<?php function usuarioEhAdminOuDiretora(PDO $connection): bool { return true; }\n", encoding="utf-8")
    result = run_php(driver(root, "admin-driver.php", admin_route))
    assert result.returncode == 0, (result.stdout, result.stderr)
    assert json.loads(result.stdout)["error"] == GENERIC_ERROR, repr(json.loads(result.stdout)["error"])
    assert "AUTH_INTERNAL_SENTINEL" not in result.stdout + result.stderr
    assert "api_internal_error endpoint=public/api/admin/feriados/list.php exception=PDOException" in result.stderr
    assert "HTTP_STATUS:500" in result.stderr, result.stderr

    # Inventory is reproducible and fails if a new route omits registration.
    api_files = list((ROOT / "public/api").rglob("*.php"))
    routes = [path for path in api_files if not path.name.startswith("_") and path.name != "config.php"]

    # Every unexpected Throwable handled as an API 500 must leave a bounded diagnostic.
    def php_block(source: str, opening_brace: int) -> str:
        depth = 1
        index = opening_brace + 1
        quote = None
        escaped = False
        while index < len(source) and depth:
            char = source[index]
            if quote is not None:
                if escaped:
                    escaped = False
                elif char == "\\":
                    escaped = True
                elif char == quote:
                    quote = None
            elif char in ("'", '\\"', "`"):
                quote = char
            elif char == "{":
                depth += 1
            elif char == "}":
                depth -= 1
            index += 1
        return source[opening_brace + 1:index - 1]

    unreported = []
    raw_diagnostics = []
    catch_pattern = re.compile(r"catch\s*\(\s*\\?Throwable\s+\$(\w+)\s*\)\s*\{")
    for route in routes:
        relative = route.relative_to(ROOT).as_posix()
        source = route.read_text(encoding="utf-8")
        for match in catch_pattern.finditer(source):
            body = php_block(source, match.end() - 1)
            emits_500 = "http_response_code(500)" in body or bool(
                re.search(r"(?:ApiJsonResponse::send|respond)\(.*?,\s*500\s*\)", body, re.S)
            )
            if not emits_500:
                continue
            variable = match.group(1)
            if "ApiErrorReporter::" not in body and "error_log(" not in body:
                unreported.append(f"{relative}:{source.count(chr(10), 0, match.start()) + 1}")
            if re.search(rf"\${variable}\s*->\s*getMessage\s*\(", body):
                raw_diagnostics.append(f"{relative}:{source.count(chr(10), 0, match.start()) + 1}")
    assert not unreported, "API 500 catches without diagnostics: " + ", ".join(unreported)
    assert not raw_diagnostics, "API 500 catches may log raw exception messages: " + ", ".join(raw_diagnostics)

    missing = [str(path.relative_to(ROOT)) for path in routes if "ApiErrorReporter::register(" not in path.read_text(encoding="utf-8")]
    assert not missing, "API routes missing error reporter: " + ", ".join(missing)

print("PASS synthetic public/authenticated API redaction, PJe transport/upstream failures, caught diagnostics, status/validation, and route inventory")
