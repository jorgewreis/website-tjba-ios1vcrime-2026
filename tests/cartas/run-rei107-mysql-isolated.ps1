[CmdletBinding()]
param()

$ErrorActionPreference = 'Stop'
$repoRoot = (Resolve-Path (Join-Path $PSScriptRoot '../..')).Path
$appRoot = Join-Path $repoRoot 'app'
$configFile = Join-Path $repoRoot 'config/config.php'
$securityFile = Join-Path $repoRoot 'config/security.php'
$testFile = Join-Path $PSScriptRoot 'rei107-delete-mysql.test.php'
$workerFile = Join-Path $PSScriptRoot 'rei107-delete-mysql-worker.php'
$mysqlImageRef = 'mysql@sha256:0426ec38c7a10aa45ba383887df7878f74ee70e2fd589c7b69207f3577901903'
$phpImageRef = 'fokus-law-linux-qa@sha256:459cc8bd25b38009a8cf9a0ef5399d131f4f2001a1376d89389f673a92de430b'
$contextName = (& docker context show 2>$null | Select-Object -Last 1).Trim()
if ($LASTEXITCODE -ne 0 -or $contextName -ne 'desktop-linux') { throw 'Runner recusado: somente contexto local desktop-linux.' }
$dockerEndpoint = 'npipe:////./pipe/dockerDesktopLinuxEngine'
$contextInfo = @((& docker context inspect $contextName 2>$null | ConvertFrom-Json))[0]
if ($LASTEXITCODE -ne 0 -or $contextInfo.Endpoints.docker.Host -ne $dockerEndpoint) { throw 'Runner recusado: endpoint Docker inesperado.' }

foreach ($path in @($appRoot, $configFile, $securityFile, $testFile, $workerFile)) {
    $item = Get-Item -LiteralPath $path -Force
    if ($item.Attributes -band [IO.FileAttributes]::ReparsePoint) { throw 'Runner recusado: source contém reparse point.' }
}
if (Get-ChildItem -LiteralPath $appRoot -Recurse -Force | Where-Object { $_.Attributes -band [IO.FileAttributes]::ReparsePoint }) { throw 'Runner recusado: app contém reparse point.' }
if (Get-ChildItem -LiteralPath $appRoot -Recurse -Force | Where-Object { $_.Name -like '.env*' }) { throw 'Runner recusado: app contém arquivo de ambiente.' }

$runId = [Guid]::NewGuid().ToString('N').ToLowerInvariant()
$marker = ([Guid]::NewGuid().ToString('N') + [Guid]::NewGuid().ToString('N')).ToLowerInvariant()
$label = 'com.fokus.rei107-isolated-test'
$dbName = "fokus-rei107-mysql-$runId"
$volumeName = "fokus-rei107-socket-$runId"
$mysqlId = $null
$phpId = $null
$testFailure = $null
$cleanupFailure = $null
$sentinelAttempted = $false
$sentinelCreated = $false

function Invoke-DockerSafe([string[]]$argsList) {
    $output = & docker --host $dockerEndpoint @argsList 2>&1
    if ($LASTEXITCODE -ne 0) { throw "Docker falhou ($($argsList[0])): $($output -join ' ')" }
    return $output
}
function Get-Owned([string]$id) {
    if ($id -notmatch '^[a-f0-9]{64}$') { throw 'ID Docker inválido.' }
    $item = @((Invoke-DockerSafe @('inspect', $id)) | ConvertFrom-Json)[0]
    if (-not $item -or $item.Config.Labels.$label -ne $runId) { throw 'Recurso Docker não pertence a esta execução.' }
    return $item
}
function Remove-Owned([string]$id) {
    if (-not $id) { return }
    try {
        $item = Get-Owned $id
        if ($item.State.Running) { Invoke-DockerSafe @('stop', $id) | Out-Null }
        Invoke-DockerSafe @('rm', $id) | Out-Null
        if (@(Invoke-DockerSafe @('ps', '-aq', '--no-trunc', '--filter', "id=$id") | Where-Object { $_ }).Count -ne 0) { throw 'Container ainda existe.' }
    } catch { $script:cleanupFailure = $_; Write-Warning 'Container não removido sem validar propriedade.' }
}

