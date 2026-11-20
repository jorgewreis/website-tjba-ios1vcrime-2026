[CmdletBinding()]
param()

$ErrorActionPreference = 'Stop'
$repoRoot = (Resolve-Path (Join-Path $PSScriptRoot '../..')).Path
$serviceFile = Join-Path $repoRoot 'app/services/InstituicoesPublicasService.php'
$repositoryFile = Join-Path $repoRoot 'app/repositories/InstituicoesPublicasRepository.php'
$testFile = Join-Path $PSScriptRoot 'rei149-mysql-isolated.test.php'
$mysqlImageRef = 'mysql@sha256:a81d043930e51745522bd5c430961cf8286f0a23f0a9a2a6e0108f7ea71b0902'
$phpImageRef = 'fokus-law-linux-qa@sha256:459cc8bd25b38009a8cf9a0ef5399d131f4f2001a1376d89389f673a92de430b'

if (Test-Path -LiteralPath (Join-Path $repoRoot '.env')) {
    throw 'Runner recusado: não execute fixtures em checkout com .env.'
}
foreach ($path in @($serviceFile, $repositoryFile, $testFile)) {
    $item = Get-Item -LiteralPath $path -Force
    if ($item.Attributes -band [IO.FileAttributes]::ReparsePoint) {
        throw 'Runner recusado: source contém reparse point.'
    }
}
foreach ($root in @((Join-Path $repoRoot 'app'), $PSScriptRoot)) {
    $reparseItems = Get-ChildItem -LiteralPath $root -Recurse -Force | Where-Object {
        $_.Attributes -band [IO.FileAttributes]::ReparsePoint
    }
    if ($reparseItems) { throw 'Runner recusado: source contém reparse point.' }
}
if (Get-ChildItem -LiteralPath (Join-Path $repoRoot 'app') -Recurse -Force | Where-Object { $_.Name -like '.env*' }) {
    throw 'Runner recusado: app contém arquivo de ambiente.'
}

function Invoke-DockerSafe([string[]]$dockerArgs) {
    $output = & docker @dockerArgs 2>&1
    if ($LASTEXITCODE -ne 0) { throw 'Comando Docker falhou; saída suprimida para evitar expor configuração.' }
    return $output
}

function Get-OwnedContainer([string]$containerId, [string]$label, [string]$runId) {
    if ($containerId -notmatch '^[a-f0-9]{64}$') { throw 'ID de container inválido.' }
    $container = @((Invoke-DockerSafe @('inspect', $containerId)) | ConvertFrom-Json)[0]
    if ($container.Id -ne $containerId -or $container.Config.Labels.$label -ne $runId) {
        throw 'Container não corresponde ao identificador descartável desta execução.'
    }
    return $container
}

function Remove-OwnedContainer([string]$containerId, [string]$label, [string]$runId) {
    if (-not $containerId) { return }
    $container = Get-OwnedContainer $containerId $label $runId
    if (-not $container.State.Running -and $container.State.Status -notin @('created', 'exited', 'dead')) {
        throw 'Estado do container descartável inesperado; limpeza interrompida.'
    }
    Invoke-DockerSafe @('rm', '--force', $containerId) | Out-Null
    $remaining = @((Invoke-DockerSafe @('ps', '-aq', '--no-trunc', '--filter', "id=$containerId")))
    if ($remaining | Where-Object { $_ }) { throw 'Container descartável permanece após remoção.' }
}

$contextName = (& docker context show 2>$null | Select-Object -Last 1).Trim()
if ($LASTEXITCODE -ne 0 -or $contextName -ne 'desktop-linux') {
    throw 'Runner recusado: somente o contexto local desktop-linux é permitido.'
}
$contextInfo = @((& docker context inspect $contextName 2>$null | ConvertFrom-Json))[0]
if ($LASTEXITCODE -ne 0 -or $contextInfo.Endpoints.docker.Host -ne 'npipe:////./pipe/dockerDesktopLinuxEngine') {
    throw 'Runner recusado: endpoint do Docker não é o pipe local esperado.'
}

$runId = [Guid]::NewGuid().ToString('N')
$marker = [Guid]::NewGuid().ToString('N')
$label = 'com.fokus.rei149-mysql-test'
$networkName = "rei149-net-$runId"
$dbName = "rei149-mysql-$runId"
$networkCreated = $false
$dbId = $null
$failure = $null

