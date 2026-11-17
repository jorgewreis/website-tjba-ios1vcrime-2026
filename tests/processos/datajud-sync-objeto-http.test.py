#!/usr/bin/env python3
"""HTTP local: política de perfil, existência local e confiança na fonte DataJud."""
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
with tempfile.TemporaryDirectory(prefix='rei76-datajud-') as tmp:
    base = Path(tmp)
    for directory in ('public/api/processos', 'public/api/datajud', 'public/api/cartorio-cartas-expedidas', 'app/helpers', 'app/access', 'app/repositories', 'app/services'):
        (base / directory).mkdir(parents=True, exist_ok=True)
    shutil.copyfile(ROOT / 'public/api/processos/datajud-sync.php', base / 'public/api/processos/datajud-sync.php')
    shutil.copyfile(ROOT / 'public/api/cartorio-cartas-expedidas/sync-processo-datajud.php', base / 'public/api/cartorio-cartas-expedidas/sync-processo-datajud.php')
    shutil.copyfile(ROOT / 'public/api/processos/assunto-sync.php', base / 'public/api/processos/assunto-sync.php')
    (base / 'app/helpers/ApiAccessGuard.php').write_text('''<?php
class ApiAccessGuard { static function requireAccess($pages, $write) {
  if ($pages !== ['consultaprocessos'] || !$write || ($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST'
      || ($_SERVER['HTTP_X_TEST_ORIGIN'] ?? '') !== 'ok'
      || ($_SERVER['HTTP_X_TEST_PROFILE'] ?? '') !== 'internal') { http_response_code(403); exit; }
  $_SESSION = ['id_usuario' => 'synthetic'];
} }''', encoding='utf-8')
    (base / 'app/helpers/CnjValidator.php').write_text('''<?php class CnjValidator {
  static function digits20OrThrow(string $number): string { if ($number !== '12345678901234567890') throw new InvalidArgumentException('CNJ inválido'); return $number; }
}''', encoding='utf-8')
    (base / 'app/helpers/Conection.php').write_text('''<?php class Conection {
  static function getInstance() { return new self; }
  function getConnection() { return new stdClass; }
}''', encoding='utf-8')
    (base / 'app/helpers/BootstrapHelper.php').write_text('<?php', encoding='utf-8')
    (base / 'app/repositories/ProcessosRepository.php').write_text('''<?php class ProcessosRepository {
  function __construct($connection) {}
  function findByNumero(string $numero): ?array { return ($_SERVER['HTTP_X_TEST_PROCESS'] ?? 'exists') === 'missing' ? null : ['id'=>'P1','numero_processo'=>$numero]; }
}''', encoding='utf-8')
    (base / 'public/api/datajud/config.php').write_text("<?php return ['api_key'=>'synthetic'];", encoding='utf-8')
    (base / 'app/services/DataJudService.php').write_text('''<?php class DataJudService {
  function __construct(array $config) {}
  function searchProcesso(string $numero): array {
    file_put_contents(__DIR__.'/source-called', '1');
    return match ($_SERVER['HTTP_X_TEST_RESULT'] ?? 'ok') {
      'ambiguous' => ['resultado_unico_verificado'=>false, 'sigilo_ausencia_verificada'=>true, 'fonte_confirmada_para_sincronizacao'=>true, 'assuntos_estado'=>'vazio', 'assuntos'=>[], 'classe'=>['codigo'=>42,'descricao'=>'Classe']],
      'restricted' => ['resultado_unico_verificado'=>true, 'sigilo_ausencia_verificada'=>true, 'fonte_confirmada_para_sincronizacao'=>true, 'sigilo_restrito'=>true, 'assuntos_estado'=>'vazio', 'assuntos'=>[], 'classe'=>['codigo'=>42,'descricao'=>'Classe']],
      'missing-sigilo' => ['resultado_unico_verificado'=>true, 'sigilo_restrito'=>false, 'sigilo_ausencia_verificada'=>false, 'fonte_confirmada_para_sincronizacao'=>true, 'assuntos_estado'=>'vazio', 'assuntos'=>[], 'classe'=>['codigo'=>42,'descricao'=>'Classe']],
      'subjects' => ['resultado_unico_verificado'=>true, 'sigilo_restrito'=>false, 'sigilo_ausencia_verificada'=>true, 'fonte_confirmada_para_sincronizacao'=>true, 'assuntos_completos'=>true, 'assuntos_estado'=>'presente', 'assuntos'=>[['codigo'=>7,'descricao'=>'Fonte']], 'classe'=>['codigo'=>null,'descricao'=>null]],
      'partial-class' => ['resultado_unico_verificado'=>true, 'sigilo_restrito'=>false, 'sigilo_ausencia_verificada'=>true, 'fonte_confirmada_para_sincronizacao'=>true, 'assuntos_completos'=>true, 'assuntos_estado'=>'presente', 'assuntos'=>[['codigo'=>7,'descricao'=>'Fonte']], 'classe'=>['codigo'=>42,'descricao'=>null]],
      'malformed-class' => ['resultado_unico_verificado'=>true, 'sigilo_restrito'=>false, 'sigilo_ausencia_verificada'=>true, 'fonte_confirmada_para_sincronizacao'=>true, 'assuntos_completos'=>true, 'assuntos_estado'=>'presente', 'assuntos'=>[['codigo'=>7,'descricao'=>'Fonte']], 'classe'=>['unexpected'=>'x']],
      'no-class-empty' => ['resultado_unico_verificado'=>true, 'sigilo_restrito'=>false, 'sigilo_ausencia_verificada'=>true, 'fonte_confirmada_para_sincronizacao'=>true, 'assuntos_completos'=>false, 'assuntos_estado'=>'vazio', 'assuntos'=>[], 'classe'=>['codigo'=>null,'descricao'=>null]],
      'absent-subjects' => ['resultado_unico_verificado'=>true, 'sigilo_restrito'=>false, 'sigilo_ausencia_verificada'=>true, 'fonte_confirmada_para_sincronizacao'=>true, 'assuntos_completos'=>false, 'assuntos_estado'=>'ausente', 'assuntos'=>[], 'classe'=>['codigo'=>42,'descricao'=>'Classe']],
      default => ['resultado_unico_verificado'=>true, 'sigilo_restrito'=>false, 'sigilo_ausencia_verificada'=>true, 'fonte_confirmada_para_sincronizacao'=>true, 'assuntos_estado'=>'vazio', 'assuntos'=>[], 'classe'=>['codigo'=>42,'descricao'=>'Classe']],
    };
  }
}''', encoding='utf-8')
    (base / 'app/services/ProcessoDataJudSyncService.php').write_text('''<?php class ProcessoDataJudSyncService {
  function __construct($connection) {}
    function sync(string $numero, ?array $classe, ?array $assuntos, bool $verifiedEmptySubjects=false): array {
    file_put_contents(__DIR__.'/write-called', json_encode([$numero,$classe,$assuntos,$verifiedEmptySubjects]));
    return ['classe'=>$classe === null ? null : ['action'=>'updated'], 'assuntos'=>$assuntos === null ? null : ['action'=>'updated']];
  }
}''', encoding='utf-8')

    with socket.socket() as sock:
        sock.bind(('127.0.0.1', 0))
        port = sock.getsockname()[1]
    env = {key: os.environ[key] for key in ('PATH', 'SYSTEMROOT', 'WINDIR', 'TEMP', 'TMP') if key in os.environ}
    server = subprocess.Popen(['php', '-S', f'127.0.0.1:{port}', '-t', str(base / 'public')], env=env, stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)
    read_marker = base / 'app/services/source-called'
    write_marker = base / 'app/services/write-called'

    def request(endpoint='processos/datajud-sync.php', result='ok', process='exists', profile='internal', origin='ok', number='12345678901234567890'):
        read_marker.unlink(missing_ok=True)
        write_marker.unlink(missing_ok=True)
        data = json.dumps({'numero': number, 'codigo':'999', 'descricao':'client spoof', 'assuntos':[{'codigo':'999','descricao':'client spoof'}]}).encode()
        url = f'http://127.0.0.1:{port}/api/{endpoint}'
        req = urllib.request.Request(url, data=data, headers={'Content-Type':'application/json','X-Test-Result':result,'X-Test-Process':process,'X-Test-Profile':profile,'X-Test-Origin':origin})
        try:
            with urllib.request.urlopen(req, timeout=3) as response:
                return response.status, json.loads(response.read())
        except urllib.error.HTTPError as error:
            raw = error.read()
            try: body = json.loads(raw)
            except json.JSONDecodeError: body = {}
            return error.code, body

    try:
        for _ in range(60):
            try:
                with socket.create_connection(('127.0.0.1', port), timeout=0.2): break
            except OSError:
                if server.poll() is not None: raise RuntimeError('Servidor sintético encerrou.')
                time.sleep(0.05)
        else: raise RuntimeError('Servidor sintético indisponível.')

        assert request(number='invalid')[0] == 400 and not read_marker.exists() and not write_marker.exists()
        assert request(process='missing')[0] == 404 and not read_marker.exists() and not write_marker.exists()
        assert request(profile='external')[0] == 403 and not read_marker.exists() and not write_marker.exists()
        assert request(origin='bad')[0] == 403 and not read_marker.exists() and not write_marker.exists()
        assert request(result='ambiguous')[0] == 422 and read_marker.exists() and not write_marker.exists()
        assert request(result='restricted')[0] == 422 and read_marker.exists() and not write_marker.exists()
        assert request(result='missing-sigilo')[0] == 422 and read_marker.exists() and not write_marker.exists()
        assert request(result='partial-class')[0] == 422 and read_marker.exists() and not write_marker.exists()
        assert request(result='malformed-class')[0] == 422 and read_marker.exists() and not write_marker.exists()
        assert request(result='no-class-empty')[0] == 422 and read_marker.exists() and not write_marker.exists()
        assert request(result='absent-subjects')[0] == 422 and read_marker.exists() and not write_marker.exists()
        code, _ = request(result='subjects')
        assert code == 200 and write_marker.exists()
        assert json.loads(write_marker.read_text(encoding='utf-8')) == ['12345678901234567890', None, [{'codigo':7,'descricao':'Fonte'}], False]
        code, body = request()
        assert code == 200 and body['success'] is True and write_marker.exists()
        payload = json.loads(write_marker.read_text(encoding='utf-8'))
        assert payload == ['12345678901234567890', {'codigo':'42','descricao':'Classe'}, [], True]

        cartorio = 'cartorio-cartas-expedidas/sync-processo-datajud.php'
        assert request(endpoint=cartorio, process='missing')[0] == 404 and not read_marker.exists() and not write_marker.exists()
        assert request(endpoint=cartorio, result='ambiguous')[0] == 422 and read_marker.exists() and not write_marker.exists()
        assert request(endpoint=cartorio, result='restricted')[0] == 422 and read_marker.exists() and not write_marker.exists()
        assert request(endpoint=cartorio, result='missing-sigilo')[0] == 422 and read_marker.exists() and not write_marker.exists()
        assert request(endpoint=cartorio, result='partial-class')[0] == 422 and read_marker.exists() and not write_marker.exists()
        assert request(endpoint=cartorio, result='malformed-class')[0] == 422 and read_marker.exists() and not write_marker.exists()
        assert request(endpoint=cartorio, result='no-class-empty')[0] == 422 and read_marker.exists() and not write_marker.exists()
        assert request(endpoint=cartorio, result='absent-subjects')[0] == 422 and read_marker.exists() and not write_marker.exists()
        code, _ = request(endpoint=cartorio, result='subjects')
        assert code == 200 and write_marker.exists()
        assert json.loads(write_marker.read_text(encoding='utf-8')) == ['12345678901234567890', None, [{'codigo':7,'descricao':'Fonte'}], False]
        code, body = request(endpoint=cartorio)
        assert code == 200 and body['success'] is True and write_marker.exists()
        assert json.loads(write_marker.read_text(encoding='utf-8')) == ['12345678901234567890', {'codigo':'42','descricao':'Classe'}, [], True]

        assunto = 'processos/assunto-sync.php'
        assert request(endpoint=assunto, process='missing')[0] == 404 and not read_marker.exists() and not write_marker.exists()
        assert request(endpoint=assunto, result='ambiguous')[0] == 422 and read_marker.exists() and not write_marker.exists()
        assert request(endpoint=assunto, result='restricted')[0] == 422 and read_marker.exists() and not write_marker.exists()
        assert request(endpoint=assunto, result='missing-sigilo')[0] == 422 and read_marker.exists() and not write_marker.exists()
        code, _ = request(endpoint=assunto, result='subjects')
        assert code == 200 and write_marker.exists()
        assert json.loads(write_marker.read_text(encoding='utf-8')) == ['12345678901234567890', None, [{'codigo':7,'descricao':'Fonte'}], False]
    finally:
        server.terminate()
        server.wait(timeout=5)

print('PASS HTTP DataJud: APIs principal e Cartório exigem perfil trusted POST, processo local e resposta verificável')
