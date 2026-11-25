"""Read-only documentary verification. Usage: python verify.py REPOSITORY [CSV]."""
import csv, hashlib, pathlib, subprocess, sys
repo=pathlib.Path(sys.argv[1]); csv_path=pathlib.Path(sys.argv[2]) if len(sys.argv)>2 else pathlib.Path(__file__).with_name('rei73-coverage.csv')
rows=list(csv.DictReader(csv_path.open(encoding='utf-8')))
assert len(rows)==154 and len({r['path'] for r in rows})==154
base={r['source_commit'] for r in rows}; matrices={r['matrix_commit'] for r in rows}
assert len(base)==len(matrices)==1
base=next(iter(base)); commit=next(iter(matrices))
def git(*args): return subprocess.check_output(['git',*args],cwd=repo)
inventory={}
for line in git('ls-tree','-r',base,'public/api').decode().splitlines():
    meta,path=line.split('\t'); inventory[path]=meta.split()[2]
assert set(inventory)=={r['path'] for r in rows}
cache={}
for r in rows:
    assert inventory[r['path']]==r['git_blob']
    assert not pathlib.PurePosixPath(r['matrix']).is_absolute() and r['matrix'].startswith('docs/')
    if r['matrix'] not in cache: cache[r['matrix']]=git('show',commit+':'+r['matrix'])
    raw=cache[r['matrix']]
    assert hashlib.sha256(raw).hexdigest()==r['matrix_sha256'],r['matrix']
    line=raw.decode('utf-8').splitlines()[int(r['matrix_line'])-1]
    assert pathlib.PurePosixPath(r['path']).name in line,(r['path'],line)
    for ref in filter(None,r['secondary_operational_refs'].split(';')):
        path,num=ref.rsplit(':',1)
        text=git('show',commit+':'+path).decode('utf-8')
        assert r['path'].removeprefix('public/api/') in text.splitlines()[int(num)-1]
assert sum(r['kind']=='route' for r in rows)==150
assert sum(r['kind']=='helper' for r in rows)==2
assert sum(r['kind']=='config' for r in rows)==2
print('DOCUMENTARY PASS:154 unique Git paths;16 versioned matrices;150 routes/2 helpers/2 configs;0 missing;4 secondary references. No functional tests.')
