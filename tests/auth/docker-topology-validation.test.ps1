$ErrorActionPreference = 'Stop'
. "$PSScriptRoot/docker-topology-validation.ps1"

function New-Inspection {
    param([hashtable]$Overrides = @{})
    $inspection = @{
        Id = 'a' * 64
        Image = 'sha256:' + ('b' * 64)
        Config = @{ Labels = @{ 'fokus.auth-e2e' = 'run-123' } }
        HostConfig = @{ Binds = @(); PortBindings = @{}; Privileged = $false; PublishAllPorts = $false; NetworkMode = 'fokus-auth-e2e-run-123' }
        NetworkSettings = @{ Networks = @{ 'fokus-auth-e2e-run-123' = @{ NetworkID = 'd' * 64; EndpointID = '1' * 64 } } }
        Mounts = @()
    }
    foreach ($key in $Overrides.Keys) { $inspection[$key] = $Overrides[$key] }
    return ($inspection | ConvertTo-Json -Depth 8 | ConvertFrom-Json)
}

function Assert-Throws([scriptblock]$Action, [string]$Case) {
    try { & $Action; throw "Esperava recusa: $Case" }
    catch { if ($_.Exception.Message -eq "Esperava recusa: $Case") { throw } }
}

$args = @{ ExpectedId = 'a' * 64; ExpectedImage = 'sha256:' + ('b' * 64); LabelKey = 'fokus.auth-e2e'; RunId = 'run-123'; Network = 'fokus-auth-e2e-run-123'; MySqlRole = $false }
Assert-AuthDockerTopology -Inspection (New-Inspection) @args

$badId = New-Inspection; $badId.Id = 'c' * 64
Assert-Throws { Assert-AuthDockerTopology -Inspection $badId @args } 'identidade'
$badImage = New-Inspection; $badImage.Image = 'sha256:' + ('c' * 64)
Assert-Throws { Assert-AuthDockerTopology -Inspection $badImage @args } 'imagem'
$badLabel = New-Inspection; $badLabel.Config.Labels.'fokus.auth-e2e' = 'other-run'
Assert-Throws { Assert-AuthDockerTopology -Inspection $badLabel @args } 'rótulo'
$badNetwork = New-Inspection; $badNetwork.NetworkSettings.Networks | Add-Member -NotePropertyName extra -NotePropertyValue @{}
Assert-Throws { Assert-AuthDockerTopology -Inspection $badNetwork @args } 'rede extra'
$missingNetwork = New-Inspection; $missingNetwork.NetworkSettings.Networks = @{}
Assert-Throws { Assert-AuthDockerTopology -Inspection $missingNetwork @args } 'rede ausente'
$publishedPort = New-Inspection; $publishedPort.HostConfig.PortBindings = [pscustomobject]@{ '80/tcp' = @(@{ HostPort = '8080' }) }
Assert-Throws { Assert-AuthDockerTopology -Inspection $publishedPort @args } 'porta publicada'
$publishAll = New-Inspection; $publishAll.HostConfig.PublishAllPorts = $true
Assert-Throws { Assert-AuthDockerTopology -Inspection $publishAll @args } 'publicação automática'
$wrongMode = New-Inspection; $wrongMode.HostConfig.NetworkMode = 'host'
Assert-Throws { Assert-AuthDockerTopology -Inspection $wrongMode @args } 'modo de rede'
$bind = New-Inspection; $bind.HostConfig.Binds = @('/host:/container')
Assert-Throws { Assert-AuthDockerTopology -Inspection $bind @args } 'bind mount'
$privileged = New-Inspection; $privileged.HostConfig.Privileged = $true
Assert-Throws { Assert-AuthDockerTopology -Inspection $privileged @args } 'privileged'
$missingPrivileged = New-Inspection; $missingPrivileged.HostConfig.PSObject.Properties.Remove('Privileged')
Assert-Throws { Assert-AuthDockerTopology -Inspection $missingPrivileged @args } 'privileged ausente'
$nullPrivileged = New-Inspection; $nullPrivileged.HostConfig.Privileged = $null
Assert-Throws { Assert-AuthDockerTopology -Inspection $nullPrivileged @args } 'privileged nulo'
$missingPorts = New-Inspection; $missingPorts.HostConfig.PSObject.Properties.Remove('PortBindings')
Assert-Throws { Assert-AuthDockerTopology -Inspection $missingPorts @args } 'portas ausentes'
$missingBinds = New-Inspection; $missingBinds.HostConfig.PSObject.Properties.Remove('Binds')
Assert-Throws { Assert-AuthDockerTopology -Inspection $missingBinds @args } 'binds ausentes'
$missingMounts = New-Inspection; $missingMounts.PSObject.Properties.Remove('Mounts')
Assert-Throws { Assert-AuthDockerTopology -Inspection $missingMounts @args } 'mounts ausentes'
$nullMounts = New-Inspection; $nullMounts.Mounts = $null
Assert-Throws { Assert-AuthDockerTopology -Inspection $nullMounts @args } 'mounts nulos'
$malformedPorts = New-Inspection; $malformedPorts.HostConfig.PortBindings = 'invalid'
Assert-Throws { Assert-AuthDockerTopology -Inspection $malformedPorts @args } 'portas malformadas'
$malformedBinds = New-Inspection; $malformedBinds.HostConfig.Binds = 0
Assert-Throws { Assert-AuthDockerTopology -Inspection $malformedBinds @args } 'binds malformados'
$mounted = New-Inspection; $mounted.Mounts = @(@{ Type = 'volume'; Destination = '/unexpected' })
Assert-Throws { Assert-AuthDockerTopology -Inspection $mounted @args } 'mount inesperado'

