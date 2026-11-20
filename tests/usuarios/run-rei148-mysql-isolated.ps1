[CmdletBinding()]
param([ValidateSet(0, 1)][int]$LowerCaseTableNames = 0)

$ErrorActionPreference = 'Stop'
$repoRoot = (Resolve-Path (Join-Path $PSScriptRoot '../..')).Path
$appRoot = Join-Path $repoRoot 'app'
$configRoot = Join-Path $repoRoot 'config'
$configFile = Join-Path $repoRoot 'config/config.php'
$securityFile = Join-Path $repoRoot 'config/security.php'
$testFile = Join-Path $PSScriptRoot 'rei148-mysql.test.php'
$mysqlImageRef = 'mysql@sha256:a81d043930e51745522bd5c430961cf8286f0a23f0a9a2a6e0108f7ea71b0902'
$phpImageRef = 'fokus-law-linux-qa@sha256:459cc8bd25b38009a8cf9a0ef5399d131f4f2001a1376d89389f673a92de430b'
if (Test-Path -LiteralPath (Join-Path $repoRoot '.env')) {
    throw 'Runner recusado: não execute a fixture em checkout com .env.'
}
foreach ($path in @($appRoot, $configRoot, $configFile, $securityFile, $testFile)) {
    $item = Get-Item -LiteralPath $path -Force
    if ($item.Attributes -band [IO.FileAttributes]::ReparsePoint) {
        throw 'Runner recusado: source com reparse point.'
    }
}
$repoPrefix = $repoRoot.TrimEnd([IO.Path]::DirectorySeparatorChar, [IO.Path]::AltDirectorySeparatorChar) + [IO.Path]::DirectorySeparatorChar
$resolvedConfigRoot = (Resolve-Path -LiteralPath $configRoot).Path
if (-not $resolvedConfigRoot.StartsWith($repoPrefix, [StringComparison]::OrdinalIgnoreCase)) {
    throw 'Runner recusado: config resolvido fora da raiz do repositório.'
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
$dockerEndpoint = 'npipe:////./pipe/dockerDesktopLinuxEngine'
$contextInfo = @((& docker context inspect $contextName 2>$null | ConvertFrom-Json))[0]
if ($LASTEXITCODE -ne 0 -or $contextInfo.Endpoints.docker.Host -ne $dockerEndpoint) {
    throw 'Runner recusado: endpoint do Docker não é o pipe local esperado.'
}

$runId = [Guid]::NewGuid().ToString('N')
$marker = ([Guid]::NewGuid().ToString('N') + [Guid]::NewGuid().ToString('N')).ToLowerInvariant()
$label = 'com.fokus.rei148-usuarios-test'
$dbName = "fokus-rei88-mysql-$runId"
$phpName = "fokus-rei88-php-$runId"
$volumeName = "fokus-rei88-socket-$runId"
$tempRoot = (Resolve-Path -LiteralPath $env:TEMP).Path
$configFixtureRoot = Join-Path $tempRoot "rei148-usuarios-config-$runId"
$mysqlId = $null
$phpId = $null
$cleanupFailed = $false
$volumeCreateAttempted = $false
$volumeCreateConfirmed = $false
$mysqlCreateAttempted = $false
$phpCreateAttempted = $false

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

function Find-OwnedContainerByName([string]$name) {
    $raw = Invoke-DockerSafe @(
        'ps', '-aq', '--no-trunc', '--filter', "label=$label=$runId", '--filter', "name=$name"
    )
    $ids = @($raw | Where-Object { $_ })
    if ($ids.Count -gt 1) { throw 'Mais de um container corresponde ao nome descartável desta execução.' }
    if ($ids.Count -eq 0) { return $null }
    $item = Get-OwnedContainer ([string]$ids[0])
    if ($item.Name -ne "/$name") { throw 'Nome do container descartável não corresponde à execução.' }
    return [string]$item.Id
}

try {
    $mysqlImage = @((Invoke-DockerSafe @('image', 'inspect', $mysqlImageRef)) | ConvertFrom-Json)[0]
    $phpImage = @((Invoke-DockerSafe @('image', 'inspect', $phpImageRef)) | ConvertFrom-Json)[0]
    $imageInvalid = -not $mysqlImage.Id -or -not $phpImage.Id -or (@($mysqlImage.RepoDigests) -notcontains $mysqlImageRef) -or (@($phpImage.RepoDigests) -notcontains $phpImageRef)
    if ($imageInvalid) { throw 'Imagens MySQL/PHP não correspondem aos digests aprovados.' }

    if (Test-Path -LiteralPath $configFixtureRoot) { throw 'Diretório temporário da configuração já existe.' }
    New-Item -ItemType Directory -Path $configFixtureRoot | Out-Null
    Copy-Item -LiteralPath $configFile -Destination (Join-Path $configFixtureRoot 'config.php')
    Copy-Item -LiteralPath $securityFile -Destination (Join-Path $configFixtureRoot 'security.php')

    $volumeCreateAttempted = $true
    Invoke-DockerSafe @('volume', 'create', '--label', "$label=$runId", $volumeName) | Out-Null
    $volumeCreateConfirmed = $true
    $volumeInfo = @((Invoke-DockerSafe @('volume', 'inspect', $volumeName)) | ConvertFrom-Json)[0]
    if ($volumeInfo.Labels.$label -ne $runId) { throw 'Identidade do volume descartável não confere.' }

    $mysqlCreateAttempted = $true
    $mysqlId = (Invoke-DockerSafe @(
        'create', '--name', $dbName, '--label', "$label=$runId", '--network', 'none',
        '--cpus', '1', '--memory', '1g', '--tmpfs', '/var/lib/mysql:rw,size=512m',
        '--security-opt', 'no-new-privileges=true',
        '--mount', "type=volume,source=$volumeName,target=/var/run/mysqld",
        '--env', 'MYSQL_ALLOW_EMPTY_PASSWORD=1', '--env', 'MYSQL_DATABASE=rei88_test',
        $mysqlImage.Id, "--lower-case-table-names=$LowerCaseTableNames"
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
    $dbInvalid = $dbInvalid -or [string]::IsNullOrWhiteSpace([string]$dbTmpfs)
    $dbInvalid = $dbInvalid -or $dbTmpfs -notmatch 'size=512m'
    $dbInvalid = $dbInvalid -or @($db.HostConfig.SecurityOpt) -notcontains 'no-new-privileges=true'
    if ($dbInvalid) {
        throw 'Topologia MySQL recusada: sem rede/portas, datadir efêmero e socket exclusivo são obrigatórios.'
    }
    Invoke-DockerSafe @('start', $mysqlId) | Out-Null

    $ready = $false
    for ($i = 0; $i -lt 90; $i++) {
        $probe = $null
        try {
            $probe = Invoke-DockerSafe @(
                'exec', $mysqlId, 'mysql', '--protocol=socket', '--socket=/var/run/mysqld/mysqld.sock',
                '-uroot', 'rei88_test', '-N', '-e', 'SELECT 1'
            )
        } catch { }
        if ($LASTEXITCODE -eq 0 -and ($probe | Select-Object -Last 1).Trim() -eq '1') {
            $ready = $true
            break
        }
        Start-Sleep -Seconds 1
    }
    if (-not $ready) { throw 'MySQL descartável ou banco de teste não ficou pronto.' }

    $markerSql = "CREATE TABLE rei88_test_guard (marker CHAR(64) NOT NULL PRIMARY KEY) ENGINE=InnoDB; INSERT INTO rei88_test_guard (marker) VALUES ('$marker');"
    Invoke-DockerSafe @('exec', $mysqlId, 'mysql', '--protocol=socket', '--socket=/var/run/mysqld/mysqld.sock', '-uroot', 'rei88_test', '-e', $markerSql) | Out-Null

    $appMount = "type=bind,source=$appRoot,target=/repo/app,readonly"
    $configMount = "type=bind,source=$configFixtureRoot,target=/repo/config,readonly"
    $testMount = "type=bind,source=$testFile,target=/test/rei148-mysql.test.php,readonly"
    $phpCreateAttempted = $true
    $phpId = (Invoke-DockerSafe @(
        'create', '--name', $phpName, '--label', "$label=$runId", '--network', 'none',
        '--cpus', '1', '--memory', '512m', '--read-only', '--cap-drop', 'ALL',
        '--security-opt', 'no-new-privileges=true', '--tmpfs', '/tmp:rw,size=64m,noexec,nosuid,nodev',
        '--mount', $appMount, '--mount', $configMount, '--mount', $testMount,
        '--mount', "type=volume,source=$volumeName,target=/var/run/mysqld",
        '--env', "REI148_EXPECT_LOWER_CASE=$LowerCaseTableNames", '--env', 'REI88_TEST_DISPOSABLE=1', '--env', 'REI88_TEST_REPO_ROOT=/repo',
        '--env', "REI88_TEST_DISPOSABLE_MARKER=$marker",
        '--env', 'REI88_MYSQL_DSN=mysql:unix_socket=/var/run/mysqld/mysqld.sock;dbname=rei88_test;charset=utf8mb4',
        '--env', 'REI88_MYSQL_USER=root', '--env', 'APP_ENV=fixture',
        '--env', 'DB_HOST=127.0.0.1', '--env', 'DB_NAME=rei88_test', '--env', 'DB_USER=fixture', '--env', 'DB_PASS=fixture-only-unused', '--env', 'DB_PORT=1',
        '--entrypoint', 'sleep', $phpImage.Id, '300'
    ) | Select-Object -Last 1).Trim()
    if ($phpId -notmatch '^[a-f0-9]{64}$') { throw 'ID PHP inválido.' }
    $php = Get-OwnedContainer $phpId
    $phpMounts = @($php.Mounts)
    $phpPortCount = if ($php.HostConfig.PortBindings) { @($php.HostConfig.PortBindings.PSObject.Properties).Count } else { 0 }
    $phpInvalid = $php.Image -ne $phpImage.Id -or $php.HostConfig.NetworkMode -ne 'none' -or $php.HostConfig.Privileged
    $phpInvalid = $phpInvalid -or $phpPortCount -ne 0 -or $phpMounts.Count -ne 4
    $phpInvalid = $phpInvalid -or -not $php.HostConfig.ReadonlyRootfs -or @($php.HostConfig.CapDrop) -notcontains 'ALL'
    $phpInvalid = $phpInvalid -or @($php.HostConfig.SecurityOpt) -notcontains 'no-new-privileges=true'
    $phpInvalid = $phpInvalid -or @($phpMounts | Where-Object { $_.Type -eq 'bind' -and $_.Destination -eq '/repo/app' -and -not $_.RW }).Count -ne 1
    $phpInvalid = $phpInvalid -or @($phpMounts | Where-Object { $_.Type -eq 'bind' -and $_.Destination -eq '/repo/config' -and -not $_.RW }).Count -ne 1
    $phpInvalid = $phpInvalid -or @($phpMounts | Where-Object { $_.Type -eq 'bind' -and $_.Destination -eq '/test/rei148-mysql.test.php' -and -not $_.RW }).Count -ne 1
    $phpInvalid = $phpInvalid -or @($phpMounts | Where-Object { $_.Type -eq 'volume' -and $_.Name -eq $volumeName -and $_.Destination -eq '/var/run/mysqld' }).Count -ne 1
    if ($phpInvalid) {
        throw 'Topologia PHP recusada: código somente leitura, socket próprio, sem rede/portas são obrigatórios.'
    }
    Invoke-DockerSafe @('start', $phpId) | Out-Null
    $result = Invoke-DockerSafe @('exec', $phpId, 'php', '-d', 'error_log=/tmp/rei160-test.log', '/test/rei148-mysql.test.php')
    $result
} finally {
    try {
        if ($phpId -notmatch '^[a-f0-9]{64}$') {
            $phpId = Find-OwnedContainerByName $phpName
            if ($phpCreateAttempted -and -not $phpId) { $cleanupFailed = $true; Write-Warning 'Criação PHP teve resultado indeterminado; ausência tardia não comprovada.' }
        }
    } catch {
        $cleanupFailed = $true
        Write-Warning 'Não foi possível localizar com segurança o container PHP desta execução.'
    }
    try {
        if ($mysqlId -notmatch '^[a-f0-9]{64}$') {
            $mysqlId = Find-OwnedContainerByName $dbName
            if ($mysqlCreateAttempted -and -not $mysqlId) { $cleanupFailed = $true; Write-Warning 'Criação MySQL teve resultado indeterminado; ausência tardia não comprovada.' }
        }
    } catch {
        $cleanupFailed = $true
        Write-Warning 'Não foi possível localizar com segurança o container MySQL desta execução.'
    }
    Remove-OwnedContainer $phpId
    Remove-OwnedContainer $mysqlId
    try {
        $ownedVolumes = @(Invoke-DockerSafe @('volume', 'ls', '--quiet', '--filter', "label=$label=$runId") | Where-Object { $_ })
        if ($volumeCreateAttempted -and -not $volumeCreateConfirmed -and $ownedVolumes.Count -eq 0) {
            $cleanupFailed = $true
            Write-Warning 'Criação do volume teve resultado indeterminado; ausência tardia não comprovada.'
        }
        if ($ownedVolumes.Count -gt 1) { throw 'Mais de um volume corresponde a esta execução.' }
        if ($ownedVolumes.Count -eq 1) {
            if ([string]$ownedVolumes[0] -ne $volumeName) { throw 'Nome do volume não corresponde à execução.' }
            $volumeInfo = @((Invoke-DockerSafe @('volume', 'inspect', $volumeName)) | ConvertFrom-Json)[0]
            if ($volumeInfo.Labels.$label -ne $runId) { throw 'Identidade do volume não confere.' }
            $users = Invoke-DockerSafe @('ps', '-aq', '--no-trunc', '--filter', "volume=$volumeName")
            if (@($users | Where-Object { $_ }).Count -ne 0) { throw 'Volume ainda está montado em container.' }
            Invoke-DockerSafe @('volume', 'rm', $volumeName) | Out-Null
        }
        $remainingVolumes = Invoke-DockerSafe @('volume', 'ls', '--quiet', '--filter', "label=$label=$runId")
        if (@($remainingVolumes | Where-Object { $_ }).Count -ne 0) {
            throw 'Volume etiquetado ainda existe após limpeza.'
        }
    } catch {
        $cleanupFailed = $true
        Write-Warning "Não removi volume sem comprovar a identidade e a ausência de usuários ($volumeName)."
    }
    try {
        if (Test-Path -LiteralPath $configFixtureRoot) {
            $actualConfigRoot = (Resolve-Path -LiteralPath $configFixtureRoot).Path
            $tempPrefix = $tempRoot.TrimEnd([IO.Path]::DirectorySeparatorChar, [IO.Path]::AltDirectorySeparatorChar) + [IO.Path]::DirectorySeparatorChar
            $configIsUnderTemp = $actualConfigRoot.StartsWith($tempPrefix, [StringComparison]::OrdinalIgnoreCase)
            $configIsReparse = ((Get-Item -LiteralPath $actualConfigRoot -Force).Attributes -band [IO.FileAttributes]::ReparsePoint) -ne 0
            if (-not $configIsUnderTemp -or $configIsReparse) {
                throw 'Diretório temporário de configuração saiu do TEMP ou virou reparse point.'
            }
            Remove-Item -LiteralPath $actualConfigRoot -Recurse -Force
            if (Test-Path -LiteralPath $actualConfigRoot) { throw 'Diretório temporário de configuração ainda existe.' }
        }
    } catch {
        $cleanupFailed = $true
        Write-Warning 'Não foi possível remover com segurança a fixture temporária de configuração.'
    }
    if ($cleanupFailed) { throw 'Limpeza dos recursos próprios não foi totalmente comprovada.' }
}
