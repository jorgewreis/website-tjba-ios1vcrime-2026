#!/usr/bin/env python3
"""Exercita as APIs reais de mutação com SQLite temporário e identidade sintética."""
import json
import os
from pathlib import Path
import shutil
import sqlite3
import subprocess
import tempfile

ROOT = Path(__file__).resolve().parents[2]
FILES = [
    'public/api/processos-partes/create.php',
    'public/api/processos-partes/update.php',
    'public/api/processos-partes/delete.php',
    'app/helpers/ApiAccessGuard.php',
    'app/helpers/BootstrapHelper.php',
    'app/helpers/UserAccessHelper.php',
    'app/helpers/ProcessoSetorAuthorization.php',
    'app/helpers/IdGenerator.php',
    'app/access/AccessEvaluator.php',
    'app/access/ProfileNormalizer.php',
    'app/access/PageAccessRegistry.php',
    'app/access/AccessAreas.php',
    'app/access/AccessPolicyVersion.php',
    'app/repositories/ProcessosPartesRepository.php',
]

with tempfile.TemporaryDirectory(prefix='processos-partes-unidade-') as directory:
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
final class UsuarioPerfisAutorizacao { public static function perfisDoUsuario(string $id): array { return [getenv('TEST_PROFILE')]; } }''')
    write('app/helpers/Conection.php', '''<?php
function appIsHttpsRequest(): bool { return false; }
final class SyntheticPdo extends PDO {
  public function __construct() {
    parent::__construct('sqlite:' . getenv('TEST_DB'));
    $this->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $this->sqliteCreateFunction('NOW', static fn (): string => '2026-11-16 12:00:00', 0);
  }
  public function prepare(string $query, array $options = []): PDOStatement|false {
    if (preg_match('/^\\s*(UPDATE|DELETE)\\b/i', $query)) $query = preg_replace('/\\s+LIMIT\\s+1\\s*$/i', '', $query);
    return parent::prepare($query, $options);
  }
}
final class Conection { public static function getInstance(): self { return new self(); } public function getConnection(): PDO { return new SyntheticPdo(); } }''')
    write('app/repositories/PartesRepository.php', '''<?php
final class PartesRepository {
  private PDO $db;
  public function __construct(PDO $db) { $this->db = $db; }
  public function syncProfissaoFromTipoProcessoParte(string $parteId, string $tipoParte): void {
    $stmt = $this->db->prepare('INSERT INTO sync_log (parte_id, tipo_parte) VALUES (?, ?)');
    $stmt->execute([$parteId, $tipoParte]);
    $profession = match (strtolower($tipoParte)) { 'advogado' => 'Advogado', 'defensor' => 'Defensor Público', 'defensor_publico' => 'Defensor Público', default => null };
    if ($profession !== null) {
      $stmt = $this->db->prepare('UPDATE partes SET profissao = ? WHERE id = ?');
      $stmt->execute([$profession, $parteId]);
    }
    if (getenv('TEST_FAIL_SYNC') === '1') throw new RuntimeException('Falha sintética após sincronização.');
  }
}''')

    db_path = root / 'fixture.sqlite'
    db = sqlite3.connect(db_path)
    db.executescript('''
      CREATE TABLE setores(id_setor TEXT PRIMARY KEY, ativo INTEGER NOT NULL);
      CREATE TABLE usuario_setor(usuario TEXT NOT NULL, setor TEXT NOT NULL);
      CREATE TABLE processos(id TEXT PRIMARY KEY, numero_processo TEXT, id_setor_responsavel TEXT NULL);
      CREATE TABLE partes(id TEXT PRIMARY KEY, nome_completo TEXT, cpf TEXT, sexo TEXT, data_nascimento TEXT, profissao TEXT);
      CREATE TABLE sync_log(parte_id TEXT, tipo_parte TEXT);
      CREATE TABLE processos_partes(id TEXT PRIMARY KEY, processo_id TEXT, parte_id TEXT, tipo_parte TEXT, polo TEXT,
        prioridade_mulher INTEGER, prioridade_crianca_adolescente INTEGER, prioridade_idoso INTEGER,
        prioridade_pessoa_deficiencia INTEGER, created_at TEXT, updated_at TEXT);
      INSERT INTO setores VALUES ('S1',1),('S2',1),('S3',0);
      INSERT INTO usuario_setor VALUES ('U1','S1'),('U2','S2'),('U4','S3');
      INSERT INTO processos VALUES ('P1','001','S1'),('P2','002','S2'),('P3','003',NULL),('P4','004','S3');
      INSERT INTO partes VALUES ('A','Pessoa A','000','X','2000-01-01','Réu'),('B','Pessoa B','111','X','2000-01-01','Advogada anterior');
      INSERT INTO processos_partes VALUES ('L1','P1','A','reu','passivo',0,0,0,0,'2026','2026'),
        ('L2','P2','B','vitima','ativo',0,0,0,0,'2026','2026');
    ''')
    db.commit()

    def invoke(endpoint, user, profile, payload, method='POST', origin='http://example.invalid', fail_sync=False):
        env = os.environ.copy()
        env.update(TEST_DB=str(db_path), TEST_USER=user, TEST_PROFILE=profile, TEST_AUTH='1',
                   TEST_PAYLOAD=json.dumps(payload), TEST_METHOD=method, TEST_ORIGIN=origin,
                   TEST_ENDPOINT=str(root / 'public/api/processos-partes' / endpoint),
                   TEST_FAIL_SYNC='1' if fail_sync else '0')
        code = '''$_SERVER['REQUEST_METHOD']=getenv('TEST_METHOD');
