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
    for rel in ('instituicoes-publicas/get.php', 'instituicoes-publicas/list.php', 'instituicoes-publicas/search.php', 'instituicoes-publicas/setores.php',
                'pessoas/get.php', 'pessoas/list.php', 'pessoas/search-by-name.php'):
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
CREATE TABLE partes (id TEXT, cpf TEXT, nome_completo TEXT, sexo TEXT, data_nascimento TEXT, profissao TEXT, created_at TEXT, updated_at TEXT);
CREATE TABLE partes_documentos (id TEXT, parte_id TEXT, tipo TEXT, numero TEXT, created_at TEXT, updated_at TEXT);
CREATE TABLE partes_telefones (id TEXT, parte_id TEXT, tipo TEXT, numero TEXT, whatsapp INTEGER, principal INTEGER, ativo INTEGER, created_at TEXT, updated_at TEXT);
CREATE TABLE processos (id TEXT, numero_processo TEXT);
CREATE TABLE processos_partes (id TEXT, processo_id TEXT, parte_id TEXT, tipo_parte TEXT, polo TEXT, prioridade_mulher INTEGER, prioridade_crianca_adolescente INTEGER, prioridade_idoso INTEGER, prioridade_pessoa_deficiencia INTEGER, created_at TEXT, updated_at TEXT);
CREATE TABLE partes_instituicoes (id TEXT, parte_id TEXT, instituicao_id TEXT, setor_id TEXT, profissao TEXT, cargo_funcao TEXT, data_inclusao TEXT, data_inicio TEXT, data_fim TEXT, ativo INTEGER, observacoes TEXT, created_at TEXT, updated_at TEXT);
INSERT INTO instituicoes_publicas VALUES ('A','Ativa','Ativa','111','OBS_ATIVA',1,'',''), ('I','Inativa','Inativa','222','OBS_INATIVA',0,'','');
INSERT INTO instituicoes_telefones VALUES ('TA','A','fixo','111',NULL,0,1,'',''), ('TI','A','fixo','222',NULL,0,0,'','');
INSERT INTO instituicoes_setores VALUES ('SA','A','Ativo','SA',1,'',''), ('SI','A','Inativo','SI',0,'',''), ('SX','I','Ativo em instituição inativa','SX',1,'','');
INSERT INTO partes VALUES ('P','','Pessoa Exemplo','','','','',''),
 ('PI','','Alfa Exemplo','','','','',''), ('PS','','Beta Exemplo','','','','',''), ('PV','','Gama Exemplo','','','','','');
INSERT INTO partes_instituicoes VALUES ('VA','P','A','SA','PROF_ATIVA','CARGO_ATIVO','2026-01-01',NULL,NULL,1,'','',''),
 ('VI','P','I','SX','PROF_INST_INATIVA','CARGO_INST_INATIVA','2026-01-01',NULL,NULL,1,'','',''),
 ('VS','P','A','SI','PROF_SETOR_INATIVO','CARGO_SETOR_INATIVO','2026-01-01',NULL,NULL,1,'','',''),
 ('VV','P','A','SA','PROF_VINCULO_INATIVO','CARGO_VINCULO_INATIVO','2026-01-01',NULL,NULL,0,'','',''),
 ('VPI','PI','I','SX','PROF_INST_INATIVA','CARGO_INST_INATIVA','2026-01-01',NULL,NULL,1,'','',''),
 ('VPS','PS','A','SI','PROF_SETOR_INATIVO','CARGO_SETOR_INATIVO','2026-01-01',NULL,NULL,1,'','',''),
 ('VPV','PV','A','SA','PROF_VINCULO_INATIVO','CARGO_VINCULO_INATIVO','2026-01-01',NULL,NULL,0,'','','');
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
    def call(path, user=None, module='instituicoes-publicas'):
        headers = {'Cookie': 'PHPSESSID=sess' + user} if user else {}
        req = urllib.request.Request(f'http://127.0.0.1:{port}/api/{module}/{path}', headers=headers)
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
        # Mesmo token e servidor local; o filtro de vínculos institucionais do
        # CONSULTOR externo é diferente do perfil misto com acesso interno.
        for user, expected in [('consultor', {'VA'}), ('misto', {'VA', 'VI', 'VS', 'VV'})]:
            status, data = call('get.php?id=P', user, 'pessoas')
            assert status == 200 and {x['id'] for x in data['vinculos_instituicoes']} == expected, (user, status, data)
            status, data = call('list.php?nome=Pessoa', user, 'pessoas')
            assert status == 200 and [x['id'] for x in data['data']] == ['P'], (user, status, data)
            # Listagem inclui apenas vínculos ativos, inclusive para perfil misto.
            assert data['data'][0]['total_instituicoes'] == (1 if user == 'consultor' else 3), (user, data)
            expected_professions = {'PROF_ATIVA'} if user == 'consultor' else {'PROF_ATIVA', 'PROF_INST_INATIVA', 'PROF_SETOR_INATIVO'}
            assert {x['profissao'] for x in data['data'][0]['instituicoes']} == expected_professions, (user, data)
            status, data = call('search-by-name.php?nome=Pessoa', user, 'pessoas')
            assert status == 200 and [x['id'] for x in data['pessoas']] == ['P'], (user, status, data)
            if user == 'consultor':
                encoded = json.dumps(data, ensure_ascii=False)
                assert not any(marker in encoded for marker in ('PROF_INST_INATIVA', 'PROF_SETOR_INATIVO', 'PROF_VINCULO_INATIVO', 'CARGO_INST_INATIVA', 'CARGO_SETOR_INATIVO', 'CARGO_VINCULO_INATIVO', 'Inativa')), data
        # Uma pessoa por tipo de vínculo inativo evita mascaramento pelo ativo.
        for name, person_id, marker in [('Alfa', 'PI', 'CARGO_INST_INATIVA'),
                                         ('Beta', 'PS', 'CARGO_SETOR_INATIVO'),
                                         ('Gama', 'PV', 'CARGO_VINCULO_INATIVO')]:
            status, data = call('search-by-name.php?nome=' + name, 'consultor', 'pessoas')
            assert status == 200 and [x['id'] for x in data['pessoas']] == [person_id], (name, status, data)
            assert marker not in json.dumps(data, ensure_ascii=False), (name, data)
        for user, expected_status in [(None, 401), ('invalida', 401), ('estudante', 403)]:
            for endpoint in ('get.php?id=P', 'list.php?nome=Pessoa', 'search-by-name.php?nome=Pessoa'):
                status, data = call(endpoint, user, 'pessoas')
                assert status == expected_status, (user, endpoint, status, data)
                assert 'data' not in data and 'pessoas' not in data and 'vinculos_instituicoes' not in data, (user, endpoint, data)
    finally:
        proc.terminate()
        try: proc.wait(timeout=5)
        except subprocess.TimeoutExpired:
            proc.kill()
            proc.wait(timeout=5)
print('PASS HTTP local, token de sessão e perfis reais; SQLite sintético')
