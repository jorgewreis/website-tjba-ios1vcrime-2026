"""Mixed rollout: old Security class must yield safe 503 without DB access."""
import json
import os
from pathlib import Path
import shutil
import subprocess
import tempfile

root = Path(__file__).resolve().parents[2]
with tempfile.TemporaryDirectory(prefix='rei161-old-security-') as temp:
    base = Path(temp)
    endpoint = base / 'public/api/sala-audiencias-pauta/status-bulk.php'
    endpoint.parent.mkdir(parents=True)
    shutil.copyfile(root / 'public/api/sala-audiencias-pauta/status-bulk.php', endpoint)
    required = ['app/helpers/Conection.php', 'app/helpers/BootstrapHelper.php',
        'app/helpers/UsuarioPerfisAutorizacao.php', 'app/models/Usuario.php',
        'app/access/AccessEvaluator.php', 'app/access/ProfileNormalizer.php',
        'app/helpers/UserAccessHelper.php', 'app/services/SalaAudienciasAtaService.php',
        'app/helpers/PrioridadesProcessuaisCatalog.php', 'app/helpers/SalaAudienciasAtaSigiloHelper.php',
        'app/helpers/SalaAudienciasEstudantePrivacidade.php']
    for name in required:
        p=base/name
        p.parent.mkdir(parents=True, exist_ok=True)
        p.write_text('<?php', encoding='utf-8')
    (base/'config').mkdir()
    (base/'config/security.php').write_text('<?php class Security { static function isAuthenticated(){throw new RuntimeException("must not renew");} }', encoding='utf-8')
    (base/'test.php').write_text('''<?php
$_SERVER['REQUEST_METHOD']='POST';
register_shutdown_function(static function(){fwrite(STDERR, 'STATUS='.http_response_code());});
require __DIR__.'/public/api/sala-audiencias-pauta/status-bulk.php';
''', encoding='utf-8')
    env={k:os.environ[k] for k in ('PATH','SystemRoot','WINDIR','TEMP','TMP') if k in os.environ}
    result=subprocess.run(['php',str(base/'test.php')], env=env, capture_output=True, timeout=10)
    assert result.returncode == 0 and result.stderr == b'STATUS=503'
    assert json.loads(result.stdout) == {'success':False,'error':'Serviço temporariamente indisponível.'}
    print('PASS old Security fail-closed')
