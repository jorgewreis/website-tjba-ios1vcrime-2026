#!/usr/bin/env python3
"""HTTP sintético para a leitura e alteração manual por processo local."""
import json
import os
from pathlib import Path
import shutil
import socket
import subprocess
import tempfile
import time
import urllib.error
import urllib.parse
import urllib.request

ROOT = Path(__file__).resolve().parents[2]
with tempfile.TemporaryDirectory(prefix='rei76-classe-objeto-') as tmp:
    base = Path(tmp)
    for directory in ('public/api/processos', 'app/helpers', 'app/repositories', 'app/services'):
        (base / directory).mkdir(parents=True, exist_ok=True)
    for endpoint in ('classe-read.php', 'update-classe.php'):
        shutil.copyfile(ROOT / 'public/api/processos' / endpoint, base / 'public/api/processos' / endpoint)
    (base / 'app/helpers/ApiAccessGuard.php').write_text('''<?php class ApiAccessGuard {
  static function requireAccess($pages, $write) {
    $expected = $write ? ['consultaprocessosedit'] : ['consultaprocessos'];
    $methodOk = !$write || (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST');
    if ($pages !== $expected || !$methodOk
        || ($_SERVER['HTTP_X_TEST_PROFILE'] ?? '') !== 'internal'
        || ($write && ($_SERVER['HTTP_X_TEST_ORIGIN'] ?? '') !== 'ok')) { http_response_code(403); exit; }
    $_SESSION = ['id_usuario'=>'synthetic'];
  }
}''', encoding='utf-8')
    (base / 'app/helpers/Conection.php').write_text('''<?php class Conection { static function getInstance(){return new self;} function getConnection(){return new stdClass;} }''', encoding='utf-8')
    (base / 'app/helpers/BootstrapHelper.php').write_text('<?php', encoding='utf-8')
    (base / 'app/helpers/CnjValidator.php').write_text('''<?php class CnjValidator { static function digits20OrThrow(string $n): string { if ($n !== '12345678901234567890') throw new InvalidArgumentException('CNJ inválido'); return $n; } }''', encoding='utf-8')
    (base / 'app/repositories/ProcessosRepository.php').write_text('''<?php class ProcessosRepository {
  function __construct($db) {}
  function findByNumero(string $n): ?array { return ($_SERVER['HTTP_X_TEST_PROCESS'] ?? 'exists') === 'missing' ? null : ['id'=>'P1','numero_processo'=>$n]; }
  function findById(string $id): ?array { return ($_SERVER['HTTP_X_TEST_PROCESS'] ?? 'exists') === 'missing' ? null : ['id'=>$id,'numero_processo'=>'12345678901234567890']; }
}''', encoding='utf-8')
    (base / 'app/services/ProcessoClasseSyncService.php').write_text('''<?php class ProcessoClasseSyncService {
  function __construct($db) {}
  function readByNumero20(string $n): array { file_put_contents(__DIR__.'/read-called','1'); return ['processo'=>['numero_processo'=>$n],'classe'=>null]; }
  function updateClasseManual(string $id,string $c,?string $n): array { file_put_contents(__DIR__.'/update-called','1'); return ['action'=>'linked','processo_id'=>$id,'classe'=>['codigo'=>$c,'nome'=>$n ?? '']]; }
}''', encoding='utf-8')

    with socket.socket() as sock:
        sock.bind(('127.0.0.1',0)); port=sock.getsockname()[1]
    env={k:os.environ[k] for k in ('PATH','SYSTEMROOT','WINDIR','TEMP','TMP') if k in os.environ}
    server=subprocess.Popen(['php','-S',f'127.0.0.1:{port}','-t',str(base/'public')],env=env,stdout=subprocess.DEVNULL,stderr=subprocess.DEVNULL)
    read_marker=base/'app/services/read-called'; update_marker=base/'app/services/update-called'
    def request(endpoint, method, process='exists', profile='internal', origin='ok'):
        read_marker.unlink(missing_ok=True); update_marker.unlink(missing_ok=True)
        headers={'X-Test-Profile':profile,'X-Test-Process':process}
        url=f'http://127.0.0.1:{port}/api/processos/{endpoint}'
        if endpoint=='classe-read.php':
            url += '?' + urllib.parse.urlencode({'numero':'12345678901234567890'})
            data=None
        else:
            headers.update({'Origin':f'http://127.0.0.1:{port}','X-Test-Origin':origin,'Content-Type':'application/json'})
            data=json.dumps({'processo_id':'P1','codigo':'42','nome':'Classe'}).encode()
        req=urllib.request.Request(url,data=data,headers=headers,method=method)
        try:
            with urllib.request.urlopen(req,timeout=3) as response: return response.status,json.loads(response.read())
        except urllib.error.HTTPError as error:
            raw=error.read()
            try: body=json.loads(raw)
            except json.JSONDecodeError: body={}
            return error.code,body
    try:
        for _ in range(60):
            try:
                with socket.create_connection(('127.0.0.1',port),timeout=.2): break
            except OSError:
                if server.poll() is not None: raise RuntimeError('Servidor sintético encerrou.')
                time.sleep(.05)
        else: raise RuntimeError('Servidor sintético indisponível.')
        assert request('classe-read.php','GET',process='missing')[0]==404 and not read_marker.exists()
        assert request('classe-read.php','GET')[0]==200 and read_marker.exists()
        assert request('classe-read.php','POST')[0]==405 and not read_marker.exists()
        assert request('update-classe.php','POST',process='missing')[0]==404 and not update_marker.exists()
        assert request('update-classe.php','POST')[0]==200 and update_marker.exists()
        assert request('update-classe.php','POST',profile='external')[0]==403 and not update_marker.exists()
        assert request('update-classe.php','POST',origin='bad')[0]==403 and not update_marker.exists()
    finally:
        server.terminate(); server.wait(timeout=5)

print('PASS HTTP classe: leitura/alteração por perfil e processo local')
