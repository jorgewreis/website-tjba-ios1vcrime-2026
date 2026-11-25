[CmdletBinding()]
param()

$ErrorActionPreference = 'Stop'
$repoRoot = (Resolve-Path (Join-Path $PSScriptRoot '../..')).Path
$testsRoot = Join-Path $repoRoot 'tests'
$testRoot = Join-Path $testsRoot 'pauta'
$appRoot = Join-Path $repoRoot 'app'
$testFile = Join-Path $testRoot 'rei77-inscricao-mysql.test.php'
$workerFile = Join-Path $testRoot 'rei77-inscricao-mysql-worker.php'
$mysqlImageRef = 'mysql@sha256:a81d043930e51745522bd5c430961cf8286f0a23f0a9a2a6e0108f7ea71b0902'
$phpImageRef = 'fokus-law-linux-qa@sha256:459cc8bd25b38009a8cf9a0ef5399d131f4f2001a1376d89389f673a92de430b'

# Refuse checkouts that could expose local application configuration to a
# container, even though only the app source is mounted and that mount is RO.
if (Test-Path -LiteralPath (Join-Path $repoRoot '.env')) {
    throw 'Runner recusado: checkout contém .env.'
}
foreach ($path in @($testsRoot, $testRoot, $appRoot, $testFile, $workerFile)) {
    $item = Get-Item -LiteralPath $path -Force
    if ($item.Attributes -band [IO.FileAttributes]::ReparsePoint) {
        throw 'Runner recusado: source do teste contém reparse point.'
    }
}
$sourceReparseItems = Get-ChildItem -LiteralPath $appRoot -Recurse -Force | Where-Object {
    $_.Attributes -band [IO.FileAttributes]::ReparsePoint
}
if ($sourceReparseItems) { throw 'Runner recusado: app contém reparse point.' }
$testReparseItems = Get-ChildItem -LiteralPath $testRoot -Recurse -Force | Where-Object {
    $_.Attributes -band [IO.FileAttributes]::ReparsePoint
}
if ($testReparseItems) { throw 'Runner recusado: tests/pauta contém reparse point.' }
$envItems = Get-ChildItem -LiteralPath $appRoot -Recurse -Force | Where-Object { $_.Name -like '.env*' }
if ($envItems) { throw 'Runner recusado: app contém arquivo de ambiente.' }

$contextName = (& docker context show 2>$null | Select-Object -Last 1).Trim()
if ($LASTEXITCODE -ne 0 -or $contextName -ne 'desktop-linux') {
    throw 'Runner recusado: somente contexto local desktop-linux.'
}
$dockerEndpoint = 'npipe:////./pipe/dockerDesktopLinuxEngine'
$contextInfo = @((& docker context inspect $contextName 2>$null | ConvertFrom-Json))[0]
if ($LASTEXITCODE -ne 0 -or $contextInfo.Endpoints.docker.Host -ne $dockerEndpoint) {
    throw 'Runner recusado: endpoint do Docker não é o pipe local Linux esperado.'
}

$runId = [Guid]::NewGuid().ToString('N').ToLowerInvariant()
$marker = ([Guid]::NewGuid().ToString('N') + [Guid]::NewGuid().ToString('N')).ToLowerInvariant()
$label = 'com.fokus.rei77-inscricao-isolated-test'
$dbName = "fokus-rei77-mysql-$runId"
$phpName = "fokus-rei77-php-$runId"
$socketVolume = "fokus-rei77-socket-$runId"
$mysqlId = $null
$phpId = $null
$sentinelAttempted = $false
$sentinelCreated = $false
$originalFailure = $null
$sentinelFailure = $null
$cleanupFailure = $null
$cleanupFailed = $false

function Invoke-DockerSafe([string[]]$argsList) {
    $output = & docker --host $dockerEndpoint @argsList 2>&1
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
        throw 'Container não pertence a esta execução.'
    }
    return $item
}

function Find-OwnedContainerByName([string]$name) {
    $raw = Invoke-DockerSafe @('ps', '-aq', '--no-trunc', '--filter', "label=$label=$runId", '--filter', "name=^/$name$")
    $ids = @($raw | Where-Object { $_ })
    if ($ids.Count -gt 1) { throw 'Identidade ambígua para container desta execução.' }
    if ($ids.Count -eq 0) { return $null }
    $item = Get-OwnedContainer ([string]$ids[0])
    if ($item.Name -ne "/$name") { throw 'Nome do container não confere.' }
    return [string]$item.Id
}

