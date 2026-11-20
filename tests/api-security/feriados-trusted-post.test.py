#!/usr/bin/env python3
"""Exercita endpoints copiados, com autenticação e serviço simulados, sem banco."""
import json
import os
from pathlib import Path
import shutil
import subprocess
import tempfile

ROOT = Path(__file__).resolve().parents[2]

with tempfile.TemporaryDirectory(prefix='feriados-origin-') as tmp:
    root = Path(tmp)
    def put(path, content):
        target = root / path
        target.parent.mkdir(parents=True, exist_ok=True)
        target.write_text(content, encoding='utf-8')
    for name in ('create.php', 'update.php'):
        target = root / 'public/api/admin/feriados' / name
        target.parent.mkdir(parents=True, exist_ok=True)
        shutil.copyfile(ROOT / 'public/api/admin/feriados' / name, target)
    target = root / 'app/helpers/BootstrapHelper.php'
    target.parent.mkdir(parents=True, exist_ok=True)
    shutil.copyfile(ROOT / 'app/helpers/BootstrapHelper.php', target)
    put('app/helpers/AutoloadHelper.php', '<?php')
    put('app/helpers/Conection.php', '<?php function appIsHttpsRequest(): bool { return true; }')
    put('public/api/admin/_auth_admin_diretora_json.php', '''<?php
function requireAdminOuDiretoraJson(string $basePath) {
 if (getenv('AUTH') !== 'yes') { http_response_code(401); echo json_encode(['error'=>'Não autenticado']); exit; }
 return null;
}''')
    put('app/services/FeriadosService.php', '''<?php
class FeriadosService {
 function __construct($connection) {}
 function create($payload) { file_put_contents(getenv('MARKER'), 'write'); return ['ok'=>true]; }
 function update(...$args) { file_put_contents(getenv('MARKER'), 'write'); return ['ok'=>true]; }
}''')
    for endpoint in ('create.php', 'update.php'):
        for name, method, origin, auth, allowed in (
            ('valid', 'POST', 'https://example.test', 'yes', True),
            ('wrong_method', 'GET', 'https://example.test', 'yes', False),
            ('wrong_origin', 'POST', 'https://evil.test', 'yes', False),
            ('no_origin', 'POST', '', 'yes', False),
            ('anonymous', 'POST', 'https://example.test', 'no', False),
        ):
            marker = root / 'marker'
            marker.unlink(missing_ok=True)
            script = ('$_SERVER["REQUEST_METHOD"]=getenv("METHOD"); $_SERVER["HTTP_HOST"]="example.test"; '
                      '$_SERVER["HTTP_ORIGIN"]=getenv("ORIGIN"); $_POST=["id"=>"TEST"]; '
                      'require ' + json.dumps((root / 'public/api/admin/feriados' / endpoint).as_posix()) + ';')
            env = dict(os.environ, METHOD=method, ORIGIN=origin, AUTH=auth, MARKER=str(marker))
            proc = subprocess.run(['php', '-r', script], env=env, capture_output=True, text=True, timeout=10)
            assert proc.returncode == 0, (endpoint, name, proc.stderr)
            assert marker.exists() == allowed, (endpoint, name, proc.stdout, proc.stderr)
            json.loads(proc.stdout)
print('PASS create/update de feriados: método, origem e autenticação simulada')
