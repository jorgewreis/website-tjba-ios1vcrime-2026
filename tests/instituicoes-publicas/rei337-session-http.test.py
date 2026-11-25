#!/usr/bin/env python3
"""HTTP local: autenticação/perfis reais, sessões e banco exclusivamente sintéticos.

O adaptador de conexão usa SQLite; não exercita login, MySQL, nem conteúdo PDF.
A tabela de pauta é intencionalmente ausente: ADMIN obtém 500 ao alcançar a
consulta real, enquanto estudante externo deve receber 403 antes dela.
"""
import hashlib
import http.client
import os
from pathlib import Path
import shutil
import socket
import sqlite3
import subprocess
import tempfile
import time
import uuid
from contextlib import closing

ROOT = Path(__file__).resolve().parents[2]
PHP = shutil.which('php')
assert PHP, 'PHP necessário'

def safe_source(source):
    for candidate in [source, *source.parents]:
        if candidate == ROOT.parent:
            break
        assert not candidate.is_symlink() and not (hasattr(candidate, 'is_junction') and candidate.is_junction()), candidate
    assert source.resolve().is_relative_to(ROOT.resolve())
    return source

with tempfile.TemporaryDirectory(prefix='rei337-session-') as tmp:
    root = Path(tmp)
    tracked = subprocess.check_output(['git', 'ls-files', '-z', 'app'], cwd=ROOT).decode('utf-8').split('\0')
    for name in tracked:
        if not name.endswith('.php'):
            continue
        source = safe_source(ROOT / name)
        target = root / name
        target.parent.mkdir(parents=True, exist_ok=True)
        shutil.copyfile(source, target)
    (root / 'config').mkdir()
    shutil.copyfile(safe_source(ROOT / 'config/security.php'), root / 'config/security.php')
    (root / 'config/config.php').write_text("<?php define('SESSION_IDLE_TIMEOUT',3600); if (session_status() !== PHP_SESSION_ACTIVE) session_start();", encoding='utf-8')
    (root / 'app/helpers/Conection.php').write_text('''<?php
require_once __DIR__ . '/../../config/config.php';
final class TracePDO extends PDO {
 private function trace(string $query): void {
  $category = str_contains(strtolower($query), 'sala_audiencias_pauta') ? 'pauta' : (str_contains(strtolower($query), 'usuario_perfil') ? 'perfis' : 'outro');
  file_put_contents(getenv('REI337_TRACE'), $category . "\\n", FILE_APPEND);
 }
 function prepare(string $query, array $options = []): PDOStatement|false {
  $this->trace($query);
  return parent::prepare($query, $options);
 }
 function query(string $query, ?int $fetchMode = null, mixed ...$fetchModeArgs): PDOStatement|false {
  $this->trace($query);
  return $fetchMode === null ? parent::query($query) : parent::query($query, $fetchMode, ...$fetchModeArgs);
 }
}
final class Conection {
 static function getInstance(): self { return new self(); }
 function getConnection(): PDO {
  $path = getenv('REI337_SYNTHETIC_DB');
  if (!$path || !is_file($path)) throw new RuntimeException('fixture database unavailable');
  $pdo = new TracePDO('sqlite:' . $path, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
  $pdo->sqliteCreateFunction('NOW', fn() => gmdate('Y-m-d H:i:s'), 0);
  return $pdo;
 }
}''', encoding='utf-8')
    out = root / 'public/api/internal-list-export'
    out.mkdir(parents=True)
    for name in ('_common.php', 'csv.php', 'pdf.php'):
        shutil.copyfile(safe_source(ROOT / 'public/api/internal-list-export' / name), out / name)

    dbpath = root / 'synthetic.sqlite'
    token = 'synthetic-session-token'
    profiles = {
        'student': ['ESTUDANTE'],
        'externalmixed': ['ESTUDANTE', 'CONSULTOR'],
        'consultor': ['CONSULTOR'],
        'adminstudent': ['ADMIN', 'ESTUDANTE'],
        'internalmixed': ['SERVIDOR-AUDIENCIAS', 'ESTUDANTE'],
        'admin': ['ADMIN'],
        'empty': [],
    }
    with closing(sqlite3.connect(dbpath)) as db:
        db.executescript('CREATE TABLE usuarios (id_usuario TEXT, token_acesso TEXT, token_expira TEXT, cadastro_ativo INTEGER); CREATE TABLE usuario_perfil (usuario TEXT, perfil TEXT);')
        for user, roles in profiles.items():
            db.execute('INSERT INTO usuarios VALUES (?,?,?,1)', (user, hashlib.sha256(token.encode()).hexdigest(), '2999-01-01 00:00:00'))
            db.executemany('INSERT INTO usuario_perfil VALUES (?,?)', [(user, role) for role in roles])
        db.commit()

    sessions = root / 'sessions'
    sessions.mkdir()
    seed = root / 'seed.php'
    seed.write_text("<?php session_id($argv[1]); session_start(); $_SESSION=['id_usuario'=>$argv[2], 'token'=>$argv[3], 'last_activity_at'=>time()]; if (($argv[4] ?? '') === 'yes') $_SESSION['access_suppress_admin_profile']=true; session_write_close();", encoding='utf-8')
    env = {key: os.environ[key] for key in ('SystemRoot', 'WINDIR', 'PATH', 'TEMP', 'TMP') if key in os.environ}
    env['REI337_SYNTHETIC_DB'] = str(dbpath)
    trace = root / 'query-categories.txt'
    env['REI337_TRACE'] = str(trace)
    for user in profiles:
        subprocess.run([PHP, '-d', 'session.save_path=' + str(sessions), str(seed), 'sess' + user, user, token], env=env, check=True, capture_output=True, timeout=15)
    for session, user, session_token, suppressed in [('invalid','student','wrong','no'), ('suppressed','adminstudent',token,'yes')]:
        subprocess.run([PHP, '-d', 'session.save_path=' + str(sessions), str(seed), 'sess' + session, user, session_token, suppressed], env=env, check=True, capture_output=True, timeout=15)
    with socket.socket() as sock:
        sock.bind(('127.0.0.1', 0))
        port = sock.getsockname()[1]
    sentinel = uuid.uuid4().hex
    (root / 'public' / (sentinel + '.txt')).write_text(sentinel, encoding='ascii')
    server = subprocess.Popen([PHP, '-d', 'session.save_path=' + str(sessions), '-S', f'127.0.0.1:{port}', '-t', str(root / 'public')], env=env, stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)
    try:
        for _ in range(100):
            try:
                with socket.create_connection(('127.0.0.1', port), timeout=.1): break
            except OSError:
                if server.poll() is not None: raise RuntimeError('servidor encerrou')
                time.sleep(.05)
        else: raise RuntimeError('servidor não iniciou')
        conn = http.client.HTTPConnection('127.0.0.1', port, timeout=5)
        conn.request('GET', '/' + sentinel + '.txt')
        assert conn.getresponse().read().decode('ascii') == sentinel
        conn.close()
        # ADMIN reaches real repository and fails because no synthetic agenda table exists.
        cases = [(None,401), ('invalid',401), ('student',403), ('externalmixed',403), ('empty',403), ('internalmixed',403), ('suppressed',403), ('admin',500), ('adminstudent',500)]
        for fmt in ('csv','pdf'):
            for user, expected in cases:
                trace.write_text('', encoding='ascii')
                conn = http.client.HTTPConnection('127.0.0.1', port, timeout=5)
                headers = {'Cookie': 'PHPSESSID=sess' + user} if user else {}
                conn.request('GET', '/api/internal-list-export/' + fmt + '.php?page=estudapauta', headers=headers)
                response = conn.getresponse()
                body = response.read()
                assert response.status == expected, (fmt, user, response.status, body)
                assert response.getheader('Content-Disposition') is None
                assert not (response.getheader('Content-Type') or '').startswith(('text/csv', 'application/pdf'))
                assert b'SQLSTATE' not in body and b'synthetic-session-token' not in body
                queries = trace.read_text(encoding='ascii').splitlines()
                assert ('pauta' in queries) == (user in ('admin', 'adminstudent')), (fmt, user, queries)
                if user not in (None, 'invalid'):
                    assert 'perfis' in queries, (fmt, user, queries)
                conn.close()
            for user in ('consultor', 'externalmixed'):
                trace.write_text('', encoding='ascii')
                conn = http.client.HTTPConnection('127.0.0.1', port, timeout=5)
                conn.request('GET', '/api/internal-list-export/' + fmt + '.php?page=consultapartes', headers={'Cookie': 'PHPSESSID=sess' + user})
                response = conn.getresponse()
                body = response.read()
                assert 'perfis' in trace.read_text(encoding='ascii').splitlines()
                if fmt == 'csv':
                    assert response.status == 200 and b'Aplique filtros' in body, (fmt, user, response.status, body)
                    assert 'attachment' in (response.getheader('Content-Disposition') or '')
                else:
                    # Renderer dependencies are deliberately absent in this isolated copy.
                    assert response.status == 500 and b'SQLSTATE' not in body, (fmt, user, response.status, body)
                conn.close()
        print('PASS 22 HTTP cases, real Security and profile lookup; SQLite adapter; agenda table absent; PDF renderer absent')
    finally:
        try:
            server.terminate()
            server.wait(timeout=10)
        except (subprocess.TimeoutExpired, OSError):
            server.kill()
            server.wait(timeout=10)
