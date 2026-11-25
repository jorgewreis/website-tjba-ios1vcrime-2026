from pathlib import Path
import re, shutil, subprocess, tempfile
root=Path(__file__).resolve().parent
source=(root/'run-isolated-auth-e2e.ps1').read_text(encoding='utf-8')
match=re.search(r"\} catch \{\n(.*?)\} finally \{\n    if \(\$creationPending\)",source,re.S)
assert match, 'bloco de falha nao encontrado'
assert "throw $executionFailure" not in source
assert 'APP_SERVER_LOG' not in source
sentinel='REI124_SYNTHETIC_SECRET_SENTINEL'
script="""$app='a'*64
$executionFailure=$null
try { throw '"""+sentinel+"""' } catch {
"""+match.group(1)+"""}
if (-not $executionFailure) { throw 'Falha nao foi preservada' }
"""
pwsh=shutil.which('pwsh') or shutil.which('powershell')
assert pwsh
with tempfile.TemporaryDirectory(prefix='rei124-sanitized-') as t:
 p=Path(t)/'probe.ps1';p.write_text(script,encoding='utf-8')
 r=subprocess.run([pwsh,'-NoProfile','-File',str(p)],capture_output=True,text=True,timeout=30)
 assert r.returncode==0,(r.returncode,r.stderr)
 assert sentinel not in r.stdout+r.stderr
 assert 'RUNNER_FAILURE' in r.stdout
print('PASS diagnostico sanitizado sem emissao do sentinela')
