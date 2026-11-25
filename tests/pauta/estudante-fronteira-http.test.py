"""Endpoints copiados com sessão e serviço fictícios; nenhum config ou dado real."""
import json, os, pathlib, shutil, socket, subprocess, tempfile, time, urllib.error, urllib.request
root = pathlib.Path(__file__).resolve().parents[2]
with tempfile.TemporaryDirectory(prefix='pauta-privacidade-') as tmp:
    base = pathlib.Path(tmp)
    for name in ('public/api/sala-audiencias-ata/inscrever.php', 'public/api/sala-audiencias-ata/download.php', 'app/helpers/SalaAudienciasInscricaoIndisponivel.php', 'app/helpers/ApiErrorReporter.php', 'app/helpers/ApiAccessGuard.php', 'app/helpers/BootstrapHelper.php'):
        dest = base / name; dest.parent.mkdir(parents=True, exist_ok=True); shutil.copyfile(root / name, dest)
    stubs = {
      'app/helpers/Conection.php': "<?php $_SESSION=['id_usuario'=>'sintetico']; function appIsHttpsRequest(): bool {return false;} class Conection {static function getInstance(){return new self;} function getConnection(){return null;}}",
      'app/helpers/AutoloadHelper.php': '<?php',
      'config/security.php': "<?php $_SESSION=['id_usuario'=>'sintetico']; class Security {static function isAuthenticated(): bool {return ($_SERVER['HTTP_X_TEST_AUTH'] ?? '') === 'valid';}}",
      'app/helpers/UsuarioPerfisAutorizacao.php': "<?php class UsuarioPerfisAutorizacao {static function perfisDoUsuario($id){return explode(',',$_SERVER['HTTP_X_TEST_PROFILE']??'EXTERNO,ESTUDANTE');}}",
      'app/models/Usuario.php': "<?php class Usuario {function listarPerfisUsuario($id){return explode(',',$_SERVER['HTTP_X_TEST_PROFILE']??'EXTERNO,ESTUDANTE');}}",
      'app/access/AccessEvaluator.php': "<?php class AccessEvaluator {static function canAccessPage($page,$profiles): bool {return in_array($page,['estudapainel','estudaata'],true) && (in_array('ESTUDANTE',$profiles,true) || in_array('ADMIN',$profiles,true));}}",
      'app/access/ProfileNormalizer.php': "<?php class ProfileNormalizer {static function normalize($profiles){return $profiles;} static function hasEstudante($profiles): bool {return in_array('ESTUDANTE',$profiles,true);}}",
      'app/helpers/UserAccessHelper.php': "<?php class UserAccessHelper {static function isUsuarioExterno($profiles): bool {return in_array('EXTERNO',$profiles,true);}}",
      'app/services/SalaAudienciasAtaService.php': "<?php require_once __DIR__.'/../helpers/SalaAudienciasInscricaoIndisponivel.php'; class SalaAudienciasAtaService {function __construct($db){} function inscreverEstudante($id,$u){file_put_contents(getenv('FOKUS_TEST_SERVICE_CALLS'), \"called\\n\", FILE_APPEND); if($id==='ok')return; if($id==='outro')throw new InvalidArgumentException('Dados inválidos.'); if($id==='erro')throw new RuntimeException('SEGREDO'); throw new SalaAudienciasInscricaoIndisponivel('MENOR SIGILO');} function getDownloadPayloadForEstudante($id,$u){if($id==='outro')throw new InvalidArgumentException('Ata ainda não disponível.'); throw new SalaAudienciasInscricaoIndisponivel('MENOR SIGILO');}}"
    }
    for name, code in stubs.items():
        dest=base/name; dest.parent.mkdir(parents=True,exist_ok=True); dest.write_text(code,encoding='utf-8')
    with socket.socket() as sock: sock.bind(('127.0.0.1',0)); port=sock.getsockname()[1]
    env={k:os.environ[k] for k in ('PATH','SystemRoot','WINDIR','TEMP','TMP') if k in os.environ}
    env['FOKUS_TEST_SERVICE_CALLS']=str(base/'service-calls')
    proc=subprocess.Popen(['php','-S',f'127.0.0.1:{port}','-t',str(base/'public')],stdout=subprocess.DEVNULL,stderr=subprocess.DEVNULL,env=env)
    try:
        for _ in range(50):
            try:
                with socket.create_connection(('127.0.0.1',port),timeout=.1): break
            except OSError:
                if proc.poll() is not None: raise RuntimeError('servidor parado')
                time.sleep(.1)
        else: raise RuntimeError('servidor indisponível')
        def request(path, data=None, headers=None):
            request_headers={'Content-Type':'application/json'}
            if headers: request_headers.update(headers)
            req=urllib.request.Request(f'http://127.0.0.1:{port}{path}', data, request_headers, method='POST' if data is not None else 'GET')
            try:
                with urllib.request.urlopen(req, timeout=2) as res: return res.status, res.read().decode()
            except urllib.error.HTTPError as e: return e.code,e.read().decode()
        endpoint='/api/sala-audiencias-ata/inscrever.php'
        payload=json.dumps({'pauta':'sigilo'}).encode()
        same_origin={'Origin':f'http://127.0.0.1:{port}','X-Test-Auth':'valid'}
        # Mesmo host (portanto same-site), outra origem (porta diferente): simula
        # cookie SameSite enviado por um subcontexto hostil na mesma origem de site.
        same_site_hostile={'Origin':f'http://127.0.0.1:{port+1}','X-Test-Auth':'valid'}
        for headers, expected in [
            ({'Origin':f'http://127.0.0.1:{port}'},401),
            ({'Origin':'http://evil.invalid','X-Test-Auth':'valid'},403),
            (same_site_hostile,403),
            ({'X-Test-Auth':'valid'},403),
            ({**same_origin,'X-Test-Profile':'EXTERNO,CONSULTOR'},403),
            ({**same_origin,'X-Test-Profile':'ADMIN,ESTUDANTE'},403),
        ]:
            status, body=request(endpoint,payload,headers)
            assert status==expected, (status,body,expected)
            assert not (base/'service-calls').exists(), 'recusa não deve chamar o serviço'
        status,body=request(endpoint,json.dumps({'pauta':'ok'}).encode(),same_origin)
        assert status==200 and json.loads(body)=={'success':True}, (status,body)
        for val in ('sigilo','menor','prioridade'):
            status, body=request(endpoint,json.dumps({'pauta':val}).encode(),same_origin); assert status==400 and json.loads(body)['error']=='Audiência indisponível.'
            status, body=request('/api/sala-audiencias-ata/download.php?pauta='+val); assert status==400 and body=='Audiência indisponível.'
        status,body=request(endpoint,b'{"pauta":"outro"}',same_origin); assert status==400 and json.loads(body)['error']=='Dados inválidos.'
        status,body=request(endpoint,b'{"pauta":"erro"}',same_origin); assert status==500 and 'SEGREDO' not in body
        status,body=request('/api/sala-audiencias-ata/download.php?pauta=outro'); assert status==400 and body=='Ata ainda não disponível.'
        assert len((base/'service-calls').read_text(encoding='utf-8').splitlines()) == 6
        print('PASS: guard de autenticação/origem, sem chamadas nas recusas e fronteiras sintéticas preservadas')
    finally:
        proc.terminate(); proc.wait(timeout=5)
