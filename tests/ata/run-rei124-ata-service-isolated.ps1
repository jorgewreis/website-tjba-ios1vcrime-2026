[CmdletBinding()]
param([string]$QaImageId = '')

$ErrorActionPreference = 'Stop'
$repoRoot = (Resolve-Path (Join-Path $PSScriptRoot '../..')).Path
$appRoot = Join-Path $repoRoot 'app'
. (Join-Path $PSScriptRoot 'rei124-mount-guard.ps1')
$testFile = Join-Path $PSScriptRoot 'rei124-ata-service-mysql.test.php'
$httpFile = Join-Path $PSScriptRoot 'rei124-ata-http-mysql.test.php'
$publicRoot = Join-Path $repoRoot 'public'
$configRoot = Join-Path $repoRoot 'config'
if ($env:DOCKER_HOST -or $env:DOCKER_CONTEXT) { throw 'Selecao de daemon por ambiente recusada.' }
$dockerContext = (& docker context show).Trim()
if ($LASTEXITCODE -ne 0 -or -not $dockerContext) { throw 'Contexto Docker indisponivel.' }
$context = @((& docker context inspect $dockerContext 2>$null | ConvertFrom-Json))[0]
if ($LASTEXITCODE -ne 0 -or -not $context) { throw 'Contexto Docker invalido.' }
$dockerHost = [string]$context.Endpoints.docker.Host
if ($dockerHost -notin @('npipe:////./pipe/dockerDesktopLinuxEngine','unix:///var/run/docker.sock')) { throw 'Daemon Docker nao local recusado.' }
$mysqlImage = 'mysql@sha256:0426ec38c7a10aa45ba383887df7878f74ee70e2fd589c7b69207f3577901903'
$phpImage = 'fokus-law-linux-qa@sha256:459cc8bd25b38009a8cf9a0ef5399d131f4f2001a1376d89389f673a92de430b'
if ($QaImageId -ne '') {
    if ($QaImageId -notmatch '^sha256:[a-f0-9]{64}$') { throw 'ID de imagem QA invalido.' }
    $phpImage = $QaImageId
}
$runId = [Guid]::NewGuid().ToString('N')
$marker = ([Guid]::NewGuid().ToString('N') + [Guid]::NewGuid().ToString('N')).ToLowerInvariant()
$password = [Guid]::NewGuid().ToString('N')
$label = 'com.fokus.rei124ata-mysql-test'
$databaseName = "fokus-rei124ata-mysql-$runId"
$phpName = "fokus-rei124ata-php-$runId"
$mysqlId = $null
$phpId = $null
$cleanupFailed = $false
$creationPending = $false
$diagnosticStage = 'preparacao'

function Invoke-DockerSafe([string[]]$arguments) {
    $output = & docker --host $dockerHost @arguments 2>&1
    if ($LASTEXITCODE -ne 0) { throw "Docker falhou na etapa $diagnosticStage (codigo $LASTEXITCODE); detalhes suprimidos." }
    return $output
}

function Remove-OwnedContainer([string]$id) {
    if ($id -notmatch '^[a-f0-9]{64}$') { return }
    try {
        $info = @((Invoke-DockerSafe @('inspect', $id)) | ConvertFrom-Json)[0]
        if ($info.Config.Labels.$label -ne $runId) { throw 'Etiqueta do container nÃ£o confere.' }
        if (@($info.HostConfig.PortBindings.PSObject.Properties).Count -gt 0) { throw 'Container inesperadamente publicou porta.' }
        Invoke-DockerSafe @('rm', '--force', $id) | Out-Null
        $left = @(Invoke-DockerSafe @('ps', '-aq', '--no-trunc', '--filter', "id=$id"))
        if ($left.Count -gt 0) { throw 'Container permaneceu apÃ³s remoÃ§Ã£o.' }
    } catch {
        $script:cleanupFailed = $true
        Write-Warning "NÃ£o foi possÃ­vel confirmar a limpeza do container prÃ³prio ($id)."
    }
}

