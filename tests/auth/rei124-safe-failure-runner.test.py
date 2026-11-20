from pathlib import Path
import json, os, shutil, subprocess, sys, tempfile
HERE=Path(__file__).resolve().parent
FAKE=r'''import json,os,sys
from pathlib import Path
a=sys.argv[1:];p=Path(os.environ['REI124_STATE']);s=json.loads(p.read_text()) if p.exists() else {'items':{}}
if a[:2]==['context','show']:print('local-test');sys.exit(0)
if a[:2]==['context','inspect']:print(json.dumps([{'Endpoints':{'docker':{'Host':'unix:///var/run/docker.sock'}}}]));sys.exit(0)
if a[:2]!=['--context','local-test']:sys.exit(3)
a=a[2:]
if a[:2]==['image','inspect']:print(json.dumps([{'Id':'sha256:'+'d'*64}]));sys.exit(0)
if a[:2]==['network','create']:
 s['label']=a[a.index('--label')+1].split('=',1)[1];s['network']=a[-1];p.write_text(json.dumps(s));print('e'*64);sys.exit(0)
if a[:2]==['network','inspect']:print(json.dumps([{'Id':'e'*64,'Internal':True,'Labels':{'fokus.auth-e2e':s['label']},'Containers':{}}]));sys.exit(0)
if a[:2]==['network','rm']:s['network_removed']=True;p.write_text(json.dumps(s));sys.exit(0)
if a[:2]==['network','ls']:sys.exit(0)
if a and a[0]=='create':
 id='abcd'[len(s['items'])]*64;s['items'][id]={'mysql':len(s['items'])==0};p.write_text(json.dumps(s));print(id);sys.exit(0)
if a and a[0]=='inspect':
 id=a[-1];item=s['items'].get(id);mounts=[{'Type':'tmpfs','Destination':'/var/lib/mysql'}] if item and item['mysql'] else []
 print(json.dumps([{'Config':{'Labels':{'fokus.auth-e2e':s['label']}},'Image':'sha256:'+'d'*64,'Mounts':mounts,'HostConfig':{'Binds':[],'PortBindings':{},'Privileged':False},'NetworkSettings':{'Networks':{s['network']:{}}}}]));sys.exit(0)
if a and a[0]=='start':print('REI124_SYNTHETIC_SECRET_SENTINEL',file=sys.stderr);sys.exit(1)
if a and a[0]=='exec':print('REI124_SYNTHETIC_SECRET_SENTINEL');sys.exit(0)
if a and a[0]=='rm':s['items'].pop(a[-1],None);p.write_text(json.dumps(s));sys.exit(0)
if a and a[0]=='ps':sys.exit(0)
sys.exit(5)
'''
with tempfile.TemporaryDirectory(prefix='rei124-auth-failure-') as tmp:
 root=Path(tmp);auth=root/'tests'/'auth';auth.mkdir(parents=True)
 for n in ['run-isolated-auth-e2e.ps1','cleanup-owned.ps1','rei155-safe-diagnostic.ps1']:shutil.copyfile(HERE/n,auth/n)
 for n in ['app','config','public','vendor','services']:(root/n).mkdir()
 (root/'vendor'/'autoload.php').write_text('<?php');bin=root/'bin';bin.mkdir();fake=bin/'fake.py';fake.write_text(FAKE)
 if os.name=='nt':(bin/'docker.cmd').write_text(f'@"{sys.executable}" "{fake}" %*\r\n')
 else:f=bin/'docker';f.write_text(f'#!/bin/sh\nexec "{sys.executable}" "{fake}" "$@"\n');f.chmod(0o700)
 pwsh=shutil.which('pwsh') or shutil.which('powershell');assert pwsh
 env=os.environ.copy();env.pop('DOCKER_HOST',None);env.pop('DOCKER_CONTEXT',None);env.update({'PATH':str(bin)+os.pathsep+env.get('PATH',''),'REI124_STATE':str(root/'state.json')})
 result=subprocess.run([pwsh,'-NoProfile','-File',str(auth/'run-isolated-auth-e2e.ps1')],env=env,capture_output=True,text=True,timeout=40)
 assert result.returncode!=0 and 'RUNNER_FAILURE' in result.stdout
 assert 'REI124_SYNTHETIC_SECRET_SENTINEL' not in result.stdout+result.stderr
 data=json.loads((root/'state.json').read_text());assert data['items']=={} and data.get('network_removed')
print('PASS runner completo: erro sanitizado e limpeza de recursos sinteticos')
