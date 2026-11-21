[CmdletBinding()]
param()

$ErrorActionPreference = 'Stop'
$repoRoot = (Resolve-Path (Join-Path $PSScriptRoot '../..')).Path
$appRoot = Join-Path $repoRoot 'app'
$securityConfig = Join-Path $repoRoot 'config/security.php'
$testRoot = $PSScriptRoot
$testFile = Join-Path $testRoot 'atomicidade-transicoes-mysql.test.php'
$mysqlImageRef = 'mysql@sha256:a81d043930e51745522bd5c430961cf8286f0a23f0a9a2a6e0108f7ea71b0902'
$phpImageRef = 'fokus-law-linux-qa@sha256:459cc8bd25b38009a8cf9a0ef5399d131f4f2001a1376d89389f673a92de430b'

if (Test-Path -LiteralPath (Join-Path $repoRoot '.env')) { throw 'Runner recusado: checkout contém .env.' }
foreach ($path in @($appRoot, $testRoot, $testFile)) {
    if ((Get-Item -LiteralPath $path -Force).Attributes -band [IO.FileAttributes]::ReparsePoint) {
        throw 'Runner recusado: origem contém reparse point.'
    }
}
foreach ($root in @($appRoot, $testRoot)) {
    if (Get-ChildItem -LiteralPath $root -Recurse -Force | Where-Object { $_.Attributes -band [IO.FileAttributes]::ReparsePoint }) {
        throw 'Runner recusado: origem contém reparse point descendente.'
    }
}
if (Get-ChildItem -LiteralPath $appRoot -Recurse -Force | Where-Object { $_.Name -like '.env*' }) {
    throw 'Runner recusado: app contém arquivo de ambiente.'
}

$contextName = (& docker context show 2>$null | Select-Object -Last 1).Trim()
if ($LASTEXITCODE -ne 0 -or $contextName -ne 'desktop-linux') { throw 'Runner exige contexto local desktop-linux.' }
$dockerEndpoint = 'npipe:////./pipe/dockerDesktopLinuxEngine'
$contextInfo = @((& docker context inspect $contextName 2>$null | ConvertFrom-Json))[0]
if ($LASTEXITCODE -ne 0 -or $contextInfo.Endpoints.docker.Host -ne $dockerEndpoint) { throw 'Runner exige endpoint local Docker Desktop.' }

$runId = [Guid]::NewGuid().ToString('N').ToLowerInvariant()
$marker = ([Guid]::NewGuid().ToString('N') + [Guid]::NewGuid().ToString('N')).ToLowerInvariant()
$label = 'com.fokus.rei120-atomicity-test'
$dbName = "fokus-rei120-mysql-$runId"
$phpName = "fokus-rei120-php-$runId"
$socketVolume = "fokus-rei120-socket-$runId"
$mysqlId = $null
$phpId = $null
$volumeCreated = $false
$sentinelAttempted = $false
$sentinelCreated = $false
$failure = $null
$cleanupFailed = $false
$tempConfigRoot = Join-Path ([IO.Path]::GetTempPath()) ("rei120-config-" + [Guid]::NewGuid().ToString('N'))
$tempSecurityConfig = Join-Path $tempConfigRoot 'security.php'

function Invoke-DockerSafe([string[]]$argsList) {
    $output = & docker --host $dockerEndpoint @argsList 2>&1
    if ($LASTEXITCODE -ne 0) { throw "Docker falhou em '$($argsList[0])' (código $LASTEXITCODE): $($output -join ' ')" }
    return $output
}

function Get-OwnedContainer([string]$id) {
    if ($id -notmatch '^[a-f0-9]{64}$') { throw 'ID de container inválido.' }
    $item = @((Invoke-DockerSafe @('inspect', $id)) | ConvertFrom-Json)[0]
    if (-not $item -or $item.Config.Labels.$label -ne $runId) { throw 'Container não pertence a esta execução.' }
    return $item
}

function Remove-OwnedContainer([string]$id) {
    if (-not $id) { return }
    try {
        $item = Get-OwnedContainer $id
        if ($item.State.Running) { Invoke-DockerSafe @('stop', $id) | Out-Null }
        Invoke-DockerSafe @('rm', $id) | Out-Null
        if (@(Invoke-DockerSafe @('ps', '-aq', '--no-trunc', '--filter', "id=$id") | Where-Object { $_ }).Count -ne 0) {
            throw 'Container permaneceu após remoção.'
        }
    } catch {
        $script:cleanupFailed = $true
        Write-Warning "Container não removido sem confirmar identidade/estado ($id)."
    }
}

