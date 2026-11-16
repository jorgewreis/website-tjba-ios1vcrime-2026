[CmdletBinding()]
param()

# Contenção provisória: recursos ou variáveis não comprovam sandbox descartável.
throw 'Testes de autenticação suspensos: sandbox descartável não comprovado.'

$ErrorActionPreference = 'Stop'
$runtimePath = Join-Path $PSScriptRoot '.env.test.runtime'
if (-not (Test-Path -LiteralPath $runtimePath)) {
    throw 'Execute bootstrap.ps1 antes dos testes de autenticação.'
}

$env:APP_ENV = 'testing'
$env:AUTH_TEST_ENV_FILE = $runtimePath

foreach ($testFile in @(
    'config-environment-selection.test.php',
    'sandbox-connectivity.test.php',
    'fixture-setup.test.php',
    'auth-fixtures.test.php',
    'login-rate-limit.test.php',
    'reset-token-concurrency.test.php',
    'smtp-capture.test.php'
)) {
    & php (Join-Path $PSScriptRoot $testFile)
    if ($LASTEXITCODE -ne 0) {
        throw "Falha em $testFile."
    }
}

$httpTest = Start-Process -FilePath 'powershell' -ArgumentList @(
    '-NoProfile',
    '-ExecutionPolicy', 'Bypass',
    '-File', (Join-Path $PSScriptRoot 'http-auth-flow.test.ps1')
) -Wait -PassThru
if ($httpTest.ExitCode -ne 0) {
    throw 'Falha em http-auth-flow.test.ps1.'
}

& php (Join-Path $PSScriptRoot 'password-reset-states.test.php')
if ($LASTEXITCODE -ne 0) {
    throw 'Falha em password-reset-states.test.php.'
}

$mailpitMessages = Invoke-RestMethod -Uri 'http://127.0.0.1:38025/api/v1/messages' -Method Get
if ($null -eq $mailpitMessages.messages -or $mailpitMessages.messages.Count -lt 1) {
    throw 'O Mailpit não registrou a mensagem sintética.'
}

Write-Output 'PASS auth-test-runner'