try {
    if (Test-Path -LiteralPath (Join-Path $repoRoot '.env')) { throw 'Runner recusado: checkout contÃ©m .env.' }
    foreach ($path in @($appRoot, $testFile, $httpFile, $publicRoot, $configRoot)) {
        $item = Get-Item -LiteralPath $path -Force
        if ($item.Attributes -band [IO.FileAttributes]::ReparsePoint) { throw 'Runner recusado: app ou teste contÃ©m reparse point.' }
    }
    foreach ($root in @($appRoot, $publicRoot, $configRoot)) {
        if (Get-ChildItem -LiteralPath $root -Recurse -Force | Where-Object { $_.Name -like '.env*' -or ($_.Attributes -band [IO.FileAttributes]::ReparsePoint) }) {
            throw 'Runner recusado: origem contem arquivo de ambiente ou reparse point.'
        }
    }
    $mysqlImageId = (Invoke-DockerSafe @('image', 'inspect', '--format', '{{.Id}}', $mysqlImage) | Select-Object -Last 1).Trim()
    $phpImageId = (Invoke-DockerSafe @('image', 'inspect', '--format', '{{.Id}}', $phpImage) | Select-Object -Last 1).Trim()
    if (-not $mysqlImageId -or -not $phpImageId) { throw 'Imagem Docker necessÃ¡ria ausente.' }

    $creationPending = $true
    $mysqlId = (Invoke-DockerSafe @('create', '--name', $databaseName, '--label', "$label=$runId", '--network', 'none', '--memory', '1g', '--cpus', '1', '--tmpfs', '/var/lib/mysql:rw,size=512m', '--tmpfs', '/var/run/mysqld:rw,size=32m', '--env', "MYSQL_ROOT_PASSWORD=$password", '--env', 'MYSQL_ROOT_HOST=%', '--env', 'MYSQL_DATABASE=rei124ata_test', $mysqlImageId) | Select-Object -Last 1).Trim()
    if ($mysqlId -notmatch '^[a-f0-9]{64}$') { throw 'ID MySQL descartavel invalido.' }
    $creationPending = $false
    $mysqlInfo = @((Invoke-DockerSafe @('inspect', $mysqlId)) | ConvertFrom-Json)[0]
    if ($mysqlInfo.Config.Labels.$label -ne $runId -or $mysqlInfo.HostConfig.NetworkMode -ne 'none' -or @($mysqlInfo.HostConfig.PortBindings.PSObject.Properties).Count -ne 0 -or $mysqlInfo.HostConfig.Privileged -or -not $mysqlInfo.HostConfig.Tmpfs.PSObject.Properties['/var/lib/mysql']) { throw 'Topologia MySQL descartÃ¡vel recusada.' }
    Invoke-DockerSafe @('start', $mysqlId) | Out-Null
    $ready = $false
    $diagnosticStage = 'mysql-prontidao'
    # O servidor temporario de inicializacao usa socket; aguarde TCP no namespace isolado.
    for ($i = 0; $i -lt 90; $i++) {
        try { Invoke-DockerSafe @('exec', '--env', "MYSQL_PWD=$password", $mysqlId, 'mysql', '--protocol=TCP', '--host=127.0.0.1', '-uroot', 'rei124ata_test', '-N', '-e', 'SELECT 1') | Out-Null; $ready = $true; break } catch { Start-Sleep -Seconds 1 }
    }
    if (-not $ready) { throw 'MySQL 8.4 descartÃ¡vel nÃ£o iniciou.' }
    $diagnosticStage = 'mysql-sentinela'
    Invoke-DockerSafe @('exec', '--env', "MYSQL_PWD=$password", $mysqlId, 'mysql', '--protocol=socket', '--socket=/var/run/mysqld/mysqld.sock', '-uroot', 'rei124ata_test', '-e', "CREATE TABLE rei124ata_test_guard (marker CHAR(64) NOT NULL PRIMARY KEY) ENGINE=InnoDB; INSERT INTO rei124ata_test_guard VALUES ('$marker');") | Out-Null

    $diagnosticStage = 'php-preparacao'
    $appMount = "type=bind,source=$appRoot,target=/repo/app,readonly"
    $testMount = "type=bind,source=$testFile,target=/repo/tests/ata/rei124-ata-service-mysql.test.php,readonly"
    $httpMount = "type=bind,source=$httpFile,target=/repo/tests/ata/rei124-ata-http-mysql.test.php,readonly"
    $publicMount = "type=bind,source=$publicRoot,target=/repo/public,readonly"
    $configMount = "type=bind,source=$configRoot,target=/repo/config,readonly"
    $creationPending = $true
    $phpId = (Invoke-DockerSafe @('create', '--name', $phpName, '--label', "$label=$runId", '--network', "container:$mysqlId", '--memory', '768m', '--cpus', '1', '--read-only', '--cap-drop', 'ALL', '--security-opt', 'no-new-privileges=true', '--tmpfs', '/tmp:rw,size=128m,noexec,nosuid,nodev', '--tmpfs', '/repo/storage:rw,size=64m,noexec,nosuid,nodev', '--mount', $appMount, '--mount', $testMount, '--mount', $httpMount, '--mount', $publicMount, '--mount', $configMount, '--env', 'DB_HOST=127.0.0.1', '--env', 'DB_NAME=rei124ata_test', '--env', 'DB_USER=root', '--env', "DB_PASS=$password", '--env', 'REI124ATA_TEST_DISPOSABLE=1', '--env', "REI124ATA_TEST_MARKER=$marker", '--entrypoint', 'sleep', $phpImageId, '300') | Select-Object -Last 1).Trim()
    if ($phpId -notmatch '^[a-f0-9]{64}$') { throw 'ID PHP descartavel invalido.' }
    $creationPending = $false
    $phpInfo = @((Invoke-DockerSafe @('inspect', $phpId)) | ConvertFrom-Json)[0]
    if ($phpInfo.Config.Labels.$label -ne $runId -or $phpInfo.HostConfig.NetworkMode -ne "container:$mysqlId" -or @($phpInfo.HostConfig.PortBindings.PSObject.Properties).Count -ne 0 -or -not $phpInfo.HostConfig.ReadonlyRootfs -or @($phpInfo.HostConfig.CapDrop) -notcontains 'ALL') { throw 'Topologia PHP descartavel recusada.' }
    Assert-Rei124AtaMounts $phpInfo
    Invoke-DockerSafe @('start', $phpId) | Out-Null
    $diagnosticStage = 'php-servico'
    Invoke-DockerSafe @('exec', '--env', 'REI124ATA_TEST_DISPOSABLE=1', '--env', "REI124ATA_TEST_MARKER=$marker", '--env', 'REI124ATA_MYSQL_DSN=mysql:host=127.0.0.1;dbname=rei124ata_test;charset=utf8mb4', '--env', 'REI124ATA_MYSQL_USER=root', '--env', "REI124ATA_MYSQL_PASSWORD=$password", $phpId, 'php', '/repo/tests/ata/rei124-ata-service-mysql.test.php')
    $diagnosticStage = 'php-http-setup'
    Invoke-DockerSafe @('exec', $phpId, 'php', '/repo/tests/ata/rei124-ata-http-mysql.test.php', 'setup')
    $diagnosticStage = 'php-http-servidor'
    Invoke-DockerSafe @('exec', '-d', $phpId, 'php', '-d', 'display_errors=0', '-S', '127.0.0.1:18766', '-t', '/repo/public') | Out-Null
    $diagnosticStage = 'php-http-check'
    Invoke-DockerSafe @('exec', $phpId, 'php', '/repo/tests/ata/rei124-ata-http-mysql.test.php', 'check')
} finally {
    if ($creationPending) {
        # Uma criacao pode terminar apos a consulta; o resultado permanece incerto.
        $cleanupFailed = $true
        try {
            $found = @(Invoke-DockerSafe @('ps', '-aq', '--no-trunc', '--filter', "label=$label=$runId") | Where-Object { $_ })
            if (@($found | Where-Object { $_ -notmatch '^[a-f0-9]{64}$' }).Count -ne 0) { throw 'Lista de candidatos invalida.' }
            $candidates = @($found | Where-Object { $_ -notin @($mysqlId, $phpId) } | Select-Object -Unique)
            if ($candidates.Count -eq 1) { Remove-OwnedContainer $candidates[0] }
        } catch { Write-Warning 'Identidade de recursos incertos nao comprovada.' }
        Write-Warning 'Criacao sem resposta: estado final incerto; nao declarar sucesso.'
    }
    Remove-OwnedContainer $phpId
    Remove-OwnedContainer $mysqlId
    if ($cleanupFailed) { throw 'Limpeza do ambiente descartÃ¡vel REI-124 ATA nÃ£o foi comprovada.' }
}