try {
    $mysqlImage = @((Invoke-DockerSafe @('image', 'inspect', $mysqlImageRef)) | ConvertFrom-Json)[0]
    $phpImage = @((Invoke-DockerSafe @('image', 'inspect', $phpImageRef)) | ConvertFrom-Json)[0]
    $imagesInvalid = -not $mysqlImage.Id -or (@($mysqlImage.RepoDigests) -notcontains $mysqlImageRef) -or -not $phpImage.Id -or (@($phpImage.RepoDigests) -notcontains $phpImageRef)
    if ($imagesInvalid) {
        throw 'Runner recusado: imagens versionadas pelo digest não estão disponíveis localmente.'
    }

    Invoke-DockerSafe @('volume', 'create', '--label', "$label=$runId", $socketVolume) | Out-Null
    $volumeCreated = $true
    $volume = @((Invoke-DockerSafe @('volume', 'inspect', $socketVolume)) | ConvertFrom-Json)[0]
    if ($volume.Labels.$label -ne $runId) { throw 'Socket volume não pertence a esta execução.' }

    $mysqlId = (Invoke-DockerSafe @(
        'create', '--name', $dbName, '--label', "$label=$runId", '--network', 'none',
        '--cpus', '1', '--memory', '1g', '--tmpfs', '/var/lib/mysql:rw,size=512m',
        '--tmpfs', '/tmp:rw,size=64m', '--security-opt', 'no-new-privileges=true',
        '--mount', "type=volume,source=$socketVolume,target=/var/run/mysqld",
        '--env', 'MYSQL_ALLOW_EMPTY_PASSWORD=1', '--env', 'MYSQL_DATABASE=rei120_test', $mysqlImage.Id
    ) | Select-Object -Last 1).Trim()
    $mysql = Get-OwnedContainer $mysqlId
    $mysqlPorts = if ($mysql.HostConfig.PortBindings) { @($mysql.HostConfig.PortBindings.PSObject.Properties).Count } else { 0 }
    $mysqlSocketMounts = @($mysql.Mounts | Where-Object { $_.Type -eq 'volume' -and $_.Name -eq $socketVolume -and $_.Destination -eq '/var/run/mysqld' }).Count
    $mysqlDatadirTmpfs = [string]$mysql.HostConfig.Tmpfs.PSObject.Properties['/var/lib/mysql'].Value
    $mysqlInvalid = $mysql.Image -ne $mysqlImage.Id -or $mysql.HostConfig.NetworkMode -ne 'none' -or $mysql.HostConfig.Privileged -or $mysqlPorts -ne 0 -or $mysqlSocketMounts -ne 1 -or [string]::IsNullOrWhiteSpace($mysqlDatadirTmpfs)
    if ($mysqlInvalid) {
        throw 'Topologia MySQL recusada: rede, portas, socket ou armazenamento efêmero inválido.'
    }
    Invoke-DockerSafe @('start', $mysqlId) | Out-Null

    $ready = $false
    for ($i = 0; $i -lt 90; $i++) {
        try {
            $probe = Invoke-DockerSafe @('exec', $mysqlId, 'mysql', '--protocol=socket', '--socket=/var/run/mysqld/mysqld.sock', '-uroot', 'rei120_test', '-N', '-e', 'SELECT 1')
            if (($probe | Select-Object -Last 1).Trim() -eq '1') { $ready = $true; break }
        } catch { }
        Start-Sleep -Seconds 1
    }
    if (-not $ready) { throw 'MySQL descartável não ficou pronto via socket.' }

    $guardSql = "CREATE TABLE rei120_disposable_guard (run_id CHAR(32) NOT NULL PRIMARY KEY, marker CHAR(64) NOT NULL) ENGINE=InnoDB; INSERT INTO rei120_disposable_guard VALUES ('$runId','$marker');"
    $sentinelAttempted = $true
    Invoke-DockerSafe @('exec', $mysqlId, 'mysql', '--protocol=socket', '--socket=/var/run/mysqld/mysqld.sock', '-uroot', 'rei120_test', '-e', $guardSql) | Out-Null
    $sentinelCreated = $true

    New-Item -ItemType Directory -Path $tempConfigRoot | Out-Null
    Copy-Item -LiteralPath $securityConfig -Destination $tempSecurityConfig
    $phpId = (Invoke-DockerSafe @(
        'create', '--name', $phpName, '--label', "$label=$runId", '--network', 'none',
        '--cpus', '1', '--memory', '512m', '--read-only', '--cap-drop', 'ALL',
        '--security-opt', 'no-new-privileges=true', '--tmpfs', '/tmp:rw,size=64m,noexec,nosuid,nodev',
        '--mount', "type=bind,source=$appRoot,target=/repo/app,readonly",
        '--mount', "type=bind,source=$testRoot,target=/repo/tests/monitoramento,readonly",
        '--mount', "type=bind,source=$tempConfigRoot,target=/repo/config,readonly",
        '--mount', "type=volume,source=$socketVolume,target=/var/run/mysqld",
        '--entrypoint', 'sleep', $phpImage.Id, '300'
    ) | Select-Object -Last 1).Trim()
    $php = Get-OwnedContainer $phpId
    $phpAppMounts = @($php.Mounts | Where-Object { $_.Type -eq 'bind' -and $_.Destination -eq '/repo/app' -and -not $_.RW }).Count
    $phpTestMounts = @($php.Mounts | Where-Object { $_.Type -eq 'bind' -and $_.Destination -eq '/repo/tests/monitoramento' -and -not $_.RW }).Count
    $phpConfigMounts = @($php.Mounts | Where-Object { $_.Type -eq 'bind' -and $_.Destination -eq '/repo/config' -and -not $_.RW }).Count
    $phpSocketMounts = @($php.Mounts | Where-Object { $_.Type -eq 'volume' -and $_.Name -eq $socketVolume -and $_.Destination -eq '/var/run/mysqld' }).Count
    $phpInvalid = $php.Image -ne $phpImage.Id -or $php.HostConfig.NetworkMode -ne 'none' -or $php.HostConfig.Privileged -or -not $php.HostConfig.ReadonlyRootfs -or (@($php.HostConfig.CapDrop) -notcontains 'ALL') -or $phpAppMounts -ne 1 -or $phpTestMounts -ne 1 -or $phpConfigMounts -ne 1 -or $phpSocketMounts -ne 1
    if ($phpInvalid) {
        throw 'Topologia PHP recusada: rede, rootfs, capacidade ou mounts RO inválidos.'
    }
    Invoke-DockerSafe @('start', $phpId) | Out-Null
    $testOutput = Invoke-DockerSafe @('exec', '--env=REI120_TEST_ISOLATION=disposable-rei120-mysql', "--env=REI120_RUN_ID=$runId", "--env=REI120_RUN_MARKER=$marker", '--env=REI120_MYSQL_DSN=mysql:unix_socket=/var/run/mysqld/mysqld.sock;dbname=rei120_test;charset=utf8mb4', '--env=REI120_MYSQL_USER=root', '--env=REI120_MYSQL_PASSWORD=', '--env=REI120_MYSQL_DSN_SECOND=mysql:unix_socket=/var/run/mysqld/mysqld.sock;dbname=rei120_test;charset=utf8mb4', $phpId, 'php', '/repo/tests/monitoramento/atomicidade-transicoes-mysql.test.php')
    $testOutput | ForEach-Object { Write-Output $_ }
} catch {
    $failure = $_
} finally {
    if ($sentinelAttempted -and $mysqlId -match '^[a-f0-9]{64}$') {
        try {
            $sentinel = Invoke-DockerSafe @('exec', $mysqlId, 'mysql', '--protocol=socket', '--socket=/var/run/mysqld/mysqld.sock', '-uroot', 'rei120_test', '-N', '-B', '-e', "SELECT CONCAT(run_id,':',marker) FROM rei120_disposable_guard WHERE run_id='$runId' AND marker='$marker'")
            if (($sentinel | Select-Object -Last 1).Trim() -ne "$runId`:$marker" -or -not $sentinelCreated) { throw 'Sentinela descartável não foi preservada.' }
        } catch { $failure = if ($failure) { $failure } else { $_ } }
    }
    Remove-OwnedContainer $phpId
    Remove-OwnedContainer $mysqlId
    if ((Test-Path -LiteralPath $tempConfigRoot) -and $tempConfigRoot.StartsWith([IO.Path]::GetTempPath(), [StringComparison]::OrdinalIgnoreCase) -and (Split-Path -Leaf $tempConfigRoot) -match '^rei120-config-[a-f0-9]{32}$') {
        Remove-Item -LiteralPath $tempConfigRoot -Recurse -Force
    }
    if ($volumeCreated) {
        try {
            $remainingContainers = Invoke-DockerSafe @('ps', '-aq', '--no-trunc', '--filter', "volume=$socketVolume")
            $owned = @(Invoke-DockerSafe @('volume', 'ls', '--quiet', '--filter', "label=$label=$runId") | Where-Object { $_ })
            if (@($remainingContainers | Where-Object { $_ }).Count -ne 0 -or $owned.Count -ne 1 -or $owned[0] -ne $socketVolume) {
                throw 'Volume socket ainda montado ou ownership não comprovado.'
            }
            $volume = @((Invoke-DockerSafe @('volume', 'inspect', $socketVolume)) | ConvertFrom-Json)[0]
            if ($volume.Labels.$label -ne $runId) { throw 'Ownership do volume não confere.' }
            Invoke-DockerSafe @('volume', 'rm', $socketVolume) | Out-Null
        } catch { $cleanupFailed = $true; Write-Warning 'Volume descartável não foi removido sem confirmação de propriedade.' }
    }
}

if ($failure) { throw $failure }
if ($cleanupFailed) { throw 'Teste executado, mas a limpeza dos recursos próprios não foi comprovada.' }
