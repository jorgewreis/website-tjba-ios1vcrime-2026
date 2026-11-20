[CmdletBinding()]
param(
    [switch]$Start
)

$ErrorActionPreference = 'Stop'
$runner = Join-Path $PSScriptRoot 'run-isolated-auth-e2e.ps1'
if (-not (Test-Path -LiteralPath $runner)) {
    throw 'O executor isolado de autenticação não está disponível.'
}

if ($Start) {
    Write-Warning 'O parâmetro -Start está obsoleto; o runner isolado cria e remove seus próprios recursos.'
}

& $runner
if ($LASTEXITCODE -ne 0) {
    throw 'A suíte E2E isolada de autenticação falhou.'
}
