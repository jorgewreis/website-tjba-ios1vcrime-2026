[CmdletBinding()]
param()

$ErrorActionPreference = 'Stop'
$repoRoot = (Resolve-Path (Join-Path $PSScriptRoot '../..')).Path
$appRoot = Join-Path $repoRoot 'app'
$testFile = Join-Path $PSScriptRoot 'carta-sigilo-mysql.test.php'
$mysqlImageRef = 'mysql@sha256:a81d043930e51745522bd5c430961cf8286f0a23f0a9a2a6e0108f7ea71b0902'
$phpImageRef = 'fokus-law-linux-qa@sha256:459cc8bd25b38009a8cf9a0ef5399d131f4f2001a1376d89389f673a92de430b'
if (Test-Path -LiteralPath (Join-Path $repoRoot '.env')) {
    throw 'Runner recusado: não execute a fixture em checkout com .env.'
}
foreach ($path in @($appRoot, $testFile)) {
    $item = Get-Item -LiteralPath $path -Force
    if ($item.Attributes -band [IO.FileAttributes]::ReparsePoint) {
        throw 'Runner recusado: source com reparse point.'
    }
}
$reparseItems = Get-ChildItem -LiteralPath $appRoot -Recurse -Force | Where-Object {
    $_.Attributes -band [IO.FileAttributes]::ReparsePoint
}
if ($reparseItems) { throw 'Runner recusado: app contém reparse point.' }
$envItems = Get-ChildItem -LiteralPath $appRoot -Recurse -Force | Where-Object { $_.Name -like '.env*' }
if ($envItems) { throw 'Runner recusado: app contém arquivo de ambiente.' }

$contextName = (& docker context show 2>$null | Select-Object -Last 1).Trim()
if ($LASTEXITCODE -ne 0 -or $contextName -ne 'desktop-linux') {
    throw 'Runner recusado: somente o contexto local desktop-linux é permitido.'
}
$contextInfo = @((& docker context inspect $contextName 2>$null | ConvertFrom-Json))[0]
if ($LASTEXITCODE -ne 0 -or $contextInfo.Endpoints.docker.Host -ne 'npipe:////./pipe/dockerDesktopLinuxEngine') {
    throw 'Runner recusado: endpoint do Docker não é o pipe local esperado.'
}

$runId = [Guid]::NewGuid().ToString('N')
$marker = ([Guid]::NewGuid().ToString('N') + [Guid]::NewGuid().ToString('N')).ToLowerInvariant()
$label = 'com.fokus.rei88-carta-test'
$dbName = "fokus-rei88-mysql-$runId"
$phpName = "fokus-rei88-php-$runId"
$volumeName = "fokus-rei88-socket-$runId"
$mysqlId = $null
$phpId = $null
$volumeCreated = $false
$cleanupFailed = $false

function Invoke-DockerSafe([string[]]$argsList) {
    $output = & docker @argsList 2>&1
    if ($LASTEXITCODE -ne 0) {
        throw "Docker falhou em '$($argsList[0])' (código $LASTEXITCODE): $($output -join ' ')"
    }
    return $output
}

function Get-OwnedContainer([string]$id) {
    if ($id -notmatch '^[a-f0-9]{64}$') { throw 'ID de container inválido.' }
    $raw = Invoke-DockerSafe @('inspect', $id)
    $item = @($raw | ConvertFrom-Json)[0]
    if (-not $item -or $item.Config.Labels.$label -ne $runId) {
        throw 'Identidade do container não corresponde a esta execução.'
    }
    return $item
}

function Remove-OwnedContainer([string]$id) {
    if (-not $id) { return }
    try {
        $item = Get-OwnedContainer $id
        if ($item.State.Running) { Invoke-DockerSafe @('stop', $id) | Out-Null }
        Invoke-DockerSafe @('rm', $id) | Out-Null
        $remaining = Invoke-DockerSafe @('ps', '-aq', '--no-trunc', '--filter', "id=$id")
        if (@($remaining | Where-Object { $_ }).Count -ne 0) { throw 'Container ainda existe após remoção.' }
    } catch {
        $script:cleanupFailed = $true
        Write-Warning "Não removi container sem comprovar a identidade ou término ($id)."
    }
}

