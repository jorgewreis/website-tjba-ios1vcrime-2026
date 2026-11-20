from pathlib import Path
import os, shutil, subprocess, tempfile
root=Path(__file__).resolve().parents[2]
text=(root/'.github/workflows/auth-isolated-e2e.yml').read_text(encoding='utf-8')
assert 'pull_request_target' not in text and 'runs-on: ubuntu-latest' in text
assert 'persist-credentials: false' in text and '  contents: read' in text
assert text.count('--label fokus.auth-e2e=rei124-ci-prep') == 2
assert 'docker build --network none' in text and '--no-scripts --no-plugins' in text
marker='      - name: Verificar resíduos do sandbox\n'
assert marker in text
section=text.split(marker,1)[1]
assert '        if: always()\n' in section
body=section.split('        run: |\n',1)[1]
lines=[]
for line in body.splitlines():
 if line and not line.startswith('          '):break
 lines.append(line[10:] if line else '')
script='\n'.join(lines)+'\n'
assert script.startswith('set -euo pipefail\n')
assert shutil.which('bash')
with tempfile.TemporaryDirectory(prefix='rei124-cleanup-check-') as tmp:
 root=Path(tmp); fake=root/'docker';fake.write_text('''#!/bin/sh
state="$REI124_STATE"
n=0; if test -f "$state"; then n=$(cat "$state"); fi
n=$((n+1)); printf "%s" "$n" > "$state"
if test "$REI124_MODE" = "error" && test "$n" = "$REI124_POINT"; then exit 7; fi
if test "$REI124_MODE" = "residual" && test "$n" = "$REI124_POINT"; then printf "synthetic-id\\n"; fi
''');fake.chmod(0o700)
 for mode in ('clean','error','residual'):
  for point in (range(1,5) if mode!='clean' else (0,)):
   state=root/'state';state.unlink(missing_ok=True)
   env=os.environ.copy();env.update({'PATH':str(root)+os.pathsep+env.get('PATH',''),'REI124_STATE':str(state),'REI124_MODE':mode,'REI124_POINT':str(point)})
   r=subprocess.run(['bash','-c',script],env=env,capture_output=True,text=True,timeout=5)
   assert (r.returncode==0)==(mode=='clean'),(mode,point,r.returncode,r.stderr)
print('PASS cleanup CI: daemon indisponivel e residuos falham fechados')
