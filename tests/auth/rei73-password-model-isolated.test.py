"""Test real password model and security classes against memory-only SQLite."""
from pathlib import Path
import shutil
import subprocess
import tempfile

ROOT = Path(__file__).resolve().parents[2]
with tempfile.TemporaryDirectory(prefix='rei73-password-model-') as temp:
    root = Path(temp)
    for name in ('app/models/Usuario.php', 'config/security.php'):
        destination = root / name
        destination.parent.mkdir(parents=True, exist_ok=True)
        shutil.copyfile(ROOT / name, destination)
    helper = root / 'app/helpers/Conection.php'
    helper.parent.mkdir(parents=True, exist_ok=True)
    helper.write_text("<?php final class Conection { public static function getInstance() { throw new RuntimeException('External connection forbidden'); } }\n", encoding='utf-8')
    subprocess.run(['php', str(ROOT / 'tests/auth/rei73-password-model-isolated.test.php'), str(root)], check=True, timeout=60)
