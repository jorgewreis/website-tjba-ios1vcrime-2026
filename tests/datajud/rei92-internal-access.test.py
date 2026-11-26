#!/usr/bin/env python3
"""Route and guard in synthetic isolation; the upstream implementation is inert."""
import json
import os
from pathlib import Path
import shutil
import subprocess
import tempfile

source = Path(__file__).resolve().parents[2]
files = [
    'public/api/datajud/search-processo.php',
    'app/helpers/ApiErrorReporter.php',
    'app/helpers/ApiAccessGuard.php',
    'app/helpers/BootstrapHelper.php',
    'app/helpers/UserAccessHelper.php',
    'app/helpers/CnjValidator.php',
    'app/access/AccessEvaluator.php',
    'app/access/ProfileNormalizer.php',
    'app/access/PageAccessRegistry.php',
    'app/access/AccessAreas.php',
    'app/access/AccessPolicyVersion.php',
]
with tempfile.TemporaryDirectory(prefix='rei92-guard-') as temp:
    root = Path(temp)

    def write(name, content):
        path = root / name
        path.parent.mkdir(parents=True, exist_ok=True)
        path.write_text(content, encoding='utf-8')

    for file in files:
        write(file, (source / file).read_text(encoding='utf-8'))
    write('app/helpers/AutoloadHelper.php', '<?php')
    write('app/helpers/Conection.php', '<?php function appIsHttpsRequest(): bool {return false;}')
    write('config/security.php', '<?php class Security { static function isAuthenticated():bool {return getenv("AUTH")==="yes";} }')
    write('app/helpers/UsuarioPerfisAutorizacao.php', '<?php class UsuarioPerfisAutorizacao { static function perfisDoUsuario(string $id):array {return explode(",",getenv("PROFILE"));} }')
    write('public/api/datajud/config.php', '<?php fwrite(STDERR,"|CONFIG_LOADED"); return ["api_key"=>"synthetic"];')
    write('app/services/DataJudService.php', '''<?php
class DataJudService {
  function __construct(array $config) {}
  function searchProcesso(string $number): array { fwrite(STDERR,'|UPSTREAM_CALLED'); return ['sintetico'=>true]; }
}''')
    write('driver.php', '''<?php
$_SERVER['REQUEST_METHOD']=getenv('METHOD');
$_SERVER['HTTP_HOST']='example.invalid';
$_SERVER['HTTP_ORIGIN']=getenv('ORIGIN');
$_SESSION=['id_usuario'=>'synthetic'];
$_POST=['numero'=>'0000001-69.2020.8.05.0001'];
register_shutdown_function(fn()=>fwrite(STDERR,'|STATUS:'.http_response_code()));
require __DIR__.'/public/api/datajud/search-processo.php';
''')

    def invoke(profile, *, auth='yes', method='POST', origin='http://example.invalid'):
        run = subprocess.run(['php', str(root/'driver.php')],
                             env=os.environ | dict(PROFILE=profile, AUTH=auth, METHOD=method, ORIGIN=origin),
                             text=True, capture_output=True, timeout=10, check=True)
        return int(run.stderr.rsplit('|STATUS:', 1)[-1] or 200), json.loads(run.stdout), run.stderr

    for profile in ['ADMIN', 'SERVIDOR', 'ESTAGIARIO', 'SERVIDOR-CARTORIO', 'ESTAGIARIO-GABINETE',
                    'SERVIDOR-AUDIENCIAS', 'CONSULTOR,SERVIDOR-CARTORIO']:
        code, body, evidence = invoke(profile)
        assert code == 200 and body['data']['sintetico'] and '|UPSTREAM_CALLED' in evidence, (code, profile, body)

    for profile, opts, expected in [
        ('SERVIDOR', {'auth':'no'}, 401), ('ESTUDANTE', {}, 403), ('CONSULTOR', {}, 403),
        ('', {}, 403), ('SERVIDOR', {'method':'GET'}, 403),
        ('SERVIDOR', {'origin':'http://outside.invalid'}, 403),
    ]:
        code, body, evidence = invoke(profile, **opts)
        assert code == expected and 'error' in body and 'data' not in body
        assert '|CONFIG_LOADED' not in evidence and '|UPSTREAM_CALLED' not in evidence
print('PASS REI-92: perfis internos e limite de sessão/origem; sem serviço externo.')