try {
    $mysqlImage = @((Invoke-DockerSafe @('image', 'inspect', $mysqlImageRef)) | ConvertFrom-Json)[0]
    $phpImage = @((Invoke-DockerSafe @('image', 'inspect', $phpImageRef)) | ConvertFrom-Json)[0]
    if (-not $mysqlImage.Id -or @($mysqlImage.RepoDigests) -notcontains $mysqlImageRef -or -not $phpImage.Id -or @($phpImage.RepoDigests) -notcontains $phpImageRef) { throw 'Runner recusado: imagens locais sem digest esperado.' }

    Invoke-DockerSafe @('volume', 'create', '--label', "$label=$runId", $volumeName) | Out-Null
    $volume = @((Invoke-DockerSafe @('volume', 'inspect', $volumeName)) | ConvertFrom-Json)[0]
    if ($volume.Labels.$label -ne $runId) { throw 'Socket volume sem ownership confirmado.' }

    $mysqlId = (Invoke-DockerSafe @('create', '--name', $dbName, '--label', "$label=$runId", '--network', 'none', '--cpus', '1', '--memory', '1g', '--tmpfs', '/var/lib/mysql:rw,size=512m', '--security-opt', 'no-new-privileges=true', '--mount', "type=volume,source=$volumeName,target=/var/run/mysqld", '--env', 'MYSQL_ALLOW_EMPTY_PASSWORD=1', '--env', 'MYSQL_DATABASE=rei107_test', $mysqlImage.Id) | Select-Object -Last 1).Trim()
    $mysql = Get-Owned $mysqlId
    $mysqlPorts = if ($mysql.HostConfig.PortBindings) { @($mysql.HostConfig.PortBindings.PSObject.Properties).Count } else { 0 }
    $mysqlTmpfs = $mysql.HostConfig.Tmpfs.PSObject.Properties['/var/lib/mysql'].Value
    if ($mysql.Image -ne $mysqlImage.Id -or $mysql.HostConfig.NetworkMode -ne 'none' -or $mysql.HostConfig.Privileged -or $mysqlPorts -ne 0 -or $mysql.HostConfig.ReadonlyRootfs -or [string]::IsNullOrWhiteSpace([string]$mysqlTmpfs) -or $mysqlTmpfs -notmatch 'size=512m' -or @($mysql.Mounts | Where-Object { $_.Type -eq 'volume' -and $_.Name -eq $volumeName -and $_.Destination -eq '/var/run/mysqld' }).Count -ne 1) { throw 'Topologia MySQL descartável inválida.' }
    Invoke-DockerSafe @('start', $mysqlId) | Out-Null
    $ready = $false
    for ($i = 0; $i -lt 90; $i++) {
        try { $probe = Invoke-DockerSafe @('exec', $mysqlId, 'mysql', '--protocol=socket', '--socket=/var/run/mysqld/mysqld.sock', '-uroot', 'rei107_test', '-N', '-e', 'SELECT 1'); if (($probe | Select-Object -Last 1).Trim() -eq '1') { $ready = $true; break } } catch { }
        Start-Sleep -Seconds 1
    }
    if (-not $ready) { throw 'MySQL descartável não ficou pronto via socket.' }

    $sentinelAttempted = $true
    $guardSql = "CREATE TABLE rei107_disposable_guard (run_id CHAR(32) PRIMARY KEY, marker CHAR(64) NOT NULL) ENGINE=InnoDB; INSERT INTO rei107_disposable_guard VALUES ('$runId','$marker');"
    Invoke-DockerSafe @('exec', $mysqlId, 'mysql', '--protocol=socket', '--socket=/var/run/mysqld/mysqld.sock', '-uroot', 'rei107_test', '-e', $guardSql) | Out-Null
    $sentinelCreated = $true

    $phpId = (Invoke-DockerSafe @('create', '--name', "fokus-rei107-php-$runId", '--label', "$label=$runId", '--network', 'none', '--cpus', '1', '--memory', '512m', '--read-only', '--cap-drop', 'ALL', '--security-opt', 'no-new-privileges=true', '--tmpfs', '/tmp:rw,size=32m,noexec,nosuid,nodev', '--tmpfs', '/repo/config:rw,size=4m,noexec,nosuid,nodev', '--mount', "type=bind,source=$appRoot,target=/repo/app,readonly", '--mount', "type=bind,source=$configFile,target=/repo/config/config.php,readonly", '--mount', "type=bind,source=$securityFile,target=/repo/config/security.php,readonly", '--mount', "type=bind,source=$testFile,target=/test/rei107-delete-mysql.test.php,readonly", '--mount', "type=bind,source=$workerFile,target=/test/rei107-delete-mysql-worker.php,readonly", '--mount', "type=volume,source=$volumeName,target=/var/run/mysqld", '--env', 'DB_HOST=isolated-fixture.invalid', '--env', 'DB_NAME=rei107_unused', '--env', 'DB_USER=fixture', '--env', 'DB_PASS=fixture', '--env', 'REI107_DISPOSABLE=1', '--env', "REI107_TEST_RUN_ID=$runId", '--env', "REI107_TEST_MARKER=$marker", '--env', 'REI107_MYSQL_DSN=mysql:unix_socket=/var/run/mysqld/mysqld.sock;dbname=rei107_test;charset=utf8mb4', '--env', 'REI107_MYSQL_USER=root', '--entrypoint', 'php', $phpImage.Id, '/test/rei107-delete-mysql.test.php') | Select-Object -Last 1).Trim()
    $php = Get-Owned $phpId
    $phpPorts = if ($php.HostConfig.PortBindings) { @($php.HostConfig.PortBindings.PSObject.Properties).Count } else { 0 }
    if ($php.Image -ne $phpImage.Id -or $php.HostConfig.NetworkMode -ne 'none' -or $php.HostConfig.Privileged -or $phpPorts -ne 0 -or -not $php.HostConfig.ReadonlyRootfs -or @($php.HostConfig.CapDrop) -notcontains 'ALL' -or @($php.HostConfig.SecurityOpt) -notcontains 'no-new-privileges=true' -or @($php.Mounts).Count -ne 6 -or [string]::IsNullOrWhiteSpace([string]$php.HostConfig.Tmpfs.PSObject.Properties['/repo/config'].Value) -or @($php.Mounts | Where-Object { $_.Type -eq 'bind' -and $_.Destination -eq '/repo/app' -and -not $_.RW }).Count -ne 1 -or @($php.Mounts | Where-Object { $_.Type -eq 'bind' -and $_.Destination -eq '/repo/config/config.php' -and -not $_.RW }).Count -ne 1 -or @($php.Mounts | Where-Object { $_.Type -eq 'bind' -and $_.Destination -eq '/repo/config/security.php' -and -not $_.RW }).Count -ne 1 -or @($php.Mounts | Where-Object { $_.Type -eq 'bind' -and $_.Destination -eq '/test/rei107-delete-mysql.test.php' -and -not $_.RW }).Count -ne 1 -or @($php.Mounts | Where-Object { $_.Type -eq 'bind' -and $_.Destination -eq '/test/rei107-delete-mysql-worker.php' -and -not $_.RW }).Count -ne 1) { throw 'Topologia PHP descartável inválida.' }
    $testOutput = Invoke-DockerSafe @('start', '-a', $phpId)
    $testOutput
} catch { $testFailure = $_ } finally {
    if ($sentinelAttempted -and $mysqlId) {
        try {
            $sentinel = Invoke-DockerSafe @('exec', $mysqlId, 'mysql', '--protocol=socket', '--socket=/var/run/mysqld/mysqld.sock', '-uroot', 'rei107_test', '-N', '-B', '-e', "SELECT CONCAT(run_id,':',marker) FROM rei107_disposable_guard WHERE run_id='$runId' AND marker='$marker'")
            if (-not $sentinelCreated -or ($sentinel | Select-Object -Last 1).Trim() -ne "$runId`:$marker") { throw 'Sentinela do banco descartável não foi comprovada.' }
        } catch { if (-not $testFailure) { $testFailure = $_ } }
    }
    Remove-Owned $phpId
    Remove-Owned $mysqlId
    try {
        $left = @(Invoke-DockerSafe @('ps', '-aq', '--no-trunc', '--filter', "label=$label=$runId") | Where-Object { $_ })
        if ($left.Count -ne 0) { throw 'Containers da execução ainda existem.' }
        $volumeInfo = @((Invoke-DockerSafe @('volume', 'inspect', $volumeName)) | ConvertFrom-Json)[0]
        if ($volumeInfo.Labels.$label -ne $runId) { throw 'Ownership do volume não confere.' }
        if (@(Invoke-DockerSafe @('ps', '-aq', '--no-trunc', '--filter', "volume=$volumeName") | Where-Object { $_ }).Count -ne 0) { throw 'Volume continua montado.' }
        Invoke-DockerSafe @('volume', 'rm', $volumeName) | Out-Null
        if (@(Invoke-DockerSafe @('volume', 'ls', '--quiet', '--filter', "label=$label=$runId") | Where-Object { $_ }).Count -ne 0) { throw 'Volume ainda existe após limpeza.' }
    } catch { if (-not $cleanupFailure) { $cleanupFailure = $_ } }
}
if ($testFailure) { if ($cleanupFailure) { Write-Error $cleanupFailure -ErrorAction Continue }; $PSCmdlet.ThrowTerminatingError($testFailure) }
if ($cleanupFailure) { $PSCmdlet.ThrowTerminatingError($cleanupFailure) }
