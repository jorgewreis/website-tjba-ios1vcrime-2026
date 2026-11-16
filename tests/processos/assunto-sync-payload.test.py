from pathlib import Path
import json
import os
import socket
import subprocess
import tempfile
import time
from urllib.error import HTTPError, URLError
from urllib.request import Request, urlopen

ROOT = Path(__file__).resolve().parents[2]

with tempfile.TemporaryDirectory(prefix='assunto-sync-') as tmp:
    root = Path(tmp)
    endpoint = root / 'public/api/processos/assunto-sync.php'
    endpoint.parent.mkdir(parents=True)
    endpoint.write_bytes((ROOT / 'public/api/processos/assunto-sync.php').read_bytes())
    stubs = {
        'app/helpers/ApiAccessGuard.php': '''<?php final class ApiAccessGuard { public static function requireAccess(array $pages, bool $write = false): void {} }''',
        'app/helpers/Conection.php': '''<?php final class Conection { public static function getInstance(): self { file_put_contents(getenv('PROBE_FILE'), 'connect'); return new self; } public function getConnection(): object { return new stdClass; } }''',
        'app/helpers/BootstrapHelper.php': '''<?php $_SESSION['id_usuario'] = 'synthetic';''',
        'app/helpers/CnjValidator.php': '''<?php final class CnjValidator { public static function digits20OrThrow(string $v): string { return $v; } }''',
        'public/api/datajud/config.php': '''<?php return ['api_key' => 'synthetic'];''',
        'app/services/DataJudService.php': '''<?php final class DataJudService { public function __construct(array $config) {} public function searchProcesso(string $n): array { if ($n === 'failure') throw new RuntimeException('PRIVATE_DATAJUD_SENTINEL'); if ($n === 'unconfirmed') return ['assuntos_completos' => true, 'assuntos' => [['codigo' => '7']]]; if ($n === 'incomplete') return ['assuntos_completos' => false, 'assuntos' => [['codigo' => '7']]]; if ($n === 'restricted') return ['assuntos_completos' => true, 'sigilo_restrito' => true, 'assuntos' => [['codigo' => '7']]]; if ($n === 'empty') return ['assuntos_completos' => true, 'assuntos' => []]; return ['fonte_confirmada_para_sincronizacao' => true, 'assuntos_completos' => true, 'assuntos' => [['codigo' => '42', 'descricao' => 'Fonte verificada']]]; } }''',
        'app/services/ProcessoAssuntoSyncService.php': '''<?php final class ProcessoAssuntoSyncService { public function __construct(object $db) {} public function syncFromDataJud(string $n, array $a): array { return ['numero' => $n, 'assuntos' => $a]; } }''',
    }
    for name, body in stubs.items():
        path = root / name
        path.parent.mkdir(parents=True, exist_ok=True)
        path.write_text(body, encoding='utf-8')
    with socket.socket() as s:
        s.bind(('127.0.0.1', 0))
        port = s.getsockname()[1]
    probe = root / 'connection-marker'
    proc = subprocess.Popen(['php', '-S', f'127.0.0.1:{port}', '-t', str(root / 'public')],
                            stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL,
                            env={**os.environ, 'PROBE_FILE': str(probe)})
    url = f'http://127.0.0.1:{port}/api/processos/assunto-sync.php'
    def send(body):
        request = Request(url, data=body.encode(), headers={'Content-Type': 'application/json'})
        try:
            with urlopen(request, timeout=3) as response:
                return response.status, json.load(response)
        except HTTPError as exc:
            return exc.code, json.load(exc)
    try:
        for attempt in range(50):
            try:
                send('{}')
                break
            except URLError:
                time.sleep(.05)
        invalid = ['{', '{}', '{"numero":"123","assuntos":null}',
                   '{"numero":"123","assuntos":{}}', '{"numero":"123","assuntos":[]}',
                   '{"numero":"123","assuntos":[null]}',
                   '{"numero":"123","assuntos":[{"codigo":"123"},{}]}',
                   '{"numero":"123","assuntos":[{"codigo":"---"}]}',
                   '{"numero":"123","assuntos":[{"codigo":[],"descricao":"x"}]}',
                   '{"numero":"123","assuntos":[{"codigo":"1","descricao":{}}]}']
        for body in invalid:
            status, _ = send(body)
            assert status == 400, (body, status)
            assert not probe.exists(), body
        status, data = send('{"numero":"123","assuntos":[{"codigo":"  ","codigoAssunto":123,"descricao":"","nome":"Teste"}]}')
        assert status == 200 and data['assuntos'] == [{'codigo': '42', 'descricao': 'Fonte verificada'}], data
        assert probe.exists()
        for numero, expected in [('unconfirmed', 422), ('incomplete', 422), ('restricted', 422), ('empty', 422), ('failure', 500)]:
            probe.unlink(missing_ok=True)
            status, data = send(json.dumps({'numero': numero, 'assuntos': [{'codigo': '123'}]}))
            assert status == expected and not probe.exists(), (numero, status, data)
            assert 'PRIVATE_DATAJUD_SENTINEL' not in json.dumps(data)
    finally:
        proc.terminate()
        proc.wait(timeout=5)
print('PASS validação e proveniência de assuntos via HTTP local (DataJud e banco simulados)')
