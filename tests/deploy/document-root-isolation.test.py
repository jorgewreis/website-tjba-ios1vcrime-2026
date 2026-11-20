#!/usr/bin/env python3
"""Demonstra uma topologia sintética com public/ como raiz do servidor PHP."""
from pathlib import Path
import socket
import subprocess
import tempfile
import time
import urllib.error
import urllib.request

with tempfile.TemporaryDirectory(prefix='document-root-test-') as tmp:
    root = Path(tmp)
    (root / 'public').mkdir()
    (root / 'config').mkdir()
    (root / 'public/index.php').write_text('<?php echo "public-ok";', encoding='utf-8')
    (root / 'config/synthetic-marker.txt').write_text('not-public', encoding='utf-8')
    (root / '.env').write_text('synthetic-only', encoding='utf-8')
    with socket.socket() as sock:
        sock.bind(('127.0.0.1', 0))
        port = sock.getsockname()[1]
    proc = subprocess.Popen(['php', '-S', f'127.0.0.1:{port}', '-t', str(root / 'public')],
                            stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)
    try:
        base = f'http://127.0.0.1:{port}'
        for _ in range(50):
            try:
                with urllib.request.urlopen(base + '/', timeout=1) as response:
                    assert response.read() == b'public-ok'
                break
            except (OSError, urllib.error.URLError):
                if proc.poll() is not None:
                    raise RuntimeError('Servidor sintético encerrou antes de iniciar')
                time.sleep(.1)
        else:
            raise RuntimeError('Servidor sintético não iniciou')
        for path in ('/config/synthetic-marker.txt', '/.env', '/%2e%2e/config/synthetic-marker.txt'):
            try:
                with urllib.request.urlopen(base + path, timeout=2) as response:
                    # O servidor embutido pode encaminhar rotas ausentes ao index.
                    assert response.read() == b'public-ok', f'{path} expôs conteúdo inesperado'
            except urllib.error.HTTPError as error:
                assert error.code in (403, 404), (path, error.code)
    finally:
        proc.terminate()
        try:
            proc.wait(timeout=5)
        except subprocess.TimeoutExpired:
            proc.kill()
            proc.wait(timeout=5)
print('PASS raiz pública sintética em servidor PHP; configuração real não verificada')