$mysql = New-Inspection -Overrides @{ Mounts = @(@{ Type = 'tmpfs'; Destination = '/var/lib/mysql' }) }
$mysqlArgs = $args.Clone(); $mysqlArgs.MySqlRole = $true
Assert-AuthDockerTopology -Inspection $mysql @mysqlArgs
$mysqlPersistent = New-Inspection -Overrides @{ Mounts = @(@{ Type = 'volume'; Destination = '/var/lib/mysql' }) }
Assert-Throws { Assert-AuthDockerTopology -Inspection $mysqlPersistent @mysqlArgs } 'MySQL persistente'

Write-Output 'PASS docker-topology-validation (22 cenários)'
$activeArgs = $args.Clone(); $activeArgs.ExpectedNetworkId = 'd' * 64; $activeArgs.ExpectedEndpointId = '1' * 64
Assert-AuthDockerTopology -Inspection (New-Inspection) @activeArgs
$bad = New-Inspection; $bad.NetworkSettings.Networks.'fokus-auth-e2e-run-123'.NetworkID = 'e' * 64
Assert-Throws { Assert-AuthDockerTopology -Inspection $bad @activeArgs } 'wrong NetworkID'
$bad = New-Inspection; $bad.NetworkSettings.Networks.'fokus-auth-e2e-run-123'.PSObject.Properties.Remove('NetworkID')
Assert-Throws { Assert-AuthDockerTopology -Inspection $bad @activeArgs } 'missing NetworkID'
$networkArgs = @{ ExpectedId = 'd' * 64; ExpectedName = 'fokus-auth-e2e-run-123'; LabelKey = 'fokus.auth-e2e'; RunId = 'run-123'; ExpectedMembers = @(('a' * 64), ('b' * 64)) }
function New-NetworkInspection {
    $members = @{}
    $members[('a' * 64)] = @{ EndpointID = '1' * 64 }
    $members[('b' * 64)] = @{ EndpointID = '2' * 64 }
    return (@{ Id = 'd' * 64; Name = 'fokus-auth-e2e-run-123'; Internal = $true; Labels = @{ 'fokus.auth-e2e' = 'run-123' }; Containers = $members } | ConvertTo-Json -Depth 8 | ConvertFrom-Json)
}
Assert-AuthDockerNetwork -Inspection (New-NetworkInspection) @networkArgs
foreach ($field in @('Id', 'Name', 'Internal', 'Labels', 'Containers')) {
    $bad = New-NetworkInspection; $bad.PSObject.Properties.Remove($field)
    Assert-Throws { Assert-AuthDockerNetwork -Inspection $bad @networkArgs } "missing $field"
}
foreach ($override in @(
    @{Id = 'e' * 64}, @{Name = 'other'}, @{Internal = $false}, @{Internal = 'true'},
    @{Labels = [pscustomobject]@{'fokus.auth-e2e' = 'other'}}, @{Containers = $null}, @{Containers = @()}, @{Containers = [pscustomobject]@{}}
)) {
    $bad = New-NetworkInspection
    foreach ($field in $override.Keys) { $bad.$field = $override[$field] }
    Assert-Throws { Assert-AuthDockerNetwork -Inspection $bad @networkArgs } 'malformed network'
}
$extra = New-NetworkInspection; $extra.Containers | Add-Member -NotePropertyName ('c' * 64) -NotePropertyValue ([pscustomobject]@{ EndpointID = '3' * 64 })
Assert-Throws { Assert-AuthDockerNetwork -Inspection $extra @networkArgs } 'extra member'
$replacement = New-NetworkInspection; $replacement.Containers.PSObject.Properties.Remove(('a' * 64)); $replacement.Containers | Add-Member -NotePropertyName ('c' * 64) -NotePropertyValue ([pscustomobject]@{ EndpointID = '3' * 64 })
Assert-Throws { Assert-AuthDockerNetwork -Inspection $replacement @networkArgs } 'replaced member'
$incomplete = New-NetworkInspection; $incomplete.Containers.('a' * 64).PSObject.Properties.Remove('EndpointID')
Assert-Throws { Assert-AuthDockerNetwork -Inspection $incomplete @networkArgs } 'missing endpoint'
$duplicateArgs = $networkArgs.Clone(); $duplicateArgs.ExpectedMembers = @(('a' * 64), ('a' * 64))
Assert-Throws { Assert-AuthDockerNetwork -Inspection (New-NetworkInspection) @duplicateArgs } 'duplicate members'
$emptyArgs = $networkArgs.Clone(); $emptyArgs.ExpectedMembers = @()
$empty = New-NetworkInspection; $empty.Containers = [pscustomobject]@{}
Assert-AuthDockerNetwork -Inspection $empty @emptyArgs
Write-Output 'PASS network identity and membership (22 additional synthetic cases)'

