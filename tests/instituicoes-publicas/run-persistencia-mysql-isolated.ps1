[CmdletBinding()]
param()

$ErrorActionPreference = 'Stop'
$repoRoot = (Resolve-Path (Join-Path $PSScriptRoot '../..')).Path
$testFile = Join-Path $PSScriptRoot 'persistencia-mysql.test.php'
$mysqlImageRef = 'mysql@sha256:0426ec38c7a10aa45ba383887df7878f74ee70e2fd589c7b69207f3577901903'
$phpImageRef = 'fokus-law-linux-qa@sha256:459cc8bd25b38009a8cf9a0ef5399d131f4f2001a1376d89389f673a92de430b'
$label = 'com.fokus.rei70-mysql-test'

if (Test-Path -LiteralPath (Join-Path $repoRoot '.env')) {
    throw 'Runner recusado: checkout possui .env.'
}
foreach ($relative in @('app', 'public', 'tests/instituicoes-publicas/persistencia-mysql.test.php')) {
    $path = Join-Path $repoRoot $relative
    $item = Get-Item -LiteralPath $path -Force
    if ($item.Attributes -band [IO.FileAttributes]::ReparsePoint) { throw "Runner recusado: reparse point em $relative." }
}
foreach ($relative in @('app', 'public', 'tests/instituicoes-publicas')) {
    $tree = Join-Path $repoRoot $relative
    $unsafe = Get-ChildItem -LiteralPath $tree -Recurse -Force | Where-Object {
        ($_.Attributes -band [IO.FileAttributes]::ReparsePoint) -or ($_.Name -like '.env*' -and $_.Name -ne '.env.example')
    }
    if ($unsafe) { throw "Runner recusado: reparse point ou arquivo de ambiente em $relative." }
}

$contextName = (& docker context show 2>$null | Select-Object -Last 1).Trim()
if ($LASTEXITCODE -ne 0 -or $contextName -ne 'desktop-linux') { throw 'Runner recusado: contexto Docker local esperado não está ativo.' }
$context = @((& docker context inspect $contextName 2>$null | ConvertFrom-Json))[0]
if ($LASTEXITCODE -ne 0 -or $context.Endpoints.docker.Host -ne 'npipe:////./pipe/dockerDesktopLinuxEngine') {
    throw 'Runner recusado: endpoint Docker não é o pipe local esperado.'
}

$runId = [Guid]::NewGuid().ToString('N')
$networkName = "fokus-rei70-mysql-$runId"
$mysqlName = "fokus-rei70-db-$runId"
$phpName = "fokus-rei70-php-$runId"
$marker = ([Guid]::NewGuid().ToString('N') + [Guid]::NewGuid().ToString('N')).ToLowerInvariant()
$dbPassword = ([Guid]::NewGuid().ToString('N') + [Guid]::NewGuid().ToString('N'))
$mysqlId = $null
$phpId = $null
$networkCreated = $false
$cleanupFailed = $false

function Invoke-Docker([string[]]$arguments) {
    $output = & docker @arguments 2>&1
    if ($LASTEXITCODE -ne 0) { throw "Docker falhou em '$($arguments[0])' (código $LASTEXITCODE): $($output -join ' ')" }
    return $output
}

function Get-OwnedContainer([string]$id) {
    if ($id -notmatch '^[a-f0-9]{64}$') { throw 'ID de container inválido.' }
    $result = Invoke-Docker @('inspect', $id)
    $item = @($result | ConvertFrom-Json)[0]
    $runLabel = $item.Config.Labels.PSObject.Properties[$label].Value
    if (-not $item -or $runLabel -ne $runId) { throw 'Container não pertence a esta execução.' }
    return $item
}

function Remove-OwnedContainer([string]$id) {
    if (-not $id) { return }
    try {
        $item = Get-OwnedContainer $id
        if ($item.State.Running) { Invoke-Docker @('stop', $id) | Out-Null }
        Invoke-Docker @('rm', $id) | Out-Null
        $remaining = Invoke-Docker @('ps', '-aq', '--no-trunc', '--filter', "id=$id")
        if (@($remaining | Where-Object { $_ }).Count -ne 0) { throw 'Container permanece após remoção.' }
    } catch {
        $script:cleanupFailed = $true
        Write-Warning "Limpeza não comprovada para container $id."
    }
}

