function Remove-OwnedAuthContainer([string]$Context, [string]$Id, [string]$Label, [string]$RunId) {
    $old = $ErrorActionPreference
    $ErrorActionPreference = 'Continue'
    try {
        $raw = & docker --context $Context inspect $Id 2>$null
        if ($LASTEXITCODE -ne 0) { return $false }
        try { $item = @($raw | ConvertFrom-Json)[0] } catch { return $false }
        if ($null -eq $item -or [string]$item.Config.Labels.$Label -ne $RunId) { return $false }
        & docker --context $Context rm -f $Id 2>$null | Out-Null
        if ($LASTEXITCODE -ne 0) { return $false }
        $remaining = @(& docker --context $Context ps -aq --no-trunc --filter "id=$Id" 2>$null)
        return ($LASTEXITCODE -eq 0 -and @($remaining | Where-Object { $_ }).Count -eq 0)
    } finally { $ErrorActionPreference = $old }
}
function Remove-OwnedAuthNetwork([string]$Context, [string]$Name, [string]$Label, [string]$RunId) {
    $old = $ErrorActionPreference
    $ErrorActionPreference = 'Continue'
    try {
        $raw = & docker --context $Context network inspect $Name 2>$null
        if ($LASTEXITCODE -ne 0) { return $false }
        try { $item = @($raw | ConvertFrom-Json)[0] } catch { return $false }
        if ($null -eq $item -or [string]$item.Labels.$Label -ne $RunId -or @($item.Containers.PSObject.Properties).Count -ne 0) { return $false }
        & docker --context $Context network rm $Name 2>$null | Out-Null
        if ($LASTEXITCODE -ne 0) { return $false }
        $remaining = @(& docker --context $Context network ls -q --filter "id=$($item.Id)" 2>$null)
        return ($LASTEXITCODE -eq 0 -and @($remaining | Where-Object { $_ }).Count -eq 0)
    } finally { $ErrorActionPreference = $old }
}
function Recover-LostAuthCreate([string]$Context, [string]$Label, [string]$RunId, [string[]]$KnownIds) {
    # A resposta perdida deixa o resultado final incerto. Só remover um único
    # candidato adicional, cujo rótulo é novamente inspecionado antes do rm.
    $old = $ErrorActionPreference
    $ErrorActionPreference = 'Continue'
    try {
        $raw = @(& docker --context $Context ps -aq --no-trunc --filter "label=$Label=$RunId" 2>$null)
        if ($LASTEXITCODE -ne 0) { return $false }
        $ids = @($raw | Where-Object { $_ })
        if (@($ids | Where-Object { $_ -notmatch '^[a-f0-9]{64}$' }).Count -ne 0) { return $false }
        $candidates = @($ids | Where-Object { $_ -notin $KnownIds } | Select-Object -Unique)
        if ($candidates.Count -ne 1) { return $false }
        return (Remove-OwnedAuthContainer $Context $candidates[0] $Label $RunId)
    } finally { $ErrorActionPreference = $old }
}
