"""Test real Security against an in-memory PDO stub, never project config/DB."""
import os
from pathlib import Path
import shutil
import subprocess
import tempfile

root = Path(__file__).resolve().parents[2]
with tempfile.TemporaryDirectory(prefix='rei161-idle-') as temp:
    base = Path(temp)
    (base / 'config').mkdir()
    (base / 'app/helpers').mkdir(parents=True)
    shutil.copyfile(root / 'config/security.php', base / 'config/security.php')
    (base / 'app/helpers/Conection.php').write_text('''<?php
class Conection { static function getInstance(){return new self;} function getConnection(){return new FakeDb;} }
class FakeDb { function prepare($sql){return new FakeStatement;} }
class FakeStatement { function execute($params){return true;} function fetchColumn(){return $GLOBALS['valid'] ? 'synthetic' : false;} }
''', encoding='utf-8')
    (base / 'test.php').write_text('''<?php
define('SESSION_IDLE_TIMEOUT', 2);
require __DIR__.'/config/security.php';
function check($v) { if (!$v) throw new RuntimeException('idle polling failure'); }
$GLOBALS['valid']=true;
$_SESSION=['id_usuario'=>'synthetic','token'=>'synthetic','last_activity_at'=>time()-1];
$before=$_SESSION['last_activity_at'];
check(Security::isAuthenticatedWithoutActivity());
check($_SESSION['last_activity_at']===$before);
sleep(2);
check(!Security::isAuthenticatedWithoutActivity());
check($_SESSION['last_activity_at']===$before);
$_SESSION['last_activity_at']=time()-1;
check(Security::isAuthenticated());
check($_SESSION['last_activity_at']>$before);
$GLOBALS['valid']=false;
check(!Security::isAuthenticatedWithoutActivity());
echo "PASS idle auth without renewal\\n";
''', encoding='utf-8')
    env = {key: os.environ[key] for key in ('PATH','SystemRoot','WINDIR','TEMP','TMP') if key in os.environ}
    result = subprocess.run(['php', str(base / 'test.php')], env=env, capture_output=True, text=True, timeout=10)
    if result.returncode != 0:
        raise RuntimeError('synthetic idle authentication test failed')
    print(result.stdout.strip())
