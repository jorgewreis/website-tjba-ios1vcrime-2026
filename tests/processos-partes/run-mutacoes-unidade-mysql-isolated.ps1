[CmdletBinding()]
param()

$ErrorActionPreference = 'Stop'
$repoRoot = (Resolve-Path (Join-Path $PSScriptRoot '../..')).Path
$appRoot = Join-Path $repoRoot 'app'
$migrationScript = Join-Path $repoRoot 'scripts/migrate-process-responsible-sector.php'
$endpointRoot = Join-Path $repoRoot 'public/api/processos-partes'
$responsibleEndpointRoot = Join-Path $repoRoot 'public/api/processos'
$testFile = Join-Path $PSScriptRoot 'mutacoes-unidade-mysql.test.php'
$mysqlImageRef = 'mysql@sha256:a81d043930e51745522bd5c430961cf8286f0a23f0a9a2a6e0108f7ea71b0902'
$phpImageRef = 'fokus-law-linux-qa@sha256:459cc8bd25b38009a8cf9a0ef5399d131f4f2001a1376d89389f673a92de430b'

if (Test-Path -LiteralPath (Join-Path $repoRoot '.env')) {
    throw 'Runner recusado: .env presente na raiz do checkout.'
}
foreach ($path in @($appRoot, $migrationScript, $endpointRoot, $responsibleEndpointRoot, $testFile)) {
    $item = Get-Item -LiteralPath $path -Force -ErrorAction Stop
    if ($item.Attributes -band [IO.FileAttributes]::ReparsePoint) {
        throw 'Runner recusado: fonte ou fixture é reparse point.'
    }
    $resolved = (Resolve-Path -LiteralPath $path).Path
    $prefix = $repoRoot.TrimEnd([IO.Path]::DirectorySeparatorChar, [IO.Path]::AltDirectorySeparatorChar) + [IO.Path]::DirectorySeparatorChar
    if (-not $resolved.StartsWith($prefix, [StringComparison]::OrdinalIgnoreCase)) {
        throw 'Runner recusado: caminho resolvido fora da raiz do repositório.'
    }
}
$reparseItems = @(
    Get-ChildItem -LiteralPath $appRoot -Recurse -Force | Where-Object { $_.Attributes -band [IO.FileAttributes]::ReparsePoint }
    Get-ChildItem -LiteralPath $endpointRoot -Recurse -Force | Where-Object { $_.Attributes -band [IO.FileAttributes]::ReparsePoint }
    Get-ChildItem -LiteralPath $responsibleEndpointRoot -Recurse -Force | Where-Object { $_.Attributes -band [IO.FileAttributes]::ReparsePoint }
)
if ($reparseItems.Count -gt 0) { throw 'Runner recusado: app/endpoints contêm reparse point.' }
$envItems = @(
    Get-ChildItem -LiteralPath $appRoot -Recurse -Force | Where-Object { $_.Name -like '.env*' }
    Get-ChildItem -LiteralPath $endpointRoot -Recurse -Force | Where-Object { $_.Name -like '.env*' }
    Get-ChildItem -LiteralPath $responsibleEndpointRoot -Recurse -Force | Where-Object { $_.Name -like '.env*' }
)
if ($envItems.Count -gt 0) { throw 'Runner recusado: fonte contém arquivo de ambiente.' }

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
$randomBytes = [byte[]]::new(32)
$rng = [Security.Cryptography.RandomNumberGenerator]::Create()
$rng.GetBytes($randomBytes)
$rng.Dispose()
$password = [BitConverter]::ToString($randomBytes).Replace('-', '').ToLowerInvariant()
$label = 'com.fokus.rei69-mutations-test'
$dbName = "fokus-rei69-mysql-$runId"
$phpName = "fokus-rei69-php-$runId"
$volumeName = "fokus-rei69-socket-$runId"
$mysqlId = $null
$phpId = $null
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
        Write-Warning "Não removi container sem comprovar identidade e término ($id)."
    }
}

function Find-OwnedContainerByName([string]$name) {
    $raw = Invoke-DockerSafe @('ps', '-aq', '--no-trunc', '--filter', "label=$label=$runId", '--filter', "name=$name")
    $ids = @($raw | Where-Object { $_ })
    if ($ids.Count -gt 1) { throw 'Mais de um container corresponde ao nome desta execução.' }
    if ($ids.Count -eq 0) { return $null }
    $item = Get-OwnedContainer ([string]$ids[0])
    if ($item.Name -ne "/$name") { throw 'Nome do container não corresponde à execução.' }
    return [string]$item.Id
}

