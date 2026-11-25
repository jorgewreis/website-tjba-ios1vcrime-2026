"""Exercise a copied endpoint with synthetic dependencies, never the project DB."""
import json
import os
import pathlib
import shutil
import socket
import subprocess
import tempfile
import time
import urllib.error
import urllib.request

ROOT = pathlib.Path(__file__).resolve().parents[2]
SOURCE = ROOT / 'public/api/sala-audiencias-pauta/status-bulk.php'


def request(port, payload, headers=None, method='POST'):
    request_headers = {'Content-Type': 'application/json'}
    request_headers.update(headers or {})
    req = urllib.request.Request(
        f'http://127.0.0.1:{port}/api/sala-audiencias-pauta/status-bulk.php',
        json.dumps(payload).encode() if method == 'POST' else None, request_headers, method=method)
    try:
        with urllib.request.urlopen(req, timeout=2) as response:
            return response.status, json.load(response)
    except urllib.error.HTTPError as error:
        return error.code, json.load(error)


with tempfile.TemporaryDirectory(prefix='pauta-status-test-') as tmp:
    isolated = pathlib.Path(tmp)
    app = isolated / 'project'
    target = app / 'public/api/sala-audiencias-pauta/status-bulk.php'
    target.parent.mkdir(parents=True)
    shutil.copyfile(SOURCE, target)
    helper = app / 'app/helpers/SalaAudienciasEstudantePrivacidade.php'
    helper.parent.mkdir(parents=True, exist_ok=True)
    shutil.copyfile(ROOT / 'app/helpers/SalaAudienciasEstudantePrivacidade.php', helper)
    # A directory at the old diagnostic path simulates an unwritable log target.
    (isolated / 'debug-07d2e0.log').mkdir()
    stubs = {
        'app/helpers/Conection.php': '''<?php
class Conection { public static function getInstance(){return new self();} public function getConnection(){file_put_contents(getenv('AUDIT_DB_MARKER'), "connected\n", FILE_APPEND); return new FakeDB();} }
class FakeDB { public function prepare($sql){if (isset($_GET['fail'])) throw new RuntimeException('sensitive synthetic error'); return new FakeStatement($sql);} }
class FakeStatement {
  private $sql; private $bindings=[];
  public function __construct($sql){$this->sql=$sql;}
  public function bindValue($k,$v){$this->bindings[$k]=$v;}
  public function execute(){
    $codes=['sigiloso','crianca_adolescente','violencia_domestica','medida_protetiva'];
    foreach($codes as $i=>$code) if(($this->bindings[$i+1]??null)!==$code) throw new RuntimeException('priority parameter order mismatch');
    if(($this->bindings[count($codes)+1]??null)!=='synthetic') throw new RuntimeException('user parameter order mismatch');
    if(substr_count($this->sql,'?')!==count($this->bindings)) throw new RuntimeException('placeholder count mismatch');
    return true;
  }
  public function fetchAll($mode){
  $base=['status'=>'designada','data_audiencia'=>'2099-01-01','hora_audiencia'=>'10:00','processo_sigiloso'=>0,'ja_inscrito'=>0,'qtd_menor'=>0,'total_inscritos_pauta'=>0,'tem_prioridade_bloqueio'=>0];
  $rows=[array_merge($base,['id_pauta'=>'SYNTHETIC-1']),array_merge($base,['id_pauta'=>'SIG','processo_sigiloso'=>1]),array_merge($base,['id_pauta'=>'MENOR','qtd_menor'=>1,'ja_inscrito'=>1]),array_merge($base,['id_pauta'=>'PRIORIDADE','tem_prioridade_bloqueio'=>1]),array_merge($base,['id_pauta'=>'MEDIDA','tem_prioridade_bloqueio'=>1]),array_merge($base,['id_pauta'=>'CAPACITY-4','total_inscritos_pauta'=>4]),array_merge($base,['id_pauta'=>'CAPACITY-5-NEW','total_inscritos_pauta'=>5]),array_merge($base,['id_pauta'=>'CAPACITY-5-ENROLLED','total_inscritos_pauta'=>5,'ja_inscrito'=>1]),array_merge($base,['id_pauta'=>'CAPACITY-6-ENROLLED','total_inscritos_pauta'=>6,'ja_inscrito'=>1])];
  $ids=array_slice(array_values($this->bindings),5);
  return array_values(array_filter($rows,static function($row) use ($ids){return in_array($row['id_pauta'],$ids,true);}));
} }
''',
        'app/helpers/BootstrapHelper.php': "<?php function bootstrapRequireTrustedPostRequest(){return ($_SERVER['HTTP_X_TEST_ORIGIN']??'trusted')==='trusted';} $_SESSION=['id_usuario'=>'synthetic'];",
        'config/security.php': "<?php class Security { public static function isAuthenticated(){return ($_SERVER['HTTP_X_TEST_AUTH']??'valid')==='valid';} }",
        'app/helpers/UsuarioPerfisAutorizacao.php': "<?php class UsuarioPerfisAutorizacao { public static function perfisDoUsuario($id){return [$_SERVER['HTTP_X_TEST_PROFILE']??'ESTUDANTE'];} }",
        'app/models/Usuario.php': "<?php class Usuario { public function listarPerfisUsuario($id){return ['ESTUDANTE'];} }",
        'app/access/AccessEvaluator.php': "<?php class AccessEvaluator { public static function canAccessPage($page,$profiles){return in_array('ESTUDANTE',$profiles,true);} }",
        'app/access/ProfileNormalizer.php': '<?php class ProfileNormalizer { public static function normalize($p){return $p;} public static function hasEstudante($p){return true;} }',
        'app/helpers/UserAccessHelper.php': '<?php class UserAccessHelper { public static function isUsuarioExterno($p){return true;} }',
        'app/services/SalaAudienciasAtaService.php': '<?php class SalaAudienciasAtaService { const MAX_ESTUDANTES_INSCRITOS_POR_AUDIENCIA=5; }',
        'app/helpers/PrioridadesProcessuaisCatalog.php': "<?php class PrioridadesProcessuaisCatalog { static function codigosBloqueantesInscricaoEstudante(){return ['sigiloso','crianca_adolescente','violencia_domestica','medida_protetiva'];} }",
        'app/helpers/SalaAudienciasAtaSigiloHelper.php': "<?php class SalaAudienciasAtaSigiloHelper { public static function sqlProcessoSigilosoEfetivo($p,$a){return '0';} }",
    }
    for name, content in stubs.items():
        path = app / name
        path.parent.mkdir(parents=True, exist_ok=True)
        path.write_text(content, encoding='utf-8')
    with socket.socket() as sock:
        sock.bind(('127.0.0.1', 0))
        port = sock.getsockname()[1]
    clean_env = {key: os.environ[key] for key in ('PATH', 'SystemRoot', 'WINDIR', 'TEMP', 'TMP') if key in os.environ}
    clean_env['AUDIT_DB_MARKER'] = str(isolated / 'db-access')
    process = subprocess.Popen(['php', '-S', f'127.0.0.1:{port}', '-t', str(app / 'public')],
                               stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL, env=clean_env)
    try:
        for _ in range(50):
            try:
                with socket.create_connection(('127.0.0.1', port), timeout=0.1):
                    break
            except OSError:
                if process.poll() is not None:
                    raise RuntimeError('PHP server stopped during startup')
                time.sleep(0.1)
        else:
            raise RuntimeError('PHP server did not start')
        for headers, method, expected in [
            ({}, 'GET', 405),
            ({'X-Test-Auth': 'invalid'}, 'POST', 401),
            ({'X-Test-Origin': 'untrusted'}, 'POST', 403),
            ({'X-Test-Profile': 'CONSULTOR'}, 'POST', 403),
        ]:
            status, body = request(port, {'pautas': ['SYNTHETIC-1']}, headers, method)
            assert status == expected and body.get('success') is False, (status, body)
            assert not (isolated / 'db-access').exists(), 'recusa chamou conexão sintética'
        status, body = request(port, {'pautas': []})
        assert status == 200 and body == {'success': True, 'items': []}
        status, body = request(port, {'pautas': ['SYNTHETIC-1']})
        assert status == 200 and body['success'] is True
        assert body['items']['SYNTHETIC-1']['pode_inscrever'] is True
        status, body = request(port, {'pautas': ['CAPACITY-4', 'CAPACITY-5-NEW', 'CAPACITY-5-ENROLLED', 'CAPACITY-6-ENROLLED']})
        assert status == 200 and body['success'] is True
        assert body['items']['CAPACITY-4']['pode_inscrever'] is True
        assert body['items']['CAPACITY-4']['vagas_esgotadas'] is False
        assert body['items']['CAPACITY-5-NEW']['pode_inscrever'] is False
        assert body['items']['CAPACITY-5-NEW']['vagas_esgotadas'] is True
        for key in ('CAPACITY-5-ENROLLED', 'CAPACITY-6-ENROLLED'):
            assert body['items'][key]['ja_inscrito'] is True
            assert body['items'][key]['vagas_esgotadas'] is True
            assert body['items'][key]['pode_inscrever'] is False
            assert body['items'][key]['status'] == 'designada'
            assert 'indisponivel' not in body['items'][key]
        status, body = request(port, {'pautas': ['SYNTHETIC-1', 'SIG', 'MENOR', 'PRIORIDADE', 'MEDIDA', 'INEXISTENTE']})
        assert status == 200 and 'INEXISTENTE' not in body['items']
        for key in ('SIG', 'MENOR', 'PRIORIDADE', 'MEDIDA'):
            assert body['items'][key] == {'id_pauta': key, 'indisponivel': True}
        assert not any(k in body['items']['SYNTHETIC-1'] for k in ('processo_sigiloso','envolve_menor','tem_prioridade_bloqueio'))
        url = f'http://127.0.0.1:{port}/api/sala-audiencias-pauta/status-bulk.php?fail=1'
        req = urllib.request.Request(url, b'{"pautas":["SYNTHETIC-1"]}', {'Content-Type': 'application/json'}, method='POST')
        try:
            urllib.request.urlopen(req, timeout=2)
            raise AssertionError('expected server error')
        except urllib.error.HTTPError as error:
            assert error.code == 500 and json.load(error) == {'success': False, 'error': 'Erro ao consultar status.'}
        assert list((isolated / 'debug-07d2e0.log').iterdir()) == []
        # With the path absent and its parent writable, a suppressed append would
        # otherwise succeed and create a file. This is a separate check from the
        # unavailable-target case above, entirely inside the temporary tree.
        (isolated / 'debug-07d2e0.log').rmdir()
        status, body = request(port, {'pautas': ['SYNTHETIC-1']})
        assert status == 200 and body['success'] is True
        assert not (isolated / 'debug-07d2e0.log').exists()
        req = urllib.request.Request(url, b'{"pautas":["SYNTHETIC-1"]}', {'Content-Type': 'application/json'}, method='POST')
        try:
            urllib.request.urlopen(req, timeout=2)
            raise AssertionError('expected server error')
        except urllib.error.HTTPError as error:
            assert error.code == 500 and json.load(error) == {'success': False, 'error': 'Erro ao consultar status.'}
        assert not (isolated / 'debug-07d2e0.log').exists()
        print('PASS status-bulk synthetic success and failure with unavailable and writable diagnostic targets')
    finally:
        process.terminate()
        process.wait(timeout=5)
