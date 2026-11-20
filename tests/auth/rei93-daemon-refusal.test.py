from pathlib import Path
import os, shutil, subprocess, tempfile, unittest
HERE = Path(__file__).resolve().parent
class DaemonRefusal(unittest.TestCase):
    def test_remote_context_and_override_refused_before_resources(self):
        with tempfile.TemporaryDirectory(prefix='rei93-daemon-') as temp:
            root = Path(temp); auth = root / 'tests' / 'auth'; auth.mkdir(parents=True)
            shutil.copyfile(HERE / 'run-isolated-auth-e2e.ps1', auth / 'run-isolated-auth-e2e.ps1')
            fake = root / 'bin'; fake.mkdir(); log = root / 'docker.log'
            if os.name == 'nt':
                (fake / 'docker.cmd').write_text('@echo off\r\necho %*>>"%REI93_FAKE_LOG%"\r\nif "%1 %2"=="context show" (echo remote-test& exit /b 0)\r\nif "%1 %2"=="context inspect" (echo [{"Name":"remote-test","Endpoints":{"docker":{"Host":"tcp://192.0.2.1:2376"}}}]& exit /b 0)\r\nexit /b 2\r\n', encoding='utf8')
            else:
                f = fake / 'docker'; f.write_text('#!/bin/sh\necho "$*" >> "$REI93_FAKE_LOG"\ncase "$1 $2" in "context show") echo remote-test;; "context inspect") echo \'[{"Name":"remote-test","Endpoints":{"docker":{"Host":"tcp://192.0.2.1:2376"}}}]\';; *) exit 2;; esac\n', encoding='utf8'); f.chmod(0o700)
            env = os.environ.copy(); env.pop('DOCKER_HOST', None); env.pop('DOCKER_CONTEXT', None)
            env['PATH'] = str(fake) + os.pathsep + env.get('PATH', ''); env['REI93_FAKE_LOG'] = str(log)
            pwsh = shutil.which('pwsh') or shutil.which('powershell'); self.assertIsNotNone(pwsh)
            cmd = [pwsh, '-NoProfile', '-NonInteractive', '-File', str(auth / 'run-isolated-auth-e2e.ps1')]
            for override in ['DOCKER_HOST', 'DOCKER_CONTEXT', None]:
                log.unlink(missing_ok=True); runenv = env.copy()
                if override: runenv[override] = 'tcp://192.0.2.1:2376'
                result = subprocess.run(cmd, env=runenv, text=True, capture_output=True, timeout=20)
                self.assertNotEqual(result.returncode, 0)
                self.assertIn('recusad', (result.stdout + result.stderr).lower())
                calls = log.read_text(encoding='utf8').splitlines() if log.exists() else []
                self.assertTrue(all(x.startswith('context ') for x in calls), calls)
                if override: self.assertEqual([], calls)
if __name__ == '__main__': unittest.main()
