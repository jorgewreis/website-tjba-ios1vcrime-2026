[CmdletBinding()]
param()

# This is an explicit preparation step outside the isolated test network.
# It exposes only manifests read-only and vendor/ as a writable target.
$ErrorActionPreference = 'Stop'
$root = (Resolve-Path (Join-Path $PSScriptRoot '../..')).Path
$image = 'fokus-law-linux-qa@sha256:459cc8bd25b38009a8cf9a0ef5399d131f4f2001a1376d89389f673a92de430b'
$composerJson = Join-Path $root 'composer.json'
$composerLock = Join-Path $root 'composer.lock'
$vendor = Join-Path $root 'vendor'

foreach ($file in @($composerJson, $composerLock)) {
    if (-not (Test-Path -LiteralPath $file)) {
        throw "Arquivo exigido ausente: $file"
    }
}
if (-not (Test-Path -LiteralPath $vendor)) { New-Item -ItemType Directory -Path $vendor | Out-Null }
if ((Get-Item -LiteralPath $vendor -Force).Attributes -band [IO.FileAttributes]::ReparsePoint) {
    throw "Diretório vendor inseguro: $vendor"
}

& docker image inspect $image 2>$null | Out-Null
if ($LASTEXITCODE -ne 0) { throw "Imagem de tooling ausente: $image" }

& docker run --rm --network bridge `
    --mount "type=bind,source=$composerJson,target=/workspace/composer.json,readonly" `
    --mount "type=bind,source=$composerLock,target=/workspace/composer.lock,readonly" `
    --mount "type=bind,source=$vendor,target=/workspace/vendor" `
    --workdir /workspace $image composer install --no-dev --no-scripts --no-plugins --prefer-dist --no-interaction --no-progress --optimize-autoloader
if ($LASTEXITCODE -ne 0) { throw 'composer install falhou.' }

if (-not (Test-Path -LiteralPath (Join-Path $root 'vendor/autoload.php'))) {
    throw 'composer install terminou sem vendor/autoload.php.'
}

Write-Output 'PASS auth-e2e-dependencies'
