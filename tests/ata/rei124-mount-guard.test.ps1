$ErrorActionPreference = 'Stop'
. (Join-Path $PSScriptRoot 'rei124-mount-guard.ps1')
$bindPaths = @('/repo/app','/repo/config','/repo/public','/repo/tests/ata/rei124-ata-service-mysql.test.php','/repo/tests/ata/rei124-ata-http-mysql.test.php')
function Make-Info([bool]$listedTmpfs) {
    $mounts = @($bindPaths | ForEach-Object { [pscustomobject]@{Type='bind';Destination=$_;RW=$false} })
    if ($listedTmpfs) { $mounts += [pscustomobject]@{Type='tmpfs';Destination='/tmp';RW=$true}; $mounts += [pscustomobject]@{Type='tmpfs';Destination='/repo/storage';RW=$true} }
    return [pscustomobject]@{ Mounts=$mounts; HostConfig=[pscustomobject]@{ Tmpfs=[pscustomobject]@{'/tmp'='rw,size=128m,noexec,nosuid,nodev';'/repo/storage'='rw,size=64m,noexec,nosuid,nodev'} } }
}
function Denied($info) { try { Assert-Rei124AtaMounts $info } catch { return }; throw 'Topologia insegura aceita.' }
Assert-Rei124AtaMounts (Make-Info $false)
Assert-Rei124AtaMounts (Make-Info $true)
$x=Make-Info $true; $x.Mounts += [pscustomobject]@{Type='volume';Destination='/unexpected';RW=$true}; Denied $x
$x=Make-Info $true; $x.Mounts[0].RW=$true; Denied $x
$x=Make-Info $true; $x.Mounts += [pscustomobject]@{Type='tmpfs';Destination='/unexpected';RW=$true}; Denied $x
$x=Make-Info $true; $x.HostConfig.Tmpfs.PSObject.Properties.Remove('/repo/storage'); Denied $x
$x=Make-Info $true; $x.HostConfig.Tmpfs.'/tmp'='rw,size=128m'; Denied $x
$x=Make-Info $true; $x.Mounts += [pscustomobject]@{Type='tmpfs';Destination='/tmp';RW=$true}; Denied $x
Write-Output 'PASS 5/7 mounts e topologias adversariais'
