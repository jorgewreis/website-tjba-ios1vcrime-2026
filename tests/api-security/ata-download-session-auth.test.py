#!/usr/bin/env python3
"""Check that both ata download handlers gate profile/service access on session auth."""
import os
import hashlib
from pathlib import Path
import shutil
import sqlite3
import subprocess
import tempfile
from datetime import datetime, timedelta, timezone

ROOT = Path(__file__).resolve().parents[2]


with tempfile.TemporaryDirectory(prefix="ata-download-auth-") as tmp:
    root = Path(tmp)

    def put(relative: str, content: str) -> None:
        target = root / relative
        target.parent.mkdir(parents=True, exist_ok=True)
        target.write_text(content, encoding="utf-8")

    for endpoint in ("download.php", "download-interno.php"):
        target = root / "public/api/sala-audiencias-ata" / endpoint
        target.parent.mkdir(parents=True, exist_ok=True)
        shutil.copyfile(ROOT / "public/api/sala-audiencias-ata" / endpoint, target)

    put("app/helpers/Conection.php", """<?php
class Conection {
    static function getInstance() { return new self(); }
    function getConnection() {
        file_put_contents(getenv('AUTH_MARKER'), 'database-checked');
        $pdo = new PDO('sqlite:' . getenv('AUTH_DB'), null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $pdo->sqliteCreateFunction('NOW', static fn() => gmdate('Y-m-d H:i:s'));
        return $pdo;
    }
}
""")
    put("app/helpers/BootstrapHelper.php", "<?php\n")
    security_path = root / "config/security.php"
    security_path.parent.mkdir(parents=True, exist_ok=True)
    shutil.copyfile(ROOT / "config/security.php", security_path)
    put("app/helpers/UsuarioPerfisAutorizacao.php", """<?php
class UsuarioPerfisAutorizacao {
    static function perfisDoUsuario($id): array {
        file_put_contents(getenv('PROFILE_MARKER'), 'called');
        return [getenv('PROFILE')];
    }
}
""")
    put("app/access/AccessEvaluator.php", """<?php
class AccessEvaluator {
    static function canAccessPage($page, $profiles): bool {
        return getenv('ALLOW_PAGE') === $page;
    }
}
""")
    put("app/access/ProfileNormalizer.php", """<?php
class ProfileNormalizer {
    static function normalize(array $profiles): array {
        return array_map(static fn($profile) => strtoupper((string) $profile), $profiles);
    }
    static function hasEstudante(array $profiles): bool {
        return in_array('ESTUDANTE', $profiles, true);
    }
}
""")
    put("app/helpers/UserAccessHelper.php", """<?php
class UserAccessHelper {
    static function isUsuarioExterno(array $profiles): bool {
        return in_array('ESTUDANTE', $profiles, true);
    }
}
""")
    put("app/models/Usuario.php", """<?php
class Usuario {
    function listarPerfisUsuario($id): array { return [getenv('PROFILE')]; }
}
""")
    put("app/services/SalaAudienciasAtaService.php", """<?php
class SalaAudienciasInscricaoIndisponivel extends RuntimeException {}
class SalaAudienciasAtaService {
    function __construct($pdo) {}
    private function payload(): array {
        file_put_contents(getenv('SERVICE_MARKER'), 'called');
        return ['nome_original' => 'synthetic.pdf', 'mime' => 'application/pdf', 'full_path' => getenv('PDF_PATH')];
    }
    function getDownloadPayloadForEstudante($idPauta, $usuarioId): array {
        file_put_contents(getenv('SERVICE_MARKER'), 'called');
        if (getenv('ENROLLED') !== '1') throw new SalaAudienciasInscricaoIndisponivel('not enrolled');
        if (getenv('SECRET_ALLOWED') !== '1') throw new InvalidArgumentException('not eligible for secret ata');
        return $this->payload();
    }
    function getDownloadPayloadInterno($idPauta): array { return $this->payload(); }
}
""")

    pdf = root / "synthetic.pdf"
    pdf.write_bytes(b"synthetic-pdf-content")

    def request(endpoint: str, auth_state: str, profile: str, allow_page: str,
                enrolled: str = "1", secret_allowed: str = "1"):
        auth_marker = root / "auth-marker"
        profile_marker = root / "profile-marker"
        service_marker = root / "service-marker"
        auth_db = root / f"auth-{endpoint}-{auth_state}-{profile}-{enrolled}-{secret_allowed}.sqlite"
        for marker in (auth_marker, profile_marker, service_marker):
            marker.unlink(missing_ok=True)
        now = datetime.now(timezone.utc)
        expiry = now + timedelta(days=1)
        active = 1
        stored_token = hashlib.sha256(b"synthetic-token").hexdigest()
        if auth_state == "expired":
            expiry = now - timedelta(days=1)
        elif auth_state == "revoked":
            stored_token = hashlib.sha256(b"different-revoked-token").hexdigest()
        elif auth_state == "inactive":
            active = 0
        elif auth_state != "valid":
            raise AssertionError(f"unsupported auth fixture state: {auth_state}")
        db = sqlite3.connect(auth_db)
        try:
            db.execute("CREATE TABLE usuarios (id_usuario TEXT, token_acesso TEXT, token_expira TEXT, cadastro_ativo INTEGER)")
            db.execute(
                "INSERT INTO usuarios (id_usuario, token_acesso, token_expira, cadastro_ativo) VALUES (?, ?, ?, ?)",
                ("synthetic-user", stored_token, expiry.strftime("%Y-%m-%d %H:%M:%S"), active),
            )
            db.commit()
        finally:
            db.close()

        target = (root / "public/api/sala-audiencias-ata" / endpoint).as_posix()
        script = (
            "$_SERVER['REQUEST_METHOD']='GET'; $_GET=['pauta'=>'synthetic-pauta']; "
            "$_SESSION=['id_usuario'=>'synthetic-user','token'=>'synthetic-token']; "
            "register_shutdown_function(function(){ $status=http_response_code(); "
            "fwrite(STDERR, 'HTTP_STATUS:' . ($status === false ? 200 : $status)); }); "
            "require " + repr(target) + ";"
        )
        env = dict(os.environ)
        env.update({
            "PROFILE": profile,
            "ALLOW_PAGE": allow_page,
            "ENROLLED": enrolled,
            "SECRET_ALLOWED": secret_allowed,
            "AUTH_MARKER": str(auth_marker),
            "AUTH_DB": str(auth_db),
            "PROFILE_MARKER": str(profile_marker),
            "SERVICE_MARKER": str(service_marker),
            "PDF_PATH": str(pdf),
        })
        result = subprocess.run(["php", "-d", "display_errors=0", "-r", script],
                                cwd=root, env=env, capture_output=True, timeout=10)
        assert result.returncode == 0, (endpoint, auth_state, profile, result.stdout, result.stderr)
        status = next((int(line.removeprefix("HTTP_STATUS:"))
                       for line in result.stderr.decode("ascii", errors="replace").splitlines()
                       if line.startswith("HTTP_STATUS:")), None)
        return status, result.stdout, auth_marker.exists(), profile_marker.exists(), service_marker.exists()

    for endpoint in ("download.php", "download-interno.php"):
        for state in ("expired", "revoked", "inactive"):
            status, body, auth_called, profile_called, service_called = request(
                endpoint, state, "ESTUDANTE" if endpoint == "download.php" else "AUDIENCIA",
                "estudaata" if endpoint == "download.php" else "salaata")
            assert status == 401 and "Sessão expirada.".encode("utf-8") in body, (endpoint, state, status, body)
            assert auth_called and not profile_called and not service_called, (endpoint, state)

    status, body, auth_called, profile_called, service_called = request(
        "download.php", "valid", "ESTUDANTE", "estudaata")
    assert status == 200 and body == b"synthetic-pdf-content", (status, body)
    assert auth_called and profile_called and service_called, "download válido de estudante não chegou ao serviço"

    status, body, auth_called, profile_called, service_called = request(
        "download-interno.php", "valid", "AUDIENCIA", "salaata")
    assert status == 200 and body == b"synthetic-pdf-content", (status, body)
    assert auth_called and profile_called and service_called, "download interno válido não chegou ao serviço"

    status, body, auth_called, profile_called, service_called = request(
        "download.php", "valid", "AUDIENCIA", "estudaata")
    assert status == 403 and "Acesso negado.".encode("utf-8") in body, (status, body)
    assert auth_called and profile_called and not service_called, "perfil sem acesso chegou ao serviço"

    status, body, auth_called, profile_called, service_called = request(
        "download.php", "valid", "ESTUDANTE", "estudaata", enrolled="0")
    assert status == 400 and "Audiência indisponível.".encode("utf-8") in body, (status, body)
    assert auth_called and profile_called and service_called, "o controle sintético de inscrição não foi acionado"

    status, body, auth_called, profile_called, service_called = request(
        "download.php", "valid", "ESTUDANTE", "estudaata", secret_allowed="0")
    assert status == 400 and b"not eligible for secret ata" in body, (status, body)
    assert auth_called and profile_called and service_called, "o controle sintético de sigilo não foi acionado"

print("PASS ata downloads: expired/revoked/inactive sessions stop before profile/service; valid profiles preserve service gates")
