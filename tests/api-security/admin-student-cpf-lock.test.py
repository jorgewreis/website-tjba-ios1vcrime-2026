#!/usr/bin/env python3
"""Exercita a criação administrativa em cópia isolada, com PDO simulada."""
import json
import hashlib
import os
from pathlib import Path
import shutil
import subprocess
import tempfile

ROOT = Path(__file__).resolve().parents[2]


def put(root, path, content):
    target = root / path
    target.parent.mkdir(parents=True, exist_ok=True)
    target.write_text(content, encoding='utf-8')


with tempfile.TemporaryDirectory(prefix='admin-cpf-lock-') as tmp:
    root = Path(tmp)
    view = root / 'app/views/internal/usuarios-create.php'
    view.parent.mkdir(parents=True)
    shutil.copyfile(ROOT / 'app/views/internal/usuarios-create.php', view)
    put(root, 'config/security.php', '''<?php class Security {
      static function requireAuthentication() {} static function getUserId() { return 'synthetic'; }
      static function verifyCSRFToken($x) { return true; } static function generateCSRFToken() { return 'synthetic'; }
      static function validatePasswordPolicy($x) { return ['valid'=>true]; }
      static function hashPassword($x) { return 'synthetic'; }
    }''')
    put(root, 'app/helpers/UsuarioPerfisAutorizacao.php', "<?php class UsuarioPerfisAutorizacao { static function perfisDoUsuario($x) { return ['ADMIN']; } }")
    put(root, 'app/helpers/DateHelper.php', '<?php class DateHelper { static function normalizeIsoDate($x) { return null; } }')
    put(root, 'app/helpers/TextFormatter.php', '<?php class TextFormatter { static function formatProperName($x,$n) { return $x; } }')
    put(root, 'app/helpers/SetoresAccessHelper.php', '<?php class SetoresAccessHelper {}')
    put(root, 'app/helpers/SexoHelper.php', '<?php class SexoHelper { static function normalize($x) { return $x; } static function isValid($x) { return true; } }')
    put(root, 'app/helpers/Conection.php', '''<?php
class SyntheticStatement extends PDOStatement {
 public function __construct(private string $sql) {}
 public function execute(?array $params = null): bool {
  SyntheticPDO::$events[] = ['sql'=>$this->sql, 'params'=>$params];
  if (getenv('CASE') === 'failure' && str_contains($this->sql, 'INSERT INTO usuarios')) throw new RuntimeException('synthetic failure');
  return true;
 }
 public function fetchColumn(int $column = 0): mixed {
  if (str_contains($this->sql, 'GET_LOCK')) return getenv('CASE') === 'busy' ? 0 : 1;
  return 0;
 }
 public function fetchAll(int $mode = PDO::FETCH_DEFAULT, mixed ...$args): array {
  return getenv('CASE') === 'existing' ? [['id_usuario'=>'synthetic','cadastro_ativo'=>1]] : [];
 }
}
class SyntheticPDO extends PDO {
 public static array $events=[]; private bool $active=false;
 public function __construct() {}
 public function prepare(string $query, array $options=[]): PDOStatement|false { return new SyntheticStatement($query); }
 public function beginTransaction(): bool { self::$events[]=['event'=>'BEGIN']; return $this->active=true; }
 public function inTransaction(): bool { return $this->active; }
 public function commit(): bool { self::$events[]=['event'=>'COMMIT']; $this->active=false; return true; }
 public function rollBack(): bool { self::$events[]=['event'=>'ROLLBACK']; $this->active=false; return true; }
}
class Conection { static function getInstance() { return new self(); } function getConnection() { return new SyntheticPDO(); } }
register_shutdown_function(function() { file_put_contents(getenv('EVENTS_PATH'), json_encode(SyntheticPDO::$events)); });
''')
    # CPF sintético válido conhecido, usado somente na cópia sem banco.
    payload = {'csrf_token':'x', 'nome':'Pessoa', 'sobrenome':'Teste', 'cpf':'529.982.247-25',
               'email':'teste@example.invalid', 'sexo':'M', 'senha':'x', 'senha_confirm':'x',
               'tipo':'interno', 'cargo':'Admin'}
    code = ('$_SERVER["REQUEST_METHOD"]="POST"; $_SESSION=[]; $_POST=json_decode('
            + repr(json.dumps(payload)) + ',true); require ' + repr(view.as_posix()) + ';')
    for case in ('new', 'existing', 'busy', 'failure'):
        events_file = root / (case + '.json')
        env = os.environ.copy()
        env.update(CASE=case, EVENTS_PATH=str(events_file))
        proc = subprocess.run(['php', '-d', 'display_errors=0', '-r', code], env=env,
                              capture_output=True, text=True, encoding='utf-8', timeout=15)
        assert proc.returncode == 0, (case, proc.stderr)
        events = json.loads(events_file.read_text(encoding='utf-8'))
        sql = [e for e in events if 'sql' in e]
        get = next(e for e in sql if 'GET_LOCK' in e['sql'])
        assert get['params'][0] == 'cadest:' + hashlib.sha256(b'52998224725').hexdigest()[:48]
        if case == 'busy':
            assert not any(e.get('event') == 'BEGIN' for e in events)
            assert not any('INSERT INTO usuarios' in e['sql'] for e in sql)
            continue
        release = next(e for e in sql if 'RELEASE_LOCK' in e['sql'])
        assert release['params'] == get['params']
        check = next(e for e in sql if 'FROM usuarios' in e['sql'])
        assert check['params'] == ['52998224725']
        assert events.index(get) < events.index(check) < events.index(release)
        assert any(e.get('event') == ('COMMIT' if case == 'new' else 'ROLLBACK') for e in events)
        assert any('INSERT INTO usuarios' in e['sql'] for e in sql) == (case in ('new', 'failure'))

print('PASS criação administrativa, CPF existente e lock ocupado (PDO simulada)')
