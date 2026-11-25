#!/usr/bin/env python3
"""Testa leitura real do endpoint com SQLite temporário e identidade simulada."""
import json
import os
from contextlib import ExitStack, closing
from pathlib import Path
import shutil
import sqlite3
import subprocess
import tempfile

ROOT = Path(__file__).resolve().parents[2]
FILES = [
    'public/api/processos-partes/list.php', 'app/helpers/ApiAccessGuard.php', 'app/helpers/ApiErrorReporter.php',
    'app/helpers/ProcessoSetorAuthorization.php',
    'app/helpers/BootstrapHelper.php', 'app/helpers/UserAccessHelper.php',
    'app/helpers/IdGenerator.php', 'app/access/AccessEvaluator.php',
    'app/access/ProfileNormalizer.php', 'app/access/PageAccessRegistry.php',
    'app/access/AccessAreas.php', 'app/access/AccessPolicyVersion.php',
    'app/repositories/ProcessosPartesRepository.php',
]

with tempfile.TemporaryDirectory(prefix='processos-partes-lista-') as directory, ExitStack() as stack:
    root = Path(directory)
    for path in FILES:
        target = root / path
        target.parent.mkdir(parents=True, exist_ok=True)
        shutil.copyfile(ROOT / path, target)

    def write(path, content):
        target = root / path
        target.parent.mkdir(parents=True, exist_ok=True)
        target.write_text(content, encoding='utf-8')

    write('app/helpers/AutoloadHelper.php', '<?php')
    write('config/security.php', '''<?php
final class Security { public static function isAuthenticated(): bool { return getenv('TEST_AUTH') === '1'; } }''')
    write('app/helpers/UsuarioPerfisAutorizacao.php', '''<?php
final class UsuarioPerfisAutorizacao { public static function perfisDoUsuario(string $id): array { return explode(',', getenv('TEST_PROFILE')); } }''')
    write('app/helpers/Conection.php', '''<?php
final class Conection {
    public static function getInstance(): self { return new self(); }
    public function getConnection(): PDO {
        $db = new PDO('sqlite:' . getenv('TEST_DB'));
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        return $db;
    }
}''')
    dbpath = root / 'synthetic.sqlite'
    db = stack.enter_context(closing(sqlite3.connect(dbpath)))
    db.executescript('''
      CREATE TABLE processos_partes(id TEXT, processo_id TEXT, parte_id TEXT, tipo_parte TEXT, polo TEXT,
        prioridade_mulher INTEGER, prioridade_crianca_adolescente INTEGER, prioridade_idoso INTEGER,
        prioridade_pessoa_deficiencia INTEGER, created_at TEXT, updated_at TEXT);
      CREATE TABLE partes(id TEXT, nome_completo TEXT, cpf TEXT, sexo TEXT, data_nascimento TEXT);
      CREATE TABLE processos(id TEXT, numero_processo TEXT, id_setor_responsavel TEXT, sigiloso INTEGER);
      CREATE TABLE setores(id_setor TEXT, ativo INTEGER);
      CREATE TABLE usuario_setor(usuario TEXT, setor TEXT);
      INSERT INTO setores VALUES ('S1',1),('S2',1);
      INSERT INTO usuario_setor VALUES ('synthetic','S1');
      INSERT INTO processos VALUES ('P1','001','S1',0),('P2','002','S2',1),('P3','003',NULL,1),
        ('P4','004','S1',1),('P5','005','S2',0),('P6','006',NULL,0);
      INSERT INTO partes VALUES ('A','Pessoa A','000','X','2000-01-01'),('B','Pessoa B','111','X','2000-01-01');
      INSERT INTO processos_partes VALUES ('L1','P1','A','reu','passivo',0,0,0,0,'2026','2026'),
        ('L2','P2','B','vitima','ativo',0,0,0,0,'2026','2026'),
        ('L3','P2','A','vitima','ativo',0,0,0,0,'2026','2026'),
        ('L4','P3','A','vitima','ativo',0,0,0,0,'2026','2026'),
        ('L5','P4','A','reu','passivo',0,0,0,0,'2026','2026'),
        ('L6','P5','A','reu','passivo',0,0,0,0,'2026','2026'),
        ('L7','P6','A','reu','passivo',0,0,0,0,'2026','2026');
    ''')
    db.commit()

    def invoke(query, profile, auth=True, method='GET'):
        env = os.environ.copy()
        env.update(TEST_DB=str(dbpath), TEST_PROFILE=profile, TEST_AUTH='1' if auth else '0',
                   TEST_QUERY=json.dumps(query), TEST_METHOD=method,
                   TEST_ENDPOINT=str(root / 'public/api/processos-partes/list.php'))
        code = '''$_SERVER['REQUEST_METHOD']=getenv('TEST_METHOD');
$_SESSION=['id_usuario'=>'synthetic']; $_GET=json_decode(getenv('TEST_QUERY'),true);
register_shutdown_function(function(){fwrite(STDERR,'HTTP:'.http_response_code());});
require getenv('TEST_ENDPOINT');'''
        result = subprocess.run(['php', '-r', code], env=env, capture_output=True,
                                text=True, encoding='utf-8', timeout=15)
        if result.returncode != 0:
            raise AssertionError(result.stderr)
        return int(result.stderr.rsplit('HTTP:', 1)[-1] or 200), json.loads(result.stdout)

    before = list(db.execute('SELECT * FROM processos_partes ORDER BY id'))
    cartorio_profile = 'SERVIDOR,CARTORIO'
    for profile, auth, expected in [(cartorio_profile, False, 401),
                                    ('ESTUDANTE', True, 403), ('SERVIDOR', True, 403)]:
        for process_id in ('P1', 'P3'):
            status, body = invoke({'processo_id': process_id}, profile, auth)
            assert status == expected and 'data' not in body, (status, body)
    status, body = invoke({'processo_id': 'P1'}, cartorio_profile)
    assert status == 200 and [r['id'] for r in body['data']] == ['L1'], body
    status, body = invoke({'parte_id': 'B'}, cartorio_profile)
    assert status == 200 and body['data'] == [], body
    # A leitura por Cartório exige setor responsável ativo e lotação do usuário.
    # Processos sem setor ou atribuídos a outra unidade não devem vazar vínculos.
    for process_id, expected_ids in [('P1', ['L1']), ('P2', []), ('P3', []),
                                     ('P4', ['L5']), ('P5', []), ('P6', [])]:
        status, body = invoke({'processo_id': process_id}, cartorio_profile)
        assert status == 200 and sorted(r['id'] for r in body['data']) == expected_ids, (process_id, body)
    status, body = invoke({'parte_id': 'A'}, cartorio_profile)
    assert status == 200 and sorted(r['id'] for r in body['data']) == ['L1', 'L5'], body
    status, body = invoke({}, cartorio_profile)
    assert status == 400 and 'data' not in body, (status, body)
    status, body = invoke({'processo_id': 'P1'}, cartorio_profile, method='POST')
    assert status == 405 and 'data' not in body, (status, body)
    assert list(db.execute('SELECT * FROM processos_partes ORDER BY id')) == before
    print('PASS leitura isolada por processo e parte (SQLite; identidade simulada)')
