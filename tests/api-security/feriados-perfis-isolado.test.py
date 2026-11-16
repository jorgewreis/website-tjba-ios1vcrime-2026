#!/usr/bin/env python3
"""Real authorization and holiday service against an exclusively temporary SQLite DB.

The connection/config adapters are test-only; no .env, network, Docker or real DB.
PHP CLI supplies a synthetic session and request, not an HTTP login flow.
"""
import hashlib
from contextlib import ExitStack
import json
import os
from pathlib import Path
import shutil
import sqlite3
import subprocess
import tempfile

ROOT = Path(__file__).resolve().parents[2]
FILES = [
    'config/security.php', 'app/helpers/BootstrapHelper.php', 'app/helpers/AutoloadHelper.php',
    'app/helpers/AdminDiretoraHelper.php', 'app/helpers/DeleteAuthorization.php',
    'app/helpers/UsuarioPerfisAutorizacao.php', 'app/helpers/EffectiveAccessProfiles.php',
    'app/access/ProfileNormalizer.php', 'app/models/Usuario.php',
    'app/services/FeriadosService.php', 'app/repositories/FeriadosRepository.php',
    'public/api/admin/_auth_admin_diretora_json.php',
    *('public/api/admin/feriados/' + x + '.php' for x in ('create', 'update', 'delete')),
]
ID = 'FER-' + 'a' * 26
TOKEN = 'synthetic-session-token'
PAYLOAD = {'id': ID, 'tipo_feriado': 'federal', 'regra_data': 'unica',
           'data_inicio': '2030-01-01', 'data_fim': '2030-01-01', 'ativo': 0,
           'descricao': 'alterado'}

with tempfile.TemporaryDirectory(prefix='rei91-') as tmp, ExitStack() as stack:
    root = Path(tmp)
    for name in FILES:
        dest = root / name
        dest.parent.mkdir(parents=True, exist_ok=True)
        shutil.copyfile(ROOT / name, dest)
    (root / 'config/config.php').write_text("<?php define('SESSION_IDLE_TIMEOUT', 3600); function appIsHttpsRequest(): bool { return true; }", encoding='utf-8')
    (root / 'app/helpers/Conection.php').write_text('''<?php
final class Conection {
 private static ?self $instance = null; private PDO $pdo;
 private function __construct() {
  $path = getenv('REI91_DB');
  if (!$path || !is_file($path)) throw new RuntimeException('Missing isolated database');
  $this->pdo = new PDO('sqlite:' . $path, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
  $this->pdo->sqliteCreateFunction('NOW', fn() => gmdate('Y-m-d H:i:s'), 0);
 }
 static function getInstance(): self { return self::$instance ??= new self(); }
 function getConnection(): PDO { return $this->pdo; }
}''', encoding='utf-8')
    dbpath = root / 'synthetic.sqlite'
    db = sqlite3.connect(dbpath)
    stack.callback(db.close)
    db.executescript('''CREATE TABLE usuarios (id_usuario TEXT, token_acesso TEXT, token_expira TEXT, cadastro_ativo INTEGER);
CREATE TABLE usuario_perfil (usuario TEXT, perfil TEXT);
CREATE TABLE feriados (id TEXT PRIMARY KEY, tipo_feriado TEXT, data_inicio TEXT, data_fim TEXT, regra_data TEXT, descricao TEXT, ativo INTEGER, created_at TEXT, updated_at TEXT);
CREATE TABLE editais (id TEXT PRIMARY KEY, sentinel TEXT);
INSERT INTO editais VALUES ('synthetic', 'unchanged');''')
    for uid, profile in [('admin', 'ADMIN'), ('diretora', 'DIRETORA'), ('chefia', 'CHEFIA'), ('servidor', 'SERVIDOR-AUDIENCIAS')]:
        db.execute('INSERT INTO usuarios VALUES (?, ?, ?, 1)', (uid, hashlib.sha256(TOKEN.encode()).hexdigest(), '2999-01-01 00:00:00'))
        db.execute('INSERT INTO usuario_perfil VALUES (?, ?)', (uid, profile))
    db.commit()

    def reset():
        db.execute('DELETE FROM feriados')
        db.execute('INSERT INTO feriados (id,tipo_feriado,data_inicio,data_fim,regra_data,descricao,ativo) VALUES (?, ?, ?, ?, ?, ?, ?)', (ID, 'federal', '2030-01-01', '2030-01-01', 'unica', 'original', 0))
        db.commit()

    def snapshot():
        return (db.execute('SELECT * FROM feriados ORDER BY id').fetchall(), db.execute('SELECT * FROM editais ORDER BY id').fetchall())

    def call(endpoint, uid, method='POST', origin='https://example.test'):
        code = '''session_save_path(getenv('REI91_SESSION_DIR')); session_start();
$_SESSION = getenv('REI91_USER') === 'anonymous' ? [] : ['id_usuario'=>getenv('REI91_USER'), 'token'=>getenv('REI91_TOKEN')];
$_SERVER['REQUEST_METHOD']=getenv('REI91_METHOD'); $_SERVER['HTTP_HOST']='example.test';
$_SERVER['HTTP_ORIGIN']=getenv('REI91_ORIGIN'); $_SERVER['HTTPS']='on';
$_POST=json_decode(getenv('REI91_PAYLOAD'), true);
register_shutdown_function(function(){fwrite(STDERR, "STATUS:" . http_response_code());});
require getenv('REI91_ENDPOINT');'''
        env = {'PATH': os.environ.get('PATH', ''), 'SystemRoot': os.environ.get('SystemRoot', ''),
               'REI91_DB': str(dbpath), 'REI91_USER': uid, 'REI91_TOKEN': TOKEN,
               'REI91_METHOD': method, 'REI91_ORIGIN': origin, 'REI91_PAYLOAD': json.dumps(PAYLOAD),
               'REI91_SESSION_DIR': str(root),
               'REI91_ENDPOINT': str(root / 'public/api/admin/feriados' / (endpoint + '.php'))}
        result = subprocess.run(['php', '-r', code], env=env, capture_output=True, text=True, timeout=10)
        assert result.returncode == 0, (endpoint, uid, result.stderr)
        data = json.loads(result.stdout)
        return result.stderr, data

    for endpoint in ('create', 'update', 'delete'):
        for uid, method, origin, status in [('anonymous', 'POST', 'https://example.test', '401'),
                                            ('servidor', 'POST', 'https://example.test', '403'),
                                            ('admin', 'GET', 'https://example.test', '403'),
                                            ('admin', 'POST', 'https://evil.test', '403'),
                                            ('admin', 'POST', '', '403')]:
            reset(); before = snapshot()
            stderr, _ = call(endpoint, uid, method, origin)
            assert 'STATUS:' + status in stderr, (endpoint, uid, stderr)
            assert snapshot() == before, (endpoint, uid, method, origin)
        reset(); before = snapshot()
        stderr, response = call(endpoint, 'admin')
        assert ('STATUS:201' if endpoint == 'create' else 'STATUS:200') in stderr or (endpoint != 'create' and stderr.endswith('STATUS:')), (endpoint, stderr, response)
        assert snapshot() != before, (endpoint, response)
        reset(); before = snapshot()
        second_allowed = 'chefia' if endpoint == 'delete' else 'diretora'
        stderr, response = call(endpoint, second_allowed)
        assert 'STATUS:201' in stderr or stderr.endswith('STATUS:') or 'STATUS:200' in stderr, (endpoint, stderr, response)
        assert snapshot() != before, (endpoint, response)
    db.close()
print('PASS: perfis, métodos e origens; guardas reais e SQLite temporário')
