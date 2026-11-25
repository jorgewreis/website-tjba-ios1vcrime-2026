"""Real endpoints/guards/origin/policy over localhost HTTP. No DB driver opened.
Identity/profile lookup and service/repository persistence are explicit test doubles.
"""
import hashlib,json,os,secrets,shutil,socket,subprocess,tempfile,time,urllib.request,urllib.error
from pathlib import Path
ROOT=Path(__file__).resolve().parents[2]
BASE_SHA=subprocess.check_output(['git','rev-parse','HEAD'],cwd=ROOT,text=True).strip()
EVIDENCE=Path(tempfile.mkdtemp(prefix='rei70-http-boundary-evidence-'))
ENDPOINTS=['create.php','update.php','get.php','list.php','search.php','setores.php','setores/create.php','setores/update.php','setores/delete.php','telefones/create.php','telefones/update.php','telefones/delete.php']
READS=['get.php','list.php','search.php','setores.php']
REAL=['app/helpers/ApiAccessGuard.php','app/helpers/BootstrapHelper.php','app/helpers/UserAccessHelper.php','app/helpers/InstituicoesConsultaPolicy.php','app/access/AccessEvaluator.php','app/access/ProfileNormalizer.php','app/access/PageAccessRegistry.php','app/access/AccessAreas.php','app/access/AccessPolicyVersion.php']
with tempfile.TemporaryDirectory(prefix='rei70-http-boundary-') as temp:
 root=Path(temp)
 def put(name,data):
  p=root/name;p.parent.mkdir(parents=True,exist_ok=True);p.write_text(data,encoding='utf8')
 for name in REAL+['public/api/instituicoes-publicas/'+x for x in ENDPOINTS]:
  put(name,(ROOT/name).read_text(encoding='utf8'))
 put('app/helpers/AutoloadHelper.php','<?php')
 put('config/security.php','''<?php
 $_SESSION=['id_usuario'=>'synthetic-user'];
 final class Security {public static function isAuthenticated(): bool {return ($_SERVER['HTTP_X_TEST_AUTH']??'yes')==='yes';}}
 ''')
 put('app/helpers/UsuarioPerfisAutorizacao.php','''<?php
 final class UsuarioPerfisAutorizacao {public static function perfisDoUsuario(string $id):array{return explode(',',$_SERVER['HTTP_X_TEST_PROFILES']??'ESTUDANTE');}}
 ''')
 put('app/helpers/Conection.php','''<?php
 function appIsHttpsRequest():bool{return false;}
 function auditEvent($event,$args=[]):void{file_put_contents(getenv('REI70_TRACE'),json_encode([$event,$args])."\\n",FILE_APPEND);}
 final class NeverConnectPDO extends PDO {public function __construct(){}}
 final class Conection {public static function getInstance():self{auditEvent('connection');return new self();} public function getConnection():PDO{return new NeverConnectPDO();}}
 ''')
 put('app/services/InstituicoesPublicasService.php','''<?php
 class InstituicoesPublicasService {
 public function __construct($connection){}
 public function __call($name,$args){auditEvent('service:'.$name,$args);if($name==='list')return ['rows'=>[],'total'=>0];if($name==='find')return ($args[0]==='MISSING'||($args[0]==='INACTIVE'&&$args[1]))?null:['id'=>$args[0],'ativo'=>1];return 'SYNTHETIC-ID';}
 }
 ''')
 for klass in ['InstituicoesPublicasRepository','InstituicoesSetoresRepository']:
  put('app/repositories/'+klass+'.php','<?php class '+klass+''' {
 public function __construct($connection){}
 public function __call($name,$args){auditEvent('repository:'.$name,$args);if($name==='findById')return ($args[0]==='MISSING'||($args[0]==='INACTIVE'&&($args[1]??false)===true))?null:['id'=>$args[0],'ativo'=>$args[0]==='INACTIVE'?0:1];if($name==='findByInstituicaoId'&&$args[0]==='INACTIVE')return [['id'=>'INACTIVE-PARENT-SECTOR','nome_setor'=>'Synthetic visible sector','ativo'=>1]];return [];}
 }''')
 with socket.socket() as sock:sock.bind(('127.0.0.1',0));port=sock.getsockname()[1]
 origin=f'http://127.0.0.1:{port}';trace=root/'trace.jsonl'
 env={k:os.environ[k] for k in ['PATH','SystemRoot','WINDIR','TEMP','TMP'] if k in os.environ};env['REI70_TRACE']=str(trace)
 # Prove the double exposes inactive parents without the true filter.
 contract_script='require '+json.dumps((root/'app/helpers/Conection.php').as_posix())+'; require '+json.dumps((root/'app/repositories/InstituicoesPublicasRepository.php').as_posix())+'; $r=new InstituicoesPublicasRepository(null); echo json_encode([$r->findById("INACTIVE",true),$r->findById("INACTIVE",false),$r->findById("INACTIVE"),$r->findById("MISSING",false)]);'
 contract=subprocess.run(['php','-r',contract_script],env=env,capture_output=True,timeout=10)
 assert contract.returncode==0,contract.stderr
 assert json.loads(contract.stdout)==[None,{'id':'INACTIVE','ativo':0},{'id':'INACTIVE','ativo':0},None]
 sentinel=secrets.token_hex(24)
 put('public/__fixture-'+sentinel+'.txt',sentinel)
 server_log=(EVIDENCE/'php-server.log').open('wb')
 process=subprocess.Popen(['php','-S',f'127.0.0.1:{port}','-t',str(root/'public')],stdout=server_log,stderr=server_log,env=env)
 results=[]
 def request(endpoint,profile='SERVIDOR-CARTORIO',method='POST',auth='yes',request_origin='trusted',record='INS-SYNTHETIC'):
  assert process.poll() is None,'Fixture PHP server exited; see '+str(EVIDENCE/'php-server.log')
  trace.unlink(missing_ok=True)
  headers={'Content-Type':'application/json','X-Test-Auth':auth,'X-Test-Profiles':profile}
  if request_origin is not None:headers['Origin']=origin if request_origin=='trusted' else request_origin
  payload={'id':'SYNTHETIC-ID','instituicao_id':'INS-SYNTHETIC','nome':'Ficticia','nome_setor':'Ficticio','numero':'00000000'}
  url=origin+'/api/instituicoes-publicas/'+endpoint+'?id='+record+'&instituicao_id='+record+'&nome=Fi&ativo=0'
  req=urllib.request.Request(url,data=json.dumps(payload).encode() if method=='POST' else None,headers=headers,method=method)
  try:
   with urllib.request.urlopen(req,timeout=5) as response:status=response.status;body=json.load(response)
  except urllib.error.HTTPError as error:status=error.code;body=json.load(error)
  events=[json.loads(x) for x in trace.read_text().splitlines()] if trace.exists() else []
  return status,body,events
 try:
  for _ in range(100):
   if process.poll() is not None:raise RuntimeError('Fixture PHP server exited; see '+str(EVIDENCE/'php-server.log'))
   try:
    with urllib.request.urlopen(origin+'/__fixture-'+sentinel+'.txt',timeout=.3) as response:
     assert response.status==200 and response.read().decode('ascii')==sentinel,'Wrong server on fixture port'
    if process.poll() is not None:raise RuntimeError('Fixture PHP server exited during startup')
    break
   except urllib.error.HTTPError as error:raise RuntimeError('Unexpected server on fixture port') from error
   except (urllib.error.URLError,TimeoutError):time.sleep(.05)
  else:raise RuntimeError('PHP did not start')
  for endpoint in ENDPOINTS:
   method='GET' if endpoint in READS else 'POST'
   cases=[('anonymous','SERVIDOR-CARTORIO',method,'no','trusted',401),('denied','ESTUDANTE',method,'yes','trusted',403)]
   if endpoint in READS:cases += [('method','SERVIDOR-CARTORIO','POST','yes','trusted',405)]
   else:cases += [('consultor','CONSULTOR','POST','yes','trusted',403),('method','SERVIDOR-CARTORIO','GET','yes','trusted',403),('foreign-origin','SERVIDOR-CARTORIO','POST','yes','http://evil.invalid',403),('missing-origin','SERVIDOR-CARTORIO','POST','yes',None,403),('wrong-port','SERVIDOR-CARTORIO','POST','yes','http://127.0.0.1:1',403)]
   for label,profile,verb,auth,request_origin,expected in cases:
    status,body,events=request(endpoint,profile,verb,auth,request_origin)
    assert status==expected and not events,(endpoint,label,status,body,events)
    results.append([endpoint,label,status,'no connection/service'])
   for profile in ['SERVIDOR-CARTORIO','CONSULTOR,SERVIDOR-CARTORIO']:
    status,body,events=request(endpoint,profile,method)
    operation={'create.php':'create','update.php':'update','get.php':'find','list.php':'list','search.php':'fetchList','setores.php':'findByInstituicaoId','setores/create.php':'addSetor','setores/update.php':'updateSetor','setores/delete.php':'deleteSetor','telefones/create.php':'addTelefone','telefones/update.php':'updateTelefone','telefones/delete.php':'deleteTelefone'}[endpoint]
    calls=[e[1] for e in events if e[0].split(':')[-1]==operation]
    assert status in [200,201] and len(calls)==1,(endpoint,profile,status,body,events)
    args=calls[0]
    if operation in ['update','updateSetor','deleteSetor','updateTelefone','deleteTelefone']:assert args[0]=='SYNTHETIC-ID'
    if operation in ['find','findByInstituicaoId','addSetor','addTelefone']:assert args[0]=='INS-SYNTHETIC'
    if operation=='create':assert args[0]['nome']=='Ficticia'
    if operation in ['list','fetchList']:assert args[0]['nome']=='Fi'
    results.append([endpoint,profile,status,'authorized simulated operation'])
  for profile,active in [('CONSULTOR',True),('CONSULTOR,SERVIDOR-CARTORIO',False)]:
   for endpoint in READS:
    status,body,events=request(endpoint,profile,'GET');assert status==200,(endpoint,status,body)
    calls={e[0]:e[1] for e in events}
    if endpoint=='get.php':assert calls['service:find'][1] is active
    if endpoint=='list.php':assert calls['service:list'][0]['ativo']==(1 if active else 0)
    if endpoint=='search.php':assert calls['repository:fetchList'][0].get('ativo')==(1 if active else None)
    if endpoint=='setores.php':
     assert ('repository:findById' in calls)==active
     if active:assert calls['repository:findById']==['INS-SYNTHETIC',True]
    results.append([endpoint,profile,200,'active-scope parameter verified'])
  for record in ['INACTIVE','MISSING']:
   for endpoint in ['get.php','setores.php']:
    status,body,events=request(endpoint,'CONSULTOR','GET',record=record)
    assert status==(404 if endpoint=='get.php' else 200),(record,endpoint,status,body)
    if endpoint=='setores.php':
     assert body['setores']==[] and not any(e[0]=='repository:findByInstituicaoId' for e in events)
     assert [e[1] for e in events if e[0]=='repository:findById']==[[record,True]]
    results.append([endpoint,record,status,'restricted parent refusal'])
  status,body,events=request('setores.php','CONSULTOR,SERVIDOR-CARTORIO','GET',record='INACTIVE')
  assert status==200 and [row['id'] for row in body['setores']]==['INACTIVE-PARENT-SECTOR']
  assert not any(e[0]=='repository:findById' for e in events)
  results.append(['setores.php','mixed inactive parent',status,'internal policy preserved'])
  evidence={'base':BASE_SHA,'cases':results,'count':len(results),'real_file_hashes':{n:hashlib.sha256((ROOT/n).read_bytes()).hexdigest() for n in REAL+['public/api/instituicoes-publicas/'+x for x in ENDPOINTS]},'test_sha256':hashlib.sha256(Path(__file__).read_bytes()).hexdigest(),'limits':'Real localhost HTTP and actual endpoints/guard/policy/origin. Synthetic identity and profiles, NeverConnectPDO, simulated service/repository. Proves denial before connection and propagation of scope; not DB filtering, sessions/login, persistence, object authorization, indirect exports or production.'}
  evidence_path=EVIDENCE/'results.json'
  evidence_path.write_text(json.dumps(evidence,indent=2),encoding='utf8')
  print('PASS',len(results),'HTTP synthetic cases; no database; evidence:',evidence_path)
 finally:
  process.terminate()
  try:process.wait(timeout=5)
  except subprocess.TimeoutExpired:process.kill();process.wait(timeout=5)
  server_log.close()
