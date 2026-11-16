#!/usr/bin/env python3
"""Exercise origin and proxy logic in a temporary copy without making network requests.

This independent test does not load or modify the suspended auth/ata suites.
The connection adapter is a fail-closed sentinel, not a database simulation.
"""
import os
from pathlib import Path
import shutil
import subprocess
import tempfile

ROOT = Path(__file__).resolve().parents[2]
FILES = ('config/config.php', 'app/helpers/BootstrapHelper.php', 'app/helpers/AutoloadHelper.php')

with tempfile.TemporaryDirectory(prefix='rei172-origin-') as tmp:
    root = Path(tmp)
    for name in FILES:
        dest = root / name
        dest.parent.mkdir(parents=True, exist_ok=True)
        shutil.copyfile(ROOT / name, dest)
    (root / 'app/helpers/Conection.php').write_text('''<?php
final class Conection {
    public static function getInstance(): never {
        throw new RuntimeException('CONNECTION_SENTINEL');
    }
}
''', encoding='utf-8')
    script = r'''<?php
require __DIR__ . '/config/config.php';
require __DIR__ . '/app/helpers/BootstrapHelper.php';
$cases = [
 ['http same', ['HTTP_HOST'=>'example.test','HTTP_ORIGIN'=>'http://example.test'], true],
 ['https default port', ['HTTPS'=>'on','HTTP_HOST'=>'example.test','HTTP_ORIGIN'=>'https://example.test:443'], true],
 ['http default port', ['HTTP_HOST'=>'example.test:80','HTTP_ORIGIN'=>'http://example.test'], true],
 ['custom same', ['HTTPS'=>'on','HTTP_HOST'=>'example.test:8443','HTTP_ORIGIN'=>'https://example.test:8443'], true],
 ['custom different', ['HTTPS'=>'on','HTTP_HOST'=>'example.test:8443','HTTP_ORIGIN'=>'https://example.test:9443'], false],
 ['wrong scheme', ['HTTPS'=>'on','HTTP_HOST'=>'example.test','HTTP_ORIGIN'=>'http://example.test'], false],
 ['wrong host', ['HTTP_HOST'=>'example.test','HTTP_ORIGIN'=>'http://evil.test'], false],
 ['ipv6 same', ['HTTPS'=>'on','HTTP_HOST'=>'[::1]:8443','HTTP_ORIGIN'=>'https://[::1]:8443'], true],
 ['ipv6 different', ['HTTPS'=>'on','HTTP_HOST'=>'[::1]:8443','HTTP_ORIGIN'=>'https://[::1]:9443'], false],
 ['referer same', ['HTTP_HOST'=>'example.test:8080','HTTP_REFERER'=>'http://example.test:8080/path?q=1'], true],
 ['referer other port', ['HTTP_HOST'=>'example.test:8080','HTTP_REFERER'=>'http://example.test:8081/path'], false],
 ['missing source', ['HTTP_HOST'=>'example.test'], false],
 ['invalid origin wins', ['HTTP_HOST'=>'example.test','HTTP_ORIGIN'=>'null','HTTP_REFERER'=>'http://example.test/'], false],
 ['origin path', ['HTTP_HOST'=>'example.test','HTTP_ORIGIN'=>'http://example.test/path'], false],
 ['wrong method', ['REQUEST_METHOD'=>'GET','HTTP_HOST'=>'example.test','HTTP_ORIGIN'=>'http://example.test'], false],
 ['trusted proxy', ['REMOTE_ADDR'=>'192.0.2.1','HTTP_X_FORWARDED_PROTO'=>'https','HTTP_HOST'=>'example.test','HTTP_ORIGIN'=>'https://example.test'], true],
 ['forged proxy', ['REMOTE_ADDR'=>'192.0.2.2','HTTP_X_FORWARDED_PROTO'=>'https','HTTP_HOST'=>'example.test','HTTP_ORIGIN'=>'https://example.test'], false],
 ['forged cf', ['REMOTE_ADDR'=>'192.0.2.2','HTTP_CF_VISITOR'=>'{"scheme":"https"}','HTTP_HOST'=>'example.test','HTTP_ORIGIN'=>'https://example.test'], false],
];
foreach ($cases as [$name,$request,$expected]) {
    $_SERVER = array_merge(['REQUEST_METHOD'=>'POST','HTTPS'=>'off'], $request);
    $actual = bootstrapRequireTrustedPostRequest();
    if ($actual !== $expected) throw new RuntimeException('Unexpected result: ' . $name);
}
echo 'PASS origin/proxy (' . count($cases) . " synthetic cases)\n";
'''
    (root / 'test.php').write_text(script, encoding='utf-8')
    env = {'PATH': os.environ.get('PATH', ''), 'SystemRoot': os.environ.get('SystemRoot', ''),
           'APP_ENV': 'production', 'DB_HOST': '127.0.0.1', 'DB_PORT': '1',
           'DB_NAME': 'synthetic', 'DB_USER': 'synthetic', 'DB_PASS': 'synthetic',
           'TRUSTED_PROXIES': '192.0.2.1'}
    result = subprocess.run(['php', str(root / 'test.php')], env=env, capture_output=True, text=True, timeout=15)
    assert result.returncode == 0, (result.stdout, result.stderr)
    assert 'CONNECTION_SENTINEL' not in result.stderr
    assert result.stdout.strip() == 'PASS origin/proxy (18 synthetic cases)', result.stdout
    print(result.stdout.strip())