$bad = New-Inspection; $bad.NetworkSettings.Networks.'fokus-auth-e2e-run-123'.EndpointID = '9' * 64
Assert-Throws { Assert-AuthDockerTopology -Inspection $bad @activeArgs } 'endpoint mismatch across snapshots'
$bad = New-Inspection; $bad.NetworkSettings.Networks.'fokus-auth-e2e-run-123'.PSObject.Properties.Remove('EndpointID')
Assert-Throws { Assert-AuthDockerTopology -Inspection $bad @activeArgs } 'missing active endpoint'
$noEndpointArgs = $activeArgs.Clone(); $noEndpointArgs.Remove('ExpectedEndpointId')
Assert-Throws { Assert-AuthDockerTopology -Inspection (New-Inspection) @noEndpointArgs } 'missing expected endpoint'
$duplicateEndpoint = New-NetworkInspection; $duplicateEndpoint.Containers.('b' * 64).EndpointID = '1' * 64
Assert-Throws { Assert-AuthDockerNetwork -Inspection $duplicateEndpoint @networkArgs } 'duplicate endpoint identities'
$preStart = New-Inspection
$preStart.NetworkSettings.Networks.'fokus-auth-e2e-run-123'.NetworkID = ''
$preStart.NetworkSettings.Networks.'fokus-auth-e2e-run-123'.EndpointID = ''
Assert-AuthDockerTopology -Inspection $preStart @args
Write-Output 'PASS endpoint identity (5 additional synthetic cases; 49 total)'
