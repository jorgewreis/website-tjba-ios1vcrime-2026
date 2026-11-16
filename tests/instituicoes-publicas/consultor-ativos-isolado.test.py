#!/usr/bin/env python3
"""Endpoints reais em cópia temporária; identidade simulada e SQLite descartável."""
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
PHP = shutil.which('php')
assert PHP, 'PHP necessário'
with tempfile.TemporaryDirectory(prefix='rei70-consultor-') as tmp:
    base = Path(tmp)
    # Apenas código PHP versionado, sem configuração local, .env ou dados do projeto.
    for source in (ROOT / 'app').rglob('*.php'):
        dest = base / source.relative_to(ROOT)
        dest.parent.mkdir(parents=True, exist_ok=True)
        shutil.copyfile(source, dest)
    for rel in ('instituicoes-publicas/get.php', 'instituicoes-publicas/list.php',
                'instituicoes-publicas/search.php', 'instituicoes-publicas/setores.php',
                'pessoas/get.php', 'pessoas/list.php', 'pessoas/search-by-name.php'):
        source = ROOT / 'public/api' / rel
        dest = base / 'public/api' / rel
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
    env = {key: os.environ[key] for key in ('SystemRoot', 'WINDIR', 'PATH', 'TEMP', 'TMP') if key in os.environ}
    env['REI70_DB'] = str(dbpath)
    def run(script, profiles, with_status=False):
        env['REI70_PROFILES'] = json.dumps(profiles)
        result = subprocess.run([PHP, '-r', script], env=env, capture_output=True, text=True, timeout=15)
        assert result.returncode == 0, (result.stdout, result.stderr)
        if with_status:
            match = re.search(r'STATUS:(\d+)$', result.stderr)
            assert match, (result.stdout, result.stderr)
            return result.stdout, int(match.group(1))
        return result.stdout
    def call(rel, query, profiles, expected_status=200):
        target = (base / 'public/api' / rel).as_posix()
        script = 'register_shutdown_function(function(){fwrite(STDERR,"STATUS:".(http_response_code() ?: 200));}); $_SESSION=["id_usuario"=>"SYN"]; $_SERVER["REQUEST_METHOD"]="GET"; parse_str(' + json.dumps(query) + ', $_GET); require ' + json.dumps(target) + ';'
        stdout, status = run(script, profiles, with_status=True)
        assert status == expected_status, (rel, query, status, stdout)
        try: return json.loads(stdout)
        except ValueError as error: raise AssertionError((rel, stdout)) from error
    consultant = ['CONSULTOR']
    mixed = ['CONSULTOR', 'SERVIDOR-CARTORIO']
    for profiles in (consultant, ['CONSULTOR', 'ESTUDANTE']):
        assert [x['id'] for x in call('instituicoes-publicas/list.php', 'ativo=0', profiles)['data']] == ['A']
        assert [x['id'] for x in call('instituicoes-publicas/search.php', 'nome=Instituicao', profiles)['instituicoes']] == ['A']
        detail = call('instituicoes-publicas/get.php', 'id=A', profiles)
        assert [x['id'] for x in detail['telefones']] == ['TA'] and [x['id'] for x in detail['setores']] == ['SA']
        assert 'error' in call('instituicoes-publicas/get.php', 'id=I', profiles, 404)
        assert call('instituicoes-publicas/setores.php', 'instituicao_id=I', profiles)['setores'] == []
        assert [x['id'] for x in call('instituicoes-publicas/setores.php', 'instituicao_id=A', profiles)['setores']] == ['SA']
        pessoa = call('pessoas/get.php', 'id=P', profiles)
        assert [x['id'] for x in pessoa['vinculos_instituicoes']] == ['VA']
        assert call('pessoas/list.php', 'instituicao_id=I', profiles)['pagination']['total'] == 0
        listed = call('pessoas/list.php', 'nome=Pessoa', profiles)['data'][0]
        assert listed['total_instituicoes'] == 1 and len(listed['instituicoes']) == 1
        assert 'Instituicao Inativa' not in json.dumps(call('pessoas/search-by-name.php', 'nome=Pessoa', profiles))
    assert {x['id'] for x in call('instituicoes-publicas/list.php', '', mixed)['data']} == {'A', 'I'}
    assert {x['id'] for x in call('instituicoes-publicas/get.php', 'id=A', mixed)['telefones']} == {'TA', 'TI'}
    assert call('instituicoes-publicas/get.php', 'id=I', mixed)['id'] == 'I'
    assert {x['id'] for x in call('pessoas/get.php', 'id=P', mixed)['vinculos_instituicoes']} == {'VA','VI','VS','VV'}
    assert 'error' in call('instituicoes-publicas/get.php', 'id=A', ['ESTUDANTE'], 403)
    helper = (base / 'app/helpers/InstituicoesConsultaPolicy.php').as_posix()
    effective = (base / 'app/helpers/EffectiveAccessProfiles.php').as_posix()
    mode_script = '$_SESSION=["id_usuario"=>"SYN", "access_suppress_admin_profile"=>true]; require ' + json.dumps(helper) + '; require_once ' + json.dumps(effective) + '; $p=EffectiveAccessProfiles::applyForUser("SYN",["ADMIN","CONSULTOR"]); echo InstituicoesConsultaPolicy::onlyActiveForProfiles($p) ? "restricted" : "broad";'
    assert run(mode_script, consultant) == 'restricted'
    mode_script = mode_script.replace('["ADMIN","CONSULTOR"]', '["ADMIN","CONSULTOR","SERVIDOR-CARTORIO"]')
    assert run(mode_script, consultant) == 'broad'
    handler = (base / 'app/helpers/InternalListExportHandlers.php').as_posix()
    def export(page, query, profiles):
        script = '$_SESSION=["id_usuario"=>"SYN"]; parse_str(' + json.dumps(query) + ', $_GET); require ' + json.dumps(handler) + '; $p=Conection::getInstance()->getConnection(); echo json_encode(InternalListExportHandlers::dispatch(' + json.dumps(page) + ',$p,"SYN",$_GET));'
        # Handler normalmente é carregado pelo bootstrap, que inclui Conection.
        script = 'require ' + json.dumps((base / 'app/helpers/Conection.php').as_posix()) + ';' + script
        return json.loads(run(script, profiles))
    assert [x['nome'] for x in export('instituicoespublicas', 'nome=Instituicao', consultant)['rows']] == ['Instituicao Ativa']
    assert {x['nome'] for x in export('instituicoespublicas', 'nome=Instituicao', mixed)['rows']} == {'Instituicao Ativa', 'Instituicao Inativa'}
    linked = export('partesinstituicoeslist', 'parte_id=P', consultant)['rows']
    assert len(linked) == 1 and linked[0]['instituicao'] == 'IA — Instituicao Ativa'
    assert len(export('partesinstituicoeslist', 'instituicao_id=I', consultant)['rows']) == 0
    assert len(export('partesinstituicoeslist', 'parte_id=P', mixed)['rows']) == 4
    parts = export('consultapartes', 'nome=Pessoa', consultant)['rows']
    assert len(parts) == 1 and len(parts[0]['instituicoes']) == 1
    assert len(export('consultapartes', 'instituicao_id=I', consultant)['rows']) == 0
    def view(name, query, profiles):
        target = (base / 'app/views/internal' / name).as_posix()
        script = '$_SESSION=["id_usuario"=>"SYN"]; parse_str(' + json.dumps(query) + ', $_GET); require ' + json.dumps(target) + ';'
        return run(script, profiles)
    for name, query in (('instituicoes-publicas-list.php', 'page=instituicoespublicas&nome=Instituicao'),
                        ('partes-instituicoes-list.php', 'page=partesinstituicoeslist&parte_id=P'),
                        ('consulta-partes-list.php', 'page=consultapartes&nome=Pessoa')):
        restricted = view(name, query, consultant)
        broad = view(name, query, mixed)
        assert 'Instituicao Ativa' in restricted and 'Instituicao Inativa' not in restricted, name
        assert 'Instituicao Inativa' in broad, name
print('PASS CONSULTOR externo, perfis mistos, filtros forçados e caminhos de pessoas (SQLite sintético)')
