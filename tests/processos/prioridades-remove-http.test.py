#!/usr/bin/env python3
"""HTTP local para provar o dispatch autorizado sem banco real."""
import json
import os
from pathlib import Path
import shutil
import socket
import subprocess
import tempfile
import time
import urllib.error
import urllib.request


ROOT = Path(__file__).resolve().parents[2]
ENDPOINT = 'public/api/processos/prioridades-remove.php'
ADD_ENDPOINT = 'public/api/processos/prioridades-add.php'
REAL_FILES = [
    ENDPOINT, ADD_ENDPOINT, 'app/helpers/ApiAccessGuard.php', 'app/helpers/BootstrapHelper.php',
    'app/helpers/UserAccessHelper.php', 'app/access/AccessEvaluator.php',
    'app/access/ProfileNormalizer.php', 'app/access/PageAccessRegistry.php',
    'app/access/AccessAreas.php', 'app/access/AccessPolicyVersion.php',
]

with tempfile.TemporaryDirectory(prefix='processos-prioridades-http-') as directory:
    root = Path(directory)
    for relative in REAL_FILES:
        target = root / relative
        target.parent.mkdir(parents=True, exist_ok=True)
        shutil.copyfile(ROOT / relative, target)

    marker = root / 'dispatch.json'
    (root / 'app/helpers/AutoloadHelper.php').write_text('<?php', encoding='utf-8')
    (root / 'app/helpers/Conection.php').write_text('''<?php
function appIsHttpsRequest(): bool { return false; }
function bootstrapSession(): void { $_SESSION = ['id_usuario' => 'synthetic-user']; }
final class Conection {
  public static function getInstance(): self { return new self(); }
  public function getConnection(): PDO { return new class extends PDO { public function __construct() {} }; }
}''', encoding='utf-8')
    (root / 'config/security.php').parent.mkdir(parents=True, exist_ok=True)
    (root / 'config/security.php').write_text('''<?php final class Security {
  public static function isAuthenticated(): bool { return ($_SERVER['HTTP_X_TEST_AUTH'] ?? '') === 'yes'; }
}''', encoding='utf-8')
    (root / 'app/helpers/UsuarioPerfisAutorizacao.php').write_text('''<?php final class UsuarioPerfisAutorizacao {
  public static function perfisDoUsuario(string $id): array { return [$_SERVER['HTTP_X_TEST_PROFILE'] ?? '']; }
}''', encoding='utf-8')
    (root / 'app/repositories').mkdir(parents=True, exist_ok=True)
    (root / 'app/services').mkdir(parents=True, exist_ok=True)
    (root / 'app/repositories/SalaAudienciasPautaParteRepository.php').write_text('''<?php final class SalaAudienciasPautaParteRepository {
  public function __construct(PDO $pdo) {}
  public function countPartesMenoresAtivasPorProcesso(string $id): int { return 0; }
}''', encoding='utf-8')
    (root / 'app/repositories/ProcessosRepository.php').write_text('''<?php final class ProcessosRepository {
  public function __construct(PDO $pdo) {}
  public function findById(string $id): ?array { return ($_SERVER['HTTP_X_TEST_PROCESS'] ?? 'yes') === 'yes' ? ['id' => $id] : null; }
}''', encoding='utf-8')
    (root / 'app/services/ProcessoPrioridadesService.php').write_text(f'''<?php final class ProcessoPrioridadesService {{
  public function __construct(PDO $pdo) {{}}
  public function removerPorCodigoOuRotulo(string $id, string $codigo, int $n): void {{
    file_put_contents({json.dumps(str(marker))}, json_encode([$id, $codigo]));
  }}
  public function adicionarManualPorCodigoOuRotulo(string $id, string $codigo): void {{
    file_put_contents({json.dumps(str(marker))}, json_encode(['add', $id, $codigo]));
  }}
  public function getEstadoEdicaoPorProcessoId(string $id, int $n): array {{ return ['processo_id' => $id]; }}
}}''', encoding='utf-8')

    with socket.socket() as sock:
        sock.bind(('127.0.0.1', 0))
        port = sock.getsockname()[1]
    server = subprocess.Popen(
        ['php', '-S', f'127.0.0.1:{port}', '-t', str(root)],
        stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL,
    )
    try:
        origin = f'http://127.0.0.1:{port}'
        base_remove = origin + '/public/api/processos/prioridades-remove.php'
        base_add = origin + '/public/api/processos/prioridades-add.php'
        for _ in range(40):
            try:
                urllib.request.urlopen(f'http://127.0.0.1:{port}/', timeout=0.2)
            except (urllib.error.URLError, urllib.error.HTTPError, TimeoutError):
                if server.poll() is not None:
                    raise RuntimeError('Servidor PHP encerrou antes do teste.')
                time.sleep(0.05)
            else:
                break

        def request(profile, method='POST', origin=None, authenticated=True, process_exists=True, endpoint='remove'):
            headers = {'Accept': 'application/json', 'X-Test-Profile': profile}
            headers['X-Test-Process'] = 'yes' if process_exists else 'no'
            if authenticated:
                headers['X-Test-Auth'] = 'yes'
            if origin is not None:
                headers['Origin'] = origin
            data = json.dumps({'processo_id': 'PROC-SINTETICO', 'codigo': '999'}).encode()
            base = base_add if endpoint == 'add' else base_remove
            req = urllib.request.Request(base, data=data if method == 'POST' else None, headers=headers, method=method)
            try:
                with urllib.request.urlopen(req, timeout=3) as response:
                    return response.status, json.loads(response.read())
            except urllib.error.HTTPError as error:
                raw = error.read().decode('utf-8', errors='replace')
                try:
                    body = json.loads(raw)
                except json.JSONDecodeError:
                    body = {'raw': raw}
                return error.code, body

        cases = [
            ('SERVIDOR-AUDIENCIAS', 'GET', origin, True, 403, False),
            ('SERVIDOR-AUDIENCIAS', 'POST', 'http://evil.invalid', True, 403, False),
            ('SERVIDOR-CARTORIO', 'POST', origin, True, 403, False),
            ('CONSULTOR', 'POST', origin, True, 403, False),
            ('SERVIDOR-AUDIENCIAS', 'POST', origin, False, 401, False),
            ('SERVIDOR-AUDIENCIAS', 'POST', origin, True, 200, True),
            ('SERVIDOR-AUDIENCIAS', 'POST', origin, True, 404, False, False),
        ]
        for case in cases:
            profile, method, request_origin, authenticated, expected, dispatched = case[:6]
            process_exists = case[6] if len(case) > 6 else True
            if marker.exists():
                marker.unlink()
            status, body = request(profile, method, request_origin, authenticated, process_exists)
            if status != expected or marker.exists() != dispatched:
                raise AssertionError((profile, method, status, body, marker.exists()))
            if dispatched and json.loads(marker.read_text(encoding='utf-8')) != ['PROC-SINTETICO', '999']:
                raise AssertionError('remove autorizado não despachou o payload esperado')
        for exists, expected, dispatched in [(False, 404, False), (True, 200, True)]:
            if marker.exists():
                marker.unlink()
            status, body = request('SERVIDOR-AUDIENCIAS', origin=origin, process_exists=exists, endpoint='add')
            if status != expected or marker.exists() != dispatched:
                raise AssertionError(('add', status, body, marker.exists()))
            if dispatched and json.loads(marker.read_text(encoding='utf-8')) != ['add', 'PROC-SINTETICO', '999']:
                raise AssertionError('add autorizado não despachou o payload esperado')
        print(f'PASS HTTP mutações de prioridade ({len(cases) + 2} casos; processo local obrigatório)')
    finally:
        server.terminate()
        server.wait(timeout=5)
