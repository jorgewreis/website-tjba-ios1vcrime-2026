[CmdletBinding()]
param()

# Contenção provisória: recursos ou variáveis não comprovam sandbox descartável.
throw 'Testes de autenticação suspensos: sandbox descartável não comprovado.'

$ErrorActionPreference = 'Stop'
$projectRoot = (Resolve-Path (Join-Path $PSScriptRoot '../..')).Path
$runtimePath = Join-Path $PSScriptRoot '.env.test.runtime'
$composeFile = Join-Path $projectRoot 'compose.auth-test.yml'
$composeProject = 'ios1vcrime-auth-test'

if (Test-Path -LiteralPath $runtimePath) {
    & docker compose --env-file $runtimePath -f $composeFile down --volumes --remove-orphans
    if ($LASTEXITCODE -ne 0) {
        throw 'A limpeza do sandbox de autenticação falhou.'
    }
    Remove-Item -LiteralPath $runtimePath -Force
} else {
    # O arquivo efêmero pode ter sido removido após uma execução interrompida.
    # Os rótulos do Compose permitem eliminar somente recursos deste sandbox.
    $containers = @(& docker ps -aq --filter "label=com.docker.compose.project=$composeProject") | Where-Object { $_ -ne '' }
    if ($containers.Count -gt 0) {
        & docker rm -f $containers
        if ($LASTEXITCODE -ne 0) {
            throw 'Não foi possível remover os containers residuais do sandbox de autenticação.'
        }
    }

    $volumes = @(& docker volume ls -q --filter "label=com.docker.compose.project=$composeProject") | Where-Object { $_ -ne '' }
    if ($volumes.Count -gt 0) {
        & docker volume rm $volumes
        if ($LASTEXITCODE -ne 0) {
            throw 'Não foi possível remover os volumes residuais do sandbox de autenticação.'
        }
    }

    $networks = @(& docker network ls -q --filter "label=com.docker.compose.project=$composeProject") | Where-Object { $_ -ne '' }
    if ($networks.Count -gt 0) {
        & docker network rm $networks
        if ($LASTEXITCODE -ne 0) {
            throw 'Não foi possível remover as redes residuais do sandbox de autenticação.'
        }
    }
}

foreach ($ataRuntimePath in @(
    (Join-Path $projectRoot 'tests/ata/.runtime-storage'),
    (Join-Path $projectRoot 'tests/ata/.runtime-storage-concurrency')
)) {
    if (Test-Path -LiteralPath $ataRuntimePath) {
        Remove-Item -LiteralPath $ataRuntimePath -Recurse -Force
    }
}
foreach ($ataBarrierPath in @(
    (Join-Path $projectRoot 'tests/ata/.ata-replacement-start.runtime'),
    (Join-Path $projectRoot 'tests/ata/.ata-replacement-continue.runtime')
)) {
    Remove-Item -LiteralPath $ataBarrierPath -Force -ErrorAction SilentlyContinue
}

Write-Output 'Sandbox de autenticação removido.'
