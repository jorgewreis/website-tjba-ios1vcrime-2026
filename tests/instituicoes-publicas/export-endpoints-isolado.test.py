#!/usr/bin/env python3
"""Real export endpoints, synthetic identity and disposable SQLite.

python -B tests/instituicoes-publicas/export-endpoints-isolado.test.py
Uses checkout vendor by default; REI70_VENDOR_SOURCE explicitly selects a local
fixture. Versions and source references must match composer.lock. No downloads.
PDF needs PyMuPDF. REI70_REQUIRE_PDF=1 makes unavailable PDF prerequisites fail
instead of reporting six explicit skips. CSV and both format guards always run.
"""
import json
import os
import re
from contextlib import closing
from pathlib import Path
import shutil
import sqlite3
import subprocess
import tempfile

ROOT = Path(__file__).resolve().parents[2]
BASE_SHA = subprocess.check_output(['git','rev-parse','HEAD'],cwd=ROOT,text=True).strip()
TRACKED = subprocess.check_output(['git','ls-files','-z','app'],cwd=ROOT).decode('utf-8').split('\0')
PHP = shutil.which('php')
assert PHP, 'PHP necessário'
with tempfile.TemporaryDirectory(prefix='rei70-consultor-') as tmp:
    base = Path(tmp)
    # Apenas código PHP versionado, sem configuração local, .env ou dados do projeto.
    for source in (ROOT / name for name in TRACKED if name.endswith('.php')):
        dest = base / source.relative_to(ROOT)
        dest.parent.mkdir(parents=True, exist_ok=True)
        shutil.copyfile(source, dest)
    (base / 'config').mkdir()
    (base / 'config/config.php').write_text('<?php', encoding='utf-8')
    (base / 'config/security.php').write_text('<?php final class Security { static function isAuthenticated(): bool { return true; } }', encoding='utf-8')
    (base / 'app/helpers/UsuarioPerfisAutorizacao.php').write_text('''<?php
final class UsuarioPerfisAutorizacao { static function perfisDoUsuario(string $id): array { return json_decode(getenv('REI70_PROFILES'), true); } }
''', encoding='utf-8')
    (base / 'app/helpers/Conection.php').write_text('''<?php
final class Conection { static function getInstance(): self { return new self(); }
function getConnection(): PDO { $p = new PDO('sqlite:' . getenv('REI70_DB')); $p->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION); $p->sqliteCreateFunction('FIELD', fn(...$args) => 0); return $p; } }
''', encoding='utf-8')
    dbpath = base / 'synthetic.sqlite'
    with closing(sqlite3.connect(dbpath)) as db:
        db.executescript('''
CREATE TABLE instituicoes_publicas (id TEXT, nome TEXT, nome_fantasia TEXT, cnpj TEXT, observacoes TEXT, ativo INTEGER, created_at TEXT, updated_at TEXT);
CREATE TABLE instituicoes_telefones (id TEXT, instituicao_id TEXT, tipo TEXT, numero TEXT, ramal TEXT, principal INTEGER, ativo INTEGER, created_at TEXT, updated_at TEXT);
CREATE TABLE instituicoes_setores (id TEXT, instituicao_id TEXT, nome_setor TEXT, sigla TEXT, ativo INTEGER, created_at TEXT, updated_at TEXT);
CREATE TABLE partes (id TEXT, cpf TEXT, nome_completo TEXT, sexo TEXT, data_nascimento TEXT, profissao TEXT, created_at TEXT, updated_at TEXT);
CREATE TABLE partes_documentos (id TEXT, parte_id TEXT, tipo TEXT, numero TEXT, created_at TEXT, updated_at TEXT);
CREATE TABLE partes_telefones (id TEXT, parte_id TEXT, tipo TEXT, numero TEXT, whatsapp INTEGER, principal INTEGER, ativo INTEGER, created_at TEXT, updated_at TEXT);
CREATE TABLE processos (id TEXT, numero_processo TEXT);
CREATE TABLE processos_partes (id TEXT, processo_id TEXT, parte_id TEXT, tipo_parte TEXT, polo TEXT, prioridade_mulher INTEGER, prioridade_crianca_adolescente INTEGER, prioridade_idoso INTEGER, prioridade_pessoa_deficiencia INTEGER, created_at TEXT, updated_at TEXT);
CREATE TABLE partes_instituicoes (id TEXT, parte_id TEXT, instituicao_id TEXT, setor_id TEXT, profissao TEXT, cargo_funcao TEXT, data_inclusao TEXT, data_inicio TEXT, data_fim TEXT, ativo INTEGER, observacoes TEXT, created_at TEXT, updated_at TEXT);
INSERT INTO instituicoes_publicas VALUES ('A','Instituicao Ativa','IA','111','OBS_ATIVA',1,'',''), ('I','Instituicao Inativa','II','222','OBS_INATIVA',0,'','');
INSERT INTO instituicoes_telefones VALUES ('TA','A','fixo','111',NULL,0,1,'',''), ('TI','A','fixo','222',NULL,0,0,'','');
INSERT INTO instituicoes_setores VALUES ('SA','A','Setor Ativo','SA',1,'',''), ('SI','A','Setor Inativo','SI',0,'',''), ('SX','I','Setor de Inativa','SX',1,'','');
INSERT INTO partes VALUES ('P','','Pessoa Exemplo','','','','','');
INSERT INTO partes_instituicoes VALUES ('VA','P','A','SA','','','2026-01-01',NULL,NULL,1,'','',''), ('VI','P','I','SX','','','2026-01-01',NULL,NULL,1,'','',''), ('VS','P','A','SI','','','2026-01-01',NULL,NULL,1,'','',''), ('VV','P','A','SA','','','2026-01-01',NULL,NULL,0,'','','');
''')
        db.commit()
        # Distinct parent names expose filtered links even in flattened exports.
        db.executescript("""
INSERT INTO instituicoes_publicas VALUES ('B','MARCADOR_SETOR_INATIVO','B','','',1,'',''),('C','MARCADOR_VINCULO_INATIVO','C','','',1,'','');
UPDATE instituicoes_setores SET instituicao_id='B' WHERE id='SI';
UPDATE partes_instituicoes SET instituicao_id='B' WHERE id='VS';
UPDATE partes_instituicoes SET instituicao_id='C',setor_id=NULL WHERE id='VV';
""")
        db.commit()
    env = {key: os.environ[key] for key in ('SystemRoot', 'WINDIR', 'PATH', 'TEMP', 'TMP') if key in os.environ}
    env['REI70_DB'] = str(dbpath)
    import csv, io, hashlib
    vendor_option = os.environ.get('REI70_VENDOR_SOURCE')
    vendor_path = Path(vendor_option).resolve() if vendor_option else ROOT/'vendor'
    vendor_source = str(vendor_path) if vendor_path.is_dir() else None
    if vendor_option and not vendor_source:
        raise RuntimeError('Explicit REI70_VENDOR_SOURCE does not exist: ' + str(vendor_path))
    pdf_parser = None
    try:
        import pymupdf as pdf_parser
    except ImportError:
        pass
    pdf_skip = None
    if vendor_source:
        lock = json.loads((ROOT/'composer.lock').read_text(encoding='utf-8'))
        installed = json.loads((Path(vendor_source)/'composer/installed.json').read_text(encoding='utf-8'))
        versions = {p['name']:(p['version'],p.get('source',{}).get('reference')) for p in installed['packages']}
        mismatches = [p['name'] for p in lock['packages'] if versions.get(p['name']) != (p['version'],p.get('source',{}).get('reference'))]
        assert not mismatches, 'vendor differs from composer.lock: ' + ', '.join(mismatches)
        assert (vendor_path/'autoload.php').is_file(), 'vendor/autoload.php missing'
        shutil.copytree(vendor_source, base/'vendor')
    else:
        pdf_skip = 'checkout vendor absent; install locked dependencies before CI PDF gate'
    if pdf_parser is None:
        pdf_skip = (pdf_skip + '; ' if pdf_skip else '') + 'PyMuPDF unavailable'
    if pdf_skip and os.environ.get('REI70_REQUIRE_PDF') == '1':
        raise RuntimeError('PDF required: ' + pdf_skip)
    for name in ('_common.php', 'csv.php', 'pdf.php'):
        dest = base / 'public/api/internal-list-export' / name
        dest.parent.mkdir(parents=True, exist_ok=True)
        shutil.copyfile(ROOT / 'public/api/internal-list-export' / name, dest)
    (base / 'config/security.php').write_text("<?php final class Security { static function isAuthenticated(): bool { return getenv('EXPORT_AUTH') === 'yes'; } }", encoding='utf-8')
    evidence = Path(tempfile.mkdtemp(prefix='rei70-export-evidence-'))
    before = hashlib.sha256(dbpath.read_bytes()).hexdigest()
    cases = []
    # consultaPartes intentionally requests active links for every profile (handler line 772).
    pages = [('instituicoespublicas','nome=Instituicao'), ('partesinstituicoeslist','parte_id=P'), ('consultapartes','nome=Pessoa')]
    for page, query in pages:
        for fmt in ('csv', 'pdf'):
            for label, profiles, auth, expected in [('anonymous',['CONSULTOR'],'no',401), ('student',['ESTUDANTE'],'yes',403), ('consultor',['CONSULTOR'],'yes',200), ('mixed',['CONSULTOR','SERVIDOR-CARTORIO'],'yes',200)]:
                if fmt == 'pdf' and expected == 200 and pdf_skip:
                    cases.append([page,fmt,label,None,'SKIP: ' + pdf_skip])
                    continue
                env['EXPORT_AUTH'] = auth
                target = (base / 'public/api/internal-list-export' / (fmt + '.php')).as_posix()
                script = 'register_shutdown_function(function(){fwrite(STDERR,"STATUS:".(http_response_code() ?: 200));}); $_SESSION=["id_usuario"=>"SYN"]; $_SERVER["REQUEST_METHOD"]="GET"; parse_str(' + json.dumps('page=' + page + '&' + query) + ', $_GET); require ' + json.dumps(target) + ';'
                env['REI70_PROFILES'] = json.dumps(profiles)
                response = subprocess.run([PHP,'-r',script],env=env,capture_output=True,timeout=30)
                assert response.returncode == 0, response.stderr
                status = int(re.search(rb'STATUS:(\d+)$',response.stderr).group(1))
                stdout = response.stdout.decode('utf-8') if not response.stdout.startswith(b'%PDF-') else ''
                if fmt == 'pdf' and expected == 200:
                    assert status == 200 and response.stdout.startswith(b'%PDF-') and b'%%EOF' in response.stdout[-40:]
                    document = pdf_parser.open(stream=response.stdout,filetype='pdf')
                    content = ''.join(page.get_text() for page in document)
                    assert document.page_count >= 1 and 'Instituicao Ativa' in content
                    assert ('Instituicao Inativa' in content) == (label == 'mixed'), (page,label,content)
                    if page != 'instituicoespublicas':
                        for marker in ('MARCADOR_SETOR_INATIVO','MARCADOR_VINCULO_INATIVO'):
                            assert (marker in content) == (label == 'mixed' and not (page == 'consultapartes' and marker == 'MARCADOR_VINCULO_INATIVO')), (page,label,marker,content)
                    document.close()
                    (evidence/(page+'-'+label+'.pdf')).write_bytes(response.stdout)
                    cases.append([page,fmt,label,status,'actual PDF parsed; active scope verified'])
                else:
                    assert status == expected, (page,fmt,label,status,stdout)
                    if expected == 200:
                        assert stdout.startswith('\ufeff'), (page,label,'BOM missing')
                        rows = list(csv.reader(io.StringIO(stdout.lstrip('\ufeff')), delimiter=';'))
                        assert len(rows) > 9, (page,label,'empty or malformed export')
                        assert 'Instituicao Ativa' in stdout, (page,label,'active missing')
                        assert ('Instituicao Inativa' in stdout) == (label == 'mixed'), (page,label,'scope mismatch')
                        if page != 'instituicoespublicas':
                            for marker in ('MARCADOR_SETOR_INATIVO','MARCADOR_VINCULO_INATIVO'):
                                assert (marker in stdout) == (label == 'mixed' and not (page == 'consultapartes' and marker == 'MARCADOR_VINCULO_INATIVO')), (page,label,marker,stdout)
                        assert '<?php' not in stdout and 'Warning:' not in stdout and 'Fatal error:' not in stdout
                        (evidence / (page + '-' + label + '.csv')).write_text(stdout,encoding='utf-8')
                    cases.append([page,fmt,label,status,'verified'])
                assert hashlib.sha256(dbpath.read_bytes()).hexdigest() == before, (page,fmt,label,'database changed')
    passed = sum(case[3] is not None for case in cases)
    hashes = {p.name:hashlib.sha256(p.read_bytes()).hexdigest() for p in evidence.iterdir() if p.is_file()}
    source_names = [name for name in TRACKED if name.endswith('.php')] + ['public/api/internal-list-export/'+name for name in ('_common.php','csv.php','pdf.php')]
    source_hashes = {name:hashlib.sha256((ROOT/name).read_bytes()).hexdigest() for name in source_names}
    (evidence/'results.json').write_text(json.dumps({'base':BASE_SHA,'test_sha256':hashlib.sha256(Path(__file__).read_bytes()).hexdigest(),'source_sha256':source_hashes,'cases':cases,'passed':passed,'skipped':len(cases)-passed,'vendor_source':vendor_source,'lock_sha256':hashlib.sha256((ROOT/'composer.lock').read_bytes()).hexdigest(),'artifact_sha256':hashes,'limits':'CLI entrypoints, synthetic identity/profile, actual SQLite repository/export. No HTTP header or visual PDF claim.'},indent=2),encoding='utf-8')
    print('PASS',passed,'export endpoint cases;',len(cases)-passed,'SKIP; evidence:',evidence)
    if pdf_skip:
        print('SKIP PDF content:',pdf_skip)