try {
    $mysqlImage = @((Invoke-DockerSafe @('image', 'inspect', $mysqlImageRef)) | ConvertFrom-Json)[0]
    $phpImage = @((Invoke-DockerSafe @('image', 'inspect', $phpImageRef)) | ConvertFrom-Json)[0]
    $imageInvalid = -not $mysqlImage.Id -or -not $phpImage.Id -or (@($mysqlImage.RepoDigests) -notcontains $mysqlImageRef) -or (@($phpImage.RepoDigests) -notcontains $phpImageRef)
    if ($imageInvalid) { throw 'Imagens MySQL/PHP não correspondem aos digests aprovados.' }

    Invoke-DockerSafe @('volume', 'create', '--label', "$label=$runId", $volumeName) | Out-Null
    $volumeCreated = $true
    $volumeInfo = @((Invoke-DockerSafe @('volume', 'inspect', $volumeName)) | ConvertFrom-Json)[0]
    if ($volumeInfo.Labels.$label -ne $runId) { throw 'Identidade do volume descartável não confere.' }

    $mysqlId = (Invoke-DockerSafe @(
        'create', '--name', $dbName, '--label', "$label=$runId", '--network', 'none',
        '--cpus', '1', '--memory', '1g', '--tmpfs', '/var/lib/mysql:rw,size=512m',
        '--security-opt', 'no-new-privileges=true',
        '--mount', "type=volume,source=$volumeName,target=/var/run/mysqld",
        '--env', 'MYSQL_ALLOW_EMPTY_PASSWORD=1', '--env', 'MYSQL_DATABASE=rei88_test',
        $mysqlImage.Id
    ) | Select-Object -Last 1).Trim()
    if ($mysqlId -notmatch '^[a-f0-9]{64}$') { throw 'ID MySQL inválido.' }
    $db = Get-OwnedContainer $mysqlId
    $dbMounts = @($db.Mounts)
    $dbPortCount = if ($db.HostConfig.PortBindings) { @($db.HostConfig.PortBindings.PSObject.Properties).Count } else { 0 }
    $dbTmpfs = $db.HostConfig.Tmpfs.PSObject.Properties['/var/lib/mysql'].Value
    $dbSocketMounts = @($dbMounts | Where-Object {
        $_.Type -eq 'volume' -and $_.Name -eq $volumeName -and $_.Destination -eq '/var/run/mysqld'
    })
    $dbInvalid = $db.Image -ne $mysqlImage.Id -or $db.HostConfig.NetworkMode -ne 'none' -or $db.HostConfig.Privileged
    $dbInvalid = $dbInvalid -or $dbPortCount -ne 0 -or $dbMounts.Count -ne 1 -or $dbSocketMounts.Count -ne 1
    $dbInvalid = $dbInvalid -or [string]::IsNullOrWhiteSpace([string]$dbTmpfs) -or $dbTmpfs -notmatch 'size=512m'
    $dbInvalid = $dbInvalid -or @($db.HostConfig.SecurityOpt) -notcontains 'no-new-privileges=true'
    if ($dbInvalid) {
        throw 'Topologia MySQL recusada: sem rede/portas, datadir efêmero e socket exclusivo são obrigatórios.'
    }
    Invoke-DockerSafe @('start', $mysqlId) | Out-Null

    $ready = $false
    for ($i = 0; $i -lt 90; $i++) {
        $probe = $null
        try { $probe = & docker exec $mysqlId mysql --protocol=socket --socket=/var/run/mysqld/mysqld.sock -uroot rei88_test -N -e 'SELECT 1' 2>$null } catch { }
if ($LASTEXITCODE -eq 0 -and ($probe | Select-Object -Last 1).Trim() -eq '1') { $ready = $true; break }
    Start-Sleep -Seconds 1
    }
    if (-not $ready) { throw 'MySQL descartável ou banco de teste não ficou pronto.' }

    $markerSql = "CREATE TABLE rei88_test_guard (marker CHAR(64) NOT NULL PRIMARY KEY) ENGINE=InnoDB; INSERT INTO rei88_test_guard (marker) VALUES ('$marker');"
    Invoke-DockerSafe @('exec', $mysqlId, 'mysql', '--protocol=socket', '--socket=/var/run/mysqld/mysqld.sock', '-uroot', 'rei88_test', '-e', $markerSql) | Out-Null

    $appMount = "type=bind,source=$appRoot,target=/repo/app,readonly"
    $testMount = "type=bind,source=$testFile,target=/test/carta-sigilo-mysql.test.php,readonly"
    $phpId = (Invoke-DockerSafe @(
        'create', '--name', $phpName, '--label', "$label=$runId", '--network', 'none',
        '--cpus', '1', '--memory', '512m', '--read-only', '--cap-drop', 'ALL',
        '--security-opt', 'no-new-privileges=true', '--tmpfs', '/tmp:rw,size=64m,noexec,nosuid,nodev',
        '--mount', $appMount, '--mount', $testMount,
        '--mount', "type=volume,source=$volumeName,target=/var/run/mysqld",
        '--env', 'REI88_TEST_DISPOSABLE=1', '--env', 'REI88_TEST_REPO_ROOT=/repo',
        '--env', "REI88_TEST_DISPOSABLE_MARKER=$marker",
        '--env', 'REI88_MYSQL_DSN=mysql:unix_socket=/var/run/mysqld/mysqld.sock;dbname=rei88_test;charset=utf8mb4',
        '--env', 'REI88_MYSQL_USER=root', '--entrypoint', 'sleep', $phpImage.Id, '300'
    ) | Select-Object -Last 1).Trim()
    if ($phpId -notmatch '^[a-f0-9]{64}$') { throw 'ID PHP inválido.' }
    $php = Get-OwnedContainer $phpId
    $phpMounts = @($php.Mounts)
    $phpPortCount = if ($php.HostConfig.PortBindings) { @($php.HostConfig.PortBindings.PSObject.Properties).Count } else { 0 }
    $phpInvalid = $php.Image -ne $phpImage.Id -or $php.HostConfig.NetworkMode -ne 'none' -or $php.HostConfig.Privileged -or $phpPortCount -ne 0 -or $phpMounts.Count -ne 3
    $phpInvalid = $phpInvalid -or -not $php.HostConfig.ReadonlyRootfs -or @($php.HostConfig.CapDrop) -notcontains 'ALL'
    $phpInvalid = $phpInvalid -or @($php.HostConfig.SecurityOpt) -notcontains 'no-new-privileges=true'
    $phpInvalid = $phpInvalid -or @($phpMounts | Where-Object { $_.Type -eq 'bind' -and $_.Destination -eq '/repo/app' -and -not $_.RW }).Count -ne 1
    $phpInvalid = $phpInvalid -or @($phpMounts | Where-Object { $_.Type -eq 'bind' -and $_.Destination -eq '/test/carta-sigilo-mysql.test.php' -and -not $_.RW }).Count -ne 1
    $phpInvalid = $phpInvalid -or @($phpMounts | Where-Object { $_.Type -eq 'volume' -and $_.Name -eq $volumeName -and $_.Destination -eq '/var/run/mysqld' }).Count -ne 1
    if ($phpInvalid) {
        throw 'Topologia PHP recusada: código somente leitura, socket próprio, sem rede/portas são obrigatórios.'
    }
    Invoke-DockerSafe @('start', $phpId) | Out-Null
    $result = Invoke-DockerSafe @('exec', $phpId, 'php', '/test/carta-sigilo-mysql.test.php')
    $result
} finally {
    Remove-OwnedContainer $phpId
    Remove-OwnedContainer $mysqlId
    if ($volumeCreated) {
        try {
            $volumeInfo = @((Invoke-DockerSafe @('volume', 'inspect', $volumeName)) | ConvertFrom-Json)[0]
            if ($volumeInfo.Labels.$label -ne $runId) { throw 'Identidade do volume não confere.' }
            $users = Invoke-DockerSafe @('ps', '-aq', '--no-trunc', '--filter', "volume=$volumeName")
            if (@($users | Where-Object { $_ }).Count -ne 0) { throw 'Volume ainda está montado em container.' }
            Invoke-DockerSafe @('volume', 'rm', $volumeName) | Out-Null
            $remainingVolumes = Invoke-DockerSafe @('volume', 'ls', '--quiet', '--filter', "label=$label=$runId")
            if (@($remainingVolumes | Where-Object { $_ }).Count -ne 0) {
                throw 'Volume etiquetado ainda existe após limpeza.'
            }
        } catch {
            $cleanupFailed = $true
            Write-Warning "Não removi volume sem comprovar a identidade e a ausência de usuários ($volumeName)."
        }
    }
    if ($cleanupFailed) { throw 'Limpeza dos recursos próprios não foi totalmente comprovada.' }
}
