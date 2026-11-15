[CmdletBinding()]
param()

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
}

Write-Output 'Sandbox de autenticação removido.'
