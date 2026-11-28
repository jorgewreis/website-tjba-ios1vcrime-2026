from pathlib import Path
import json, os, shutil, subprocess, sys, tempfile
HERE=Path(__file__).resolve().parent
FAKE=r'''import json,os,sys
from pathlib import Path
a=sys.argv[1:];p=Path(os.environ['REI124_STATE']);s=json.loads(p.read_text()) if p.exists() else {};mode=os.environ['REI124_SCENARIO']
host = 'unix:///var/run/docker.sock' if mode=='linux' else ('tcp://198.51.100.1:2375' if mode=='remote' else 'npipe:////./pipe/dockerDesktopLinuxEngine')
if a[:2]==['context','show']: print('desktop-linux');sys.exit(0)
if a[:2]==['context','inspect']: print(json.dumps([{'Endpoints':{'docker':{'Host':host}}}]));sys.exit(0)
if len(a)<3 or a[0]!='--host' or a[1]!=host:sys.exit(4)
a=a[2:]
if a[:2]==['image','inspect']: print('sha256:'+'d'*64);sys.exit(0)
if a and a[0]=='create':
 s['label']=a[a.index('--label')+1].split('=',1)[1];p.write_text(json.dumps(s));print('REI124_SECRET_SENTINEL',file=sys.stderr);sys.exit(1)
if a and a[0]=='ps':
 if any('label=' in x for x in a):
  if mode in ('one','linux','ambiguous','wronglabel','inspectfail','rmfail'):print('a'*64)
  if mode=='ambiguous':print('b'*64)
 sys.exit(0)
if a and a[0]=='inspect':
 if mode=='inspectfail':sys.exit(1)
 print(json.dumps([{'Config':{'Labels':{'com.fokus.rei124ata-mysql-test':('other' if mode=='wronglabel' else s['label'])}},'HostConfig':{'PortBindings':{}}}]));sys.exit(0)
if a and a[0]=='rm':
 if mode=='rmfail':sys.exit(1)
 s.setdefault('removed',[]).append(a[-1]);p.write_text(json.dumps(s));sys.exit(0)
sys.exit(9)
'''
with tempfile.TemporaryDirectory(prefix='rei124-ata-fake-') as tmp:
 root=Path(tmp);(root/'app').mkdir();(root/'public').mkdir();(root/'config').mkdir();a=root/'tests'/'ata';a.mkdir(parents=True);shutil.copyfile(HERE/'run-rei124-ata-service-isolated.ps1',a/'run.ps1');shutil.copyfile(HERE/'rei124-mount-guard.ps1',a/'rei124-mount-guard.ps1');(a/'rei124-ata-service-mysql.test.php').write_text('<?php',encoding='utf-8');(a/'rei124-ata-http-mysql.test.php').write_text('<?php',encoding='utf-8');(a/'rei124-ata-ui-fixture.php').write_text('<?php',encoding='utf-8');(a/'rei124-ata-browser.cjs').write_text("'use strict';",encoding='utf-8');(a/'node_modules'/'playwright-core').mkdir(parents=True);(a/'node_modules'/'playwright-core'/'package.json').write_text('{"version":"1.64.1"}',encoding='utf-8');bin=root/'bin';bin.mkdir();fake=bin/'fake.py';fake.write_text(FAKE,encoding='utf-8')
 if os.name=='nt': (bin/'docker.cmd').write_text(f'@"{sys.executable}" "{fake}" %*\r\n',encoding='utf-8')
 else: f=bin/'docker';f.write_text(f'#!/bin/sh\nexec "{sys.executable}" "{fake}" "$@"\n',encoding='utf-8');f.chmod(0o700)
 pwsh=shutil.which('pwsh') or shutil.which('powershell');assert pwsh
 for mode in ('one','linux','remote','ambiguous','none','wronglabel','inspectfail','rmfail'):
  state=root/'state.json';state.unlink(missing_ok=True);env=os.environ.copy();env.update({'PATH':str(bin)+os.pathsep+env.get('PATH',''),'REI124_STATE':str(state),'REI124_SCENARIO':mode})
  r=subprocess.run([pwsh,'-NoProfile','-File',str(a/'run.ps1')],env=env,capture_output=True,text=True,timeout=30)
  assert r.returncode!=0 and 'PASS ata service' not in r.stdout
  assert 'REI124_SECRET_SENTINEL' not in r.stdout+r.stderr
  if mode=='remote':
   assert not state.exists(), 'daemon remoto chegou a create'
   continue
  removed=json.loads(state.read_text()).get('removed',[])
  assert removed==(['a'*64] if mode in ('one','linux') else []),(mode,removed)
print('PASS docker local Linux/Windows e remoto negado; create perdido falha fechado')
