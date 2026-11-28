from pathlib import Path
import json, os, shutil, subprocess, sys, tempfile, unittest
HERE = Path(__file__).resolve().parent
FAKE = r'''import json, os, sys
from pathlib import Path
args = sys.argv[1:]
statefile = Path(os.environ['REI93_STATE'])
s = json.loads(statefile.read_text()) if statefile.exists() else {}
scenario = os.environ['REI93_SCENARIO']
if args[:2] == ['context', 'show']:
 print('local-test'); sys.exit(0)
if args[:2] == ['context', 'inspect']:
 print(json.dumps([{'Endpoints': {'docker': {'Host': 'unix:///var/run/docker.sock'}}}])); sys.exit(0)
if args[:2] != ['--context', 'local-test']: sys.exit(5)
args = args[2:]
if args[:2] == ['image','inspect']:
 print(json.dumps([{'Id':'sha256:'+'d'*64}])); sys.exit(0)
if args[:2] == ['network','create']:
 s['label'] = args[args.index('--label')+1].split('=',1)[1]; s['network'] = args[-1]; statefile.write_text(json.dumps(s)); print('e'*64); sys.exit(0)
if args[:2] == ['network','inspect']:
 print(json.dumps([{'Id':'e'*64,'Name':s['network'],'Internal':True,'Labels':{'fokus.auth-e2e':s['label']},'Containers':{}}])); sys.exit(0)
if args[:2] == ['network','rm']:
 s['network_removed']=True
 if scenario == 'late': s['late_container_exists']=True
 statefile.write_text(json.dumps(s)); print('e'*64); sys.exit(0)
if args[:2] == ['network','ls']: sys.exit(0)
if args and args[0] == 'create':
 s['create_attempted']=True; statefile.write_text(json.dumps(s)); print('synthetic create response lost', file=sys.stderr); sys.exit(1)
if args and args[0] == 'ps':
 if any('label=' in a for a in args):
  if scenario == 'one': print('a'*64)
  if scenario == 'ambiguous': print('a'*64); print('b'*64)
  sys.exit(0)
 sys.exit(0)
if args and args[0] == 'inspect':
 print(json.dumps([{'Config':{'Labels':{'fokus.auth-e2e':s['label']}}}])); sys.exit(0)
if args and args[0] == 'rm':
 s.setdefault('removed',[]).append(args[-1]); statefile.write_text(json.dumps(s)); print(args[-1]); sys.exit(0)
sys.exit(4)
'''
class LostCreateRunner(unittest.TestCase):
 def test_lost_create_remains_failure_and_removes_only_unique_owner(self):
  with tempfile.TemporaryDirectory(prefix='rei93-runner-fake-') as temp:
   root=Path(temp); auth=root/'tests'/'auth'; auth.mkdir(parents=True)
   for name in ['run-isolated-auth-e2e.ps1','cleanup-owned.ps1','docker-topology-validation.ps1']: shutil.copyfile(HERE/name,auth/name)
   for name in ['app','config','public','vendor','services']: (root/name).mkdir()
   (root/'vendor'/'autoload.php').write_text('<?php',encoding='utf8')
   fake=root/'bin';fake.mkdir(); script=fake/'fake_docker.py';script.write_text(FAKE,encoding='utf8')
   if os.name=='nt': (fake/'docker.cmd').write_text(f'@"{sys.executable}" "{script}" %*\r\n',encoding='utf8')
   else:
    f=fake/'docker';f.write_text(f'#!/bin/sh\nexec "{sys.executable}" "{script}" "$@"\n',encoding='utf8');f.chmod(0o700)
   pwsh=shutil.which('pwsh') or shutil.which('powershell');self.assertIsNotNone(pwsh)
   key_fd, key_path = tempfile.mkstemp(prefix='rei93-attestation-key-')
   os.write(key_fd, b'\x11'*64); os.close(key_fd)
   self.addCleanup(lambda: os.path.exists(key_path) and os.remove(key_path))
   for scenario in ['one','ambiguous','late']:
    state=root/'state.json';state.unlink(missing_ok=True)
    env=os.environ.copy();env.pop('DOCKER_HOST',None);env.pop('DOCKER_CONTEXT',None);env.pop('AUTH_TEST_ATTESTATION_KEY',None)
    env.update({'PATH':str(fake)+os.pathsep+env.get('PATH',''),'REI93_STATE':str(state),'REI93_SCENARIO':scenario,'AUTH_TEST_ATTESTATION_KEY':key_path})
    p=subprocess.run([pwsh,'-NoProfile','-NonInteractive','-File',str(auth/'run-isolated-auth-e2e.ps1')],env=env,text=True,capture_output=True,timeout=30)
    self.assertNotEqual(p.returncode,0,(p.stdout,p.stderr))
    self.assertNotIn('PASS auth-test-runner',p.stdout+p.stderr)
    self.assertIn('comprovada',p.stdout+p.stderr)
    observed=json.loads(state.read_text(encoding='utf8'))
    self.assertTrue(observed.get('create_attempted'));self.assertTrue(observed.get('network_removed'))
    if scenario == 'late': self.assertTrue(observed.get('late_container_exists'))
    self.assertEqual(observed.get('removed',[]),['a'*64] if scenario=='one' else [])
if __name__=='__main__': unittest.main()
