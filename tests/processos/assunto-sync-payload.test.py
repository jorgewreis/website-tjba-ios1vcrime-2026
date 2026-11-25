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
        'app/helpers/Conection.php': '''<?php final class Conection { public static function getInstance(): self { return new self; } public function getConnection(): object { return new stdClass; } }''',
        'app/helpers/BootstrapHelper.php': '''<?php $_SESSION['id_usuario'] = 'synthetic';''',
        'app/helpers/ProcessoSetorAuthorization.php': '''<?php final class ProcessoSetorAuthorization { public static function canAccessProcess(object $db, string $userId, string $processId, bool $lock=false): bool { return ($_SERVER['HTTP_X_TEST_SECTOR'] ?? 'allowed') !== 'denied'; } public static function canModifyProcess(object $db, string $userId, string $processId, bool $lock=true): bool { return ($_SERVER['HTTP_X_TEST_SECTOR'] ?? 'allowed') !== 'denied'; } }''',
        'app/helpers/CnjValidator.php': '''<?php final class CnjValidator { public static function digits20OrThrow(string $v): string { $d = preg_replace('/\\D/', '', $v); if (!is_string($d) || strlen($d) !== 20) throw new InvalidArgumentException('CNJ inválido'); return $d; } }''',
        'public/api/datajud/config.php': '''<?php return ['api_key' => 'synthetic'];''',
        'app/services/DataJudService.php': '''<?php final class DataJudService { public function __construct(array $config) {} public function searchProcesso(string $n): array { file_put_contents(getenv('SOURCE_PROBE_FILE'), 'read'); if (str_ends_with($n, '0005')) throw new RuntimeException('PRIVATE_DATAJUD_SENTINEL'); if (str_ends_with($n, '0002')) return ['assuntos_completos' => true, 'assuntos' => [['codigo' => '7']]]; if (str_ends_with($n, '0003')) return ['assuntos_completos' => false, 'assuntos' => [['codigo' => '7']]]; if (str_ends_with($n, '0004')) return ['assuntos_completos' => true, 'sigilo_restrito' => true, 'assuntos' => [['codigo' => '7']]]; if (str_ends_with($n, '0006')) return ['resultado_unico_verificado' => true, 'sigilo_ausencia_verificada' => true, 'fonte_confirmada_para_sincronizacao' => true, 'assuntos_completos' => true, 'assuntos_estado' => 'vazio', 'assuntos' => []]; return ['resultado_unico_verificado' => true, 'sigilo_ausencia_verificada' => true, 'fonte_confirmada_para_sincronizacao' => true, 'assuntos_completos' => true, 'assuntos_estado' => 'presente', 'assuntos' => [['codigo' => '42', 'descricao' => 'Fonte verificada']]]; } }''',
        'app/repositories/ProcessosRepository.php': '''<?php final class ProcessosRepository { public function __construct(object $db) {} public function findByNumero(string $n): ?array { return ['id' => 'synthetic', 'numero_processo' => $n]; } }''',
        'app/services/ProcessoDataJudSyncService.php': '''<?php final class ProcessoDataJudSyncService { public function __construct(object $db) {} public function sync(string $n, ?array $c, ?array $a, bool $empty=false, ?callable $beforeWrite=null): array { file_put_contents(getenv('PROBE_FILE'), 'write'); return ['assuntos' => $a]; } }''',
    }
    for name, body in stubs.items():
        path = root / name
        path.parent.mkdir(parents=True, exist_ok=True)
        path.write_text(body, encoding='utf-8')
    with socket.socket() as s:
        s.bind(('127.0.0.1', 0))
        port = s.getsockname()[1]
    probe = root / 'connection-marker'
    source_probe = root / 'source-probe'
    proc = subprocess.Popen(['php', '-S', f'127.0.0.1:{port}', '-t', str(root / 'public')],
                            stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL,
                            env={**os.environ, 'PROBE_FILE': str(probe), 'SOURCE_PROBE_FILE': str(root / 'source-probe')})
    url = f'http://127.0.0.1:{port}/api/processos/assunto-sync.php'
    def send(body, sector='allowed'):
        request = Request(url, data=body.encode(), headers={'Content-Type': 'application/json', 'X-Test-Sector': sector})
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
        invalid = ['{', '{}', '{"numero":"123","assuntos":null}']
        for body in invalid:
            status, _ = send(body)
            assert status == 400, (body, status)
            assert not probe.exists(), body
        denied = '0000001-69.2020.8.05.0001'
        status, data = send(json.dumps({'numero': denied, 'assuntos': []}), 'denied')
        assert status == 403 and data.get('code') == 'acesso_setorial_obrigatorio', data
        assert not source_probe.exists() and not probe.exists(), 'A consulta ou escrita DataJud ocorreu sem acesso setorial.'
        status, data = send('{"numero":"0000001-69.2020.8.05.0001","assuntos":[{"codigo":"  ","codigoAssunto":123,"descricao":"","nome":"Ataque"}]}')
        assert status == 200 and data == [{'codigo': '42', 'descricao': 'Fonte verificada'}], data
        assert probe.exists()
        for numero, expected in [('0000001-69.2020.8.05.0002', 422), ('0000001-69.2020.8.05.0003', 422), ('0000001-69.2020.8.05.0004', 422), ('0000001-69.2020.8.05.0006', 422), ('0000001-69.2020.8.05.0005', 502)]:
            probe.unlink(missing_ok=True)
            status, data = send(json.dumps({'numero': numero, 'assuntos': [{'codigo': '123'}]}))
            assert status == expected and not probe.exists(), (numero, status, data)
            assert 'PRIVATE_DATAJUD_SENTINEL' not in json.dumps(data)
    finally:
        proc.terminate()
        proc.wait(timeout=5)
print('PASS validação e proveniência de assuntos via HTTP local (DataJud e banco simulados)')
