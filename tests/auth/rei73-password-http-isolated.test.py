"""Exercise the copied password endpoint with HTTP stubs; no project DB."""
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
import uuid

ROOT = Path(__file__).resolve().parents[2]
with tempfile.TemporaryDirectory(prefix='rei73-password-http-') as temp:
    root = Path(temp)
    endpoint = root / 'public/api/alterar_senha.php'
    endpoint.parent.mkdir(parents=True)
    shutil.copyfile(ROOT / 'public/api/alterar_senha.php', endpoint)
    sentinel = uuid.uuid4().hex
    (root / 'public' / (sentinel + '.txt')).write_text(sentinel, encoding='ascii')
    stubs = {
        'config/config.php': '<?php',
        'app/helpers/bootstrap.php': '<?php',
        'config/security.php': '''<?php
class Security {
    static function requireAuthentication() { if (($_SERVER['HTTP_X_AUTH'] ?? '') !== 'ok') { http_response_code(401); echo json_encode(['success'=>false]); exit; } }
    static function verifyCSRFToken($token) { return $token === 'synthetic-csrf'; }
    static function getUserId() { return ($_SERVER['HTTP_X_USER'] ?? '') === 'missing' ? null : 'synthetic-user'; }
}''',
        'app/models/Usuario.php': '''<?php
class Usuario {
    function __construct() { file_put_contents(getenv('MODEL_MARKER'), "called\\n", FILE_APPEND); }
    function alterarSenha($id, $current, $new) {
        if ($current === 'exception') throw new RuntimeException('synthetic-secret-exception');
        if ($current === 'wrong' || $new === 'weak') return ['success'=>false, 'message'=>'rejected'];
        return ['success'=>true];
    }
}''',
    }
    for name, body in stubs.items():
        path = root / name
        path.parent.mkdir(parents=True, exist_ok=True)
        path.write_text(body, encoding='utf-8')
    with socket.socket() as sock:
        sock.bind(('127.0.0.1', 0))
        port = sock.getsockname()[1]
    env = {k: os.environ[k] for k in ('PATH', 'SystemRoot', 'WINDIR', 'TEMP', 'TMP') if k in os.environ}
    marker = root / 'model-called'
    env['MODEL_MARKER'] = str(marker)
    process = subprocess.Popen(['php', '-S', f'127.0.0.1:{port}', '-t', str(root / 'public')], env=env,
                               stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)
    try:
        for _ in range(50):
            try:
                with socket.create_connection(('127.0.0.1', port), timeout=.1):
                    break
            except OSError:
                if process.poll() is not None:
                    raise RuntimeError('PHP server stopped during startup')
                time.sleep(.1)
        else:
            raise RuntimeError('PHP server did not start')
        if process.poll() is not None:
            raise RuntimeError('PHP server exited during startup')
        with urllib.request.urlopen(f'http://127.0.0.1:{port}/{sentinel}.txt', timeout=3) as response:
            if response.read().decode('ascii') != sentinel or process.poll() is not None:
                raise RuntimeError('Unexpected server on selected port')
        def request(payload, method='POST', headers=None):
            data = json.dumps(payload).encode() if method == 'POST' else None
            hdrs = {'Content-Type':'application/json', 'X-Auth':'ok'}
            hdrs.update(headers or {})
            req = urllib.request.Request(f'http://127.0.0.1:{port}/api/alterar_senha.php', data, hdrs, method=method)
            try:
                with urllib.request.urlopen(req, timeout=3) as res:
                    return res.status, json.load(res)
            except urllib.error.HTTPError as error:
                return error.code, json.load(error)
        valid = {'csrf_token':'synthetic-csrf', 'senha_atual':'old', 'nova_senha':'new', 'confirmar_senha':'new'}
        for payload, method, headers, code in [
            (valid, 'GET', {}, 405),
            (valid, 'POST', {'X-Auth':'no'}, 401),
            ({**valid, 'csrf_token':'wrong'}, 'POST', {}, 403),
            ({k: v for k, v in valid.items() if k != 'csrf_token'}, 'POST', {}, 403),
            ({**valid, 'senha_atual':''}, 'POST', {}, 422),
            ({**valid, 'confirmar_senha':'different'}, 'POST', {}, 422),
            (valid, 'POST', {'X-User':'missing'}, 401),
        ]:
            status, body = request(payload, method, headers)
            assert status == code and body['success'] is False, (status, body)
            assert not marker.exists(), 'Rejected request called model'
        for payload, code, success in [
            ({**valid, 'senha_atual':'wrong'}, 422, False),
            ({**valid, 'nova_senha':'weak', 'confirmar_senha':'weak'}, 422, False),
            (valid, 200, True),
            ({**valid, 'senha_atual':'exception'}, 500, False),
        ]:
            status, body = request(payload)
            assert status == code and body['success'] is success, (status, body)
            assert 'synthetic-secret-exception' not in json.dumps(body)
        assert marker.read_text(encoding='utf-8').count('called') == 4
        print('PASS: password endpoint HTTP rejection, success and sanitized exception with stubs')
    finally:
        process.terminate()
        process.wait(timeout=5)