function Remove-OwnedContainer([string]$id) {
    if (-not $id) { return }
    try {
        $item = Get-OwnedContainer $id
        if ($item.State.Running) { Invoke-DockerSafe @('stop', $id) | Out-Null }
        Invoke-DockerSafe @('rm', $id) | Out-Null
        $remaining = Invoke-DockerSafe @('ps', '-aq', '--no-trunc', '--filter', "id=$id")
        if (@($remaining | Where-Object { $_ }).Count -ne 0) { throw 'Container ainda existe após limpeza.' }
    } catch {
        $script:cleanupFailed = $true
        Write-Warning "Não removi container sem comprovar propriedade/término ($id)."
    }
}

try {
    # Inspect only: never pull or build images. Both exact immutable references
    # and their RepoDigests must already exist locally.
    $mysqlImage = @((Invoke-DockerSafe @('image', 'inspect', $mysqlImageRef)) | ConvertFrom-Json)[0]
    $phpImage = @((Invoke-DockerSafe @('image', 'inspect', $phpImageRef)) | ConvertFrom-Json)[0]
    $mysqlDigestInvalid = (@($mysqlImage.RepoDigests) -notcontains $mysqlImageRef)
    $phpDigestInvalid = (@($phpImage.RepoDigests) -notcontains $phpImageRef)
    if (-not $mysqlImage.Id -or -not $phpImage.Id -or $mysqlDigestInvalid -or $phpDigestInvalid) {
        throw 'Runner recusado: imagens locais não correspondem aos digests fixados.'
    }

    Invoke-DockerSafe @('volume', 'create', '--label', "$label=$runId", $socketVolume) | Out-Null
    $volumeInfo = @((Invoke-DockerSafe @('volume', 'inspect', $socketVolume)) | ConvertFrom-Json)[0]
    if ($volumeInfo.Labels.$label -ne $runId) { throw 'Socket volume não pertence a esta execução.' }

    $mysqlId = (Invoke-DockerSafe @(
        'create', '--name', $dbName, '--label', "$label=$runId", '--network', 'none',
        '--cpus', '1', '--memory', '1g', '--tmpfs', '/var/lib/mysql:rw,size=512m',
        '--security-opt', 'no-new-privileges=true',
        '--mount', "type=volume,source=$socketVolume,target=/var/run/mysqld",
        '--env', 'MYSQL_ALLOW_EMPTY_PASSWORD=1', '--env', 'MYSQL_DATABASE=rei77_test',
        $mysqlImage.Id
    ) | Select-Object -Last 1).Trim()
    $mysql = Get-OwnedContainer $mysqlId
    $mysqlPortBindings = if ($mysql.HostConfig.PortBindings) { @($mysql.HostConfig.PortBindings.PSObject.Properties).Count } else { 0 }
    $mysqlDataTmpfs = $mysql.HostConfig.Tmpfs.PSObject.Properties['/var/lib/mysql'].Value
    $mysqlMounts = @($mysql.Mounts)
    $mysqlSocketMount = @($mysqlMounts | Where-Object {
        $_.Type -eq 'volume' -and $_.Name -eq $socketVolume -and $_.Destination -eq '/var/run/mysqld'
    })
    $mysqlInvalid = $mysql.Image -ne $mysqlImage.Id -or $mysql.HostConfig.NetworkMode -ne 'none' -or $mysql.HostConfig.Privileged
    $mysqlInvalid = $mysqlInvalid -or $mysqlPortBindings -ne 0 -or $mysqlMounts.Count -ne 1 -or $mysqlSocketMount.Count -ne 1
    $mysqlInvalid = $mysqlInvalid -or [string]::IsNullOrWhiteSpace([string]$mysqlDataTmpfs) -or $mysqlDataTmpfs -notmatch 'size=512m'
    $mysqlInvalid = $mysqlInvalid -or @($mysql.HostConfig.SecurityOpt) -notcontains 'no-new-privileges=true'
    if ($mysqlInvalid) { throw 'Topologia MySQL recusada: isolamento, datadir efêmero ou socket exclusivo inválido.' }
    Invoke-DockerSafe @('start', $mysqlId) | Out-Null

    $ready = $false
    for ($i = 0; $i -lt 90; $i++) {
        try {
            $probe = Invoke-DockerSafe @('exec', $mysqlId, 'mysql', '--protocol=socket', '--socket=/var/run/mysqld/mysqld.sock', '-uroot', 'rei77_test', '-N', '-e', 'SELECT 1')
            if (($probe | Select-Object -Last 1).Trim() -eq '1') { $ready = $true; break }
        } catch { }
        Start-Sleep -Seconds 1
    }
    if (-not $ready) { throw 'MySQL descartável não ficou pronto via socket.' }

    # This unpredictable per-run row is the test's internal fail-closed marker.
    $guardSql = "CREATE TABLE rei77_disposable_guard (run_id CHAR(32) NOT NULL PRIMARY KEY, marker CHAR(64) NOT NULL) ENGINE=InnoDB; INSERT INTO rei77_disposable_guard (run_id,marker) VALUES ('$runId','$marker');"
    $sentinelAttempted = $true
    Invoke-DockerSafe @('exec', $mysqlId, 'mysql', '--protocol=socket', '--socket=/var/run/mysqld/mysqld.sock', '-uroot', 'rei77_test', '-e', $guardSql) | Out-Null
    $sentinelCreated = $true

    $phpId = (Invoke-DockerSafe @(
        'create', '--name', $phpName, '--label', "$label=$runId", '--network', 'none',
        '--cpus', '1', '--memory', '512m', '--read-only', '--cap-drop', 'ALL',
        '--security-opt', 'no-new-privileges=true', '--tmpfs', '/tmp:rw,size=64m,noexec,nosuid,nodev',
        '--mount', "type=bind,source=$appRoot,target=/repo/app,readonly",
        '--mount', "type=bind,source=$testFile,target=/test/rei77-inscricao-mysql.test.php,readonly",
        '--mount', "type=bind,source=$workerFile,target=/test/rei77-inscricao-mysql-worker.php,readonly",
        '--mount', "type=volume,source=$socketVolume,target=/var/run/mysqld",
        '--env', 'REI77_TEST_DISPOSABLE=1', '--env', "REI77_TEST_RUN_ID=$runId",
        '--env', "REI77_TEST_DISPOSABLE_MARKER=$marker", '--env', 'REI77_MYSQL_DSN=mysql:unix_socket=/var/run/mysqld/mysqld.sock;dbname=rei77_test;charset=utf8mb4',
        '--env', 'REI77_MYSQL_USER=root', '--entrypoint', 'sleep', $phpImage.Id, '300'
    ) | Select-Object -Last 1).Trim()
    $php = Get-OwnedContainer $phpId
    $phpMounts = @($php.Mounts)
    $phpPortBindings = if ($php.HostConfig.PortBindings) { @($php.HostConfig.PortBindings.PSObject.Properties).Count } else { 0 }
    $phpInvalid = $php.Image -ne $phpImage.Id -or $php.HostConfig.NetworkMode -ne 'none' -or $php.HostConfig.Privileged
    $phpInvalid = $phpInvalid -or $phpPortBindings -ne 0 -or $phpMounts.Count -ne 4 -or -not $php.HostConfig.ReadonlyRootfs
    $phpInvalid = $phpInvalid -or @($php.HostConfig.CapDrop) -notcontains 'ALL' -or @($php.HostConfig.SecurityOpt) -notcontains 'no-new-privileges=true'
    $phpInvalid = $phpInvalid -or @($phpMounts | Where-Object { $_.Type -eq 'bind' -and $_.Destination -eq '/repo/app' -and -not $_.RW }).Count -ne 1
    $phpInvalid = $phpInvalid -or @($phpMounts | Where-Object { $_.Type -eq 'bind' -and $_.Destination -eq '/test/rei77-inscricao-mysql.test.php' -and -not $_.RW }).Count -ne 1
    $phpInvalid = $phpInvalid -or @($phpMounts | Where-Object { $_.Type -eq 'bind' -and $_.Destination -eq '/test/rei77-inscricao-mysql-worker.php' -and -not $_.RW }).Count -ne 1
    $phpInvalid = $phpInvalid -or @($phpMounts | Where-Object { $_.Type -eq 'volume' -and $_.Name -eq $socketVolume -and $_.Destination -eq '/var/run/mysqld' }).Count -ne 1
    $tmpfsTmp = $php.HostConfig.Tmpfs.PSObject.Properties['/tmp'].Value
    $phpInvalid = $phpInvalid -or [string]::IsNullOrWhiteSpace([string]$tmpfsTmp) -or $tmpfsTmp -notmatch 'size=64m'
    if ($phpInvalid) { throw 'Topologia PHP recusada: mount RO, socket e ausência de rede/portas obrigatórios.' }
    Invoke-DockerSafe @('start', $phpId) | Out-Null
    $testResult = Invoke-DockerSafe @('exec', $phpId, 'php', '/test/rei77-inscricao-mysql.test.php')
} catch {
    $originalFailure = $_
} finally {
    # Check the run sentinel while MySQL is still alive, including when the
    # PHP test failed. Keep this separate from teardown so both failures can
    # be reported without masking the original test error.
    if ($sentinelAttempted -and $mysqlId -match '^[a-f0-9]{64}$') {
        try {
            $sentinel = Invoke-DockerSafe @(
                'exec', $mysqlId, 'mysql', '--protocol=socket', '--socket=/var/run/mysqld/mysqld.sock',
                '-uroot', 'rei77_test', '-N', '-B', '-e', "SELECT CONCAT(run_id,':',marker) FROM rei77_disposable_guard WHERE run_id='$runId' AND marker='$marker'"
            )
            if (($sentinel | Select-Object -Last 1).Trim() -ne "$runId`:$marker") {
                throw 'Sentinela descartável não sobreviveu intacta ao teste.'
            }
            if (-not $sentinelCreated) {
                throw 'Sentinela apareceu apesar de a criação não ter sido confirmada.'
            }
        } catch {
            $sentinelFailure = $_
        }
    } elseif ($sentinelAttempted) {
        $sentinelFailure = [System.Management.Automation.ErrorRecord]::new(
            [InvalidOperationException]::new('Sentinela foi tentada, mas não há container MySQL próprio identificável para verificá-la.'),
            'Rei77SentinelUnverifiable',
            [System.Management.Automation.ErrorCategory]::InvalidOperation,
            $mysqlId
        )
    }

    try { if ($phpId -notmatch '^[a-f0-9]{64}$') { $phpId = Find-OwnedContainerByName $phpName } } catch { $cleanupFailed = $true; Write-Warning 'Não localizei com segurança o PHP descartável.' }
    try { if ($mysqlId -notmatch '^[a-f0-9]{64}$') { $mysqlId = Find-OwnedContainerByName $dbName } } catch { $cleanupFailed = $true; Write-Warning 'Não localizei com segurança o MySQL descartável.' }
    Remove-OwnedContainer $phpId
    Remove-OwnedContainer $mysqlId

    try {
        $remainingContainers = Invoke-DockerSafe @('ps', '-aq', '--no-trunc', '--filter', "label=$label=$runId")
        if (@($remainingContainers | Where-Object { $_ }).Count -ne 0) { throw 'Containers desta execução ainda existem.' }
        $ownedVolumes = @(Invoke-DockerSafe @('volume', 'ls', '--quiet', '--filter', "label=$label=$runId") | Where-Object { $_ })
        if ($ownedVolumes.Count -gt 1) { throw 'Mais de um volume etiquetado nesta execução.' }
        if ($ownedVolumes.Count -eq 1) {
            if ([string]$ownedVolumes[0] -ne $socketVolume) { throw 'Volume etiquetado não tem o nome esperado.' }
            $volume = @((Invoke-DockerSafe @('volume', 'inspect', $socketVolume)) | ConvertFrom-Json)[0]
            if ($volume.Labels.$label -ne $runId) { throw 'Ownership do volume não confere.' }
            $users = Invoke-DockerSafe @('ps', '-aq', '--no-trunc', '--filter', "volume=$socketVolume")
            if (@($users | Where-Object { $_ }).Count -ne 0) { throw 'Socket volume continua montado.' }
            Invoke-DockerSafe @('volume', 'rm', $socketVolume) | Out-Null
        }
        $remainingVolumes = Invoke-DockerSafe @('volume', 'ls', '--quiet', '--filter', "label=$label=$runId")
        if (@($remainingVolumes | Where-Object { $_ }).Count -ne 0) { throw 'Volume desta execução ainda existe após limpeza.' }
    } catch {
        $cleanupFailed = $true
        Write-Warning 'Limpeza do volume descartável não foi comprovada; recurso foi preservado.'
    }
    if ($cleanupFailed) {
        $cleanupFailure = [System.Management.Automation.ErrorRecord]::new(
            [InvalidOperationException]::new('Limpeza dos recursos próprios não foi totalmente comprovada.'),
            'Rei77CleanupUnverified',
            [System.Management.Automation.ErrorCategory]::InvalidOperation,
            $runId
        )
    }
}

if ($originalFailure) {
    if ($sentinelFailure) { Write-Error -ErrorRecord $sentinelFailure -ErrorAction Continue }
    if ($cleanupFailure) { Write-Error -ErrorRecord $cleanupFailure -ErrorAction Continue }
    $PSCmdlet.ThrowTerminatingError($originalFailure)
}
if ($sentinelFailure) {
    if ($cleanupFailure) { Write-Error -ErrorRecord $cleanupFailure -ErrorAction Continue }
    $PSCmdlet.ThrowTerminatingError($sentinelFailure)
}
if ($cleanupFailure) { $PSCmdlet.ThrowTerminatingError($cleanupFailure) }
$testResult
