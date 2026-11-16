"""Exercita instaladores em cópias, com crontab substituído por dublês locais."""
import os
from pathlib import Path
import shutil
import subprocess
import tempfile

ROOT = Path(__file__).resolve().parents[2]
WINDOWS = os.name == 'nt'
BASH = (os.environ.get('GIT_BASH') or next(
    (str(candidate) for candidate in (
        Path(r'D:\Git\bin\bash.exe'),
        Path(os.environ.get('ProgramFiles', r'C:\Program Files')) / 'Git' / 'bin' / 'bash.exe',
    ) if candidate.is_file()), None)) if WINDOWS else shutil.which('bash')
PHP = shutil.which('php')
assert PHP and BASH and Path(BASH).is_file(), 'PHP e Bash são necessários.'

with tempfile.TemporaryDirectory(prefix='cron138-') as directory:
    base = Path(directory)
    scripts = base / 'scripts'
    scripts.mkdir()
    (base / 'config').mkdir()
    (base / 'config' / 'config.php').write_text('<?php\n')
    for name in ('install-cron-gabinete-reus-presos-revisao-email.sh',
                 'install-cron-gabinete-reus-presos-revisao-email.php',
                 'install-cron-inactive-external-users.sh',
                 'resolve_project_root_cli.php', 'send-gabinete-reus-presos-revisao-email.php'):
        shutil.copyfile(ROOT / 'scripts' / name, scripts / name)
    fake = base / 'fake'
    fake.mkdir()
    # Ambos os executáveis registram escritas; nenhum chama o crontab do host.
    (fake / 'crontab').write_text('''#!/bin/sh
if [ "$1" = -l ]; then
  case "$CRON_MODE" in
    existing|existing_nolf|collision|managed_inactive) cat "$CRON_EXISTING"; exit 0 ;;
    missing) echo "no crontab for fixture" >&2; exit 1 ;;
    partial) echo "5 4 * * * /existing"; echo "no crontab for fixture" >&2; exit 1 ;;
    wrongstatus) echo "no crontab for fixture" >&2; exit 2 ;;
    error) echo "permission denied" >&2; exit 1 ;;
    misleading) echo "no crontab for fixture" >&2; echo "permission denied" >&2; exit 1 ;;
  esac
fi
cp "$1" "$CRON_WRITTEN"
''', encoding='utf-8', newline='\n')
    (fake / 'crontab').chmod(0o755)
    (fake / 'php').write_text('#!/bin/sh\nexit 0\n', encoding='utf-8')
    (fake / 'php').chmod(0o755)
    (fake / 'crontab.cmd').write_text('''@echo off
if not "%~1"=="-l" goto write
if "%CRON_MODE%"=="existing" (type "%CRON_EXISTING%" & exit /b 0)
if "%CRON_MODE%"=="existing_nolf" (type "%CRON_EXISTING%" & exit /b 0)
if "%CRON_MODE%"=="collision" (type "%CRON_EXISTING%" & exit /b 0)
if "%CRON_MODE%"=="managed_inactive" (type "%CRON_EXISTING%" & exit /b 0)
if "%CRON_MODE%"=="missing" (echo no crontab for fixture 1>&2 & exit /b 1)
if "%CRON_MODE%"=="partial" (echo 5 4 * * * /existing & echo no crontab for fixture 1>&2 & exit /b 1)
if "%CRON_MODE%"=="wrongstatus" (echo no crontab for fixture 1>&2 & exit /b 2)
if "%CRON_MODE%"=="misleading" (echo no crontab for fixture 1>&2 & echo permission denied 1>&2 & exit /b 1)
echo permission denied 1>&2
exit /b 1
:write
copy /y "%~1" "%CRON_WRITTEN%" >nul
exit /b %errorlevel%
''', encoding='utf-8', newline='\r\n')
    existing = base / 'existing.txt'
    existing.write_text('MAILTO=ops@example.invalid\n\n5 4 * * * /existing\n0 18 * * 4 cd /old && php /old/send-gabinete-reus-presos-revisao-email.php >> /old/log 2>&1 #tjba-reus-presos-revisao-email\n', encoding='utf-8')
    existing_nolf = base / 'existing-nolf.txt'
    existing_nolf.write_bytes(b'5 4 * * * /existing')
    collision = base / 'collision.txt'
    collision.write_text('5 4 * * * /existing #tjba-reus-presos-revisao-email\n6 4 * * * /other # ios1vcrime-inactive-external-users\n', encoding='utf-8')
    managed_inactive = base / 'managed-inactive.txt'
    managed_inactive.write_text('0 3 * * * cd "/old" && "/php" "/old/scripts/purge-inactive-external-users.php" >> "/old/storage/logs/inactive-external-users.log" 2>&1 # ios1vcrime-inactive-external-users\n', encoding='utf-8')
    for kind in ('shell', 'php', 'inactive'):
        for mode in ('existing', 'existing_nolf', 'collision', 'missing', 'error', 'misleading', 'partial', 'wrongstatus'):
            written = base / (kind + '-' + mode + '.txt')
            env = os.environ.copy()
            command_path = (str(Path(BASH).parent.parent / 'usr' / 'bin') + os.pathsep + os.environ.get('SystemRoot', r'C:\Windows') + r'\System32') if WINDOWS else '/usr/bin:/bin'
            fixture = existing_nolf if mode == 'existing_nolf' else collision if mode == 'collision' else existing
            env.update(CRON_MODE=mode, CRON_EXISTING=str(fixture), CRON_WRITTEN=str(written),
                       PATH=str(fake) + os.pathsep + command_path,
                       PHP_BIN='/bin/true')
            if kind in ('shell', 'inactive'):
                # Paths passed to the Git Bash dublê must use its POSIX namespace.
                if WINDOWS:
                    for key in ('CRON_EXISTING', 'CRON_WRITTEN'):
                        env[key] = subprocess.check_output([BASH, '-c', 'cygpath -u "$1"', '_', env[key]], text=True).strip()
                script = 'install-cron-inactive-external-users.sh' if kind == 'inactive' else 'install-cron-gabinete-reus-presos-revisao-email.sh'
                command = [BASH, str(scripts / script)]
            else:
                command = [PHP, str(scripts / 'install-cron-gabinete-reus-presos-revisao-email.php')]
            result = subprocess.run(command, env=env, capture_output=True, text=True)
            if mode in ('error', 'misleading', 'partial', 'wrongstatus') or (mode == 'collision' and kind == 'inactive'):
                assert result.returncode != 0 and not written.exists(), (kind, mode, result.stdout, result.stderr)
            else:
                assert result.returncode == 0 and written.exists(), (kind, mode, result.stdout, result.stderr)
                content = written.read_text(encoding='utf-8')
                marker = '# ios1vcrime-inactive-external-users' if kind == 'inactive' else '#tjba-reus-presos-revisao-email'
                assert content.count(marker) == (2 if mode == 'collision' else 1)
                if mode == 'existing':
                    assert 'MAILTO=ops@example.invalid\n\n5 4 * * * /existing' in content
                    assert ('/old/send-gabinete-reus-presos-revisao-email.php >> /old/log 2>&1 #tjba-reus-presos-revisao-email' in content) == (kind == 'inactive')
                if mode == 'existing_nolf':
                    assert content.startswith('5 4 * * * /existing\n0 '), (kind, repr(content))
                if mode == 'collision':
                    assert content.startswith(collision.read_text(encoding='utf-8'))
            print('PASS', kind, mode)
    env = os.environ.copy()
    env.update(CRON_MODE='managed_inactive', CRON_EXISTING=str(managed_inactive), CRON_WRITTEN=str(base / 'unexpected.txt'),
               PATH=str(fake) + os.pathsep + command_path)
    if WINDOWS:
        env['CRON_EXISTING'] = subprocess.check_output([BASH, '-c', 'cygpath -u "$1"', '_', env['CRON_EXISTING']], text=True).strip()
        env['CRON_WRITTEN'] = subprocess.check_output([BASH, '-c', 'cygpath -u "$1"', '_', env['CRON_WRITTEN']], text=True).strip()
    result = subprocess.run([BASH, str(scripts / 'install-cron-inactive-external-users.sh')], env=env, capture_output=True, text=True)
    assert result.returncode == 0 and (base / 'unexpected.txt').exists(), (result.stdout, result.stderr)
    assert '/old/scripts/purge-inactive-external-users.php' not in (base / 'unexpected.txt').read_text(encoding='utf-8')
    print('PASS inactive managed reconciliation')
