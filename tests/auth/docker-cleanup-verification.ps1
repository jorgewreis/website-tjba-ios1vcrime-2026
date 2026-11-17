function Test-DockerResourceAbsent {
    [CmdletBinding()]
    param(
        [Parameter(Mandatory)][ValidateSet('container','network')][string]$Kind,
        [Parameter(Mandatory)][string]$Identifier,
        [Parameter(Mandatory)][scriptblock]$Probe
    )

    $inspectArgs = if($Kind -eq 'container') { @('inspect',$Identifier) } else { @('network','inspect',$Identifier) }
    $inspect = & $Probe $inspectArgs
    if($null -eq $inspect -or $inspect.ExitCode -eq 0) { return $false }

    if($Kind -eq 'container') {
        $listArgs = @('ps','-a','--no-trunc','--filter',"id=$Identifier",'--format','{{.ID}}')
        $expected = $Identifier
    } else {
        $listArgs = @('network','ls','--no-trunc','--filter',"name=$Identifier",'--format','{{.Name}}')
        $expected = $Identifier
    }
    $listing = & $Probe $listArgs
    if($null -eq $listing -or $listing.ExitCode -ne 0) {
        throw "Não foi possível confirmar se o recurso Docker '$Identifier' ainda existe."
    }

    $items = @($listing.Output | ForEach-Object { ([string]$_).Trim() } | Where-Object { $_ -ne '' })
    foreach($item in $items) {
        $valid = if($Kind -eq 'container') { $item -match '^[a-f0-9]{64}$' } else { $item -match '^[A-Za-z0-9][A-Za-z0-9_.-]*$' }
        if(-not $valid) { throw "Resposta inesperada ao verificar o recurso Docker '$Identifier'." }
    }
    return -not ($items -contains $expected)
}
