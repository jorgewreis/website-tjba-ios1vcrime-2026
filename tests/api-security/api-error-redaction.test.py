#!/usr/bin/env python3
"""Synthetic public and authenticated API failures must not disclose internals."""
import json
import os
from pathlib import Path
import shutil
import subprocess
import tempfile

ROOT = Path(__file__).resolve().parents[2]
GENERIC_ERROR = "".join(map(chr, [78, 227, 111, 32, 102, 111, 105, 32, 112, 111, 115, 115, 237, 118, 101, 108, 32, 99, 111, 110, 99, 108, 117, 105, 114, 32, 97, 32, 111, 112, 101, 114, 97, 231, 227, 111, 46]))


def copy(root: Path, relative: str) -> Path:
    target = root / relative
    target.parent.mkdir(parents=True, exist_ok=True)
    shutil.copyfile(ROOT / relative, target)
    return target


def run_php(script: Path) -> subprocess.CompletedProcess:
    env = {"PATH": os.environ.get("PATH", ""), "SystemRoot": os.environ.get("SystemRoot", "")}
    return subprocess.run(
        ["php", "-d", "display_errors=1", str(script)],
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

    # Actual admin route and auth helper; only Security and the DB adapter are
    # stubbed so the helper passes authentication before the synthetic failure.
    admin_route = copy(root, "public/api/admin/feriados/list.php")
    copy(root, "public/api/admin/_auth_admin_diretora_json.php")
    config = root / "config/config.php"
    config.parent.mkdir(parents=True, exist_ok=True)
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
    missing = [str(path.relative_to(ROOT)) for path in routes if "ApiErrorReporter::register(" not in path.read_text(encoding="utf-8")]
    assert not missing, "API routes missing error reporter: " + ", ".join(missing)

print("PASS synthetic public/authenticated API redaction, status/validation, and route inventory")