$_SERVER['HTTP_HOST']='example.invalid'; $_SERVER['HTTP_ORIGIN']=getenv('TEST_ORIGIN');
$_SESSION=['id_usuario'=>getenv('TEST_USER')];
$payload=json_decode(getenv('TEST_PAYLOAD'),true); $_POST=$payload; $_GET=$payload;
register_shutdown_function(function(){fwrite(STDERR,'HTTP:'.http_response_code());});
require getenv('TEST_ENDPOINT');'''
        result = subprocess.run(['php', '-r', code], env=env, capture_output=True, text=True,
                                encoding='utf-8', timeout=15)
        if result.returncode != 0:
            raise AssertionError(result.stderr + result.stdout)
        status_text = result.stderr.rsplit('HTTP:', 1)[-1]
        if status_text == '':
            status_text = '200'
        if not status_text.isdigit():
            db.close()
            raise AssertionError(f'PHP endpoint did not report HTTP status: stderr={result.stderr!r}; stdout={result.stdout!r}')
        body = json.loads(result.stdout) if result.stdout.strip() else {}
        return int(status_text), body

    def snapshot():
        return (
            list(db.execute('SELECT id, processo_id, parte_id, tipo_parte, polo FROM processos_partes ORDER BY id')),
            list(db.execute('SELECT parte_id, tipo_parte FROM sync_log ORDER BY parte_id, tipo_parte')),
            list(db.execute('SELECT id, profissao FROM partes ORDER BY id')),
        )

    before = snapshot()
    shared_profession = db.execute("SELECT profissao FROM partes WHERE id='B'").fetchone()[0]
    failed_create_status, _ = invoke('create.php', 'U1', 'SERVIDOR-CARTORIO',
                  {'processo_id': 'P1', 'parte_id': 'B', 'tipo_parte': 'advogado', 'polo': 'passivo'}, fail_sync=True)
    assert failed_create_status == 500 and snapshot() == before
    create_status, created_body = invoke('create.php', 'U1', 'SERVIDOR-CARTORIO',
                  {'processo_id': 'P1', 'parte_id': 'B', 'tipo_parte': 'advogado', 'polo': 'passivo'})
    assert create_status == 201
    after_create = snapshot()
    assert len(after_create[0]) == len(before[0]) + 1 and len(after_create[1]) == len(before[1]) + 1
    assert db.execute("SELECT profissao FROM partes WHERE id='B'").fetchone()[0] == 'Advogado' and shared_profession != 'Advogado'

    # Other unit, no unit assignment, process without assignment, inactive unit.
    for user, process in [('U2', 'P1'), ('U3', 'P1'), ('U1', 'P3'), ('U4', 'P4')]:
        status, _ = invoke('create.php', user, 'SERVIDOR-CARTORIO',
                           {'processo_id': process, 'parte_id': 'B', 'tipo_parte': 'reu', 'polo': 'passivo'})
        assert status == 403, (user, process, status)
        assert snapshot() == after_create, (user, process, snapshot())

    # A role with no operation permission cannot update even a same-sector record.
    status, _ = invoke('update.php', 'U1', 'SERVIDOR-GABINETE', {'id': 'L1', 'tipo_parte': 'vitima'})
    assert status == 403
    assert snapshot() == after_create

    # The operation permission is insufficient without same-sector ownership.
    status, unauthorized_update = invoke('update.php', 'U2', 'SERVIDOR-CARTORIO', {'id': 'L1', 'tipo_parte': 'vitima'})
    assert status == 404
    assert snapshot() == after_create

    status, unauthorized_delete = invoke('delete.php', 'U2', 'SERVIDOR-CARTORIO', {'id': 'L1'})
    assert status == 404
    assert snapshot() == after_create
    status, missing_update = invoke('update.php', 'U1', 'SERVIDOR-CARTORIO', {'id': 'MISSING', 'tipo_parte': 'vitima'})
    assert status == 404 and unauthorized_update == missing_update
    status, unauthorized_noop = invoke('update.php', 'U2', 'SERVIDOR-CARTORIO', {'id': 'L1'})
    missing_noop_status, missing_noop = invoke('update.php', 'U1', 'SERVIDOR-CARTORIO', {'id': 'MISSING'})
    assert status == missing_noop_status == 404 and unauthorized_noop == missing_noop
    status, missing_delete = invoke('delete.php', 'U1', 'SERVIDOR-CARTORIO', {'id': 'MISSING'})
    assert status == 404 and unauthorized_delete == missing_delete
    assert snapshot() == after_create
    # Cross-sector and missing process are indistinguishable on create.
    status, cross_process_create = invoke('create.php', 'U2', 'SERVIDOR-CARTORIO',
        {'processo_id': 'P1', 'parte_id': 'B', 'tipo_parte': 'reu', 'polo': 'passivo'})
    missing_process_create_status, missing_process_create = invoke('create.php', 'U2', 'SERVIDOR-CARTORIO',
        {'processo_id': 'MISSING', 'parte_id': 'B', 'tipo_parte': 'reu', 'polo': 'passivo'})
    assert status == missing_process_create_status == 403
    assert cross_process_create == missing_process_create
    assert snapshot() == after_create

    before_failed_update = snapshot()
    failed_update_status, _ = invoke('update.php', 'U1', 'SERVIDOR-CARTORIO',
                                     {'id': created_body['id'], 'tipo_parte': 'defensor_publico'}, fail_sync=True)
    assert failed_update_status == 500 and snapshot() == before_failed_update
    update_status, update_body = invoke('update.php', 'U1', 'SERVIDOR-CARTORIO',
                                        {'id': created_body['id'], 'tipo_parte': 'defensor_publico'})
    assert update_status == 200, (update_status, update_body)
    assert db.execute("SELECT profissao FROM partes WHERE id='B'").fetchone()[0] == 'Defensor Público'
    assert len(snapshot()[1]) == len(after_create[1]) + 1
    before_delete = snapshot()
    delete_created_status, _ = invoke('delete.php', 'U1', 'SERVIDOR-CARTORIO', {'id': created_body['id']})
    assert delete_created_status == 200 and db.execute("SELECT profissao FROM partes WHERE id='B'").fetchone()[0] == 'Defensor Público'
    assert len(snapshot()[1]) == len(before_delete[1])

    status, _ = invoke('update.php', 'U1', 'SERVIDOR-CARTORIO', {'id': 'L1', 'tipo_parte': 'vitima'})
    assert status == 200
    assert db.execute("SELECT tipo_parte FROM processos_partes WHERE id='L1'").fetchone()[0] == 'vitima'
    status, body = invoke('delete.php', 'U1', 'SERVIDOR-CARTORIO', {'id': 'L1'})
    assert status == 200 and body.get('deleted') is True
    assert db.execute("SELECT COUNT(*) FROM processos_partes WHERE id='L1'").fetchone()[0] == 0

    before_bad_request = snapshot()
    for method, origin in [('GET', 'http://example.invalid'), ('POST', 'http://evil.invalid')]:
        status, _ = invoke('delete.php', 'U1', 'SERVIDOR-CARTORIO', {'id': 'L2'}, method, origin)
        assert status == 403
        assert snapshot() == before_bad_request

    # Sector lookup failure must not permit a write or leave the transaction open.
    db.execute('DROP TABLE usuario_setor')
    db.commit()
    status, _ = invoke('delete.php', 'U1', 'SERVIDOR-CARTORIO', {'id': 'L2'})
    assert status == 500
    assert db.execute("SELECT COUNT(*) FROM processos_partes WHERE id='L2'").fetchone()[0] == 1
    print('PASS mutações por unidade (SQLite sintético; sessão/perfis simulados; isolamento HTTP local)')
    db.close()
