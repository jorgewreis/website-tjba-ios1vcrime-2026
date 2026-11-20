"""Endpoint real com guard e conexão sintéticos; não lê .env ou dados reais."""
import json
import os
from contextlib import closing
from pathlib import Path
import shutil
import socket
import sqlite3
import subprocess
import tempfile
import time
import urllib.parse
import urllib.request

ROOT = Path(__file__).resolve().parents[2]
with tempfile.TemporaryDirectory(prefix='carta-comarca-http-') as temporary:
    root = Path(temporary)
    for rel in ('public/api/cartorio-cartas-recebidas/buscar-cidade.php',
                'app/repositories/ComarcasRepository.php', 'app/repositories/TribunaisRepository.php',
                'app/helpers/IdGenerator.php'):
        target = root / rel
        target.parent.mkdir(parents=True, exist_ok=True)
        shutil.copyfile(ROOT / rel, target)
    (root / 'app/helpers/ApiAccessGuard.php').write_text('<?php final class ApiAccessGuard { public static function requireAccess($p,$w) {} }', encoding='utf-8')
    (root / 'app/helpers/Conection.php').write_text('''<?php
final class Conection {
  public static function getInstance(): self { return new self(); }
  public function getConnection(): PDO { $p=new PDO('sqlite:'.getenv('FIXTURE_DB')); $p->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION); return $p; }
}''', encoding='utf-8')
    db_path = root / 'fixture.sqlite'
    with closing(sqlite3.connect(db_path)) as db, db:
        db.executescript('''CREATE TABLE tribunais(id TEXT, sigla TEXT, nome TEXT, created_at TEXT, updated_at TEXT);
          CREATE TABLE comarcas(id TEXT, tribunal_id TEXT, uf TEXT, nome TEXT, cidade TEXT, status_cidade TEXT, created_at TEXT, updated_at TEXT);
          INSERT INTO tribunais VALUES ('T','TJBA','TJ','',''),('P','TJPI','TJ','',''),('O','OUTRO','Outro','','');
          INSERT INTO comarcas VALUES ('AG','T','BA','Ilheus','Itajuipe','Agregada','',''),('SEDE','T','BA','Ilhéus','Ilhéus','Ativa','',''),('OUT','O','BA','Ilhéus','Ilhéus','Ativa','',''),('PI','P','PI','Itajuipe','Itajuipe','Ativa','','');''')
    with socket.socket() as sock:
        sock.bind(('127.0.0.1', 0))
        port = sock.getsockname()[1]
    env = {k: os.environ[k] for k in ('PATH', 'SystemRoot', 'WINDIR', 'TEMP', 'TMP') if k in os.environ}
    env['FIXTURE_DB'] = str(db_path)
    proc = subprocess.Popen(['php', '-S', f'127.0.0.1:{port}', '-t', str(root / 'public')], stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL, env=env)
    try:
        url = f'http://127.0.0.1:{port}/api/cartorio-cartas-recebidas/buscar-cidade.php?'
        def lookup(city, uf):
            query = urllib.parse.urlencode({'cidade': city, 'uf': uf})
            for attempt in range(30):
                try:
                    with urllib.request.urlopen(url + query, timeout=2) as response:
                        return json.load(response)
                except urllib.error.URLError:
                    if attempt == 29 or proc.poll() is not None: raise
                    time.sleep(0.05)
        result = lookup('Itajuipe', 'BA')
        assert result['found'] and result['id'] == 'SEDE' and result['nome'] == 'Ilhéus' and result['status_cidade'] == 'Agregada'
        assert lookup('Itajuipe', 'PI')['id'] == 'PI'
        assert lookup('Ilheus', 'BA')['id'] == 'SEDE'
        with closing(sqlite3.connect(db_path)) as db, db:
            db.execute("DELETE FROM comarcas WHERE id='SEDE'")
        assert lookup('Itajuipe', 'BA')['found'] is False
        with closing(sqlite3.connect(db_path)) as db:
            assert db.execute('SELECT COUNT(*) FROM comarcas').fetchone()[0] == 3
        print('PASS lookup HTTP com sede, tribunal, UF e sede ausente (SQLite; guard simulado)')
    finally:
        proc.terminate()
        proc.wait(timeout=5)
