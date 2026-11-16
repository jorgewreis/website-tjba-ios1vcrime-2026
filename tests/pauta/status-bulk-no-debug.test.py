"""Exercise a copied endpoint with synthetic dependencies, never the project DB."""
import json
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


def request(port, payload):
    req = urllib.request.Request(
        f'http://127.0.0.1:{port}/api/sala-audiencias-pauta/status-bulk.php',
        json.dumps(payload).encode(), {'Content-Type': 'application/json'}, method='POST')
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
    # A directory at the old diagnostic path simulates an unwritable log target.
    (isolated / 'debug-07d2e0.log').mkdir()
    stubs = {
        'app/helpers/Conection.php': '''<?php
class Conection { public static function getInstance(){return new self();} public function getConnection(){return new FakeDB();} }
class FakeDB { public function prepare($sql){if (isset($_GET['fail'])) throw new RuntimeException('sensitive synthetic error'); return new FakeStatement();} }
class FakeStatement { public function bindValue($k,$v){} public function execute(){} public function fetchAll($mode){return [['id_pauta'=>'SYNTHETIC-1','status'=>'designada','data_audiencia'=>'2099-01-01','hora_audiencia'=>'10:00','processo_sigiloso'=>0,'ja_inscrito'=>0,'qtd_menor'=>0,'total_inscritos_pauta'=>0,'tem_prioridade_bloqueio'=>0]];} }
''',
        'app/helpers/BootstrapHelper.php': "<?php function bootstrapRequireTrustedPostRequest(){return true;} $_SESSION=['id_usuario'=>'synthetic'];",
        'config/security.php': '<?php class Security { public static function isAuthenticated(){return true;} }',
        'app/helpers/UsuarioPerfisAutorizacao.php': "<?php class UsuarioPerfisAutorizacao { public static function perfisDoUsuario($id){return ['ESTUDANTE'];} }",
        'app/models/Usuario.php': "<?php class Usuario { public function listarPerfisUsuario($id){return ['ESTUDANTE'];} }",
        'app/access/AccessEvaluator.php': '<?php class AccessEvaluator { public static function canAccessPage($page,$profiles){return true;} }',
        'app/access/ProfileNormalizer.php': '<?php class ProfileNormalizer { public static function normalize($p){return $p;} public static function hasEstudante($p){return true;} }',
        'app/helpers/UserAccessHelper.php': '<?php class UserAccessHelper { public static function isUsuarioExterno($p){return true;} }',
        'app/services/SalaAudienciasAtaService.php': '<?php class SalaAudienciasAtaService { const MAX_ESTUDANTES_INSCRITOS_POR_AUDIENCIA=10; }',
        'app/helpers/PrioridadesProcessuaisCatalog.php': '<?php class PrioridadesProcessuaisCatalog {}',
        'app/helpers/SalaAudienciasAtaSigiloHelper.php': "<?php class SalaAudienciasAtaSigiloHelper { public static function sqlProcessoSigilosoEfetivo($p,$a){return '0';} }",
    }
    for name, content in stubs.items():
        path = app / name
        path.parent.mkdir(parents=True, exist_ok=True)
        path.write_text(content, encoding='utf-8')
    with socket.socket() as sock:
        sock.bind(('127.0.0.1', 0))
        port = sock.getsockname()[1]
    process = subprocess.Popen(['php', '-S', f'127.0.0.1:{port}', '-t', str(app / 'public')],
                               stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)
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
        status, body = request(port, {'pautas': []})
        assert status == 200 and body == {'success': True, 'items': []}
        status, body = request(port, {'pautas': ['SYNTHETIC-1']})
        assert status == 200 and body['success'] is True
        assert body['items']['SYNTHETIC-1']['pode_inscrever'] is True
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