try {
    $mysqlImage = @((Invoke-DockerSafe @('image', 'inspect', $mysqlImageRef)) | ConvertFrom-Json)[0]
    $phpImage = @((Invoke-DockerSafe @('image', 'inspect', $phpImageRef)) | ConvertFrom-Json)[0]
    if (-not $mysqlImage.Id -or @($mysqlImage.RepoDigests) -notcontains $mysqlImageRef -or
        -not $phpImage.Id -or @($phpImage.RepoDigests) -notcontains $phpImageRef) {
        throw 'Imagens MySQL/PHP não correspondem aos digests fixados.'
    }

    Invoke-DockerSafe @('network', 'create', '--internal', '--label', "$label=$runId", $networkName) | Out-Null
    $networkCreated = $true
    $network = @((Invoke-DockerSafe @('network', 'inspect', $networkName)) | ConvertFrom-Json)[0]
    if ($network.Labels.$label -ne $runId -or -not $network.Internal) {
        throw 'Rede criada não está isolada ou não pertence à execução.'
    }

    $dbId = (Invoke-DockerSafe @(
        'run', '--detach', '--name', $dbName, '--label', "$label=$runId", '--network', $networkName,
        '--network-alias', 'mysql', '--cpus', '1', '--memory', '1g', '--pids-limit', '128',
        '--read-only', '--security-opt', 'no-new-privileges=true',
        '--tmpfs', '/var/lib/mysql:rw,size=512m', '--tmpfs', '/var/run/mysqld:rw,size=16m', '--tmpfs', '/tmp:rw,size=32m',
        '--env', 'MYSQL_ALLOW_EMPTY_PASSWORD=1', '--env', 'MYSQL_DATABASE=rei149_test',
        $mysqlImage.Id
    ) | Select-Object -Last 1).Trim()

    $db = Get-OwnedContainer $dbId $label $runId
    $dbHostname = [string]$db.Config.Hostname
    if ($dbHostname -notmatch '^[a-f0-9]{12,64}$') { throw 'Hostname MySQL descartável inesperado.' }
    $bindings = if ($db.HostConfig.PortBindings) { @($db.HostConfig.PortBindings.PSObject.Properties) } else { @() }
    $mounts = @($db.Mounts)
    $dbNetwork = $db.NetworkSettings.Networks.PSObject.Properties[$networkName].Value
    if ($db.Image -ne $mysqlImage.Id -or -not $db.HostConfig.ReadonlyRootfs -or $bindings.Count -gt 0 -or
        $mounts.Count -gt 0 -or -not $dbNetwork -or $db.HostConfig.NetworkMode -ne $networkName) {
        throw 'Container MySQL não respeita imagem, isolamento, filesystem ou ausência de portas esperados.'
    }

    $ready = $false
    for ($attempt = 0; $attempt -lt 60; $attempt++) {
        $db = Get-OwnedContainer $dbId $label $runId
        if ($db.State.Status -eq 'exited' -or $db.State.Status -eq 'dead') {
            throw 'MySQL descartável encerrou antes da prontidão.'
        }
        $ping = & docker exec --env MYSQL_PWD= $dbId mysqladmin ping -h 127.0.0.1 -uroot --silent 2>$null
        if ($LASTEXITCODE -eq 0) { $ready = $true; break }
        Start-Sleep -Seconds 1
    }
    if (-not $ready) { throw 'MySQL descartável não ficou pronto em 60 segundos.' }

$markerSql = "CREATE TABLE rei149_test_guard (marker CHAR(32) NOT NULL PRIMARY KEY) ENGINE=InnoDB; INSERT INTO rei149_test_guard (marker) VALUES ('$marker');"
    Invoke-DockerSafe @('exec', $dbId, 'mysql', '--protocol=socket', '--socket=/var/run/mysqld/mysqld.sock', '-uroot', 'rei149_test', '-e', $markerSql) | Out-Null

    $phpOutput = Invoke-DockerSafe @(
        'run', '--rm', '--network', $networkName, '--cpus', '1', '--memory', '512m', '--pids-limit', '128',
        '--read-only', '--security-opt', 'no-new-privileges=true', '--tmpfs', '/tmp:rw,size=32m',
        '--mount', "type=bind,source=$repoRoot,target=/workspace,readonly", '--workdir', '/workspace',
        '--env', 'REI149_DB_HOST=mysql', '--env', 'REI149_DB_NAME=rei149_test', '--env', 'REI149_DB_USER=root', '--env', 'REI149_DB_PASSWORD=',
        '--env', 'REI149_TEST_DISPOSABLE=1', '--env', 'REI149_TEST_REPO_ROOT=/workspace', '--env', "REI149_TEST_MARKER=$marker",
        '--env', "REI149_EXPECTED_DB_HOSTNAME=$dbHostname",
        $phpImage.Id, 'php', 'tests/instituicoes-publicas/rei149-mysql-isolated.test.php'
    )
    $phpOutput | ForEach-Object { Write-Output $_ }
} catch {
    $failure = $_
} finally {
    try { Remove-OwnedContainer $dbId $label $runId } catch { if (-not $failure) { $failure = $_ } else { Write-Warning 'Container cleanup could not be verified.' } }
    if ($networkCreated) {
        try {
            $network = @((Invoke-DockerSafe @('network', 'inspect', $networkName)) | ConvertFrom-Json)[0]
            if ($network.Labels.$label -ne $runId -or -not $network.Internal) { throw 'Rede descartável não corresponde à execução.' }
            Invoke-DockerSafe @('network', 'rm', $networkName) | Out-Null
            $remainingNetwork = @((Invoke-DockerSafe @('network', 'ls', '-q', '--filter', "name=^$networkName$")))
            if ($remainingNetwork | Where-Object { $_ }) { throw 'Rede descartável permanece após remoção.' }
        } catch { if (-not $failure) { $failure = $_ } else { Write-Warning 'Network cleanup could not be verified.' } }
    }
}

if ($failure) { throw $failure }
Write-Output 'CLEANUP: disposable container and internal network removed; no volume or published port was used.'