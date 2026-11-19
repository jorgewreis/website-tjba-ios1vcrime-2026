[CmdletBinding()]
param()

$ErrorActionPreference = 'Stop'
$repoRoot = (Resolve-Path (Join-Path $PSScriptRoot '../..')).Path
$appRoot = Join-Path $repoRoot 'app'
$testFile = Join-Path $PSScriptRoot 'rei104-conclusao-mysql.test.php'
$mysqlImageRef = 'mysql@sha256:a81d043930e51745522bd5c430961cf8286f0a23f0a9a2a6e0108f7ea71b0902'
$phpImageRef = 'fokus-law-linux-qa@sha256:459cc8bd25b38009a8cf9a0ef5399d131f4f2001a1376d89389f673a92de430b'

if (Test-Path -LiteralPath (Join-Path $repoRoot '.env')) { throw 'Runner recusado: checkout contém .env.' }
foreach ($path in @($appRoot, $PSScriptRoot, $testFile)) {
    if ((Get-Item -LiteralPath $path -Force).Attributes -band [IO.FileAttributes]::ReparsePoint) { throw 'Runner recusado: origem contém reparse point.' }
}
foreach ($root in @($appRoot, $PSScriptRoot)) {
    if (Get-ChildItem -LiteralPath $root -Recurse -Force | Where-Object { $_.Attributes -band [IO.FileAttributes]::ReparsePoint }) { throw 'Runner recusado: origem contém reparse point descendente.' }
}
if (Get-ChildItem -LiteralPath $appRoot -Recurse -Force | Where-Object { $_.Name -like '.env*' }) { throw 'Runner recusado: app contém arquivo de ambiente.' }

$contextName = (& docker context show 2>$null | Select-Object -Last 1).Trim()
if ($LASTEXITCODE -ne 0 -or $contextName -ne 'desktop-linux') { throw 'Runner exige contexto local desktop-linux.' }
$dockerEndpoint = 'npipe:////./pipe/dockerDesktopLinuxEngine'
$contextInfo = @((& docker context inspect $contextName 2>$null | ConvertFrom-Json))[0]
if ($LASTEXITCODE -ne 0 -or $contextInfo.Endpoints.docker.Host -ne $dockerEndpoint) { throw 'Runner exige endpoint local Docker Desktop.' }

$runId = [Guid]::NewGuid().ToString('N').ToLowerInvariant()
$marker = ([Guid]::NewGuid().ToString('N') + [Guid]::NewGuid().ToString('N')).ToLowerInvariant()
$label = 'com.fokus.rei104-mysql-test'
$database = 'rei104_test'
$mysqlName = "fokus-rei104-mysql-$runId"
$phpName = "fokus-rei104-php-$runId"
$socketVolume = "fokus-rei104-socket-$runId"
$mysqlId = $null
$phpId = $null
$volumeCreated = $false
$failure = $null
$cleanupFailed = $false

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
        if ($item.State.Running) { Invoke-DockerSafe @('stop', '--time', '5', $id) | Out-Null }
        Invoke-DockerSafe @('rm', $id) | Out-Null
    } catch { $script:cleanupFailed = $true; Write-Warning 'Container desta execução não foi removido com segurança.' }
}

