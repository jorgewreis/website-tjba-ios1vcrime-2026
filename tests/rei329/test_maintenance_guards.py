from pathlib import Path
import os, re, shutil, subprocess, tempfile, unittest
ROOT = Path(__file__).resolve().parents[2]
SCRIPTS = tuple(p.name for p in sorted((ROOT / 'scripts').glob('migrate-*.php'))) + ('drop-enderecos-partes-instituicoes-tables.php', 'drop-medidas-protetivas-tables.php')
GUARD = "declare(strict_types=1);\n\nif (PHP_SAPI !== 'cli') {\n    http_response_code(403);\n    exit;\n}"
OTHER_SCRIPTS = (
    'fix-registry.php', 'gen-seed-sql.php', 'patch-apis-mirror.php',
    'patch-guias-legacy-cartorio.php', 'patch-guias-legacy.php',
    'patch-guias-legacy2.php', 'patch-guias-service.php', 'patch-guias2.php',
    'patch-helper-session.php', 'patch-lancamentos-repo.php', 'patch-service-final.php',
    'reconciliar-prioridades-processuais.php', 'send-gabinete-reus-presos-revisao-email.php',
)
OTHER_GUARD = re.compile(r"\A<\?php\s*(?:declare\(strict_types=1\);\s*)?if \(PHP_SAPI !== 'cli'\) \{\s*http_response_code\(404\);\s*exit;\s*\}")
class MaintenanceGuardTest(unittest.TestCase):
    def test_other_mutating_scripts_guard_first(self):
        for name in OTHER_SCRIPTS:
            with self.subTest(name=name):
                source = (ROOT / 'scripts' / name).read_text(encoding='utf-8')
                self.assertRegex(source, OTHER_GUARD)

    def test_guard_first(self):
        for name in SCRIPTS:
            with self.subTest(name=name):
                source = (ROOT / 'scripts' / name).read_text(encoding='utf-8')
                self.assertIn(GUARD, source)
                prefix = source.split(GUARD, 1)[0]
                self.assertNotIn('require', prefix)
                self.assertNotIn('include', prefix)
                self.assertNotIn('$', prefix)
    def test_cgi_rejection_and_cli_stub(self):
        php, cgi = shutil.which('php'), shutil.which('php-cgi')
        self.assertTrue(php, 'php CLI is required')
        self.assertTrue(cgi, 'php-cgi is required')
        # Editais scripts are checked statically only; no replay.
        with tempfile.TemporaryDirectory(prefix='rei329-guard-') as tmp:
            root = Path(tmp)
            (root / 'scripts').mkdir()
            helper = root / 'app' / 'helpers'
            helper.mkdir(parents=True)
            marker = root / 'connection-invoked'
            (helper / 'Conection.php').write_text("<?php file_put_contents(dirname(__DIR__, 2) . '/connection-invoked', 'stub'); exit(71);\n", encoding='utf-8')
            for name in (n for n in SCRIPTS if not n.startswith('migrate-editais-')):
                with self.subTest(name=name):
                    script = root / 'scripts' / name
                    shutil.copyfile(ROOT / 'scripts' / name, script)
                    env = {k: v for k, v in os.environ.items() if k in ('SystemRoot','WINDIR','PATH','TEMP','TMP')}
                    env.update({'GATEWAY_INTERFACE':'CGI/1.1','REQUEST_METHOD':'GET','SCRIPT_FILENAME':str(script),'REDIRECT_STATUS':'1','SERVER_PROTOCOL':'HTTP/1.1'})
                    result = subprocess.run([cgi], env=env, capture_output=True, timeout=10)
                    self.assertIn(b'Status: 403', result.stdout)
                    self.assertFalse(marker.exists(), 'HTTP reached connection stub')
                    result = subprocess.run([php, str(script)], env=env, capture_output=True, timeout=10)
                    self.assertEqual(result.returncode, 71, result.stderr)
                    self.assertEqual(marker.read_text(), 'stub')
                    marker.unlink()
if __name__ == '__main__': unittest.main()
