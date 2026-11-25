function Assert-AuthDockerTopology {
    [CmdletBinding()]
    param(
        [Parameter(Mandatory)][object]$Inspection,
        [Parameter(Mandatory)][ValidatePattern('^[a-f0-9]{64}$')][string]$ExpectedId,
        [Parameter(Mandatory)][string]$ExpectedImage,
        [Parameter(Mandatory)][string]$LabelKey,
        [Parameter(Mandatory)][string]$RunId,
        [Parameter(Mandatory)][string]$Network,
        [Parameter(Mandatory)][bool]$MySqlRole,
        [string]$ExpectedNetworkId = '',
        [string]$ExpectedEndpointId = ''
    )

    # Missing fields cannot be interpreted as safe defaults. Docker may report
    # null for empty bindings, but the fields themselves must be present.
    foreach ($field in @('Id', 'Image', 'Config', 'HostConfig', 'NetworkSettings', 'Mounts')) {
        if ($null -eq $Inspection.PSObject.Properties[$field]) { throw "Inspect incompleto: $field" }
    }
    if ($null -eq $Inspection.Config -or $null -eq $Inspection.HostConfig -or $null -eq $Inspection.NetworkSettings) {
        throw 'Inspect incompleto: objeto obrigatório ausente.'
    }
    foreach ($field in @('Binds', 'PortBindings', 'Privileged', 'PublishAllPorts', 'NetworkMode')) {
        if ($null -eq $Inspection.HostConfig.PSObject.Properties[$field]) { throw "Inspect incompleto: HostConfig.$field" }
    }
    if ($Inspection.HostConfig.Privileged -isnot [bool]) { throw 'Inspect inválido: Privileged deve ser booleano.' }
    if ($Inspection.HostConfig.PublishAllPorts -isnot [bool]) { throw 'Inspect inválido: PublishAllPorts deve ser booleano.' }
    if ($null -ne $Inspection.HostConfig.Binds -and $Inspection.HostConfig.Binds -isnot [array]) { throw 'Inspect inválido: Binds deve ser uma lista ou null.' }
    if ($null -ne $Inspection.HostConfig.PortBindings -and $Inspection.HostConfig.PortBindings -isnot [pscustomobject]) { throw 'Inspect inválido: PortBindings deve ser um objeto ou null.' }
    if ($null -eq $Inspection.Mounts -or $Inspection.Mounts -isnot [array]) { throw 'Inspect inválido: Mounts deve ser uma lista.' }
    if ($null -eq $Inspection.NetworkSettings.PSObject.Properties['Networks'] -or $null -eq $Inspection.NetworkSettings.Networks) {
        throw 'Inspect incompleto: Networks.'
    }

    $issues = [Collections.Generic.List[string]]::new()
    if ([string]$Inspection.Id -ne $ExpectedId) { $issues.Add('identidade') }
    $label = $Inspection.Config.Labels.PSObject.Properties[$LabelKey]
    if ($null -eq $label -or [string]$label.Value -ne $RunId) { $issues.Add('rótulo') }
    if ([string]$Inspection.Image -ne $ExpectedImage) { $issues.Add('imagem') }

    $binds = @($Inspection.HostConfig.Binds | Where-Object { $null -ne $_ })
    if ($binds.Count -ne 0) { $issues.Add('bind') }
    $ports = @($Inspection.HostConfig.PortBindings.PSObject.Properties | Where-Object { $_.MemberType -eq 'NoteProperty' })
    if ($ports.Count -ne 0) { $issues.Add('porta') }
    if ($Inspection.HostConfig.PublishAllPorts) { $issues.Add('publicação automática de portas') }
    if ($Inspection.HostConfig.Privileged) { $issues.Add('privileged') }
    if ([string]$Inspection.HostConfig.NetworkMode -ne $Network) { $issues.Add('modo de rede') }

    $networks = @($Inspection.NetworkSettings.Networks.PSObject.Properties | Where-Object { $_.MemberType -eq 'NoteProperty' })
    if ($networks.Count -ne 1 -or $networks[0].Name -ne $Network) { $issues.Add('rede') }

    if ($ExpectedNetworkId -ne '') {
        if ($ExpectedNetworkId -cnotmatch '^[a-f0-9]{64}$' -or $networks.Count -ne 1 -or
            [string]$networks[0].Value.NetworkID -cne $ExpectedNetworkId) { $issues.Add('identidade da rede') }
        if ($ExpectedEndpointId -cnotmatch '^[a-f0-9]{64}$' -or $networks.Count -ne 1 -or
            [string]$networks[0].Value.EndpointID -cne $ExpectedEndpointId) { $issues.Add('identidade do endpoint') }
    }

    $mounts = @($Inspection.Mounts | Where-Object { $null -ne $_ })
    if ($MySqlRole) {
        if ($mounts.Count -ne 1 -or $mounts[0].Type -ne 'tmpfs' -or $mounts[0].Destination -ne '/var/lib/mysql') {
            $issues.Add('tmpfs MySQL')
        }
    } elseif ($mounts.Count -ne 0) {
        $issues.Add('mount')
    }

    if ($issues.Count -ne 0) {
        throw "Topologia recusada ($($issues -join ', ')): $ExpectedId"
    }
}


