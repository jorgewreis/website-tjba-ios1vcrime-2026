[CmdletBinding()]
param()

# This is a one-time, explicit host preparation step. The isolated test
# containers never receive a bind mount or external Docker network access.
$ErrorActionPreference = 'Stop'
$root = (Resolve-Path (Join-Path $PSScriptRoot '../..')).Path
$image = 'fokus-law-linux-qa:20261107-php857-node2413'

foreach ($file in @('composer.json', 'composer.lock')) {
    if (-not (Test-Path -LiteralPath (Join-Path $root $file))) {
        throw "Arquivo exigido ausente: $file"
    }
}

& docker image inspect $image 2>$null | Out-Null
if ($LASTEXITCODE -ne 0) { throw "Imagem de tooling ausente: $image" }

& docker run --rm --mount "type=bind,source=$root,target=/workspace" --workdir /workspace $image composer install --no-dev --prefer-dist --no-interaction --no-progress --optimize-autoloader
if ($LASTEXITCODE -ne 0) { throw 'composer install falhou.' }

if (-not (Test-Path -LiteralPath (Join-Path $root 'vendor/autoload.php'))) {
    throw 'composer install terminou sem vendor/autoload.php.'
}

Write-Output 'PASS auth-e2e-dependencies'
