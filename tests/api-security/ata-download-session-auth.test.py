#!/usr/bin/env python3
"""HTTP regression for ata downloads using only temporary code, SQLite and files."""
from datetime import datetime, timedelta
from http.client import HTTPConnection
from pathlib import Path
import os
import shutil
import socket
import sqlite3
import subprocess
import tempfile
import time

ROOT = Path(__file__).resolve().parents[2]
SOURCE = ROOT


def write(path: Path, content: str) -> None:
    path.parent.mkdir(parents=True, exist_ok=True)
    path.write_text(content, encoding="utf-8")


if (ROOT / ".env").exists():
    raise RuntimeError("Recusado: teste isolado não usa checkout com .env.")
for name in ("helpers", "repositories", "services", "models", "access"):
    source = SOURCE / "app" / name
    if not source.is_dir() or any(p.is_symlink() for p in source.rglob("*")):
        raise RuntimeError(f"Recusado: árvore de código ausente ou com symlink: {source}")

with tempfile.TemporaryDirectory(prefix="ata-download-http-") as tmp:
    root = Path(tmp).resolve()
    for name in ("helpers", "repositories", "services", "models", "access"):
        shutil.copytree(SOURCE / "app" / name, root / "app" / name)
    for relative in (
        "public/api/sala-audiencias-ata/download.php",
        "public/api/sala-audiencias-ata/download-interno.php",
        "config/security.php",
    ):
        target = root / relative
        target.parent.mkdir(parents=True, exist_ok=True)
        shutil.copyfile(SOURCE / relative, target)

    write(root / "app/helpers/Conection.php", """<?php
final class Conection {
    public static function getInstance(): self { static $instance; return $instance ??= new self(); }
    public function getConnection(): PDO {
        $pdo = new PDO('sqlite:' . getenv('ATA_TEST_DB'), null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $pdo->sqliteCreateFunction('NOW', static fn(): string => date('Y-m-d H:i:s'));
        return $pdo;
    }
}
""")
    write(root / "app/helpers/UsuarioPerfisAutorizacao.php", """<?php
final class UsuarioPerfisAutorizacao {
    public static function perfisDoUsuario(string $id): array { return $_SESSION['fixture_profiles'] ?? []; }
}
""")
    write(root / "app/models/Usuario.php", """<?php
final class Usuario {
    public function listarPerfisUsuario(string $id): array { return $_SESSION['fixture_profiles'] ?? []; }
}
""")

    db_path = root / "fixture.sqlite"
    db = sqlite3.connect(db_path)
    db.executescript("""
        CREATE TABLE usuarios (id_usuario TEXT PRIMARY KEY, token_acesso TEXT, token_expira TEXT, cadastro_ativo INTEGER);
        CREATE TABLE classes (id TEXT PRIMARY KEY, nome TEXT);
        CREATE TABLE processos (id TEXT PRIMARY KEY, numero_processo TEXT, classe_id TEXT, sigiloso INTEGER);
        CREATE TABLE sala_audiencias_pauta (
            id_pauta TEXT PRIMARY KEY, ativo INTEGER, processo TEXT, numero_sequencial INTEGER, ano INTEGER,
            tipo_audiencia TEXT, data_audiencia TEXT, hora_audiencia TEXT, status TEXT,
            data_hora_inicio_efetivo TEXT, data_hora_fim_efetivo TEXT, duracao_estimada_minutos INTEGER,
            localizacao TEXT, link_audiencia TEXT
        );
        CREATE TABLE sala_audiencias_pauta_parte (pauta TEXT, ativo INTEGER, maioridade TEXT);
        CREATE TABLE sala_audiencias_estudante_inscricao (id TEXT, pauta TEXT, usuario TEXT, created_at TEXT);
        CREATE TABLE processo_prioridades (id TEXT, processo_id TEXT, codigo TEXT, origem TEXT, regra_detalhe TEXT, created_at TEXT);
        CREATE TABLE sala_audiencias_ata (
            id_ata TEXT PRIMARY KEY, pauta TEXT, arquivo_relativo TEXT, nome_original TEXT, mime TEXT,
            tamanho_bytes INTEGER, uploaded_at TEXT, uploaded_by TEXT, expires_at TEXT
        );
    """)
    expires = (datetime.now() + timedelta(days=1)).strftime("%Y-%m-%d %H:%M:%S")
    db.execute("INSERT INTO classes VALUES ('class-1', 'Classe sintética')")
    db.executemany("INSERT INTO processos VALUES (?, ?, 'class-1', ?)", [
        ("process-1", "0000001-69.2020.8.05.0001", 0),
        ("process-secret", "0000002-54.2020.8.05.0001", 1),
    ])
    db.executemany("""INSERT INTO sala_audiencias_pauta
        (id_pauta, ativo, processo, numero_sequencial, ano, tipo_audiencia, data_audiencia, hora_audiencia,
         status, localizacao) VALUES (?, 1, ?, 1, 2026, 'Instrução', '2026-11-19', '10:00', 'realizada', 'Sala sintética')""", [
        ("hearing-valid", "process-1"), ("hearing-secret", "process-secret"),
    ])
    db.execute("INSERT INTO sala_audiencias_estudante_inscricao VALUES ('enrollment-1','hearing-valid','synthetic-user',CURRENT_TIMESTAMP)")
    db.executemany("""INSERT INTO sala_audiencias_ata
        (id_ata,pauta,arquivo_relativo,nome_original,mime,tamanho_bytes,uploaded_at,uploaded_by,expires_at)
        VALUES (?, ?, 'sala-audiencias-atas/synthetic.pdf', 'synthetic.pdf', 'application/pdf', 21, CURRENT_TIMESTAMP, 'staff', ?)""", [
        ("ata-valid", "hearing-valid", expires), ("ata-secret", "hearing-secret", expires),
    ])
    db.commit()
    db.close()

    storage = root / "storage/sala-audiencias-atas"
    storage.mkdir(parents=True)
    (storage / "synthetic.pdf").write_bytes(b"synthetic-pdf-content")
    sessions = root / "sessions"
    sessions.mkdir()
    router = root / "router.php"
    write(router, """<?php
define('SESSION_IDLE_TIMEOUT', 3600);
$state = (string) ($_SERVER['HTTP_X_FIXTURE_AUTH'] ?? 'valid');
$profile = (string) ($_SERVER['HTTP_X_FIXTURE_PROFILE'] ?? 'ESTUDANTE');
$token = 'synthetic-token';
$storedToken = hash('sha256', $token);
$expires = date('Y-m-d H:i:s', time() + 3600);
$active = 1;
$lastActivity = time();
if ($state === 'expired') $expires = date('Y-m-d H:i:s', time() - 3600);
elseif ($state === 'idle') $lastActivity = time() - SESSION_IDLE_TIMEOUT - 1;
elseif ($state === 'revoked') $storedToken = hash('sha256', 'revoked-token');
elseif ($state === 'inactive') $active = 0;
elseif ($state !== 'valid') { http_response_code(400); echo 'bad fixture'; return true; }
$db = new PDO('sqlite:' . getenv('ATA_TEST_DB'), null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$st = $db->prepare('INSERT OR REPLACE INTO usuarios (id_usuario, token_acesso, token_expira, cadastro_ativo) VALUES (?, ?, ?, ?)');
$st->execute(['synthetic-user', $storedToken, $expires, $active]);
session_save_path(getenv('ATA_TEST_SESSIONS'));
session_id(bin2hex(random_bytes(16)));
session_start();
$_SESSION = ['id_usuario' => 'synthetic-user', 'token' => $token, 'last_activity_at' => $lastActivity, 'fixture_profiles' => array_filter(array_map('trim', explode(',', $profile)))];
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if ($path === '/api/sala-audiencias-ata/download.php') {
    require __DIR__ . '/public/api/sala-audiencias-ata/download.php';
    return true;
}
if ($path === '/api/sala-audiencias-ata/download-interno.php') {
    require __DIR__ . '/public/api/sala-audiencias-ata/download-interno.php';
    return true;
}
http_response_code(404);
echo 'not found';
return true;
""")

    with socket.socket() as sock:
        sock.bind(("127.0.0.1", 0))
        port = sock.getsockname()[1]
    env = dict(os.environ)
    env.update({"ATA_TEST_DB": str(db_path), "ATA_TEST_SESSIONS": str(sessions)})
    server = subprocess.Popen(
        ["php", "-d", "log_errors=1", "-d", f"error_log={root / 'php.log'}", "-S", f"127.0.0.1:{port}", str(router)], cwd=root, env=env,
        stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL,
    )

    def request(path: str, auth: str = "valid", profile: str = "ESTUDANTE"):
        conn = HTTPConnection("127.0.0.1", port, timeout=3)
        try:
            conn.request("GET", path, headers={"X-Fixture-Auth": auth, "X-Fixture-Profile": profile})
            response = conn.getresponse()
            return response.status, response.read(), dict(response.getheaders())
        finally:
            conn.close()

    try:
        ready = False
        for _ in range(50):
            if server.poll() is not None:
                raise RuntimeError("Servidor PHP local encerrou antes de iniciar.")
            try:
                status, _, _ = request("/missing")
                if status == 404:
                    ready = True
                    break
            except OSError:
                time.sleep(0.1)
        if not ready:
            raise RuntimeError("Servidor PHP local não ficou pronto.")

        for endpoint, profile in (("download.php", "ESTUDANTE"), ("download-interno.php", "SERVIDOR-AUDIENCIAS")):
            for state in ("expired", "idle", "revoked", "inactive"):
                status, body, _ = request(f"/api/sala-audiencias-ata/{endpoint}?pauta=hearing-valid", state, profile)
                assert status == 401 and body == "Sessão expirada.".encode(), (endpoint, state, status, body)

        status, body, headers = request("/api/sala-audiencias-ata/download.php?pauta=hearing-valid")
        assert status == 200 and body == b"synthetic-pdf-content", (status, body)
        assert headers.get("Content-Type") == "application/pdf"
        status, body, _ = request("/api/sala-audiencias-ata/download-interno.php?pauta=hearing-valid", profile="SERVIDOR-AUDIENCIAS")
        assert status == 200 and body == b"synthetic-pdf-content", (status, body)

        status, body, _ = request("/api/sala-audiencias-ata/download.php?pauta=hearing-valid", profile="CONSULTOR")
        assert status == 403 and body == "Acesso negado.".encode(), (status, body)
        status, body, _ = request("/api/sala-audiencias-ata/download.php?pauta=hearing-valid", profile="SERVIDOR-AUDIENCIAS")
        assert status == 403 and body == "Acesso negado.".encode(), (status, body)

        status, body, _ = request("/api/sala-audiencias-ata/download.php?pauta=hearing-secret")
        assert status == 400 and b"synthetic-pdf-content" not in body, (status, body)
        status, body, _ = request("/api/sala-audiencias-ata/download-interno.php?pauta=hearing-secret", profile="SERVIDOR-AUDIENCIAS")
        assert status == 400 and b"synthetic-pdf-content" not in body, (status, body)

        # Re-evaluate student eligibility at download time, after enrollment.
        db = sqlite3.connect(db_path)
        db.execute("INSERT INTO sala_audiencias_pauta_parte (pauta, ativo, maioridade) VALUES ('hearing-valid', 1, 'menor')")
        db.commit()
        db.close()
        status, body, _ = request("/api/sala-audiencias-ata/download.php?pauta=hearing-valid")
        assert status == 400 and b"synthetic-pdf-content" not in body, ("minor added after enrollment", status, body)

        db = sqlite3.connect(db_path)
        db.execute("DELETE FROM sala_audiencias_pauta_parte WHERE pauta='hearing-valid'")
        db.execute("INSERT INTO processo_prioridades (id, processo_id, codigo) VALUES ('priority-after-enrollment', 'process-1', 'violencia_domestica')")
        db.commit()
        db.close()
        status, body, _ = request("/api/sala-audiencias-ata/download.php?pauta=hearing-valid")
        assert status == 400 and b"synthetic-pdf-content" not in body, ("blocking priority added after enrollment", status, body)

        db = sqlite3.connect(db_path)
        db.execute("DELETE FROM processo_prioridades WHERE id='priority-after-enrollment'")
        db.execute("UPDATE processos SET sigiloso=1 WHERE id='process-1'")
        db.commit()
        db.close()
        status, body, _ = request("/api/sala-audiencias-ata/download.php?pauta=hearing-valid")
        assert status == 400 and b"synthetic-pdf-content" not in body, ("secrecy enabled after enrollment", status, body)

        db = sqlite3.connect(db_path)
        db.execute("UPDATE processos SET sigiloso=0 WHERE id='process-1'")
        db.execute("UPDATE sala_audiencias_ata SET expires_at='2000-01-01 00:00:00' WHERE pauta='hearing-valid'")
        db.commit()
        db.close()
        status, body, _ = request("/api/sala-audiencias-ata/download.php?pauta=hearing-valid")
        assert status == 400 and b"synthetic-pdf-content" not in body, ("ata expired after enrollment", status, body)

        db = sqlite3.connect(db_path)
        db.execute("UPDATE sala_audiencias_ata SET expires_at='2099-01-01 00:00:00' WHERE pauta='hearing-valid'")
        db.execute("DELETE FROM sala_audiencias_estudante_inscricao WHERE pauta='hearing-valid'")
        db.commit()
        db.close()
        status, body, _ = request("/api/sala-audiencias-ata/download.php?pauta=hearing-valid")
        assert status == 400 and b"synthetic-pdf-content" not in body, (status, body)
        print("PASS HTTP ata download: session/profile/enrollment gates and post-enrollment eligibility, secrecy and expiry changes")
    finally:
        server.terminate()
        try:
            server.wait(timeout=5)
        except subprocess.TimeoutExpired:
            server.kill()
            server.wait(timeout=5)
