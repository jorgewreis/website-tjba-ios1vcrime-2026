[CmdletBinding()]
param()

$ErrorActionPreference = 'Stop'
$repoRoot = (Resolve-Path (Join-Path $PSScriptRoot '..')).Path
$testFile = Join-Path $repoRoot 'tests/rei152-documentos-mysql.test.php'
$workerFile = Join-Path $repoRoot 'tests/rei152-documentos-mysql-worker.php'
$appRoot = Join-Path $repoRoot 'app'
$configRoot = Join-Path $repoRoot 'config'
$mysqlImageRef = 'mysql@sha256:a81d043930e51745522bd5c430961cf8286f0a23f0a9a2a6e0108f7ea71b0902'
$phpImageRef = 'fokus-law-linux-qa@sha256:459cc8bd25b38009a8cf9a0ef5399d131f4f2001a1376d89389f673a92de430b'

if (Test-Path -LiteralPath (Join-Path $repoRoot '.env')) { throw 'Runner recusado: checkout contém .env.' }
foreach ($path in @($appRoot, $configRoot, $testFile, $workerFile)) {
    $item = Get-Item -LiteralPath $path -Force
    if ($item.Attributes -band [IO.FileAttributes]::ReparsePoint) { throw 'Runner recusado: source contém reparse point.' }
}
if (Get-ChildItem -LiteralPath $appRoot -Recurse -Force | Where-Object { $_.Attributes -band [IO.FileAttributes]::ReparsePoint }) {
    throw 'Runner recusado: app contém reparse point.'
}
if (Get-ChildItem -LiteralPath $appRoot -Recurse -Force | Where-Object { $_.Name -like '.env*' }) {
    throw 'Runner recusado: app contém arquivo de ambiente.'
}

$contextName = (& docker context show 2>$null | Select-Object -Last 1).Trim()
if ($LASTEXITCODE -ne 0 -or $contextName -ne 'desktop-linux') { throw 'Runner recusado: contexto local desktop-linux obrigatório.' }
$dockerEndpoint = 'npipe:////./pipe/dockerDesktopLinuxEngine'
$context = @((& docker context inspect $contextName 2>$null | ConvertFrom-Json))[0]
if ($LASTEXITCODE -ne 0 -or $context.Endpoints.docker.Host -ne $dockerEndpoint) { throw 'Runner recusado: endpoint do Docker não é local.' }

$runId = [Guid]::NewGuid().ToString('N').ToLowerInvariant()
$label = 'com.fokus.rei152-isolated-test'
$mysqlName = "fokus-rei152-mysql-$runId"
$phpName = "fokus-rei152-php-$runId"
$volumeName = "fokus-rei152-socket-$runId"
$mysqlId = $null
$phpId = $null
$volumeCreated = $false
$cleanupFailed = $false

function Invoke-DockerSafe([string[]]$arguments) {
    $output = & docker --host $dockerEndpoint @arguments 2>&1
    if ($LASTEXITCODE -ne 0) { throw "Docker falhou em '$($arguments[0])': $($output -join ' ')" }
    return $output
}

function Assert-OwnedContainer([string]$id, [string]$name) {
    if ($id -notmatch '^[a-f0-9]{64}$') { throw 'ID de container inválido.' }
    $item = @((Invoke-DockerSafe @('inspect', $id)) | ConvertFrom-Json)[0]
    if ($item.Config.Labels.$label -ne $runId -or $item.Name -ne "/$name") { throw 'Identidade/propriedade do container não confere.' }
    return $item
}