try {
    $mysqlImage = @((Invoke-DockerSafe @('image', 'inspect', $mysqlImageRef)) | ConvertFrom-Json)[0]
    $phpImage = @((Invoke-DockerSafe @('image', 'inspect', $phpImageRef)) | ConvertFrom-Json)[0]
    $imageInvalid = -not $mysqlImage.Id -or -not $phpImage.Id
    $imageInvalid = $imageInvalid -or (@($mysqlImage.RepoDigests) -notcontains $mysqlImageRef)
    $imageInvalid = $imageInvalid -or (@($phpImage.RepoDigests) -notcontains $phpImageRef)
    if ($imageInvalid) { throw 'Imagens MySQL/PHP não correspondem aos digests aprovados; o runner não fará pull.' }

    Invoke-DockerSafe @('volume', 'create', '--label', "$label=$runId", $volumeName) | Out-Null
    $volumeInfo = @((Invoke-DockerSafe @('volume', 'inspect', $volumeName)) | ConvertFrom-Json)[0]
    if ($volumeInfo.Labels.$label -ne $runId) { throw 'Identidade do volume descartável não confere.' }

    $mysqlId = (Invoke-DockerSafe @(
        'create', '--name', $dbName, '--label', "$label=$runId", '--network', 'none',
        '--cpus', '1', '--memory', '1g', '--tmpfs', '/var/lib/mysql:rw,size=512m',
        '--security-opt', 'no-new-privileges=true',
        '--mount', "type=volume,source=$volumeName,target=/var/run/mysqld",
        '--env', "MYSQL_ROOT_PASSWORD=$password", '--env', 'MYSQL_DATABASE=rei69_test',
        $mysqlImage.Id
    ) | Select-Object -Last 1).Trim()
    if ($mysqlId -notmatch '^[a-f0-9]{64}$') { throw 'ID MySQL inválido.' }
    $db = Get-OwnedContainer $mysqlId
    $dbMounts = @($db.Mounts)
    $dbPortCount = if ($db.HostConfig.PortBindings) { @($db.HostConfig.PortBindings.PSObject.Properties).Count } else { 0 }
    $dbTmpfs = $db.HostConfig.Tmpfs.PSObject.Properties['/var/lib/mysql'].Value
    $dbSocketMounts = @($dbMounts | Where-Object { $_.Type -eq 'volume' -and $_.Name -eq $volumeName -and $_.Destination -eq '/var/run/mysqld' })
    $dbInvalid = $db.Image -ne $mysqlImage.Id -or $db.HostConfig.NetworkMode -ne 'none' -or $db.HostConfig.Privileged
    $dbInvalid = $dbInvalid -or $dbPortCount -ne 0 -or $dbMounts.Count -ne 1 -or $dbSocketMounts.Count -ne 1
    $dbInvalid = $dbInvalid -or [string]::IsNullOrWhiteSpace([string]$dbTmpfs) -or $dbTmpfs -notmatch 'size=512m'
    $dbInvalid = $dbInvalid -or @($db.HostConfig.SecurityOpt) -notcontains 'no-new-privileges=true'
    if ($dbInvalid) { throw 'Topologia MySQL recusada: sem rede/portas, datadir tmpfs e socket exclusivo.' }
    Invoke-DockerSafe @('start', $mysqlId) | Out-Null

    $ready = $false
    $lastProbeExit = $null
    $lastProbeOutput = ''
    for ($i = 0; $i -lt 90; $i++) {
        $ErrorActionPreference = 'Continue'
        $probe = & docker --host $dockerEndpoint exec $mysqlId sh -c 'MYSQL_PWD="$MYSQL_ROOT_PASSWORD" mysql --protocol=socket --socket=/var/run/mysqld/mysqld.sock -uroot rei69_test -N -e SELECT\ 1' 2>&1
        $probeExit = $LASTEXITCODE
        $ErrorActionPreference = 'Stop'
        $lastProbeExit = $probeExit
        $lastProbeOutput = [string]::Join(' ', @($probe | ForEach-Object { [string] $_ })).Trim()
        if ($probeExit -eq 0 -and ($probe | Select-Object -Last 1).Trim() -eq '1') { $ready = $true; break }
        Start-Sleep -Seconds 1
    }
    if (-not $ready) { throw "MySQL descartável não ficou pronto; probe exit=$lastProbeExit output='$lastProbeOutput'." }

    $markerSql = "CREATE TABLE rei69_test_guard (marker CHAR(64) NOT NULL PRIMARY KEY) ENGINE=InnoDB; INSERT INTO rei69_test_guard (marker) VALUES ('$marker');"
    $markerSqlBase64 = [Convert]::ToBase64String([Text.Encoding]::UTF8.GetBytes($markerSql))
    Invoke-DockerSafe @(
        'exec', '--env', "REI69_MARKER_SQL_B64=$markerSqlBase64", $mysqlId, 'sh', '-c',
        'printf %s "$REI69_MARKER_SQL_B64" | base64 -d | MYSQL_PWD="$MYSQL_ROOT_PASSWORD" mysql --protocol=socket --socket=/var/run/mysqld/mysqld.sock -uroot rei69_test'
    ) | Out-Null

    # Nenhuma configuração de produção é montada. O teste gera stubs sintéticos em /tmp.
    $appMount = "type=bind,source=$appRoot,target=/repo/app,readonly"
    $migrationMount = "type=bind,source=$migrationScript,target=/repo/scripts/migrate-process-responsible-sector.php,readonly"
    $endpointMount = "type=bind,source=$endpointRoot,target=/repo/public/api/processos-partes,readonly"
    $responsibleEndpointMount = "type=bind,source=$responsibleEndpointRoot,target=/repo/public/api/processos,readonly"
    $testMount = "type=bind,source=$testFile,target=/test/mutacoes-unidade-mysql.test.php,readonly"
    $phpId = (Invoke-DockerSafe @(
        'create', '--name', $phpName, '--label', "$label=$runId", '--network', 'none',
        '--cpus', '1', '--memory', '512m', '--read-only', '--cap-drop', 'ALL',
        '--security-opt', 'no-new-privileges=true', '--tmpfs', '/tmp:rw,size=128m,noexec,nosuid,nodev',
        '--mount', $appMount, '--mount', $migrationMount, '--mount', $endpointMount, '--mount', $responsibleEndpointMount, '--mount', $testMount,
        '--mount', "type=volume,source=$volumeName,target=/var/run/mysqld",
        '--env', 'REI69_TEST_DISPOSABLE=1', '--env', 'REI69_TEST_REPO_ROOT=/repo',
        '--env', "REI69_TEST_MARKER=$marker",
        '--env', 'REI69_MYSQL_DSN=mysql:unix_socket=/var/run/mysqld/mysqld.sock;dbname=rei69_test;charset=utf8mb4',
        '--env', 'REI69_MYSQL_USER=root', '--env', "REI69_MYSQL_PASSWORD=$password",
        '--entrypoint', 'sleep', $phpImage.Id, '300'
    ) | Select-Object -Last 1).Trim()
    if ($phpId -notmatch '^[a-f0-9]{64}$') { throw 'ID PHP inválido.' }
    $php = Get-OwnedContainer $phpId
    $phpMounts = @($php.Mounts)
    $phpPortCount = if ($php.HostConfig.PortBindings) { @($php.HostConfig.PortBindings.PSObject.Properties).Count } else { 0 }
    $phpInvalid = $php.Image -ne $phpImage.Id -or $php.HostConfig.NetworkMode -ne 'none' -or $php.HostConfig.Privileged
    $phpInvalid = $phpInvalid -or $phpPortCount -ne 0 -or $phpMounts.Count -ne 6
    $phpInvalid = $phpInvalid -or -not $php.HostConfig.ReadonlyRootfs -or @($php.HostConfig.CapDrop) -notcontains 'ALL'
    $phpInvalid = $phpInvalid -or @($php.HostConfig.SecurityOpt) -notcontains 'no-new-privileges=true'
    $phpInvalid = $phpInvalid -or @($phpMounts | Where-Object { $_.Type -eq 'bind' -and $_.Destination -eq '/repo/app' -and -not $_.RW }).Count -ne 1
    $phpInvalid = $phpInvalid -or @($phpMounts | Where-Object { $_.Type -eq 'bind' -and $_.Destination -eq '/repo/scripts/migrate-process-responsible-sector.php' -and -not $_.RW }).Count -ne 1
    $phpInvalid = $phpInvalid -or @($phpMounts | Where-Object { $_.Type -eq 'bind' -and $_.Destination -eq '/repo/public/api/processos-partes' -and -not $_.RW }).Count -ne 1
    $phpInvalid = $phpInvalid -or @($phpMounts | Where-Object { $_.Type -eq 'bind' -and $_.Destination -eq '/repo/public/api/processos' -and -not $_.RW }).Count -ne 1
    $phpInvalid = $phpInvalid -or @($phpMounts | Where-Object { $_.Type -eq 'bind' -and $_.Destination -eq '/test/mutacoes-unidade-mysql.test.php' -and -not $_.RW }).Count -ne 1
    $phpInvalid = $phpInvalid -or @($phpMounts | Where-Object { $_.Type -eq 'volume' -and $_.Name -eq $volumeName -and $_.Destination -eq '/var/run/mysqld' }).Count -ne 1
    if ($phpInvalid) { throw 'Topologia PHP recusada: código readonly, socket descartável, sem rede/portas.' }
    Invoke-DockerSafe @('start', $phpId) | Out-Null
    $output = Invoke-DockerSafe @('exec', $phpId, 'php', '/test/mutacoes-unidade-mysql.test.php')
    $output
} catch {
    if ($mysqlId -match '^[a-f0-9]{64}$') {
        try {
            $logs = Invoke-DockerSafe @('logs', '--tail', '80', $mysqlId)
            $safeLogs = [string]::Join("`n", @($logs | ForEach-Object { [string] $_ }))
            if ($password) { $safeLogs = $safeLogs.Replace($password, '[REDACTED]') }
            Write-Warning "Diagnóstico MySQL descartável (últimas 80 linhas):`n$safeLogs"
        } catch {
            Write-Warning 'Não foi possível coletar logs do container MySQL próprio.'
        }
    }
    throw
} finally {
    try { if ($phpId -notmatch '^[a-f0-9]{64}$') { $phpId = Find-OwnedContainerByName $phpName } }
    catch { $cleanupFailed = $true; Write-Warning 'Não foi possível localizar com segurança o container PHP.' }
    try { if ($mysqlId -notmatch '^[a-f0-9]{64}$') { $mysqlId = Find-OwnedContainerByName $dbName } }
    catch { $cleanupFailed = $true; Write-Warning 'Não foi possível localizar com segurança o container MySQL.' }
    Remove-OwnedContainer $phpId
    Remove-OwnedContainer $mysqlId
    try {
        $ownedVolumes = @(Invoke-DockerSafe @('volume', 'ls', '--quiet', '--filter', "label=$label=$runId") | Where-Object { $_ })
        if ($ownedVolumes.Count -gt 1) { throw 'Mais de um volume corresponde à execução.' }
        if ($ownedVolumes.Count -eq 1) {
            if ([string]$ownedVolumes[0] -ne $volumeName) { throw 'Nome do volume não corresponde à execução.' }
            $volumeInfo = @((Invoke-DockerSafe @('volume', 'inspect', $volumeName)) | ConvertFrom-Json)[0]
            if ($volumeInfo.Labels.$label -ne $runId) { throw 'Identidade do volume não confere.' }
            $users = Invoke-DockerSafe @('ps', '-aq', '--no-trunc', '--filter', "volume=$volumeName")
            if (@($users | Where-Object { $_ }).Count -ne 0) { throw 'Volume ainda está montado em container.' }
            Invoke-DockerSafe @('volume', 'rm', $volumeName) | Out-Null
        }
        $remaining = Invoke-DockerSafe @('volume', 'ls', '--quiet', '--filter', "label=$label=$runId")
        if (@($remaining | Where-Object { $_ }).Count -ne 0) { throw 'Volume etiquetado ainda existe.' }
    } catch { $cleanupFailed = $true; Write-Warning "Limpeza do volume próprio não foi comprovada ($volumeName)." }
    $password = $null
    $randomBytes = $null
    if ($cleanupFailed) { throw 'Limpeza dos recursos descartáveis não foi totalmente comprovada.' }
}
