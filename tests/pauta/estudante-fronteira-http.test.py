"""Endpoints copiados com sessão e serviço fictícios; nenhum config ou dado real."""
import json, os, pathlib, shutil, socket, subprocess, tempfile, time, urllib.error, urllib.request
root = pathlib.Path(__file__).resolve().parents[2]
with tempfile.TemporaryDirectory(prefix='pauta-privacidade-') as tmp:
    base = pathlib.Path(tmp)
    for name in ('public/api/sala-audiencias-ata/inscrever.php', 'public/api/sala-audiencias-ata/download.php', 'app/helpers/SalaAudienciasInscricaoIndisponivel.php'):
        dest = base / name; dest.parent.mkdir(parents=True, exist_ok=True); shutil.copyfile(root / name, dest)
    stubs = {
      'app/helpers/Conection.php': '<?php class Conection {static function getInstance(){return new self;} function getConnection(){return null;}}',
      'app/helpers/BootstrapHelper.php': "<?php $_SESSION=['id_usuario'=>'sintetico'];",
      'app/helpers/UsuarioPerfisAutorizacao.php': "<?php class UsuarioPerfisAutorizacao {static function perfisDoUsuario($id){return ['ESTUDANTE'];}}",
      'app/models/Usuario.php': "<?php class Usuario {function listarPerfisUsuario($id){return ['ESTUDANTE'];}}",
      'app/access/AccessEvaluator.php': '<?php class AccessEvaluator {static function canAccessPage($p,$u){return true;}}',
      'app/access/ProfileNormalizer.php': '<?php class ProfileNormalizer {static function normalize($p){return $p;} static function hasEstudante($p){return true;}}',
      'app/helpers/UserAccessHelper.php': '<?php class UserAccessHelper {static function isUsuarioExterno($p){return true;}}',
      'app/services/SalaAudienciasAtaService.php': "<?php require_once __DIR__.'/../helpers/SalaAudienciasInscricaoIndisponivel.php'; class SalaAudienciasAtaService {function __construct($db){} function inscreverEstudante($id,$u){if($id==='outro')throw new InvalidArgumentException('Dados inválidos.'); if($id==='erro')throw new RuntimeException('SEGREDO'); throw new SalaAudienciasInscricaoIndisponivel('MENOR SIGILO');} function getDownloadPayloadForEstudante($id,$u){if($id==='outro')throw new InvalidArgumentException('Ata ainda não disponível.'); throw new SalaAudienciasInscricaoIndisponivel('MENOR SIGILO');}}"
    }
    for name, code in stubs.items():
        dest=base/name; dest.parent.mkdir(parents=True,exist_ok=True); dest.write_text(code,encoding='utf-8')
    with socket.socket() as sock: sock.bind(('127.0.0.1',0)); port=sock.getsockname()[1]
    env={k:os.environ[k] for k in ('PATH','SystemRoot','WINDIR','TEMP','TMP') if k in os.environ}
    proc=subprocess.Popen(['php','-S',f'127.0.0.1:{port}','-t',str(base/'public')],stdout=subprocess.DEVNULL,stderr=subprocess.DEVNULL,env=env)
    try:
        for _ in range(50):
            try:
                with socket.create_connection(('127.0.0.1',port),timeout=.1): break
            except OSError:
                if proc.poll() is not None: raise RuntimeError('servidor parado')
                time.sleep(.1)
        else: raise RuntimeError('servidor indisponível')
        def request(path, data=None):
            req=urllib.request.Request(f'http://127.0.0.1:{port}{path}', data, {'Content-Type':'application/json'}, method='POST' if data is not None else 'GET')
            try:
                with urllib.request.urlopen(req, timeout=2) as res: return res.status, res.read().decode()
            except urllib.error.HTTPError as e: return e.code,e.read().decode()
        for val in ('sigilo','menor','prioridade'):
            status, body=request('/api/sala-audiencias-ata/inscrever.php',json.dumps({'pauta':val}).encode()); assert status==400 and json.loads(body)['error']=='Audiência indisponível.'
            status, body=request('/api/sala-audiencias-ata/download.php?pauta='+val); assert status==400 and body=='Audiência indisponível.'
        status,body=request('/api/sala-audiencias-ata/inscrever.php',b'{"pauta":"outro"}'); assert status==400 and json.loads(body)['error']=='Dados inválidos.'
        status,body=request('/api/sala-audiencias-ata/inscrever.php',b'{"pauta":"erro"}'); assert status==500 and 'SEGREDO' not in body
        status,body=request('/api/sala-audiencias-ata/download.php?pauta=outro'); assert status==400 and body=='Ata ainda não disponível.'
        print('PASS: fronteiras sintéticas ocultam motivo sensível')
    finally:
        proc.terminate(); proc.wait(timeout=5)
