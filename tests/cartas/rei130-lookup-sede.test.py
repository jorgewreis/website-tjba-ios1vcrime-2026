"""Consulta cópia temporária do endpoint com SQLite sintético e guarda simulada."""
import json
import os
from pathlib import Path
import shutil
import sqlite3
import subprocess
import tempfile

root = Path(__file__).resolve().parents[2]
with tempfile.TemporaryDirectory(prefix='rei130-lookup-') as temp:
    base = Path(temp)
    for name in ('public/api/cartorio-cartas-recebidas/buscar-cidade.php', 'app/repositories/ComarcasRepository.php', 'app/helpers/IdGenerator.php'):
        path = base / name
        path.parent.mkdir(parents=True, exist_ok=True)
        shutil.copyfile(root / name, path)
    (base / 'app/helpers/ApiAccessGuard.php').write_text('<?php class ApiAccessGuard { static function requireAccess($pages, $write) {} }', encoding='utf-8')
    (base / 'app/helpers/Conection.php').write_text('''<?php
class Conection { static function getInstance() { return new self(); }
function getConnection() { return new PDO('sqlite:' . getenv('REI130_DB')); } }
''', encoding='utf-8')
    dbpath = base / 'synthetic.sqlite'
    db = sqlite3.connect(dbpath)
    db.execute('CREATE TABLE comarcas (id TEXT, tribunal_id TEXT, uf TEXT, nome TEXT, cidade TEXT, status_cidade TEXT, created_at TEXT, updated_at TEXT)')
    db.executemany('INSERT INTO comarcas VALUES (?,?,?,?,?,?,?,?)', [
        ('agregada','tjba','BA','Itabuna','Itajuípe','Agregada','',''),
        ('sede','tjba','BA','Itabuna','Itabuna','Ativa','',''),
    ])
    db.commit()
    endpoint = (base / 'public/api/cartorio-cartas-recebidas/buscar-cidade.php').as_posix()
    def call(query):
        script = 'parse_str(' + json.dumps(query) + ', $_GET); require ' + json.dumps(endpoint) + ';'
        result = subprocess.run(['php', '-r', script], env={**os.environ, 'REI130_DB': str(dbpath)}, capture_output=True, text=True, timeout=10)
        assert result.returncode == 0, result.stderr
        return json.loads(result.stdout)
    resolved = call('cidade=Itaju%C3%ADpe&uf=BA&resolver_sede=1')
    assert resolved['found'] and resolved['id'] == 'sede' and resolved['nome'] == 'Itabuna'
    # O fluxo legado de cadastro continua com o contrato da localidade encontrada.
    legacy = call('cidade=Itaju%C3%ADpe')
    assert legacy['found'] and legacy['id'] == 'agregada'
    assert 'error' in call('cidade=Itaju%C3%ADpe&resolver_sede=1')
    db.execute("DELETE FROM comarcas WHERE id = 'sede'")
    db.commit()
    assert 'error' in call('cidade=Itaju%C3%ADpe&uf=BA&resolver_sede=1')
    db.close()
print('PASS lookup de sede e contrato legado (guarda simulada, SQLite temporário)')
