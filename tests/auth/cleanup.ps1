[CmdletBinding()]
param()

throw 'Limpeza manual suspensa: propriedade e isolamento do sandbox descartável não comprovados.'

$ErrorActionPreference = 'Stop'
$label = 'fokus.auth-e2e'

function Confirm-RunLabel([object]$labels, [string]$resource) {
    $value = [string]$labels.$label
    if ($value -notmatch '^[a-f0-9]{32}$') {
        throw "Recurso não reconhecido; limpeza recusada: $resource"
    }
}

$containers = @(& docker ps -aq --filter "label=$label") | Where-Object { $_ -match '^[a-f0-9]{64}$' }
foreach ($container in $containers) {
    $detail = @(& docker inspect $container | ConvertFrom-Json)[0]
    Confirm-RunLabel $detail.Config.Labels $container
    & docker rm -f $container | Out-Null
    if ($LASTEXITCODE -ne 0) { throw "Falha ao remover contêiner E2E: $container" }
}

$networks = @(& docker network ls -q --filter "label=$label") | Where-Object { $_ -ne '' }
foreach ($network in $networks) {
    $detail = @(& docker network inspect $network | ConvertFrom-Json)[0]
    Confirm-RunLabel $detail.Labels $network
    & docker network rm $network | Out-Null
    if ($LASTEXITCODE -ne 0) { throw "Falha ao remover rede E2E: $network" }
}

Write-Output 'PASS auth-e2e-recovery-cleanup'
