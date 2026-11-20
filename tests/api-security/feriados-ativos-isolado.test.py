#!/usr/bin/env python3
"""Active holiday recalculation through real authorization and services on SQLite.

The connection/config adapters are test-only; no .env, network, Docker or real DB.
PHP CLI supplies a synthetic session and request, not an HTTP login flow.
The SQLite adapter registers a synthetic collation and removes MySQL UPDATE LIMIT 1.
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
    'app/services/EditaisService.php', 'app/repositories/EditaisRepository.php',
    'app/repositories/EditaisSubtarefasRepository.php', 'app/repositories/EditaisAlocadosRepository.php',
    'app/repositories/ProcessosRepository.php', 'app/repositories/ClassesRepository.php',
    'app/helpers/IdGenerator.php', 'app/helpers/TextFormatter.php',
    'app/helpers/CnjValidator.php', 'app/helpers/UserInitialsHelper.php', 'app/helpers/CartorioAlocacaoTipo.php',
    'app/helpers/OrphanSubtarefasCleaner.php', 'app/helpers/CartorioTarefaStatusPrincipalHelper.php',
    'public/api/admin/_auth_admin_diretora_json.php',
    *('public/api/admin/feriados/' + x + '.php' for x in ('create', 'update', 'delete')),
]
ID = 'FER-' + 'a' * 26
TOKEN = 'synthetic-session-token'
PAYLOAD = {'id': ID, 'tipo_feriado': 'federal', 'regra_data': 'unica',
           'data_inicio': '2026-11-17', 'data_fim': '2026-11-17', 'ativo': 1,
           'descricao': 'alterado'}

with tempfile.TemporaryDirectory(prefix='rei91-') as tmp, ExitStack() as stack:
    root = Path(tmp)
    for name in FILES:
        dest = root / name
        dest.parent.mkdir(parents=True, exist_ok=True)
        shutil.copyfile(ROOT / name, dest)
    (root / 'config/config.php').write_text("<?php define('SESSION_IDLE_TIMEOUT', 3600); function appIsHttpsRequest(): bool { return true; }", encoding='utf-8')
    (root / 'app/helpers/Conection.php').write_text('''<?php
final class TestPDO extends PDO {
 public function prepare(string $query, array $options = []): PDOStatement|false {
  if (str_starts_with(ltrim($query), 'UPDATE `editais`')) $query = preg_replace('/ LIMIT 1$/', '', $query);
  return parent::prepare($query, $options);
 }
}
final class Conection {
 private static ?self $instance = null; private PDO $pdo;
 private function __construct() {
  $path = getenv('REI91_DB');
  if (!$path || !is_file($path)) throw new RuntimeException('Missing isolated database');
  $this->pdo = new TestPDO('sqlite:' . $path, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
  $this->pdo->sqliteCreateFunction('NOW', fn() => gmdate('Y-m-d H:i:s'), 0);
  $this->pdo->sqliteCreateCollation('utf8mb4_unicode_ci', fn($a, $b) => strcasecmp($a, $b));
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
CREATE TABLE editais (id TEXT PRIMARY KEY, processo_id TEXT, status TEXT, prazo_dias INTEGER, data_publicacao TEXT, data_inicio_contagem TEXT, data_decurso_prazo TEXT, updated_at TEXT);
CREATE TABLE processos (id TEXT PRIMARY KEY, numero_processo TEXT, classe_id TEXT);
CREATE TABLE classes (id TEXT PRIMARY KEY, nome TEXT, codigo TEXT);''')
    for uid, profile in [('admin', 'ADMIN'), ('diretora', 'DIRETORA'), ('chefia', 'CHEFIA'), ('servidor', 'SERVIDOR-AUDIENCIAS')]:
        db.execute('INSERT INTO usuarios VALUES (?, ?, ?, 1)', (uid, hashlib.sha256(TOKEN.encode()).hexdigest(), '2999-01-01 00:00:00'))
        db.execute('INSERT INTO usuario_perfil VALUES (?, ?)', (uid, profile))
    db.commit()

    def reset(endpoint):
        db.execute('DELETE FROM feriados')
        db.execute('DELETE FROM editais')
        if endpoint != 'create':
            db.execute('INSERT INTO feriados (id,tipo_feriado,data_inicio,data_fim,regra_data,descricao,ativo) VALUES (?, ?, ?, ?, ?, ?, ?)', (ID, 'federal', '2026-11-18', '2026-11-18', 'unica', 'original', 1))
        due = '2026-11-18' if endpoint == 'create' else '2026-11-19'
        db.execute('INSERT INTO editais VALUES (?, NULL, ?, ?, ?, ?, ?, NULL)', ('synthetic', 'pendente', 1, '2026-11-16', '2026-11-17', due))
        db.commit()

    def snapshot():
        return (db.execute('SELECT * FROM feriados ORDER BY id').fetchall(), db.execute('SELECT * FROM editais ORDER BY id').fetchall())

    def call(endpoint, uid, method='POST', origin='https://example.test'):
        code = '''session_save_path(getenv('REI91_SESSION_DIR')); session_start();
$_SESSION = getenv('REI91_USER') === 'anonymous' ? [] : ['id_usuario'=>getenv('REI91_USER'), 'token'=>getenv('REI91_TOKEN')];
$_SERVER['REQUEST_METHOD']=getenv('REI91_METHOD'); $_SERVER['HTTP_HOST']='example.test';
$_SERVER['HTTP_ORIGIN']=getenv('REI91_ORIGIN'); $_SERVER['HTTPS']='on';
$_POST=json_decode(getenv('REI91_PAYLOAD'), true);
register_shutdown_function(function(){fwrite(STDERR, "\nSTATUS:" . (http_response_code() ?: 200) . "\n");});
require getenv('REI91_ENDPOINT');'''
        env = {'PATH': os.environ.get('PATH', ''), 'SystemRoot': os.environ.get('SystemRoot', ''),
               'REI91_DB': str(dbpath), 'REI91_USER': uid, 'REI91_TOKEN': TOKEN,
               'REI91_METHOD': method, 'REI91_ORIGIN': origin, 'REI91_PAYLOAD': json.dumps(PAYLOAD),
               'REI91_SESSION_DIR': str(root),
               'REI91_ENDPOINT': str(root / 'public/api/admin/feriados' / (endpoint + '.php'))}
        result = subprocess.run(['php', '-r', code], env=env, capture_output=True, text=True, timeout=10)
        assert result.returncode == 0, (endpoint, uid, result.stderr)
        data = json.loads(result.stdout)
        lines = [line for line in result.stderr.splitlines() if line.startswith('STATUS:')]
        assert len(lines) == 1 and lines[0][7:].isdigit(), (endpoint, result.stderr)
        return int(lines[0][7:]), data

    def check_allowed(endpoint, uid):
        reset(endpoint)
        status, response = call(endpoint, uid)
        rows, editais = snapshot()
        if endpoint == 'create':
            assert status == 201 and isinstance(response.get('id'), str), (status, response)
            expected_id = response['id']
        else:
            assert status == 200 and response.get('success') is True, (status, response)
            expected_id = ID
        assert response.get('editais_decurso_recalculados') == 1, response
        if endpoint == 'delete':
            assert rows == [], rows
            dates = ('2026-11-17', '2026-11-18')
        else:
            assert len(rows) == 1 and rows[0][0] == expected_id and rows[0][6] == 1 and rows[0][2:4] == ('2026-11-17', '2026-11-17'), rows
            dates = ('2026-11-18', '2026-11-19')
        assert len(editais) == 1 and editais[0][5:7] == dates, editais

    for endpoint in ('create', 'update', 'delete'):
        for uid, method, origin, status in [('anonymous', 'POST', 'https://example.test', 401),
                                            ('servidor', 'POST', 'https://example.test', 403),
                                            ('admin', 'GET', 'https://example.test', 403),
                                            ('admin', 'POST', 'https://evil.test', 403),
                                            ('admin', 'POST', '', 403)]:
            reset(endpoint); before = snapshot()
            actual, response = call(endpoint, uid, method, origin)
            assert actual == status and response.get('error'), (endpoint, uid, actual, response)
            assert snapshot() == before, (endpoint, uid, method, origin)
        check_allowed(endpoint, 'admin')
        second_allowed = 'chefia' if endpoint == 'delete' else 'diretora'
        check_allowed(endpoint, second_allowed)
    db.close()
print('PASS: perfis, métodos e origens; guardas reais e SQLite temporário')
