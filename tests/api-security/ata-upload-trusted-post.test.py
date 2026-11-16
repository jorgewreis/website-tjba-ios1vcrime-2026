#!/usr/bin/env python3
"""Exercise the real upload endpoint and origin guard with synthetic dependencies."""
import json
import base64
import os
from pathlib import Path
import shutil
import subprocess
import tempfile

ROOT = Path(__file__).resolve().parents[2]


with tempfile.TemporaryDirectory(prefix="rei68-upload-") as tmp:
    root = Path(tmp)
    for name in (
        "public/api/sala-audiencias-ata/upload.php",
        "config/config.php",
        "app/helpers/BootstrapHelper.php",
        "app/helpers/AutoloadHelper.php",
        "app/helpers/ApiAccessGuard.php",
    ):
        dest = root / name
        dest.parent.mkdir(parents=True, exist_ok=True)
        shutil.copyfile(ROOT / name, dest)

    (root / "app/helpers/Conection.php").write_text("""<?php
require_once __DIR__ . '/../../config/config.php';
class Conection {
    static function getInstance() { return new self(); }
    function getConnection() { return null; }
}
""", encoding="utf-8")
    (root / "config/security.php").write_text("""<?php
class Security {
    static function isAuthenticated(): bool {
        return getenv('VALID_SESSION') === '1'
            && !empty($_SESSION['id_usuario']) && !empty($_SESSION['token']);
    }
}
""", encoding="utf-8")
    (root / "app/helpers/UsuarioPerfisAutorizacao.php").write_text("""<?php
class UsuarioPerfisAutorizacao {
    static function perfisDoUsuario($id) { return ['AUDIENCIA']; }
}
""", encoding="utf-8")
    (root / "app/access").mkdir(parents=True, exist_ok=True)
    (root / "app/access/AccessEvaluator.php").write_text("""<?php
class AccessEvaluator {
    static function canAccessPage($page, $profiles) { return getenv('ALLOW_PROFILE') === '1'; }
}
""", encoding="utf-8")
    (root / "app/services").mkdir(parents=True, exist_ok=True)
    (root / "app/services/SalaAudienciasAtaService.php").write_text("""<?php
class SalaAudienciasAtaService {
    function __construct($pdo) {}
    function uploadPdf($id, $file, $user) {
        file_put_contents(getenv('SIDE_EFFECT_MARKER'), 'uploaded');
    }
}
""", encoding="utf-8")

    def request(name, origin=None, referer=None, user="synthetic", allowed="1", valid_session=True):
        marker = root / "side-effect.txt"
        marker.unlink(missing_ok=True)
        server = {"REQUEST_METHOD": "POST", "HTTP_HOST": "court.example.test", "HTTPS": "on"}
        if origin is not None:
            server["HTTP_ORIGIN"] = origin
        if referer is not None:
            server["HTTP_REFERER"] = referer
        server_json = base64.b64encode(json.dumps(server).encode()).decode()
        session = {"id_usuario": user, "token": "synthetic-token"} if user else {}
        session_json = base64.b64encode(json.dumps(session).encode()).decode()
        setup = (
            "register_shutdown_function(function(){$s=http_response_code(); fwrite(STDERR, 'HTTP_STATUS:' . ($s === false ? 200 : $s));});"
            "$_SERVER = json_decode(base64_decode('" + server_json + "'), true);"
            "$_SESSION = json_decode(base64_decode('" + session_json + "'), true);"
            "$_POST = ['pauta'=>'synthetic'];"
            "$_FILES = ['arquivo'=>['error'=>UPLOAD_ERR_OK,'name'=>'ata.pdf','tmp_name'=>'synthetic','size'=>8]];"
        )
        code = setup + " require " + json.dumps(str(root / "public/api/sala-audiencias-ata/upload.php").replace("\\", "/")) + ";"
        env = os.environ.copy()
        env.update({"APP_ENV": "production", "DB_HOST": "127.0.0.1", "DB_PORT": "1", "DB_NAME": "synthetic",
                    "DB_USER": "synthetic", "DB_PASS": "synthetic", "TRUSTED_PROXIES": "",
                    "ALLOW_PROFILE": allowed, "VALID_SESSION": "1" if valid_session else "0",
                    "SIDE_EFFECT_MARKER": str(marker)})
        result = subprocess.run(["php", "-d", "display_errors=0", "-r", code], cwd=root, env=env,
                                capture_output=True, text=True, timeout=15)
        assert result.returncode == 0, (name, result.stdout, result.stderr)
        assert "HTTP_STATUS:" + str(expected_status) in result.stderr, (name, result.stderr)
        body = json.loads(result.stdout)
        changed = marker.exists()
        return body, changed

    cases = [
        ("hostile origin", "https://attacker.example", None, "synthetic", "1", True, 403, False),
        ("missing origin and referer", None, None, "synthetic", "1", True, 403, False),
        ("missing session", "https://court.example.test", None, "", "1", True, 401, False),
        ("invalid session token", "https://court.example.test", None, "synthetic", "1", False, 401, False),
        ("denied profile", "https://court.example.test", None, "synthetic", "0", True, 403, False),
        ("trusted origin", "https://court.example.test", None, "synthetic", "1", True, 200, True),
        ("trusted referer", None, "https://court.example.test/internal/atas", "synthetic", "1", True, 200, True),
    ]
    for name, origin, referer, user, allowed, valid_session, expected_status, expected_side_effect in cases:
        body, changed = request(name, origin, referer, user, allowed, valid_session)
        assert (body.get("success") is True) == expected_side_effect, (name, body)
        assert changed is expected_side_effect, (name, changed)
        # The endpoint leaves the normal PHP success status unchanged; failures are explicit.
        if not expected_side_effect:
            assert isinstance(body.get("error"), str) and body["error"], (name, body)

print("PASS 6 synthetic upload requests; untrusted/session/profile rejects caused no service write")