try {
    $mysqlImage = @((Invoke-Docker @('image', 'inspect', $mysqlImageRef)) | ConvertFrom-Json)[0]
    $phpImage = @((Invoke-Docker @('image', 'inspect', $phpImageRef)) | ConvertFrom-Json)[0]
    $imageInvalid = -not $mysqlImage.Id -or -not $phpImage.Id -or (@($mysqlImage.RepoDigests) -notcontains $mysqlImageRef) -or (@($phpImage.RepoDigests) -notcontains $phpImageRef)
    if ($imageInvalid) {
        throw 'Imagens MySQL/PHP não correspondem aos digests fixados.'
    }

    Invoke-Docker @('network', 'create', '--internal', '--label', "$label=$runId", $networkName) | Out-Null
    $networkCreated = $true
    $network = @((Invoke-Docker @('network', 'inspect', $networkName)) | ConvertFrom-Json)[0]
    if (-not $network.Internal -or $network.Labels.PSObject.Properties[$label].Value -ne $runId) {
        throw 'Rede interna descartável não confere.'
    }

    $mysqlId = (Invoke-Docker @(
        'create', '--name', $mysqlName, '--label', "$label=$runId", '--network', $networkName,
        '--network-alias', 'rei70-mysql', '--cpus', '1', '--memory', '1g',
        '--tmpfs', '/var/lib/mysql:rw,size=512m', '--security-opt', 'no-new-privileges=true',
        '--env', "MYSQL_ROOT_PASSWORD=$dbPassword", '--env', 'MYSQL_ROOT_HOST=%', '--env', 'MYSQL_DATABASE=instituicoes_test', $mysqlImage.Id
    ) | Select-Object -Last 1).Trim()
    if ($mysqlId -notmatch '^[a-f0-9]{64}$') { throw 'ID MySQL inválido.' }
    $mysql = Get-OwnedContainer $mysqlId
    $mysqlMounts = @($mysql.Mounts)
    $mysqlPorts = @($mysql.HostConfig.PortBindings.PSObject.Properties | Where-Object { $_.MemberType -eq 'NoteProperty' }).Count
    $mysqlTmpfs = $mysql.HostConfig.Tmpfs.PSObject.Properties['/var/lib/mysql'].Value
    $mysqlInvalid = $mysql.Image -ne $mysqlImage.Id -or $mysql.HostConfig.NetworkMode -ne $networkName -or $mysql.HostConfig.Privileged -or $mysqlPorts -ne 0 -or $mysqlMounts.Count -ne 0 -or $mysqlTmpfs -notmatch 'size=512m' -or (@($mysql.HostConfig.SecurityOpt) -notcontains 'no-new-privileges=true')
    if ($mysqlInvalid) {
        throw 'Topologia MySQL recusada: rede privada, sem portas, datadir tmpfs e sem privilégio são obrigatórios.'
    }
    Invoke-Docker @('start', $mysqlId) | Out-Null

    $databaseReady = $false
    $probeDiagnostic = ''
    $probeExitCode = -1
    for ($i = 0; $i -lt 90; $i++) {
        $probe = $null
        try {
            $probe = & docker exec --env "MYSQL_PWD=$dbPassword" $mysqlId mysql --protocol=SOCKET -uroot --database=instituicoes_test --batch --skip-column-names -e 'SELECT 1' 2>&1
            $probeExitCode = $LASTEXITCODE
            $probeDiagnostic = (($probe | ForEach-Object { [string]$_ }) -join ' ')
        } catch { }
        if ($probeExitCode -eq 0 -and ($probe | Select-Object -Last 1).Trim() -eq '1') { $databaseReady = $true; break }
        Start-Sleep -Seconds 1
    }
    if (-not $databaseReady) {
        $previousErrorActionPreference = $ErrorActionPreference
        $ErrorActionPreference = 'Continue'
        try {
            $mysqlLogs = & docker logs --tail 60 $mysqlId 2>&1
            $logExitCode = $LASTEXITCODE
        } finally {
            $ErrorActionPreference = $previousErrorActionPreference
        }
        $mysqlLogText = (($mysqlLogs | ForEach-Object { [string]$_ }) -join ' ')
        if ($dbPassword) { $mysqlLogText = $mysqlLogText.Replace($dbPassword, '[REDACTED]') }
        if ($dbPassword) { $probeDiagnostic = $probeDiagnostic.Replace($dbPassword, '[REDACTED]') }
        throw "MySQL descartável não ficou pronto (probe exit=$probeExitCode; docker logs exit=$logExitCode). Última sonda: $probeDiagnostic. Logs limitados: $mysqlLogText"
    }
    $tables = Invoke-Docker @('exec', '--env', "MYSQL_PWD=$dbPassword", $mysqlId, 'mysql', '--protocol=SOCKET', '-uroot', '--database=instituicoes_test', '--batch', '--skip-column-names', '-e', 'SHOW TABLES')
    if (@($tables | Where-Object { $_ }).Count -ne 0) { throw 'Schema de teste não estava vazio antes da sentinela.' }
    $markerSql = "CREATE TABLE _sandbox_proof (token CHAR(64) NOT NULL PRIMARY KEY) ENGINE=InnoDB; INSERT INTO _sandbox_proof (token) VALUES ('$marker');"
    Invoke-Docker @('exec', '--env', "MYSQL_PWD=$dbPassword", $mysqlId, 'mysql', '--protocol=SOCKET', '-uroot', '--database=instituicoes_test', '-e', $markerSql) | Out-Null

    $repoMount = "type=bind,source=$repoRoot,target=/repo,readonly"
    $phpId = (Invoke-Docker @(
        'create', '--name', $phpName, '--label', "$label=$runId", '--network', $networkName,
        '--cpus', '1', '--memory', '512m', '--read-only', '--cap-drop', 'ALL',
        '--security-opt', 'no-new-privileges=true', '--tmpfs', '/tmp:rw,size=128m,noexec,nosuid,nodev',
        '--mount', $repoMount, '--env', 'MYSQL_TEST_DSN=mysql:host=rei70-mysql;port=3306;dbname=instituicoes_test;charset=utf8mb4',
        '--env', 'MYSQL_TEST_USER=root', '--env', "MYSQL_TEST_PASSWORD=$dbPassword", '--env', "MYSQL_TEST_MARKER=$marker",
        '--entrypoint', 'sleep', $phpImage.Id, '300'
    ) | Select-Object -Last 1).Trim()
    if ($phpId -notmatch '^[a-f0-9]{64}$') { throw 'ID PHP inválido.' }
    $php = Get-OwnedContainer $phpId
    $phpMounts = @($php.Mounts)
    $phpPorts = @($php.HostConfig.PortBindings.PSObject.Properties | Where-Object { $_.MemberType -eq 'NoteProperty' }).Count
    $phpInvalid = $php.Image -ne $phpImage.Id -or $php.HostConfig.NetworkMode -ne $networkName -or $php.HostConfig.Privileged -or $phpPorts -ne 0 -or $phpMounts.Count -ne 1 -or $phpMounts[0].Type -ne 'bind' -or $phpMounts[0].Destination -ne '/repo' -or $phpMounts[0].RW -or -not $php.HostConfig.ReadonlyRootfs -or (@($php.HostConfig.CapDrop) -notcontains 'ALL') -or (@($php.HostConfig.SecurityOpt) -notcontains 'no-new-privileges=true')
    if ($phpInvalid) {
        throw 'Topologia PHP recusada: código somente leitura, rede privada e sem portas são obrigatórios.'
    }
    Invoke-Docker @('start', $phpId) | Out-Null
    $testResult = Invoke-Docker @('exec', $phpId, 'php', '/repo/tests/instituicoes-publicas/persistencia-mysql.test.php')
    $testResult
} finally {
    Remove-OwnedContainer $phpId
    Remove-OwnedContainer $mysqlId
    if ($networkCreated) {
        try {
            $networkInfo = @((Invoke-Docker @('network', 'inspect', $networkName)) | ConvertFrom-Json)[0]
            $networkLabel = $networkInfo.Labels.PSObject.Properties[$label].Value
            $remaining = @($networkInfo.Containers.PSObject.Properties | Where-Object { $_.MemberType -eq 'NoteProperty' }).Count
            if ($networkLabel -ne $runId -or $remaining -ne 0) { throw 'Rede não pertence somente à execução.' }
            Invoke-Docker @('network', 'rm', $networkName) | Out-Null
            if ((& docker network ls -q --filter "name=^$networkName$").Count -ne 0) { throw 'Rede persiste após remoção.' }
        } catch {
            $cleanupFailed = $true
            Write-Warning "Limpeza não comprovada para rede $networkName."
        }
    }
    if ($cleanupFailed) { throw 'Limpeza dos recursos efêmeros não foi comprovada.' }
}
