"""Exercise the real versioning script in disposable directories only."""
from pathlib import Path
import shutil
import subprocess
import tempfile

SOURCE = Path(__file__).resolve().parents[2] / 'scripts' / 'bump-version.ps1'
SHELLS = [name for name in ('pwsh', 'powershell') if shutil.which(name)]
assert SHELLS, 'PowerShell not available'


def invoke(shell, root, bump):
    return subprocess.run(
        [shell, '-NoProfile', '-NonInteractive', '-ExecutionPolicy', 'Bypass',
         '-File', str(root / 'bump-version.ps1'), '-Bump', bump,
         '-VersionFile', str(root / 'VERSION'), '-ChangelogPath', str(root / 'CHANGELOG.md'),
         '-CommitMessage', 'test: version boundary'],
        cwd=root, stdout=subprocess.PIPE, stderr=subprocess.PIPE,
    )


def read(path):
    return path.read_text(encoding='utf-8-sig').strip()


for shell in SHELLS:
    with tempfile.TemporaryDirectory(prefix='bump-version-test-') as tmp:
        root = Path(tmp)
        shutil.copyfile(SOURCE, root / 'bump-version.ps1')
        version, changelog = root / 'VERSION', root / 'CHANGELOG.md'
        for initial, bump, expected in [
            ('V. 1.99', 'minor', 'V. 1.100'),
            ('V. 1.100', 'minor', 'V. 1.101'),
            ('V. 1.02.9999', 'patch', 'V. 1.02.10000'),
            ('V. 1.02.10000', 'patch', 'V. 1.02.10001'),
            ('V. 99.100.10000', 'major', 'V. 100.0'),
            ('v1.100.10000', 'patch', 'V. 1.100.10001'),
        ]:
            version.write_text(initial, encoding='utf-8')
            changelog.write_text('# Changelog\n', encoding='utf-8')
            result = invoke(shell, root, bump)
            assert result.returncode == 0, (shell, initial, result.stdout, result.stderr)
            assert read(version) == expected, (shell, initial, read(version))
            assert '## ' + expected in read(changelog)
        for initial, bump in [
            ('not a version', 'patch'), ('', 'minor'), ('1.2.3.4', 'patch'),
            ('1.-2.0', 'major'), ('1.2147483648', 'minor'),
            ('2147483647.0', 'major'), ('1.2147483647', 'minor'),
            ('1.2.2147483647', 'patch'), ('2.9999999999999999999999.0', 'patch'),
        ]:
            version.write_text(initial, encoding='utf-8')
            changelog.write_bytes(b'unchanged changelog\n')
            version_backup = root / 'VERSION.bak'
            changelog_backup = root / 'CHANGELOG.md.bak'
            version_backup.write_bytes(b'previous version backup')
            changelog_backup.write_bytes(b'previous changelog backup')
            originals = {p: p.read_bytes() for p in (version, changelog, version_backup, changelog_backup)}
            result = invoke(shell, root, bump)
            assert result.returncode != 0, (shell, initial, result.stdout, result.stderr)
            assert all(p.read_bytes() == content for p, content in originals.items()), (shell, initial)
        version.unlink()
        originals = {p: p.read_bytes() for p in (changelog, version_backup, changelog_backup)}
        result = invoke(shell, root, 'patch')
        assert result.returncode != 0 and not version.exists(), shell
        assert all(p.read_bytes() == content for p, content in originals.items()), shell
    print(f'PASS {shell}: boundaries, invalid versions, overflow and unchanged files')
