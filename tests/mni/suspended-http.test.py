"""Verifica a barreira HTTP MNI em cópia sem credenciais ou rede externa."""
import json
import pathlib
import shutil
import socket
import subprocess
import tempfile
import time
import unittest
import urllib.error
import urllib.request


ROOT = pathlib.Path(__file__).resolve().parents[2]


class SuspendedMniTest(unittest.TestCase):
    def test_all_requests_stop_before_configuration_or_service(self):
        with tempfile.TemporaryDirectory(prefix="rei81-mni-") as tmp:
            root = pathlib.Path(tmp)
            api = root / "public" / "api" / "mni"
            api.mkdir(parents=True)
            shutil.copy2(ROOT / "public/api/mni/consultar-processo.php", api)
            reporter = root / "app" / "helpers" / "ApiErrorReporter.php"
            reporter.parent.mkdir(parents=True, exist_ok=True)
            shutil.copy2(ROOT / "app/helpers/ApiErrorReporter.php", reporter)
            marker = root / "unexpected-access"
            # Todo include do caminho antigo termina aqui e denuncia seu uso.
            for path in (api / "config.php", root / "app/helpers/BootstrapHelper.php",
                         root / "app/helpers/CnjValidator.php", root / "app/services/MniService.php"):
                path.parent.mkdir(parents=True, exist_ok=True)
                path.write_text("<?php file_put_contents(" + json.dumps(str(marker).replace('\\', '/'))
                                + ", 'access'); throw new Exception('sentinel');", encoding="utf-8")
            with socket.socket() as sock:
                sock.bind(("127.0.0.1", 0))
                port = sock.getsockname()[1]
            server = subprocess.Popen(["php", "-S", f"127.0.0.1:{port}", "-t", str(root / "public")],
                                      stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)
            try:
                for _ in range(100):
                    if server.poll() is not None:
                        self.fail("Servidor PHP encerrou antes do teste")
                    try:
                        with socket.create_connection(("127.0.0.1", port), timeout=0.1):
                            break
                    except OSError:
                        time.sleep(0.05)
                else:
                    self.fail("Servidor PHP não iniciou")
                url = f"http://127.0.0.1:{port}/api/mni/consultar-processo.php?numero=123"
                for method in ("GET", "POST", "DELETE"):
                    request = urllib.request.Request(url, method=method,
                                                     data=b'{"numero":"123"}' if method == "POST" else None)
                    with self.assertRaises(urllib.error.HTTPError) as caught:
                        urllib.request.urlopen(request, timeout=3)
                    response = caught.exception
                    self.assertEqual(response.code, 503)
                    self.assertEqual(response.headers.get("Cache-Control"), "no-store")
                    self.assertEqual(json.load(response), {
                        "error": "Consulta MNI temporariamente indisponível.", "kind": "unavailable"
                    })
                    response.close()
                    self.assertFalse(marker.exists(), "Configuração ou serviço foi carregado")
            finally:
                server.terminate()
                server.wait(timeout=5)


if __name__ == "__main__":
    unittest.main()