# Pure validation of a network snapshot; no daemon access or execution permission.
function Assert-AuthDockerNetwork {
    [CmdletBinding()]
    param(
        [Parameter(Mandatory)][object]$Inspection,
        [Parameter(Mandatory)][ValidatePattern('^[a-f0-9]{64}$')][string]$ExpectedId,
        [Parameter(Mandatory)][string]$ExpectedName,
        [Parameter(Mandatory)][string]$LabelKey,
        [Parameter(Mandatory)][string]$RunId,
        [Parameter(Mandatory)][AllowEmptyCollection()][string[]]$ExpectedMembers
    )
    foreach ($field in @('Id', 'Name', 'Internal', 'Labels', 'Containers')) {
        if ($null -eq $Inspection.PSObject.Properties[$field]) { throw "Rede incompleta: $field" }
    }
    if ([string]$Inspection.Id -cne $ExpectedId -or [string]$Inspection.Name -cne $ExpectedName -or
        $Inspection.Internal -isnot [bool] -or -not $Inspection.Internal -or
        $Inspection.Labels -isnot [pscustomobject] -or $Inspection.Containers -isnot [pscustomobject]) {
        throw 'Identidade ou estrutura da rede recusada.'
    }
    $labelValue = $Inspection.Labels.PSObject.Properties[$LabelKey]
    if ($null -eq $labelValue -or [string]$labelValue.Value -cne $RunId) { throw 'R�tulo da rede recusado.' }
    foreach ($member in $ExpectedMembers) {
        if ($member -cnotmatch '^[a-f0-9]{64}$') { throw 'ID esperado de membro inv�lido.' }
    }
    if (@($ExpectedMembers | Select-Object -Unique).Count -ne $ExpectedMembers.Count) { throw 'Membros esperados duplicados.' }
    $actualMembers = @($Inspection.Containers.PSObject.Properties | Where-Object MemberType -eq 'NoteProperty')
    if ($actualMembers.Count -ne $ExpectedMembers.Count) { throw 'Quantidade de membros da rede divergente.' }
    $endpointIds = [Collections.Generic.HashSet[string]]::new([StringComparer]::Ordinal)
    foreach ($member in $actualMembers) {
        if ($member.Name -cnotin $ExpectedMembers -or $member.Value -isnot [pscustomobject] -or
            [string]$member.Value.EndpointID -cnotmatch '^[a-f0-9]{64}$') { throw 'Membro da rede inesperado ou incompleto.' }
        if (-not $endpointIds.Add([string]$member.Value.EndpointID)) { throw 'EndpointID duplicado na rede.' }
    }
}
