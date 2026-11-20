"""Real endpoint and PHP sessions with synthetic dependencies; no project DB."""
import http.cookiejar
import json
import os
from pathlib import Path
import shutil
import socket
import subprocess
import tempfile
import time
import urllib.error
import urllib.request

root = Path(__file__).resolve().parents[2]
with tempfile.TemporaryDirectory(prefix='rei161-idle-http-') as temp:
    base = Path(temp)
    for name in ('config/security.php', 'public/api/sala-audiencias-pauta/status-bulk.php', 'app/helpers/SalaAudienciasEstudantePrivacidade.php'):
        dest = base / name
        dest.parent.mkdir(parents=True, exist_ok=True)
        shutil.copyfile(root / name, dest)
    stubs = {
        'app/helpers/Conection.php': '''<?php
class Conection { static function getInstance(){return new self;} function getConnection(){return new FakeDb;} }
class FakeDb { function prepare($sql){return new FakeStmt;} }
class FakeStmt { function execute($params=[]){return true;} function fetchColumn(){return 'synthetic';} function bindValue($k,$v){} function fetchAll($mode){return [];} }
''',
        'app/helpers/BootstrapHelper.php': '''<?php
session_start();
if (isset($_GET['setup'])) $_SESSION=['id_usuario'=>'synthetic','token'=>'synthetic','last_activity_at'=>time()];
function bootstrapRequireTrustedPostRequest(){return true;}
''',
        'app/helpers/UsuarioPerfisAutorizacao.php': "<?php class UsuarioPerfisAutorizacao { static function perfisDoUsuario($id){return ['ESTUDANTE'];} }",
        'app/models/Usuario.php': "<?php class Usuario { function listarPerfisUsuario($id){return ['ESTUDANTE'];} }",
        'app/access/AccessEvaluator.php': '<?php class AccessEvaluator { static function canAccessPage($a,$b){return true;} }',
        'app/access/ProfileNormalizer.php': '<?php class ProfileNormalizer { static function normalize($p){return $p;} static function hasEstudante($p){return true;} }',
        'app/helpers/UserAccessHelper.php': '<?php class UserAccessHelper { static function isUsuarioExterno($p){return true;} }',
        'app/services/SalaAudienciasAtaService.php': '<?php class SalaAudienciasAtaService { const MAX_ESTUDANTES_INSCRITOS_POR_AUDIENCIA=10; }',
        'app/helpers/PrioridadesProcessuaisCatalog.php': "<?php class PrioridadesProcessuaisCatalog { static function codigosBloqueantesInscricaoEstudante(){return ['synthetic'];} }",
        'app/helpers/SalaAudienciasAtaSigiloHelper.php': "<?php class SalaAudienciasAtaSigiloHelper { static function sqlProcessoSigilosoEfetivo($p,$a){return '0';} }",
        'auto.php': '<?php define("SESSION_IDLE_TIMEOUT", 2);',
    }
    for name, data in stubs.items():
        dest = base / name
        dest.parent.mkdir(parents=True, exist_ok=True)
        dest.write_text(data, encoding='utf-8')
    (base / 'sessions').mkdir()
    with socket.socket() as sock:
        sock.bind(('127.0.0.1', 0))
        port = sock.getsockname()[1]
    env = {key: os.environ[key] for key in ('PATH','SystemRoot','WINDIR','TEMP','TMP') if key in os.environ}
    proc = subprocess.Popen(['php', '-d', f'auto_prepend_file={base / "auto.php"}', '-d', f'session.save_path={base / "sessions"}', '-S', f'127.0.0.1:{port}', '-t', str(base / 'public')], env=env, stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)
    client = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
    def call(query=''):
        req = urllib.request.Request(f'http://127.0.0.1:{port}/api/sala-audiencias-pauta/status-bulk.php{query}', b'{"pautas":["SYNTHETIC"]}', {'Content-Type':'application/json'}, method='POST')
        try:
            with client.open(req, timeout=3) as result:
                return result.status, json.load(result)
        except urllib.error.HTTPError as error:
            return error.code, json.load(error)
    try:
        for _ in range(50):
            try:
                with socket.create_connection(('127.0.0.1', port), timeout=.1): break
            except OSError:
                if proc.poll() is not None: raise RuntimeError('PHP server failed')
                time.sleep(.1)
        else: raise RuntimeError('PHP server not ready')
        assert call('?setup=1')[0] == 200
        time.sleep(1)
        assert call()[0] == 200
        time.sleep(2)
        status, body = call()
        assert status == 401 and body['success'] is False
        print('PASS polling HTTP não renova inatividade sintética')
    finally:
        proc.terminate()
        proc.wait(timeout=5)
