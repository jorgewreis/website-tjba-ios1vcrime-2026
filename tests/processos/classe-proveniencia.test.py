import json
import os
import pathlib
import socket
import subprocess
import tempfile
import time
import urllib.error
import urllib.request

ROOT = pathlib.Path(__file__).resolve().parents[2]
with tempfile.TemporaryDirectory() as tmp:
    base = pathlib.Path(tmp)
    for path in ('public/api/processos', 'public/api/datajud', 'app/helpers', 'app/services', 'app/repositories'):
        (base / path).mkdir(parents=True)
    (base / 'public/api/processos/classe-sync.php').write_bytes((ROOT / 'public/api/processos/classe-sync.php').read_bytes())
    (base / 'app/helpers/ApiAccessGuard.php').write_text('''<?php class ApiAccessGuard { static function requireAccess($p,$w) { if (!$w || $p !== ['consultaprocessos'] || ($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || ($_SERVER['HTTP_X_TEST_ORIGIN'] ?? '') !== 'ok') { http_response_code(403); exit; } $_SESSION=['id_usuario'=>'fixture']; } }''')
    (base / 'app/helpers/CnjValidator.php').write_text('''<?php class CnjValidator { static function digits20OrThrow($n) { if ($n !== '12345678901234567890') throw new InvalidArgumentException('CNJ invalido'); return $n; } }''')
    (base / 'app/helpers/Conection.php').write_text('''<?php class Conection { static function getInstance(){return new self;} function getConnection(){return new stdClass;} }''')
    (base / 'app/repositories/ProcessosRepository.php').write_text('''<?php class ProcessosRepository { function __construct($db) {} function findByNumero($n) { return ($_SERVER['HTTP_X_TEST_PROCESS'] ?? 'exists') === 'missing' ? null : ['id'=>'P1','numero_processo'=>$n]; } }''')
    (base / 'app/helpers/BootstrapHelper.php').write_text('<?php')
    (base / 'public/api/datajud/config.php').write_text('''<?php return ['api_key' => 'fixture'];''')
    (base / 'app/services/DataJudService.php').write_text('''<?php class DataJudService { function __construct($config) {} function searchProcesso($n) { file_put_contents(__DIR__.'/source-called','1'); $v = $_SERVER['HTTP_X_TEST_RESULT'] ?? ''; if ($v === 'falha') throw new RuntimeException('SENTINELA_PRIVADA'); if ($v === 'ausente') return []; if ($v === 'ambiguo') return ['resultado_unico_verificado'=>false,'sigilo_ausencia_verificada'=>true,'classe'=>['codigo'=>42,'descricao'=>'Oficial']]; if ($v === 'sem-sigilo') return ['resultado_unico_verificado'=>true,'sigilo_ausencia_verificada'=>false,'classe'=>['codigo'=>42,'descricao'=>'Oficial']]; if ($v === 'malformada') return ['resultado_unico_verificado'=>true,'sigilo_ausencia_verificada'=>true,'classe'=>['codigo'=>'invalid','descricao'=>'Oficial']]; return ['resultado_unico_verificado'=>true,'sigilo_ausencia_verificada'=>true,'classe'=>['codigo'=>42,'descricao'=>'Oficial']]; } }''')
    (base / 'app/services/ProcessoDataJudSyncService.php').write_text('''<?php class ProcessoDataJudSyncService { function __construct($db) {} function sync($n,$c,$a) { if (($_SERVER['HTTP_X_TEST_RESULT']??'')==='invalid-write') throw new InvalidArgumentException('SENTINELA_PRIVADA_ESCRITA'); file_put_contents(__DIR__.'/called', json_encode([$n,$c,$a])); return ['classe'=>$c,'assuntos'=>null]; } }''')
    with socket.socket() as sock:
        sock.bind(('127.0.0.1', 0))
        port = sock.getsockname()[1]
    env = {k: os.environ[k] for k in ('PATH', 'SYSTEMROOT', 'WINDIR', 'TEMP', 'TMP') if k in os.environ}
    proc = subprocess.Popen(['php', '-S', f'127.0.0.1:{port}', '-t', str(base / 'public')], env=env, stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)
    url = f'http://127.0.0.1:{port}/api/processos/classe-sync.php'
    marker = base / 'app/services/called'
    source_marker = base / 'app/services/source-called'
    def request(result, origin='ok', numero='12345678901234567890', process='exists'):
        marker.unlink(missing_ok=True)
        source_marker.unlink(missing_ok=True)
        payload = {'numero':numero,'codigo':'999','descricao':'FORJADO'}
        req = urllib.request.Request(url, data=json.dumps(payload).encode(), headers={'Content-Type':'application/json','X-Test-Result':result,'X-Test-Origin':origin,'X-Test-Process':process})
        try:
            with urllib.request.urlopen(req) as response: return response.status, response.read().decode()
        except urllib.error.HTTPError as error: return error.code, error.read().decode()
    try:
        for _ in range(100):
            try:
                with socket.create_connection(('127.0.0.1', port), timeout=0.1): break
            except OSError: time.sleep(0.05)
        else: raise RuntimeError('Servidor sintético indisponível')
        for result, status in [('ausente',422), ('ambiguo',422), ('sem-sigilo',422), ('malformada',422), ('falha',500)]:
            code, body = request(result)
            assert code == status and not marker.exists() and 'SENTINELA_PRIVADA' not in body
        assert request('ok', 'invalida')[0] == 403 and not marker.exists()
        assert request('ok', numero='invalido')[0] == 400 and not marker.exists() and not source_marker.exists()
        assert request('ok', process='missing')[0] == 404 and not marker.exists()
        assert not source_marker.exists()
        code, body = request('invalid-write')
        assert code == 409 and 'sincronizacao_resultado_indeterminado' in body and 'SENTINELA_PRIVADA' not in body and not marker.exists()
        code, body = request('ok')
        assert code == 200 and json.loads(body) == {'codigo':'42','descricao':'Oficial'}
        assert json.loads(marker.read_text()) == ['12345678901234567890', {'codigo':'42','descricao':'Oficial'}, None]
    finally:
        proc.terminate()
        proc.wait(timeout=10)
print('PASS: classe exclusiva da fonte, falha e ambiguidade sem escrita')
