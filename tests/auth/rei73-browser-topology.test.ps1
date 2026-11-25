$ErrorActionPreference = 'Stop'
. (Join-Path $PSScriptRoot 'docker-topology-validation.ps1')
$ids = @('a','b','c','d','e') | ForEach-Object { $_ * 64 }
$members = @{}
for ($i = 0; $i -lt 5; $i++) { $members[$ids[$i]] = @{ EndpointID = ([string]($i + 1) * 64) } }
function Inspection($items) {
    return (@{ Id = 'f' * 64; Name = 'fokus-auth-e2e-synthetic'; Internal = $true; Labels = @{ 'fokus.auth-e2e' = 'run-synthetic' }; Containers = $items } | ConvertTo-Json -Depth 8 | ConvertFrom-Json)
}
$networkParams = @{ ExpectedId = 'f' * 64; ExpectedName = 'fokus-auth-e2e-synthetic'; LabelKey = 'fokus.auth-e2e'; RunId = 'run-synthetic'; ExpectedMembers = $ids }
function MustReject([scriptblock]$block, [string]$case) { $rejected = $false; try { & $block } catch { $rejected = $true }; if (-not $rejected) { throw "Aceitou caso inseguro: $case" } }
Assert-AuthDockerNetwork -Inspection (Inspection $members) @networkParams
$missing = Inspection $members; $missing.Containers.PSObject.Properties.Remove($ids[4])
MustReject { Assert-AuthDockerNetwork -Inspection $missing @networkParams } 'navegador ausente'
$extra = Inspection $members; $extra.Containers | Add-Member -NotePropertyName ('0' * 64) -NotePropertyValue ([pscustomobject]@{EndpointID = '6' * 64})
MustReject { Assert-AuthDockerNetwork -Inspection $extra @networkParams } 'membro extra'
$replacement = Inspection $members; $replacement.Containers.PSObject.Properties.Remove($ids[4]); $replacement.Containers | Add-Member -NotePropertyName ('0' * 64) -NotePropertyValue ([pscustomobject]@{EndpointID = '6' * 64})
MustReject { Assert-AuthDockerNetwork -Inspection $replacement @networkParams } 'navegador substituído'
$duplicate = Inspection $members; $duplicate.Containers.($ids[4]).EndpointID = $duplicate.Containers.($ids[0]).EndpointID
MustReject { Assert-AuthDockerNetwork -Inspection $duplicate @networkParams } 'endpoint duplicado'
Write-Output 'PASS cinco membros: aceita exatos; recusa ausência, extra, substituição e endpoint duplicado'
