"""Verifica recusa em cópias sem configuração real; não executa runner PowerShell."""
from pathlib import Path
import os
import shutil
import subprocess
import tempfile
import unittest

HERE = Path(__file__).resolve().parent
PHP_FILES = ('ata-replacement.test.php', 'ata-replacement-concurrency.test.php',
             'ata-replacement.worker.php', 'fixture-setup.test.php')
MESSAGE = 'sandbox descartável não comprovado'


class AtaGuardRefusalTest(unittest.TestCase):
    def test_php_entrypoints_refuse_before_configuration(self):
        with tempfile.TemporaryDirectory(prefix='ata-guard-refusal-') as tmp:
            root = Path(tmp)
            ata = root / 'tests' / 'ata'
            ata.mkdir(parents=True)
            config = root / 'config'
            config.mkdir()
            (config / 'config.php').write_text("<?php throw new Exception('CONFIG_ACCESSED');", encoding='utf-8')
            for name in (*PHP_FILES, 'sandbox-guard.php'):
                shutil.copyfile(HERE / name, ata / name)
            env = os.environ.copy()
            env.update(APP_ENV='testing', AUTH_TEST_ISOLATION='isolated-auth-test')
            for name in PHP_FILES:
                with self.subTest(entrypoint=name):
                    result = subprocess.run(['php', str(ata / name), 'a', 'b', 'c', 'd', 'e', 'f'],
                                            env=env, capture_output=True, text=True, encoding='utf-8',
                                            errors='replace', timeout=10)
                    self.assertNotEqual(result.returncode, 0)
                    self.assertIn(MESSAGE, result.stdout + result.stderr)
                    self.assertNotIn('CONFIG_ACCESSED', result.stdout + result.stderr)

    def test_powershell_guard_precedes_operations_without_running_runner(self):
        source = (HERE / 'run-ata-tests.ps1').read_text(encoding='utf-8-sig')
        guard = source.index("throw 'Testes de atas suspensos: sandbox descartável não comprovado.'")
        for operation in ('$runtimePath', 'Test-Path', '& php'):
            self.assertLess(guard, source.index(operation))


if __name__ == '__main__':
    unittest.main()
