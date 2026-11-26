"""HTTP JSON contract with the real endpoint/guard and inert dependencies.

The PHP server serves only a temporary copy containing synthetic fixtures. It
never loads the checkout's configuration, .env, database or external network.
"""
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


ROOT = Path(__file__).resolve().parents[2]
ENDPOINT = 'public/api/gabinete-monitoramento-eletronico/reus-partes.php'
REAL_FILES = [
    ENDPOINT, 'app/helpers/ApiErrorReporter.php', 'app/helpers/ApiAccessGuard.php', 'app/helpers/BootstrapHelper.php',
    'app/helpers/UserAccessHelper.php', 'app/access/AccessEvaluator.php',
    'app/access/ProfileNormalizer.php', 'app/access/PageAccessRegistry.php',
    'app/access/AccessAreas.php', 'app/access/AccessPolicyVersion.php',
]
STUBS = {
    'app/helpers/AutoloadHelper.php': '<?php',
    'app/helpers/ProcessoSetorAuthorization.php': '''<?php final class ProcessoSetorAuthorization {
      public static function canAccessProcess(PDO $pdo, string $user, string $id, bool $lock): bool { return $id !== 'PROC-BLOCKED'; }
    }''',
    'app/helpers/Conection.php': '''<?php
function appIsHttpsRequest(): bool { return false; }
function bootstrapSession(): void { $_SESSION = ['id_usuario' => 'SYNTHETIC']; }
final class Conection {
  public static function getInstance(): self { return new self(); }
  public function getConnection(): PDO { return new class extends PDO { public function __construct() {} }; }
}''',
    'config/security.php': '''<?php final class Security {
  public static function isAuthenticated(): bool { return ($_SERVER['HTTP_X_TEST_AUTH'] ?? '') === 'yes'; }
}''',
    'app/helpers/UsuarioPerfisAutorizacao.php': '''<?php final class UsuarioPerfisAutorizacao {
  public static function perfisDoUsuario(string $id): array { return [$_SERVER['HTTP_X_TEST_PROFILE'] ?? '']; }
}''',
    'app/services/GabineteMonitoramentoEletronicoService.php': '''<?php
final class GabineteMonitoramentoEletronicoService {
  public function __construct(PDO $pdo) {}
  public function listarReusDoProcesso(string $id): array {
    file_put_contents(getenv('FIXTURE_RECORD'), 'called');
    return [['fixture_id' => $id]];
  }
  public function buscarIdProcessoParaConsulta(string $cnj): string {
    return $cnj === '0000002-54.2020.8.05.0001' ? 'PROC-BLOCKED' : 'PROC-SYNTHETIC';
  }
}''',
}


def request(port, method='POST', *, auth='yes', profile='SERVIDOR-GABINETE', origin=None, payload=None):
    base = f'http://127.0.0.1:{port}'
    headers = {'X-Test-Auth': auth, 'X-Test-Profile': profile,
               'Origin': origin or base, 'Content-Type': 'application/json'}
    data = None if method == 'GET' else json.dumps(payload or {}).encode('utf-8')
    req = urllib.request.Request(f'{base}/api/gabinete-monitoramento-eletronico/reus-partes.php',
                                 data=data, headers=headers, method=method)
    try:
        response = urllib.request.urlopen(req, timeout=3)
    except urllib.error.HTTPError as error:
        response = error
    with response:
        return response.status, json.load(response)


with tempfile.TemporaryDirectory(prefix='monitoramento-json-') as temporary:
    isolated = Path(temporary)
    for relative in REAL_FILES:
        target = isolated / relative
        target.parent.mkdir(parents=True, exist_ok=True)
        shutil.copyfile(ROOT / relative, target)
    for relative, content in STUBS.items():
        target = isolated / relative
        target.parent.mkdir(parents=True, exist_ok=True)
        target.write_text(content, encoding='utf-8')

    with socket.socket() as listener:
        listener.bind(('127.0.0.1', 0))
        port = listener.getsockname()[1]
    env = {key: os.environ[key] for key in ('PATH', 'SYSTEMROOT', 'WINDIR', 'TEMP', 'TMP') if key in os.environ}
    record = isolated / 'service-called'
    env['FIXTURE_RECORD'] = str(record)
    server = subprocess.Popen(['php', '-S', f'127.0.0.1:{port}', '-t', str(isolated / 'public')],
                              cwd=isolated, env=env, stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)
    try:
        deadline = time.monotonic() + 10
        while True:
            if server.poll() is not None:
                raise RuntimeError('Servidor PHP de teste encerrou antes de iniciar')
            try:
                with socket.create_connection(('127.0.0.1', port), timeout=0.2):
                    break
            except OSError:
                if time.monotonic() >= deadline:
                    raise RuntimeError('Servidor PHP de teste não ficou pronto')
                time.sleep(0.05)

        payload = {'numero_processo': '0000001-69.2020.8.05.0001',
                   'classe_codigo': 'SYN-42', 'classe_descricao': 'Classe sintetica'}
        status, body = request(port, payload=payload)
        assert status == 200 and body == {'processo_id': 'PROC-SYNTHETIC', 'reus': [{'fixture_id': 'PROC-SYNTHETIC'}]}, (status, body)
        status, body = request(port, payload={'processo_id': 'PROC-SYNTHETIC'})
        assert status == 200 and body == {'processo_id': 'PROC-SYNTHETIC',
                                          'reus': [{'fixture_id': 'PROC-SYNTHETIC'}]}, (status, body)
        assert record.read_text() == 'called'
        record.unlink()
        for forbidden in [{'processo_id': 'PROC-BLOCKED'}, {'numero_processo': '0000002-54.2020.8.05.0001'}]:
            status, body = request(port, payload=forbidden)
            assert status == 403 and 'reus' not in body and not record.exists(), (status, body)
        for kwargs, expected in [({'auth': 'no'}, 401), ({'profile': 'ESTUDANTE'}, 403),
                                 ({'origin': 'http://evil.invalid'}, 403), ({'method': 'GET'}, 403)]:
            status, body = request(port, payload=payload, **kwargs)
            assert status == expected and 'fixture_args' not in body and 'error' in body, (kwargs, status, body)
            assert not record.exists(), f'Serviço chamado apesar de recusa: {kwargs}'
        print('PASS POST JSON real e guard (servidor local; identidade e serviço simulados)')
    finally:
        server.terminate()
        try:
            server.wait(timeout=5)
        except subprocess.TimeoutExpired:
            server.kill()
            server.wait(timeout=5)
