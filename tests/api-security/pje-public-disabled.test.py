"""Exercise the disabled feature gate in an isolated copy without network or real config."""
import json
import os
from pathlib import Path
import shutil
import subprocess
import tempfile

root = Path(__file__).resolve().parents[2]
with tempfile.TemporaryDirectory(prefix='rei73-pje-disabled-') as tmp:
    isolated = Path(tmp)
    endpoint = isolated / 'public/api/pje-publico/search-processo.php'
    endpoint.parent.mkdir(parents=True)
    shutil.copyfile(root / 'public/api/pje-publico/search-processo.php', endpoint)
    config = isolated / 'config/config.php'
    config.parent.mkdir(parents=True)
    config.write_text("<?php function envValue($name,$default='') { return $default; }", encoding='utf-8')
    env = {key: os.environ[key] for key in ('PATH', 'SystemRoot', 'WINDIR', 'TEMP', 'TMP') if key in os.environ}
    env['REI73_TEST_ENDPOINT'] = str(endpoint)
    for query in ({}, {'healthcheck': '1'}, {'raw': '1', 'numero': '0' * 20}):
        code = (
            '$_SERVER["REQUEST_METHOD"]="GET"; '
            f'$_GET=json_decode({json.dumps(json.dumps(query))},true); '
            'register_shutdown_function(function(){fwrite(STDERR,"STATUS:".http_response_code());}); '
            'require getenv("REI73_TEST_ENDPOINT");'
        )
        result = subprocess.run(['php', '-r', code], env=env, capture_output=True,
                                text=True, encoding='utf-8', timeout=10)
        assert result.returncode == 0 and result.stderr == 'STATUS:404', result.stderr
        assert json.loads(result.stdout) == {'error': 'Consulta pública PJe de teste desabilitada neste ambiente.'}
    print('PASS: feature flag desativada recusa consulta, raw e healthcheck antes de rede')
