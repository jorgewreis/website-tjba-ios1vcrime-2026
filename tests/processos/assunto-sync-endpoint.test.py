from pathlib import Path
import os
import socket
import subprocess
import tempfile
import time
from urllib.error import HTTPError, URLError
from urllib.request import Request, urlopen

SOURCE = Path(__file__).resolve().parents[2]

with tempfile.TemporaryDirectory(prefix="assunto-sync-endpoint-") as tmp:
    root = Path(tmp)
    endpoint = root / "public/api/processos/assunto-sync.php"
    endpoint.parent.mkdir(parents=True)
    endpoint.write_bytes((SOURCE / "public/api/processos/assunto-sync.php").read_bytes())
    stubs = {
        "app/helpers/Conection.php": """<?php final class Conection { public static function getInstance(): self { file_put_contents(getenv('MARKER'), 'connected'); return new self; } public function getConnection(): object { return new stdClass; } }""",
        "app/helpers/BootstrapHelper.php": "<?php $_SESSION['id_usuario']='synthetic';",
        "app/helpers/CnjValidator.php": "<?php final class CnjValidator { public static function digits20OrThrow(string $v): string { return $v; } }",
        "app/services/ProcessoAssuntoSyncService.php": """<?php final class ProcessoAssuntoSyncService { public function __construct(object $db) {} public function syncFromDataJud(string $n, array $a): array { file_put_contents(getenv('MARKER'), json_encode($a)); return ['ok'=>true]; } }""",
    }
    for name, source in stubs.items():
        path = root / name
        path.parent.mkdir(parents=True, exist_ok=True)
        path.write_text(source, encoding="utf-8")
    with socket.socket() as sock:
        sock.bind(("127.0.0.1", 0))
        port = sock.getsockname()[1]
    marker = root / "marker"
    env = {**os.environ, "MARKER": str(marker)}
    proc = subprocess.Popen(["php", "-S", f"127.0.0.1:{port}", "-t", str(root / "public")],
                            stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL, env=env)
    url = f"http://127.0.0.1:{port}/api/processos/assunto-sync.php"
    try:
        for _ in range(50):
            try:
                req = Request(url, data=b"{}", headers={"Content-Type": "application/json"})
                with urlopen(req, timeout=0.5):
                    pass
            except HTTPError:
                break
            except URLError:
                time.sleep(0.05)
        for payload in ("{}", '{"assuntos":null}', '{"assuntos":"bad"}', '{"assuntos":{"codigo":"7"}}'):
            marker.unlink(missing_ok=True)
            req = Request(url, data=payload.encode(), headers={"Content-Type": "application/json"})
            try:
                with urlopen(req, timeout=2) as response:
                    raise AssertionError(f"Payload inválido aceito: HTTP {response.status}")
            except HTTPError as error:
                assert error.code == 400, f"status inesperado: {error.code}"
            assert not marker.exists(), "Payload inválido abriu conexão/serviço antes da rejeição"
        req = Request(url, data=b'{"numero":"123","assuntos":[]}', headers={"Content-Type": "application/json"})
        with urlopen(req, timeout=2) as response:
            assert response.status == 200
        assert marker.read_text(encoding="utf-8") == "[]", "Lista vazia explícita não foi encaminhada intacta"
        print("PASS endpoint rejeita payload ausente/inválido antes da conexão e aceita [] explícito")
    finally:
        proc.terminate()
        proc.wait(timeout=5)
