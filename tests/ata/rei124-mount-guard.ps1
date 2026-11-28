function Assert-Rei124AtaMounts($Info) {
    $expectedBinds = @('/repo/app','/repo/config','/repo/public',
        '/repo/tests/ata/rei124-ata-service-mysql.test.php','/repo/tests/ata/rei124-ata-http-mysql.test.php',
        '/repo/tests/ata/rei124-ata-ui-fixture.php')
    $expectedTmpfs = @('/tmp','/repo/storage')
    $mounts = @($Info.Mounts | Where-Object { $null -ne $_ })
    $binds = @($mounts | Where-Object { $_.Type -eq 'bind' })
    if ($binds.Count -ne $expectedBinds.Count) { throw 'Quantidade de binds recusada.' }
    foreach ($destination in $expectedBinds) {
        if (@($binds | Where-Object { $_.Destination -eq $destination -and $_.RW -eq $false }).Count -ne 1) { throw 'Bind inesperado ou gravavel.' }
    }
    foreach ($mount in $mounts) {
        if ($mount.Type -eq 'bind') { continue }
        if ($mount.Type -ne 'tmpfs' -or $mount.Destination -notin $expectedTmpfs) { throw 'Mount inesperado.' }
        if (@($mounts | Where-Object { $_.Type -eq 'tmpfs' -and $_.Destination -eq $mount.Destination }).Count -ne 1) { throw 'Tmpfs duplicado.' }
    }
    $tmpfs = @($Info.HostConfig.Tmpfs.PSObject.Properties | Where-Object { $_.MemberType -eq 'NoteProperty' })
    if ($tmpfs.Count -ne $expectedTmpfs.Count) { throw 'Quantidade de tmpfs recusada.' }
    foreach ($destination in $expectedTmpfs) {
        $item = $Info.HostConfig.Tmpfs.PSObject.Properties[$destination]
        if ($null -eq $item) { throw 'Tmpfs obrigatorio ausente.' }
        $options = @(([string]$item.Value) -split ',')
        foreach ($option in @('rw','noexec','nosuid','nodev')) {
            if ($options -notcontains $option) { throw 'Opcoes de tmpfs incompletas.' }
        }
    }
}
