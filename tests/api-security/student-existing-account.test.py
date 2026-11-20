#!/usr/bin/env python3
"""Executa o endpoint copiado com PDO simulada; nenhum banco ou dado real."""
import json
import os
from pathlib import Path
import shutil
import subprocess
import tempfile

ROOT = Path(__file__).resolve().parents[2]


def put(root, path, text):
    target = root / path
    target.parent.mkdir(parents=True, exist_ok=True)
    target.write_text(text, encoding='utf-8')


with tempfile.TemporaryDirectory(prefix='student-account-') as tmp:
    root = Path(tmp)
    endpoint = root / 'public/api/cadastro_estudantes.php'
    endpoint.parent.mkdir(parents=True)
    shutil.copyfile(ROOT / 'public/api/cadastro_estudantes.php', endpoint)
    put(root, 'config/config.php', '<?php')
    put(root, 'config/security.php', '''<?php class Security {
 static function normalizeCPF($v) { return $v; } static function validarCPF($v) { return true; }
 static function formatCPF($v) { return $v; } static function validatePasswordPolicy($v) { return ['valid'=>true]; }
 static function hashPassword($v) { SyntheticConnection::$events[] = 'HASH'; return 'synthetic-hash'; } }''')
    put(root, 'app/helpers/TextFormatter.php', '<?php class TextFormatter { static function formatProperName($v,$n) { return $v; } }')
    put(root, 'app/helpers/SexoHelper.php', '<?php class SexoHelper { static function normalize($v) { return $v; } static function isValid($v) { return true; } }')
    put(root, 'app/helpers/Conection.php', '''<?php
class SyntheticStatement extends PDOStatement {
 public function __construct(private string $sql) {}
 public function execute(?array $params = null): bool {
  SyntheticConnection::$events[] = $this->sql;
  if (str_contains($this->sql, 'GET_LOCK') || str_contains($this->sql, 'RELEASE_LOCK')) {
   SyntheticConnection::$events[] = 'LOCK_VALUE:' . ($params[0] ?? '');
  }
  if (str_contains($this->sql, 'FROM usuarios')) SyntheticConnection::$userChecks++;
  if (str_starts_with(trim($this->sql), 'INSERT INTO usuarios') && str_starts_with(getenv('CASE'), 'duplicate_')) {
   $e = new PDOException('synthetic duplicate'); $e->errorInfo = ['23000', 1062, 'synthetic']; throw $e;
  }
  return true;
 }
 public function fetch(int $mode = PDO::FETCH_DEFAULT, int $orientation = PDO::FETCH_ORI_NEXT, int $offset = 0): mixed {
  if (str_contains($this->sql, 'FROM usuarios') && str_starts_with(getenv('CASE'), 'existing')) return ['id_usuario'=>'synthetic'];
  if (str_contains($this->sql, 'FROM usuarios') && getenv('CASE') === 'duplicate_cpf' && SyntheticConnection::$userChecks > 1) return ['id_usuario'=>'synthetic'];
  return false;
 }
 public function fetchColumn(int $column = 0): mixed {
  if (str_contains($this->sql, 'GET_LOCK')) return getenv('CASE') === 'lock_busy' ? 0 : 1;
  return 0;
 }
}
class SyntheticConnection extends PDO {
 public static array $events = []; public static int $userChecks = 0; private bool $active = false;
 public function __construct() {}
 public function query(string $query, ?int $fetchMode = null, mixed ...$args): PDOStatement|false {
  self::$events[] = $query; return new SyntheticStatement($query);
 }
 public function prepare(string $query, array $options = []): PDOStatement|false { return new SyntheticStatement($query); }
 public function beginTransaction(): bool { self::$events[] = 'BEGIN'; return $this->active = true; }
 public function inTransaction(): bool { return $this->active; }
 public function commit(): bool { self::$events[] = 'COMMIT'; $this->active = false; return true; }
 public function rollBack(): bool { self::$events[] = 'ROLLBACK'; $this->active = false; return true; }
}
class Conection { static function getInstance() { return new self(); } function getConnection() { return new SyntheticConnection(); } }
register_shutdown_function(function() { file_put_contents(getenv('EVENTS_PATH'), json_encode(SyntheticConnection::$events)); });
''')
    payload = {'nome_completo': 'Pessoa Sintetica', 'cpf': '123', 'telefone': '71999999999',
               'sexo': 'M', 'faculdade': 'Teste', 'email': 'teste@example.invalid',
               'aceite_termos_lgpd': True, 'senha': 'Teste123!'}
    code = ('$_SERVER["REQUEST_METHOD"]="POST"; $_POST=json_decode(' + repr(json.dumps(payload)) + ',true); '
            'if (getenv("CASE") === "new_other_cpf") $_POST["cpf"]="456"; '
            'require ' + repr(endpoint.as_posix()) + ';')
    results = {}
    for case in ('existing_no_profile', 'existing_student', 'existing_internal', 'new', 'new_other_cpf', 'duplicate_cpf', 'duplicate_other', 'lock_busy'):
        events = root / ('events-' + case + '.json')
        env = os.environ.copy()
        env.update(CASE=case, EVENTS_PATH=str(events))
        proc = subprocess.run(['php', '-d', 'display_errors=0', '-r', code], cwd=root, env=env,
                              capture_output=True, text=True, encoding='utf-8', timeout=15)
        assert proc.returncode == 0, (case, proc.stderr)
        results[case] = (json.loads(proc.stdout), json.loads(events.read_text(encoding='utf-8')))

    generic = results['new'][0]
    for case in ('existing_no_profile', 'existing_student', 'existing_internal', 'duplicate_cpf', 'duplicate_other'):
        assert results[case][0] == generic, case
    for case in ('existing_no_profile', 'existing_student', 'existing_internal'):
        events = results[case][1]
        assert 'HASH' in events
        assert 'ROLLBACK' in events and any('FOR UPDATE' in event for event in events)
        assert not any(event.lstrip().startswith(('INSERT', 'UPDATE', 'DELETE')) for event in events)
        assert not any('FROM usuario_perfil' in event for event in events)
        assert any('RELEASE_LOCK' in event for event in events)
    assert 'COMMIT' in results['new'][1]
    assert 'HASH' in results['new'][1]
    assert any(event.lstrip().startswith('INSERT INTO usuarios') for event in results['new'][1])
    assert 'ROLLBACK' in results['duplicate_cpf'][1]
    assert 'ROLLBACK' in results['duplicate_other'][1]
    # Mesmo e-mail sintético: CPF existente e CPF ausente com conflito em
    # outro índice têm JSON idêntico, sem afirmar que a conta foi criada.
    assert results['existing_student'][0] == results['duplicate_other'][0]
    assert 'criado' not in generic['message'].lower() and 'sucesso' not in generic['message'].lower()
    lock_events = [event for event in results['new'][1] if event.startswith('LOCK_VALUE:')]
    assert len(lock_events) == 2 and lock_events[0] == lock_events[1]
    assert '123' not in lock_events[0] and len(lock_events[0].split(':', 1)[1]) <= 64
    other_lock = next(event for event in results['new_other_cpf'][1] if event.startswith('LOCK_VALUE:'))
    assert other_lock != lock_events[0] and '456' not in other_lock
    assert results['lock_busy'][0]['success'] is False
    assert not any(event.lstrip().startswith(('INSERT', 'UPDATE', 'DELETE')) for event in results['lock_busy'][1])

print('PASS contas existentes, cadastro novo, conflito e lock (PDO simulada; sem rede)')
