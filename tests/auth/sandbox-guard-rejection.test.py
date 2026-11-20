"""Verify containment in disposable copies without opening a DB or reading .env."""
from pathlib import Path
import os
import shutil
import subprocess
import tempfile
import unittest

HERE = Path(__file__).resolve().parent
PHP_FILES = (
    'auth-fixtures.test.php', 'config-environment-selection.test.php', 'fixture-setup.test.php',
    'http-auth-flow.test.php', 'http-ready.test.php', 'rei171-login-reset-race.test.php',
    'rei155-concurrency.worker.php', 'rei155-legacy-denied.test.php', 'rei155-password-change.test.php',
    'rei155-nontransactional.test.php', 'rei155-metadata-lock.test.php',
    'rei155-ordered.test.php', 'rei155-ordered.worker.php',
    'http-reset-token.fixture.php', 'login-rate-limit.fixture.php',
    'login-rate-limit-storage.fixture.php', 'login-rate-limit.test.php',
    'password-reset-states.test.php', 'reset-concurrency.worker.php',
    'reset-token-concurrency.test.php', 'sandbox-connectivity.test.php',
    'smtp-capture.test.php', 'trusted-post-origin.test.php',
)
PS_FILES = (
    'http-auth-flow.test.ps1', 'run-isolated-mysql-php.ps1',
)
MESSAGE = 'comprovado'


class SandboxRefusalTest(unittest.TestCase):
    def test_generic_cleanup_refuses_before_docker(self):
        with tempfile.TemporaryDirectory(prefix='auth-cleanup-refusal-') as temp:
            root = Path(temp)
            script = root / 'cleanup.ps1'
            shutil.copyfile(HERE / 'cleanup.ps1', script)
            fakebin = root / 'bin'
            fakebin.mkdir()
            marker = root / 'docker-called'
            if os.name == 'nt':
                (fakebin / 'docker.cmd').write_text(f'@echo called > "{marker}"\r\n', encoding='utf-8')
            else:
                fake = fakebin / 'docker'
                fake.write_text(f'#!/bin/sh\necho called > "{marker}"\n', encoding='utf-8')
                fake.chmod(0o700)
            env = os.environ.copy()
            env['PATH'] = str(fakebin) + os.pathsep + env.get('PATH', '')
            powershell = shutil.which('pwsh') or shutil.which('powershell')
            self.assertIsNotNone(powershell, 'PowerShell is required')
            result = subprocess.run([powershell, '-NoProfile', '-NonInteractive', '-File', str(script)],
                                    env=env, text=True, capture_output=True, timeout=15)
            self.assertNotEqual(result.returncode, 0)
            self.assertIn('suspensa', result.stderr + result.stdout)
            self.assertFalse(marker.exists(), 'docker must not be invoked')

    def test_all_php_entrypoints_are_in_refusal_matrix(self):
        discovered = {p.name for p in HERE.glob('*.php')} - {'sandbox-guard.php'}
        self.assertSetEqual(set(PHP_FILES), discovered,
                            'Classifique explicitamente novos entrypoints PHP na matriz de recusa.')

    def test_direct_entrypoints_refuse_before_config_or_docker(self):
        with tempfile.TemporaryDirectory(prefix='auth-guard-refusal-') as temp:
            root = Path(temp)
            auth = root / 'tests' / 'auth'
            auth.mkdir(parents=True)
            config = root / 'config'
            config.mkdir()
            # The sentinel must never be loaded, even when env markers are set.
            (config / 'config.php').write_text("<?php throw new Exception('CONFIG_ACCESSED');", encoding='utf-8')
            for name in (*PHP_FILES, *PS_FILES, 'sandbox-guard.php'):
                shutil.copyfile(HERE / name, auth / name)
            fakebin = root / 'bin'
            fakebin.mkdir()
            marker = root / 'docker-called'
            # Name lookup remains local to the disposable test copy.
            if os.name == 'nt':
                (fakebin / 'docker.cmd').write_text(f'@echo called > "{marker}"\r\n', encoding='utf-8')
            else:
                fake = fakebin / 'docker'
                fake.write_text(f'#!/bin/sh\necho called > "{marker}"\n', encoding='utf-8')
                fake.chmod(0o700)
            env = os.environ.copy()
            env.pop('APP_ENV', None)
            env.pop('AUTH_TEST_ISOLATION', None)
            env.pop('AUTH_TEST_ENV_FILE', None)
            env['PATH'] = str(fakebin) + os.pathsep + env.get('PATH', '')
            marked_env = env.copy()
            marked_env.update({'APP_ENV': 'testing', 'AUTH_TEST_ISOLATION': 'isolated-auth-test'})
            powershell = shutil.which('pwsh') or shutil.which('powershell')
            self.assertIsNotNone(powershell, 'PowerShell is required')
            for variant, process_env in [('default', env), ('markers', marked_env)]:
                for name in PHP_FILES:
                    with self.subTest(entrypoint=name, environment=variant):
                        result = subprocess.run(['php', str(auth / name), 'a', 'b', 'c'], env=process_env,
                                                text=True, capture_output=True, timeout=15)
                        self.assertNotEqual(result.returncode, 0)
                        self.assertIn(MESSAGE, result.stderr + result.stdout)
                        self.assertNotIn('CONFIG_ACCESSED', result.stderr + result.stdout)
                for name in PS_FILES:
                    with self.subTest(entrypoint=name, environment=variant):
                        args = [powershell, '-NoProfile', '-NonInteractive', '-File', str(auth / name)]
                        if name == 'run-isolated-mysql-php.ps1':
                            args += ['-TestScript', 'example.php', '-Files', 'example.php']
                        result = subprocess.run(args, env=process_env, text=True, capture_output=True, timeout=15)
                        self.assertNotEqual(result.returncode, 0)
                        self.assertIn(MESSAGE, result.stderr + result.stdout)
                        self.assertFalse(marker.exists(), 'docker must not be invoked')
                        self.assertFalse((auth / '.env.test.runtime').exists())


if __name__ == '__main__':
    unittest.main()
