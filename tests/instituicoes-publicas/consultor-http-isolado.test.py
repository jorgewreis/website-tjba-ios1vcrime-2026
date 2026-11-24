#!/usr/bin/env python3
"""HTTP local com Security real, sessões sintéticas e SQLite temporário.

Não exercita login, MySQL, proxy nem as suítes suspensas de autenticação.
"""
import hashlib
import json
import os
from pathlib import Path
import shutil
import socket
import sqlite3
import subprocess
import tempfile
import time
import urllib.error
import urllib.request
import uuid
from contextlib import closing

ROOT = Path(__file__).resolve().parents[2]
PHP = shutil.which('php')
assert PHP, 'PHP necessário'

with tempfile.TemporaryDirectory(prefix='rei70-http-') as tmp:
    root = Path(tmp)
    shutil.copytree(ROOT / 'app', root / 'app', ignore=shutil.ignore_patterns('*.env', '.env*'))
    (root / 'config').mkdir()
    shutil.copyfile(ROOT / 'config/security.php', root / 'config/security.php')
    (root / 'config/config.php').write_text("<?php define('SESSION_IDLE_TIMEOUT',3600); function bootstrapSession(){if(session_status()!==PHP_SESSION_ACTIVE)session_start();}", encoding='utf-8')
    (root / 'app/helpers/Conection.php').write_text('''<?php
require_once __DIR__ . '/../../config/config.php';
final class Conection {
 static function getInstance(): self {return new self();}
 function getConnection(): PDO {
  $path = getenv('REI70_DB');
  if (!$path || !is_file($path)) throw new RuntimeException('isolated DB missing');
  $p = new PDO('sqlite:' . $path, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
  $p->sqliteCreateFunction('NOW', fn() => gmdate('Y-m-d H:i:s'), 0);
  $p->sqliteCreateFunction('FIELD', fn(...$args) => 0);
  return $p;
 }
}''', encoding='utf-8')
    for rel in ('instituicoes-publicas/get.php', 'instituicoes-publicas/list.php', 'instituicoes-publicas/search.php', 'instituicoes-publicas/setores.php'):
        target = root / 'public/api' / rel
        target.parent.mkdir(parents=True, exist_ok=True)
        shutil.copyfile(ROOT / 'public/api' / rel, target)
    dbpath = root / 'synthetic.sqlite'
    token = 'rei70-synthetic-token'
    with closing(sqlite3.connect(dbpath)) as db:
        db.executescript('''
CREATE TABLE usuarios (id_usuario TEXT, token_acesso TEXT, token_expira TEXT, cadastro_ativo INTEGER);
CREATE TABLE usuario_perfil (usuario TEXT, perfil TEXT);
CREATE TABLE instituicoes_publicas (id TEXT, nome TEXT, nome_fantasia TEXT, cnpj TEXT, observacoes TEXT, ativo INTEGER, created_at TEXT, updated_at TEXT);
CREATE TABLE instituicoes_telefones (id TEXT, instituicao_id TEXT, tipo TEXT, numero TEXT, ramal TEXT, principal INTEGER, ativo INTEGER, created_at TEXT, updated_at TEXT);
CREATE TABLE instituicoes_setores (id TEXT, instituicao_id TEXT, nome_setor TEXT, sigla TEXT, ativo INTEGER, created_at TEXT, updated_at TEXT);
INSERT INTO instituicoes_publicas VALUES ('A','Ativa','Ativa','111','OBS_ATIVA',1,'',''), ('I','Inativa','Inativa','222','OBS_INATIVA',0,'','');
INSERT INTO instituicoes_telefones VALUES ('TA','A','fixo','111',NULL,0,1,'',''), ('TI','A','fixo','222',NULL,0,0,'','');
INSERT INTO instituicoes_setores VALUES ('SA','A','Ativo','SA',1,'',''), ('SI','A','Inativo','SI',0,'',''), ('SX','I','Ativo em instituição inativa','SX',1,'','');
''')
        for user in ('consultor', 'misto', 'estudante'):
            db.execute('INSERT INTO usuarios VALUES (?,?,?,1)', (user, hashlib.sha256(token.encode()).hexdigest(), '2999-01-01 00:00:00'))
        db.executemany('INSERT INTO usuario_perfil VALUES (?,?)', [('consultor','CONSULTOR'),('misto','CONSULTOR'),('misto','SERVIDOR-CARTORIO'),('estudante','ESTUDANTE')])
        db.commit()
    sessions = root / 'sessions'
    sessions.mkdir()
    env = {k: os.environ[k] for k in ('SystemRoot', 'WINDIR', 'PATH', 'TEMP', 'TMP') if k in os.environ}
    env['REI70_DB'] = str(dbpath)
    seed = root / 'seed.php'
    seed.write_text("<?php session_id($argv[1]);session_start();$_SESSION=['id_usuario'=>$argv[2],'token'=>$argv[3],'last_activity_at'=>time()];session_write_close();", encoding='utf-8')
    for user in ('consultor', 'misto', 'estudante'):
        result = subprocess.run([PHP, '-d', 'session.save_path=' + str(sessions), str(seed), 'sess' + user, user, token], env=env, capture_output=True, text=True, timeout=15)
        assert result.returncode == 0, result.stderr
    invalid = subprocess.run([PHP, '-d', 'session.save_path=' + str(sessions), str(seed), 'sessinvalida', 'consultor', 'token-incorreto'], env=env, capture_output=True, text=True, timeout=15)
    assert invalid.returncode == 0, invalid.stderr
    with socket.socket() as sock:
        sock.bind(('127.0.0.1', 0))
        port = sock.getsockname()[1]
    sentinel = uuid.uuid4().hex
    (root / 'public' / (sentinel + '.txt')).write_text(sentinel, encoding='ascii')
    proc = subprocess.Popen([PHP, '-d', 'session.save_path=' + str(sessions), '-S', f'127.0.0.1:{port}', '-t', str(root / 'public')], env=env, stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)
    def call(path, user=None):
        headers = {'Cookie': 'PHPSESSID=sess' + user} if user else {}
        req = urllib.request.Request(f'http://127.0.0.1:{port}/api/instituicoes-publicas/{path}', headers=headers)
        try:
            with urllib.request.urlopen(req, timeout=3) as res:
                return res.status, json.load(res)
        except urllib.error.HTTPError as e:
            return e.code, json.load(e)
    try:
        for _ in range(60):
            try:
                with socket.create_connection(('127.0.0.1', port), timeout=.1): break
            except OSError:
                if proc.poll() is not None: raise RuntimeError('Servidor local encerrou')
                time.sleep(.1)
        else: raise RuntimeError('Servidor local não iniciou')
        if proc.poll() is not None:
            raise RuntimeError('Servidor local encerrou durante a inicialização')
        with urllib.request.urlopen(f'http://127.0.0.1:{port}/{sentinel}.txt', timeout=3) as response:
            if response.read().decode('ascii') != sentinel or proc.poll() is not None:
                raise RuntimeError('Servidor inesperado na porta selecionada')
        status, data = call('list.php?ativo=0', 'consultor')
        assert status == 200 and [x['id'] for x in data['data']] == ['A'], (status, data)
        status, data = call('get.php?id=A', 'consultor')
        assert status == 200 and [x['id'] for x in data['telefones']] == ['TA'] and [x['id'] for x in data['setores']] == ['SA'], (status, data)
        assert data['cnpj'] == '111' and data['observacoes'] == 'OBS_ATIVA', data
        status, data = call('search.php?nome=Ativa', 'consultor')
        assert status == 200 and [x['id'] for x in data['instituicoes']] == ['A'], (status, data)
        status, data = call('search.php?nome=Inativa', 'consultor')
        assert status == 200 and data['instituicoes'] == [], (status, data)
        status, data = call('setores.php?instituicao_id=A', 'consultor')
        assert status == 200 and [x['id'] for x in data['setores']] == ['SA'], (status, data)
        status, data = call('setores.php?instituicao_id=I', 'consultor')
        assert status == 200 and data['setores'] == [], (status, data)
        status, data = call('setores.php?instituicao_id=I', 'misto')
        assert status == 200 and [x['id'] for x in data['setores']] == ['SX'], (status, data)
        assert call('get.php?id=I', 'consultor')[0] == 404
        assert call('get.php?id=I', 'misto')[0] == 200
        status, data = call('get.php?id=A', 'misto')
        assert status == 200 and {x['id'] for x in data['telefones']} == {'TA', 'TI'} and {x['id'] for x in data['setores']} == {'SA', 'SI'}, (status, data)
        assert call('get.php?id=A', 'estudante')[0] == 403
        assert call('get.php?id=A')[0] == 401
        assert call('get.php?id=A', 'invalida')[0] == 401
    finally:
        proc.terminate()
        try: proc.wait(timeout=5)
        except subprocess.TimeoutExpired:
            proc.kill()
            proc.wait(timeout=5)
print('PASS HTTP local, token de sessão e perfis reais; SQLite sintético')
