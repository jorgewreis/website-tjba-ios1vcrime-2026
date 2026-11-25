#!/usr/bin/env python3
"""Entradas HTTP reais; identidade, conexão e serializadores são stubs sintéticos."""
import http.client
import os
from pathlib import Path
import shutil
import socket
import subprocess
import tempfile
import time

ROOT = Path(__file__).resolve().parents[2]
PHP = shutil.which('php')
assert PHP, 'PHP necessário'

with tempfile.TemporaryDirectory(prefix='rei337-export-') as temp:
    base = Path(temp)
    for folder in ('public/api/internal-list-export', 'app/access', 'app/helpers', 'app/models', 'config'):
        (base / folder).mkdir(parents=True, exist_ok=True)
    for name in ('_common.php', 'csv.php', 'pdf.php'):
        shutil.copyfile(ROOT / 'public/api/internal-list-export' / name, base / 'public/api/internal-list-export' / name)
    for name in ('AccessEvaluator.php', 'ProfileNormalizer.php', 'PageAccessRegistry.php', 'AccessAreas.php', 'AccessPolicyVersion.php'):
        shutil.copyfile(ROOT / 'app/access' / name, base / 'app/access' / name)
    shutil.copyfile(ROOT / 'app/helpers/UserAccessHelper.php', base / 'app/helpers/UserAccessHelper.php')
    stubs = {
        'config/security.php': "<?php class Security { static function isAuthenticated(): bool { return ($_SERVER['HTTP_X_TEST_AUTH'] ?? '') !== 'no'; } } $_SESSION=['id_usuario'=>'SINTETICO'];",
        'app/models/Usuario.php': '<?php',
        'app/helpers/UsuarioPerfisAutorizacao.php': "<?php class UsuarioPerfisAutorizacao { static function perfisDoUsuario(string $id): array { if (($_SERVER['HTTP_X_TEST_PROFILES'] ?? '') === 'LOOKUP_EXCEPTION') { throw new RuntimeException('SYNTHETIC_LOOKUP_FAILURE'); } return explode(',', $_SERVER['HTTP_X_TEST_PROFILES'] ?? ''); } }",
        'app/helpers/Conection.php': "<?php class Conection { static function getInstance(): self { header('X-Test-Connection: reached'); return new self(); } function getConnection(): PDO { return new PDO('sqlite::memory:'); } }",
        'app/helpers/ApiErrorReporter.php': "<?php class ApiErrorReporter { static function register(string $s): void {} static function genericMessage(Throwable $e, string $s): string { return 'erro'; } }",
        'app/helpers/InternalListExportHandlers.php': "<?php class InternalListExportHandlers { static function dispatch($page,$pdo,$user,$get): array { header('X-Test-Dispatch: reached'); return ['slug'=>'test','title'=>'test','columns'=>[],'rows'=>[['SYNTHETIC_ROW']]]; } }",
        'app/helpers/ListExportFilterSummary.php': "<?php class ListExportFilterSummary { static function forInternalList($page,$get): array { return []; } }",
        'app/helpers/ListExportHelper.php': "<?php class ListExportHelper { static function streamCsv($s,$t,$f,$c,$r): void { header('Content-Type: text/csv'); echo $r[0][0]; } static function streamPdf($s,$t,$f,$c,$r): void { header('Content-Type: application/pdf'); echo '%PDF-STUB ' . $r[0][0]; } }",
    }
    for name, content in stubs.items():
        (base / name).write_text(content, encoding='utf-8')
    with socket.socket() as s:
        s.bind(('127.0.0.1', 0))
        port = s.getsockname()[1]
    proc = subprocess.Popen([PHP, '-S', f'127.0.0.1:{port}', '-t', str(base / 'public')], stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)
    try:
        for _ in range(100):
            try:
                with socket.create_connection(('127.0.0.1', port), timeout=.2):
                    break
            except OSError:
                time.sleep(.05)
        else:
            raise RuntimeError('servidor PHP não iniciou')
        cases = [
            ('estudapauta', 'ESTUDANTE', 403),
            ('estudapauta', 'ESTUDANTE,CONSULTOR', 403),
            ('estudapauta', 'ADMIN', 200),
            ('estudapauta', 'ADMIN,ESTUDANTE', 200),
            ('estudapauta', 'SERVIDOR-AUDIENCIAS,ESTUDANTE', 403),
            ('consultapartes', 'SERVIDOR-CARTORIO,ESTUDANTE', 200),
            ('consultapartes', 'ESTUDANTE,CONSULTOR', 200),
            ('consultapartes', 'CONSULTOR', 200),
            ('estudapauta', '', 403),
        ]
        for fmt in ('csv', 'pdf'):
            for page, profiles, expected in cases:
                conn = http.client.HTTPConnection('127.0.0.1', port, timeout=5)
                conn.request('GET', f'/api/internal-list-export/{fmt}.php?page={page}', headers={'X-Test-Profiles': profiles})
                response = conn.getresponse()
                body = response.read()
                headers = dict(response.getheaders())
                assert response.status == expected, (fmt, page, profiles, response.status, body)
                if expected == 403:
                    assert body == b'Acesso negado.' and b'SYNTHETIC_ROW' not in body
                    assert 'X-Test-Dispatch' not in headers and 'X-Test-Connection' not in headers
                    assert 'Content-Disposition' not in headers and not headers.get('Content-Type', '').startswith(('text/csv', 'application/pdf'))
                else:
                    assert b'SYNTHETIC_ROW' in body and headers.get('X-Test-Dispatch') == 'reached'
                conn.close()
            conn = http.client.HTTPConnection('127.0.0.1', port, timeout=5)
            conn.request('GET', f'/api/internal-list-export/{fmt}.php?page=estudapauta', headers={'X-Test-Auth': 'no', 'X-Test-Profiles': 'ESTUDANTE'})
            response = conn.getresponse()
            assert response.status == 401 and b'SYNTHETIC_ROW' not in response.read()
            assert response.getheader('X-Test-Dispatch') is None and response.getheader('X-Test-Connection') is None
            conn.close()
            conn = http.client.HTTPConnection('127.0.0.1', port, timeout=5)
            conn.request('GET', f'/api/internal-list-export/{fmt}.php?page=estudapauta', headers={'X-Test-Profiles': 'LOOKUP_EXCEPTION'})
            response = conn.getresponse()
            body = response.read()
            assert response.status == 500 and b'SYNTHETIC_ROW' not in body and b'SYNTHETIC_LOOKUP_FAILURE' not in body
            assert response.getheader('X-Test-Dispatch') is None and response.getheader('X-Test-Connection') is None
            assert response.getheader('Content-Disposition') is None
            assert not (response.getheader('Content-Type') or '').startswith(('text/csv', 'application/pdf'))
            conn.close()
        print('PASS 22 HTTP cases; export dispatch and serializers stubbed')
    finally:
        proc.terminate()
        proc.wait(timeout=10)