try {
    foreach ($imageRef in @($mysqlImageRef, $phpImageRef)) {
        $image = @((Invoke-DockerSafe @('image', 'inspect', $imageRef)) | ConvertFrom-Json)[0]
        if (-not $image.Id -or @($image.RepoDigests) -notcontains $imageRef) { throw "Imagem imutável ausente localmente: $imageRef" }
    }

    Invoke-DockerSafe @('volume', 'create', '--label', "$label=$runId", $volumeName) | Out-Null
    $volumeCreated = $true
    $volume = @((Invoke-DockerSafe @('volume', 'inspect', $volumeName)) | ConvertFrom-Json)[0]
    if ($volume.Labels.$label -ne $runId) { throw 'Volume de socket não pertence a esta execução.' }

    $mysqlId = (Invoke-DockerSafe @(
        'create', '--name', $mysqlName, '--label', "$label=$runId", '--network', 'none',
        '--tmpfs', '/var/lib/mysql:rw,size=512m', '--security-opt', 'no-new-privileges=true',
        '--mount', "type=volume,source=$volumeName,target=/var/run/mysqld",
        '--env', 'MYSQL_ALLOW_EMPTY_PASSWORD=1', '--env', 'MYSQL_DATABASE=rei152_test', $mysqlImageRef
    ) | Select-Object -Last 1).Trim()
    $mysql = Assert-OwnedContainer $mysqlId $mysqlName
    $mysqlPortBindings = if ($mysql.HostConfig.PortBindings) { @($mysql.HostConfig.PortBindings.PSObject.Properties).Count } else { 0 }
    if ($mysql.HostConfig.NetworkMode -ne 'none' -or $mysql.HostConfig.Privileged -or $mysqlPortBindings -ne 0) {
        throw 'Topologia MySQL recusada: rede, privilégio ou porta exposta.'
    }
    Invoke-DockerSafe @('start', $mysqlId) | Out-Null
    $ready = $false
    for ($i = 0; $i -lt 90; $i++) {
        try {
            Invoke-DockerSafe @('exec', $mysqlId, 'mysql', '--protocol=socket', '--socket=/var/run/mysqld/mysqld.sock', '-uroot', 'rei152_test', '-N', '-B', '-e', 'SELECT 1') | Out-Null
            $ready = $true
            break
        } catch { Start-Sleep -Seconds 1 }
    }
    if (-not $ready) { throw 'MySQL descartável não ficou pronto.' }

    $phpId = (Invoke-DockerSafe @(
        'create', '--name', $phpName, '--label', "$label=$runId", '--network', 'none', '--read-only',
        '--cap-drop', 'ALL', '--security-opt', 'no-new-privileges=true', '--tmpfs', '/tmp:rw,size=64m',
        '--mount', "type=bind,source=$appRoot,target=/repo/app,readonly",
        '--mount', "type=bind,source=$configRoot,target=/repo/config,readonly",
        '--mount', "type=bind,source=$testFile,target=/repo/tests/rei152-documentos-mysql.test.php,readonly",
        '--mount', "type=bind,source=$workerFile,target=/repo/tests/rei152-documentos-mysql-worker.php,readonly",
        '--mount', "type=volume,source=$volumeName,target=/var/run/mysqld",
        '--env', 'REI152_TEST_ISOLATION=rei152-disposable-mysql',
        '--env', 'REI152_MYSQL_DSN=mysql:unix_socket=/var/run/mysqld/mysqld.sock;dbname=rei152_test;charset=utf8mb4',
        '--env', 'REI152_MYSQL_USER=root', '--entrypoint', 'sleep', $phpImageRef, '300'
    ) | Select-Object -Last 1).Trim()
    $php = Assert-OwnedContainer $phpId $phpName
    $phpPortBindings = if ($php.HostConfig.PortBindings) { @($php.HostConfig.PortBindings.PSObject.Properties).Count } else { 0 }
    if ($php.HostConfig.NetworkMode -ne 'none' -or $php.HostConfig.Privileged -or $phpPortBindings -ne 0 -or
        @($php.HostConfig.CapDrop) -notcontains 'ALL' -or -not $php.HostConfig.ReadonlyRootfs) {
        throw 'Topologia PHP recusada: isolamento, rootfs, capacidades ou portas inválidas.'
    }
    Invoke-DockerSafe @('start', $phpId) | Out-Null
    Invoke-DockerSafe @('exec', $phpId, 'php', '/repo/tests/rei152-documentos-mysql.test.php')
} finally {
    foreach ($entry in @(@{ Id = $phpId; Name = $phpName }, @{ Id = $mysqlId; Name = $mysqlName })) {
        if (-not $entry.Id) { continue }
        try {
            $item = Assert-OwnedContainer ([string]$entry.Id) ([string]$entry.Name)
            if ($item.State.Running) { Invoke-DockerSafe @('stop', [string]$entry.Id) | Out-Null }
            Invoke-DockerSafe @('rm', [string]$entry.Id) | Out-Null
            if (@(Invoke-DockerSafe @('ps', '-aq', '--no-trunc', '--filter', "id=$($entry.Id)")).Count -ne 0) { throw 'Container ainda existe.' }
        } catch {
            $cleanupFailed = $true
            Write-Warning "Container não removido sem prova de identidade/limpeza ($($entry.Name))."
        }
    }
    if ($volumeCreated) {
        try {
            $volume = @((Invoke-DockerSafe @('volume', 'inspect', $volumeName)) | ConvertFrom-Json)[0]
            if ($volume.Labels.$label -ne $runId) { throw 'Volume sem propriedade confirmada.' }
            Invoke-DockerSafe @('volume', 'rm', $volumeName) | Out-Null
            if (@(Invoke-DockerSafe @('volume', 'ls', '-q', '--filter', "name=^$volumeName$" )).Count -ne 0) { throw 'Volume ainda existe.' }
        } catch {
            $cleanupFailed = $true
            Write-Warning "Volume não removido sem prova de identidade/limpeza ($volumeName)."
        }
    }
    if ($cleanupFailed) { throw 'Limpeza dos recursos descartáveis não foi confirmada.' }
}
