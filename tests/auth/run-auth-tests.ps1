[CmdletBinding()]
param()

$ErrorActionPreference = 'Stop'
$runner = Join-Path $PSScriptRoot 'run-isolated-auth-e2e.ps1'
if (-not (Test-Path -LiteralPath $runner)) {
    throw 'O executor isolado de autenticação não está disponível.'
}

& $runner
if ($LASTEXITCODE -ne 0) {
    throw 'Falha em run-isolated-auth-e2e.ps1.'
}
