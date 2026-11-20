#!/usr/bin/env python3
"""Exercita endpoints reais e persistência SQLite temporária; identidade simulada."""
import json
import os
from pathlib import Path
import shutil
import sqlite3
import subprocess
import tempfile

ROOT = Path(__file__).resolve().parents[2]
FILES = [
    'app/helpers/ApiAccessGuard.php', 'app/helpers/BootstrapHelper.php',
    'app/helpers/UserAccessHelper.php', 'app/helpers/IdGenerator.php',
    'app/helpers/TextFormatter.php', 'app/access/AccessEvaluator.php',
    'app/access/ProfileNormalizer.php', 'app/access/PageAccessRegistry.php',
    'app/access/AccessAreas.php', 'app/access/AccessPolicyVersion.php',
    'app/services/InstituicoesPublicasService.php',
    'app/repositories/InstituicoesPublicasRepository.php',
    'app/repositories/InstituicoesSetoresRepository.php',
    'app/repositories/InstituicoesTelefonesRepository.php',
]
ENDPOINTS = {
    'create.php': {'nome': 'Instituicao Teste'},
    'update.php': {'id': 'INS-1', 'nome': 'Instituicao Alterada'},
    'setores/create.php': {'instituicao_id': 'INS-1', 'nome_setor': 'Setor Teste'},
    'setores/update.php': {'id': 'SET-1', 'nome_setor': 'Setor Alterado'},
    'setores/delete.php': {'id': 'SET-1'},
    'telefones/create.php': {'instituicao_id': 'INS-1', 'numero': '12345678'},
    'telefones/update.php': {'id': 'TEL-1', 'numero': '87654321'},
    'telefones/delete.php': {'id': 'TEL-1'},
}

with tempfile.TemporaryDirectory(prefix='instituicoes-sqlite-') as directory:
    temp = Path(directory)
    for file in FILES + ['public/api/instituicoes-publicas/' + name for name in ENDPOINTS]:
        target = temp / file
        target.parent.mkdir(parents=True, exist_ok=True)
        shutil.copyfile(ROOT / file, target)

    def write(path, content):
        target = temp / path
        target.parent.mkdir(parents=True, exist_ok=True)
        target.write_text(content, encoding='utf-8')

    write('app/helpers/AutoloadHelper.php', '<?php')
    write('config/security.php', '''<?php
final class Security {
    public static function isAuthenticated(): bool { return getenv('TEST_AUTH') === '1'; }
    public static function validarCNPJ($v): bool { return true; }
}''')
    write('app/helpers/UsuarioPerfisAutorizacao.php', '''<?php
final class UsuarioPerfisAutorizacao {
    public static function perfisDoUsuario(string $id): array { return [getenv('TEST_PROFILE')]; }
}''')
    write('app/helpers/Conection.php', '''<?php
function appIsHttpsRequest(): bool { return false; }
final class TestPDO extends PDO {
    public function prepare(string $query, array $options = []): PDOStatement|false {
        return parent::prepare(preg_replace('/\\s+LIMIT 1$/i', '', $query), $options);
    }
}
final class Conection {
    public static function getInstance(): self { return new self(); }
    public function getConnection(): PDO {
        $pdo = new TestPDO('sqlite:' . getenv('TEST_DB'));
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->sqliteCreateFunction('NOW', static fn() => '2026-01-01 00:00:00', 0);
        return $pdo;
    }
}''')
    dbpath = temp / 'synthetic.sqlite'
    db = sqlite3.connect(dbpath)
    db.executescript('''
        CREATE TABLE instituicoes_publicas (id TEXT PRIMARY KEY, nome TEXT, nome_fantasia TEXT, cnpj TEXT, observacoes TEXT, ativo INTEGER, created_at TEXT, updated_at TEXT);
        CREATE TABLE instituicoes_setores (id TEXT PRIMARY KEY, instituicao_id TEXT, nome_setor TEXT, sigla TEXT, ativo INTEGER, created_at TEXT, updated_at TEXT);
        CREATE TABLE instituicoes_telefones (id TEXT PRIMARY KEY, instituicao_id TEXT, tipo TEXT, numero TEXT, ramal TEXT, principal INTEGER, ativo INTEGER, created_at TEXT, updated_at TEXT);
        INSERT INTO instituicoes_publicas VALUES ('INS-1','Instituicao Inicial',NULL,NULL,NULL,1,'2026','2026');
        INSERT INTO instituicoes_setores VALUES ('SET-1','INS-1','Setor Inicial',NULL,1,'2026','2026');
        INSERT INTO instituicoes_telefones VALUES ('TEL-1','INS-1',NULL,'00000000',NULL,0,1,'2026','2026');
    ''')
    db.commit()

    def snapshot():
        return tuple(tuple(db.execute('SELECT * FROM ' + table + ' ORDER BY id').fetchall()) for table in
                     ('instituicoes_publicas', 'instituicoes_setores', 'instituicoes_telefones'))

    def invoke(endpoint, payload, profile, authenticated=True, origin='http://example.invalid', method='POST'):
        env = os.environ.copy()
        env.update(TEST_DB=str(dbpath), TEST_PROFILE=profile, TEST_AUTH='1' if authenticated else '0',
                   TEST_PAYLOAD=json.dumps(payload), TEST_ORIGIN=origin, TEST_METHOD=method,
                   TEST_ENDPOINT=str(temp / 'public/api/instituicoes-publicas' / endpoint))
        code = '''$_SERVER['REQUEST_METHOD']=getenv('TEST_METHOD'); $_SERVER['HTTP_HOST']='example.invalid';
$_SERVER['HTTP_ORIGIN']=getenv('TEST_ORIGIN'); $_SESSION=['id_usuario'=>'synthetic'];
$_POST=json_decode(getenv('TEST_PAYLOAD'),true);
register_shutdown_function(function(){fwrite(STDERR, 'HTTP:'.http_response_code());});
require getenv('TEST_ENDPOINT');'''
        result = subprocess.run(['php', '-r', code], env=env, text=True, encoding='utf-8', capture_output=True, timeout=15)
        if result.returncode != 0:
            raise AssertionError((endpoint, result.stderr))
        status = result.stderr.rsplit('HTTP:', 1)[-1]
        return int(status or 200), json.loads(result.stdout)

    # Every denied request targets an existing object or a valid creation payload.
    for endpoint, payload in ENDPOINTS.items():
        for profile, auth, origin, method, expected in [
            ('SERVIDOR-CARTORIO', False, 'http://example.invalid', 'POST', 401),
            ('CONSULTOR', True, 'http://example.invalid', 'POST', 403),
            ('SERVIDOR-GABINETE', True, 'http://example.invalid', 'POST', 403),
            ('SERVIDOR-CARTORIO', True, 'http://evil.invalid', 'POST', 403),
            ('SERVIDOR-CARTORIO', True, 'http://example.invalid', 'GET', 403),
        ]:
            before = snapshot()
            status, _ = invoke(endpoint, payload, profile, auth, origin, method)
            assert status == expected, (endpoint, profile, status, expected)
            assert snapshot() == before, (endpoint, profile, 'denied write changed data')

    # Each allowed operation must actually change the synthetic database.
    for endpoint, payload in ENDPOINTS.items():
        before = snapshot()
        status, _ = invoke(endpoint, payload, 'SERVIDOR-CARTORIO')
        assert status in (200, 201), (endpoint, status)
        assert snapshot() != before, (endpoint, 'authorized write did not change data')
    db.close()

print('PASS 40 recusas sem alteração e 8 escritas autorizadas (SQLite isolado, identidade simulada)')
