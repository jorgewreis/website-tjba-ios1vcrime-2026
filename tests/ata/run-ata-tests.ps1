[CmdletBinding()]
param()

$ErrorActionPreference = 'Stop'
$runtimePath = Join-Path $PSScriptRoot '../auth/.env.test.runtime'
if (-not (Test-Path -LiteralPath $runtimePath)) {
    throw 'Execute tests/auth/bootstrap.ps1 -Start antes dos testes de atas.'
}

$env:APP_ENV = 'testing'
$env:AUTH_TEST_ENV_FILE = (Resolve-Path $runtimePath).Path
try {
    foreach ($testFile in @('fixture-setup.test.php', 'ata-replacement.test.php', 'ata-replacement-concurrency.test.php')) {
        & php (Join-Path $PSScriptRoot $testFile)
        if ($LASTEXITCODE -ne 0) {
            throw "Falha em $testFile."
        }
    }
} finally {
    $storage = Join-Path $PSScriptRoot '.runtime-storage'
    if (Test-Path -LiteralPath $storage) {
        Remove-Item -LiteralPath $storage -Recurse -Force
    }
    $concurrencyStorage = Join-Path $PSScriptRoot '.runtime-storage-concurrency'
    if (Test-Path -LiteralPath $concurrencyStorage) {
        Remove-Item -LiteralPath $concurrencyStorage -Recurse -Force
    }
    Remove-Item -LiteralPath (Join-Path $PSScriptRoot '.ata-replacement-start.runtime') -Force -ErrorAction SilentlyContinue
    Remove-Item -LiteralPath (Join-Path $PSScriptRoot '.ata-replacement-continue.runtime') -Force -ErrorAction SilentlyContinue
}

Write-Output 'PASS ata-test-runner'
