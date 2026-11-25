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
    for path in ('public/api/processos', 'public/api/cartorio-cartas-expedidas', 'public/api/datajud', 'app/helpers', 'app/services', 'app/repositories'):
        (base / path).mkdir(parents=True)
    (base / 'public/api/processos/datajud-sync.php').write_bytes((ROOT / 'public/api/processos/datajud-sync.php').read_bytes())
    (base / 'public/api/cartorio-cartas-expedidas/sync-processo-datajud.php').write_bytes((ROOT / 'public/api/cartorio-cartas-expedidas/sync-processo-datajud.php').read_bytes())
    (base / 'app/helpers/ApiAccessGuard.php').write_text("<?php class ApiAccessGuard { static function requireAccess($p,$w) { $_SESSION=['id_usuario'=>'fixture']; } }")
    (base / 'app/helpers/CnjValidator.php').write_text("<?php class CnjValidator { static function digits20OrThrow($n) { if ($n === 'invalid') throw new InvalidArgumentException('CNJ inválido'); return $n; } }")
    (base / 'app/helpers/Conection.php').write_text("<?php class Conection { static function getInstance(){file_put_contents(__DIR__.'/opened','1'); return new self;} function getConnection(){return null;} }")
    (base / 'app/helpers/BootstrapHelper.php').write_text('<?php')
    (base / 'app/repositories/ProcessosRepository.php').write_text("<?php class ProcessosRepository { function __construct($db){} function findByNumero($n){return ['id'=>'p1','numero_processo'=>$n];} }")
    (base / 'public/api/datajud/config.php').write_text("<?php return ['api_key'=>'fixture'];")
    (base / 'app/services/DataJudService.php').write_text("<?php class DataJudService { function __construct($c){} function searchProcesso($n){ $v=$_SERVER['HTTP_X_TEST_CASE']??''; $c=match($v){'codigo'=>['codigo'=>'x','descricao'=>'Oficial'],'zero'=>['codigo'=>0,'descricao'=>'Oficial'],'descricao'=>['codigo'=>42,'descricao'=>''],'tipo'=>['codigo'=>42,'descricao'=>['x']],default=>['codigo'=>42,'descricao'=>'Oficial']}; $estado=match($v){'ausente'=>'ausente','indisponivel'=>'indisponivel','incoerente'=>'presente',default=>'vazio'}; return ['resultado_unico_verificado'=>true,'sigilo_ausencia_verificada'=>true,'fonte_confirmada_para_sincronizacao'=>true,'classe'=>$c,'assuntos_estado'=>$estado,'assuntos'=>[]]; } }")
    (base / 'app/services/ProcessoClasseSyncService.php').write_text("<?php class ProcessoClasseSyncService { function __construct($db){} function syncFromDataJud($n,$c,$d){ return ['action'=>'noop']; } }")
    (base / 'app/services/ProcessoAssuntoSyncService.php').write_text("<?php class ProcessoAssuntoSyncService { function __construct($db){} function syncFromDataJud($n,$a){file_put_contents(__DIR__.'/assuntos-called','1'); return []; } }")
    (base / 'app/services/ProcessoDataJudSyncService.php').write_text("<?php class ProcessoDataJudSyncService { function __construct($db){} function sync($n,$c,$a){ $v=$_SERVER['HTTP_X_TEST_CASE']??''; if ($v==='commit') throw new RuntimeException('SENTINELA_INTERNA_COMMIT'); if ($v==='invalid-write') throw new InvalidArgumentException('SENTINELA_INTERNA_INVALID'); if ($a !== []) file_put_contents(__DIR__.'/assuntos-called','1'); return ['classe'=>['action'=>'noop'],'assuntos'=>null]; } }")
    with socket.socket() as sock:
        sock.bind(('127.0.0.1', 0))
        port = sock.getsockname()[1]
    env = {k: os.environ[k] for k in ('PATH','SYSTEMROOT','WINDIR','TEMP','TMP') if k in os.environ}
    proc = subprocess.Popen(['php','-S',f'127.0.0.1:{port}','-t',str(base/'public')], env=env, stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)
    try:
        for _ in range(100):
            try:
                with socket.create_connection(('127.0.0.1',port),timeout=.1): break
            except OSError: time.sleep(.05)
        else: raise RuntimeError('Servidor sintético indisponível')
        for endpoint in ('processos/datajud-sync.php',):
            req=urllib.request.Request(f'http://127.0.0.1:{port}/api/{endpoint}', data=json.dumps({'numero':'invalid'}).encode(), headers={'Content-Type':'application/json'})
            try:
                urllib.request.urlopen(req)
                raise AssertionError('CNJ inválido aceito')
            except urllib.error.HTTPError as exc:
                assert exc.code == 400
            assert not (base/'app/helpers/opened').exists()
            for case in ('codigo','zero','descricao','tipo','ausente','indisponivel','incoerente'):
                req=urllib.request.Request(f'http://127.0.0.1:{port}/api/{endpoint}', data=json.dumps({'numero':'12345678901234567890'}).encode(), headers={'Content-Type':'application/json','X-Test-Case':case})
                try:
                    urllib.request.urlopen(req)
                    raise AssertionError('classe inválida aceita')
                except urllib.error.HTTPError as exc:
                    assert exc.code == 422
                # Processo local é conferido antes da consulta externa, então
                # abrir a conexão para essa verificação é esperado.
                if (base/'app/helpers/opened').exists():
                    (base/'app/helpers/opened').unlink()
            req=urllib.request.Request(f'http://127.0.0.1:{port}/api/{endpoint}', data=json.dumps({'numero':'12345678901234567890'}).encode(), headers={'Content-Type':'application/json','X-Test-Case':'vazio'})
            with urllib.request.urlopen(req) as response:
                assert response.status == 200
            assert not (base/'app/services/assuntos-called').exists()
            (base/'app/helpers/opened').unlink()
            for case in ('commit', 'invalid-write'):
                req=urllib.request.Request(f'http://127.0.0.1:{port}/api/{endpoint}', data=json.dumps({'numero':'12345678901234567890'}).encode(), headers={'Content-Type':'application/json','X-Test-Case':case})
                try:
                    urllib.request.urlopen(req)
                    raise AssertionError('falha simulada deve gerar resposta não confirmada')
                except urllib.error.HTTPError as exc:
                    body=exc.read().decode()
                    assert exc.code == 409 and 'sincronizacao_resultado_indeterminado' in body
                    assert 'SENTINELA_INTERNA' not in body
                (base/'app/helpers/opened').unlink()
    finally:
        proc.terminate()
        proc.wait(timeout=10)
print('PASS: classes inválidas recusadas antes da conexão')
