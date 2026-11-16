from __future__ import annotations

import hashlib
import http.cookiejar
import json
import os
import shutil
import socket
import sqlite3
import subprocess
import tempfile
import time
import urllib.error
import urllib.request
from contextlib import closing
from pathlib import Path


ROOT = Path(__file__).resolve().parents[2]
PHP = shutil.which("php")
if not PHP:
    raise SystemExit("php CLI não encontrado")


def free_port() -> int:
    with socket.socket() as sock:
        sock.bind(("127.0.0.1", 0))
        return int(sock.getsockname()[1])


with tempfile.TemporaryDirectory(prefix="rei114-http-") as temp_name:
    temp = Path(temp_name)
    shutil.copytree(ROOT / "app", temp / "app")
    shutil.copytree(ROOT / "config", temp / "config")
    api_dir = temp / "public" / "api" / "cartorio-editais"
    api_dir.mkdir(parents=True)
    endpoint = (ROOT / "public/api/cartorio-editais/reconciliar.php").read_text(encoding="utf-8")
    endpoint = endpoint.replace(
        "} catch (Throwable $exception) {",
        "} catch (Throwable $exception) { error_log('REI114 synthetic failure: ' . get_class($exception) . ': ' . $exception->getMessage());",
    )
    (api_dir / "reconciliar.php").write_text(endpoint, encoding="utf-8")

    db_path = temp / "synthetic.sqlite"
    connection_stub = '''<?php
declare(strict_types=1);
final class Conection {
    private static ?self $instance = null;
    private PDO $pdo;
    private function __construct() {
        $this->pdo = new Rei114SqlitePdo('sqlite:' . getenv('REI114_TEST_DB'));
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->sqliteCreateCollation('utf8mb4_unicode_ci', static fn($a, $b) => strcmp($a, $b));
        $this->pdo->sqliteCreateFunction('NOW', static fn() => date('Y-m-d H:i:s'));
        $this->pdo->sqliteCreateFunction('FIELD', static fn(...$args) => 1, -1);
        $this->pdo->sqliteCreateFunction('CONCAT', static fn(...$args) => implode('', $args), -1);
    }
    public static function getInstance(): self { return self::$instance ??= new self(); }
    public function getConnection(): PDO { return $this->pdo; }
}
final class Rei114SqlitePdo extends PDO {
    public function prepare(string $query, array $options = []): PDOStatement|false {
        $query = preg_replace('/\\s+FOR UPDATE\\s*$/i', '', $query);
        $query = preg_replace('/\\s+LIMIT 1\\s*$/i', '', $query);
        return parent::prepare($query, $options);
    }
}
'''
    (temp / "app/helpers/Conection.php").write_text(connection_stub, encoding="utf-8")

    router = temp / "router.php"
    router.write_text('''<?php
function appIsHttpsRequest(): bool { return !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'; }
if (!defined('SESSION_IDLE_TIMEOUT')) { define('SESSION_IDLE_TIMEOUT', 14400); }
if (parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) === '/seed-session') {
    session_start();
    $profile = (string) ($_GET['profile'] ?? 'CARTORIO');
    if ($profile !== 'NONE') {
        $_SESSION['id_usuario'] = 'synthetic-' . strtolower($profile);
        $_SESSION['token'] = 'token-' . strtolower($profile);
        $_SESSION['nome'] = 'Conta sintética';
    }
    header('Content-Type: application/json');
    echo json_encode(['seeded' => true]);
    return true;
}
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
if (str_starts_with($path, '/api/')) { session_start(); }
$file = __DIR__ . '/public' . $path;
if ($path !== '/' && is_file($file)) { return false; }
http_response_code(404);
return true;
''', encoding="utf-8")

    with closing(sqlite3.connect(db_path)) as db, db:
        db.executescript("""
            CREATE TABLE usuario_perfil (usuario TEXT, perfil TEXT);
            CREATE TABLE processos (id TEXT, numero_processo TEXT, classe_id TEXT);
            CREATE TABLE classes (id TEXT, nome TEXT, codigo TEXT);
            CREATE TABLE usuarios (id_usuario TEXT, nome TEXT, sobrenome TEXT, perfil TEXT, token_acesso TEXT, token_expira TEXT, cadastro_ativo INTEGER DEFAULT 1);
            CREATE TABLE feriados (ativo INTEGER, data_inicio TEXT, data_fim TEXT, regra_data TEXT);
            CREATE TABLE editais (
                id TEXT PRIMARY KEY, processo_id TEXT, tipo_edital TEXT, prazo_dias INTEGER,
                data_publicacao TEXT, data_inicio_contagem TEXT, data_decurso_prazo TEXT,
                status TEXT, created_at TEXT, updated_at TEXT, observacoes TEXT,
                criado_por TEXT, atualizado_por TEXT
            );
            CREATE TABLE editais_subtarefas (
                id TEXT PRIMARY KEY, edital_id TEXT, tipo TEXT, ordem INTEGER, status TEXT,
                dados_especificos TEXT, created_at TEXT, updated_at TEXT
            );
            INSERT INTO editais (id, prazo_dias, data_publicacao, status)
            VALUES ('E-HTTP', 1, '2026-01-01', 'pendente');
        """)
        for profile in ("SERVIDOR-CARTORIO", "AUDIENCIA"):
            user = "synthetic-" + profile.lower()
            token = "token-" + profile.lower()
            db.execute(
                "INSERT INTO usuarios (id_usuario, token_acesso, token_expira, cadastro_ativo) VALUES (?, ?, '2999-01-01 00:00:00', 1)",
                (user, hashlib.sha256(token.encode()).hexdigest()),
            )
            db.execute("INSERT INTO usuario_perfil (usuario, perfil) VALUES (?, ?)", (user, profile))

    port = free_port()
    origin = f"http://127.0.0.1:{port}"
    env = os.environ.copy()
    env.update({"REI114_TEST_DB": str(db_path), "APP_ENV": "testing", "PHP_SESSION_SAVE_PATH": str(temp / "sessions")})
    (temp / "sessions").mkdir()
    server = subprocess.Popen(
        [PHP, "-d", "session.save_path=" + str(temp / "sessions"), "-S", f"127.0.0.1:{port}", "-t", str(temp / "public"), str(router)],
        cwd=temp, env=env, stdout=subprocess.PIPE, stderr=subprocess.PIPE,
    )
    try:
        base = urllib.request.build_opener()
        for _ in range(100):
            if server.poll() is not None:
                raise RuntimeError("servidor PHP encerrou antes de ficar pronto")
            try:
                urllib.request.urlopen(origin + "/seed-session?profile=NONE", timeout=0.2).close()
                break
            except Exception:
                time.sleep(0.05)
        else:
            raise RuntimeError("servidor PHP não iniciou")

        def client(profile: str | None = None):
            jar = http.cookiejar.CookieJar()
            opener = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(jar))
            if profile is not None:
                opener.open(origin + "/seed-session?profile=" + profile, timeout=3).close()
            return opener

        def request(opener, method: str, body=None, request_origin=origin):
            data = None if body is None else json.dumps(body).encode()
            req = urllib.request.Request(
                origin + "/api/cartorio-editais/reconciliar.php", data=data,
                method=method, headers={"Content-Type": "application/json"},
            )
            if request_origin is not None:
                req.add_header("Origin", request_origin)
            try:
                with opener.open(req, timeout=5) as response:
                    return response.status, response.read()
            except urllib.error.HTTPError as error:
                return error.code, error.read()

        anonymous = client()
        assert request(anonymous, "POST", {"edital_ids": ["E-HTTP"]})[0] == 401
        unauthorized = client("AUDIENCIA")
        status, body = request(unauthorized, "POST", {"edital_ids": ["E-HTTP"]})
        assert status == 403, (status, body.decode(errors="replace"))
        cartorio = client("SERVIDOR-CARTORIO")
        assert request(cartorio, "GET")[0] == 403
        assert request(cartorio, "POST", {"edital_ids": ["E-HTTP"]}, None)[0] == 403
        assert request(cartorio, "POST", {"edital_ids": ["E-HTTP"]}, "http://evil.invalid")[0] == 403
        with closing(sqlite3.connect(db_path)) as db:
            assert db.execute("SELECT COUNT(*) FROM editais_subtarefas").fetchone()[0] == 0
        status, payload = request(cartorio, "POST", {"edital_ids": ["E-HTTP"]})
        assert status == 200, (status, payload.decode(errors="replace"))
        parsed = json.loads(payload)
        assert parsed.get("success") is True, parsed
        with closing(sqlite3.connect(db_path)) as db:
            assert db.execute("SELECT COUNT(*) FROM editais_subtarefas WHERE edital_id='E-HTTP'").fetchone()[0] == 2
        print("PASS REI-114 HTTP: sessão/guard, método, origem, recusa sem escrita e reconciliação autorizada (SQLite sintético)")
    finally:
        server.terminate()
        try:
            server.wait(timeout=5)
        except subprocess.TimeoutExpired:
            server.kill()
            server.wait(timeout=5)
        if server.stdout:
            server.stdout.close()
        if server.stderr:
            log = server.stderr.read().decode(errors="replace")
            if "Fatal error" in log or "REI114 synthetic failure:" in log:
                print("PHP server log:\n" + log)
            server.stderr.close()
