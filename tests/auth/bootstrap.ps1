[CmdletBinding()]
param()

# Contenção provisória: recursos ou variáveis não comprovam sandbox descartável.
throw 'Testes de autenticação suspensos: sandbox descartável não comprovado.'

$ErrorActionPreference = 'Stop'
$runner = Join-Path $PSScriptRoot 'run-isolated-auth-e2e.ps1'
if (-not (Test-Path -LiteralPath $runner)) {
    throw 'O executor isolado de autenticação não está disponível.'
}

& $runner
if ($LASTEXITCODE -ne 0) {
    throw 'A suíte E2E isolada de autenticação falhou.'
}