try {
    $mysqlImage = @((Invoke-DockerSafe @('image', 'inspect', $mysqlImageRef)) | ConvertFrom-Json)[0]
    $phpImage = @((Invoke-DockerSafe @('image', 'inspect', $phpImageRef)) | ConvertFrom-Json)[0]
    if (@($mysqlImage.RepoDigests) -notcontains $mysqlImageRef -or @($phpImage.RepoDigests) -notcontains $phpImageRef) { throw 'Runner recusado: imagens imutáveis não disponíveis localmente.' }

    Invoke-DockerSafe @('volume', 'create', '--label', "$label=$runId", $socketVolume) | Out-Null
    $volumeCreated = $true
    $mysqlId = (Invoke-DockerSafe @(
        'create', '--name', $mysqlName, '--label', "$label=$runId", '--network', 'none',
        '--cpus', '1', '--memory', '1g', '--tmpfs', '/var/lib/mysql:rw,size=512m',
        '--tmpfs', '/tmp:rw,size=64m', '--security-opt', 'no-new-privileges=true',
        '--mount', "type=volume,source=$socketVolume,target=/var/run/mysqld",
        '--env', 'MYSQL_ALLOW_EMPTY_PASSWORD=1', '--env', "MYSQL_DATABASE=$database", $mysqlImage.Id
    ) | Select-Object -Last 1).Trim()
    $mysql = Get-OwnedContainer $mysqlId
    $mysqlPorts = if ($mysql.HostConfig.PortBindings) { @($mysql.HostConfig.PortBindings.PSObject.Properties).Count } else { 0 }
    if ($mysql.Image -ne $mysqlImage.Id -or $mysql.HostConfig.NetworkMode -ne 'none' -or $mysql.HostConfig.Privileged -or $mysqlPorts -ne 0) { throw 'Topologia MySQL recusada.' }
    Invoke-DockerSafe @('start', $mysqlId) | Out-Null
    $ready = $false
    for ($i = 0; $i -lt 90; $i++) {
        try {
            $probe = Invoke-DockerSafe @('exec', $mysqlId, 'mysql', '--protocol=socket', '--socket=/var/run/mysqld/mysqld.sock', '-uroot', $database, '-N', '-e', 'SELECT 1')
            if (($probe | Select-Object -Last 1).Trim() -eq '1') { $ready = $true; break }
        } catch { }
        Start-Sleep -Seconds 1
    }
    if (-not $ready) { throw 'MySQL descartável não ficou pronto via socket.' }
    $guardSql = "CREATE TABLE rei104_disposable_guard (run_id CHAR(32) NOT NULL PRIMARY KEY, marker CHAR(64) NOT NULL) ENGINE=InnoDB; INSERT INTO rei104_disposable_guard VALUES ('$runId','$marker');"
    Invoke-DockerSafe @('exec', $mysqlId, 'mysql', '--protocol=socket', '--socket=/var/run/mysqld/mysqld.sock', '-uroot', $database, '-e', $guardSql) | Out-Null

    $phpId = (Invoke-DockerSafe @(
        'create', '--name', $phpName, '--label', "$label=$runId", '--network', 'none',
        '--cpus', '1', '--memory', '512m', '--read-only', '--cap-drop', 'ALL',
        '--security-opt', 'no-new-privileges=true', '--tmpfs', '/tmp:rw,size=64m,noexec,nosuid,nodev',
        '--mount', "type=bind,source=$appRoot,target=/repo/app,readonly",
        '--mount', "type=bind,source=$PSScriptRoot,target=/repo/tests/cartas,readonly",
        '--mount', "type=volume,source=$socketVolume,target=/var/run/mysqld",
        '--entrypoint', 'sleep', $phpImage.Id, '300'
    ) | Select-Object -Last 1).Trim()
    $php = Get-OwnedContainer $phpId
    if ($php.Image -ne $phpImage.Id -or $php.HostConfig.NetworkMode -ne 'none' -or $php.HostConfig.Privileged -or -not $php.HostConfig.ReadonlyRootfs -or @($php.HostConfig.CapDrop) -notcontains 'ALL') { throw 'Topologia PHP recusada.' }
    Invoke-DockerSafe @('start', $phpId) | Out-Null
    Invoke-DockerSafe @('exec', '--env=REI104_TEST_ISOLATION=disposable-rei104-mysql', "--env=REI104_TEST_RUN_ID=$runId", "--env=REI104_TEST_MARKER=$marker", "--env=REI104_TEST_DSN=mysql:unix_socket=/var/run/mysqld/mysqld.sock;dbname=$database;charset=utf8mb4", '--env=REI104_TEST_USER=root', '--env=REI104_TEST_PASSWORD=', $phpId, 'php', '/repo/tests/cartas/rei104-conclusao-mysql.test.php') | ForEach-Object { Write-Output $_ }
} catch { $failure = $_ }
finally {
    if ($mysqlId) {
        try {
            $sentinel = Invoke-DockerSafe @('exec', $mysqlId, 'mysql', '--protocol=socket', '--socket=/var/run/mysqld/mysqld.sock', '-uroot', $database, '-N', '-B', '-e', "SELECT CONCAT(run_id,':',marker) FROM rei104_disposable_guard WHERE run_id='$runId' AND marker='$marker'")
            if (($sentinel | Select-Object -Last 1).Trim() -ne "$runId`:$marker") { throw 'Sentinela do banco descartável não confere.' }
        } catch { $script:cleanupFailed = $true; Write-Warning 'Sentinela do banco descartável não foi confirmada.' }
    }
    Remove-OwnedContainer $phpId
    Remove-OwnedContainer $mysqlId
    if ($volumeCreated) {
        try {
            $remaining = Invoke-DockerSafe @('ps', '-aq', '--no-trunc', '--filter', "volume=$socketVolume")
            $volumes = @(Invoke-DockerSafe @('volume', 'ls', '--quiet', '--filter', "label=$label=$runId") | Where-Object { $_ })
            if (@($remaining | Where-Object { $_ }).Count -ne 0 -or $volumes.Count -ne 1 -or $volumes[0] -ne $socketVolume) { throw 'Ownership do volume descartável não comprovada.' }
            Invoke-DockerSafe @('volume', 'rm', $socketVolume) | Out-Null
        } catch { $cleanupFailed = $true; Write-Warning 'Volume desta execução não foi removido com segurança.' }
    }
}
if ($failure) { throw $failure }
if ($cleanupFailed) { throw 'Teste executado, mas a limpeza dos recursos da execução não foi comprovada.' }
