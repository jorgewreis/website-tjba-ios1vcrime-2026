[CmdletBinding()]
param([ValidateSet(0, 1)][int]$LowerCaseTableNames = 0, [switch]$CiLinux, [string]$QaImageId)

$ErrorActionPreference = 'Stop'
if (-not $CiLinux -or -not $IsLinux -or $env:GITHUB_ACTIONS -ne 'true') { throw 'Somente CI Linux descartável autorizado.' }
# GITHUB_ACTIONS is a mode check, not proof of hosted-runner provenance.
foreach ($origin in @($PSScriptRoot, (Join-Path $PSScriptRoot '../..'), (Join-Path $PSScriptRoot '../../config'))) {
    $cursor = [IO.Path]::GetFullPath($origin)
    while ($cursor) {
        $component = Get-Item -LiteralPath $cursor -Force
        if ($component.Attributes -band [IO.FileAttributes]::ReparsePoint) { throw 'Ancestral contém reparse point.' }
        $parent = [IO.Directory]::GetParent($cursor)
        $cursor = if ($parent) { $parent.FullName } else { $null }
    }
}
$repoRoot = (Resolve-Path (Join-Path $PSScriptRoot '../..')).Path
$appRoot = Join-Path $repoRoot 'app'

$testFile = Join-Path $PSScriptRoot 'rei69-diretos-mysql.test.php'
$inertConfig = Join-Path $PSScriptRoot 'inert-config.php'
$securityConfig = Join-Path $repoRoot 'config/security.php'
$endpointRoot = Join-Path $repoRoot 'public/api/processos-partes'
$endpointFiles = @('create.php','update.php','delete.php') | ForEach-Object { Join-Path $endpointRoot $_ }
foreach ($origin in $endpointFiles) {
    $cursor = [IO.Path]::GetFullPath($origin)
    while ($cursor) {
        $component = Get-Item -LiteralPath $cursor -Force
        if ($component.Attributes -band [IO.FileAttributes]::ReparsePoint) { throw 'Ancestral do endpoint contém reparse point.' }
        $parent = [IO.Directory]::GetParent($cursor)
        $cursor = if ($parent) { $parent.FullName } else { $null }
    }
}
$mysqlImageRef = 'mysql@sha256:0426ec38c7a10aa45ba383887df7878f74ee70e2fd589c7b69207f3577901903'
if ($QaImageId -notmatch '^sha256:[a-f0-9]{64}$') { throw 'ID de imagem CI explicito obrigatorio.' }
$phpImageRef = $QaImageId
if (Test-Path -LiteralPath (Join-Path $repoRoot '.env')) {
    throw 'Runner recusado: não execute a fixture em checkout com .env.'
}
foreach ($path in (@($appRoot, $testFile, $inertConfig, $securityConfig) + $endpointFiles)) {
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
if ($LASTEXITCODE -ne 0) { throw 'Contexto Docker indisponível.' }
$contextInfo = @((& docker context inspect $contextName 2>$null | ConvertFrom-Json))[0]
$dockerEndpoint = 'unix:///var/run/docker.sock'
if ($LASTEXITCODE -ne 0 -or $contextInfo.Endpoints.docker.Host -ne $dockerEndpoint) {
    throw 'Runner recusado: endpoint Docker local esperado não confere.'
}

$runId = [Guid]::NewGuid().ToString('N')
$marker = ([Guid]::NewGuid().ToString('N') + [Guid]::NewGuid().ToString('N')).ToLowerInvariant()
$label = 'com.fokus.rei69diretos-pauta-test'
$dbName = "fokus-rei69diretos-mysql-$runId"
$phpName = "fokus-rei69diretos-php-$runId"
$volumeName = "fokus-rei69diretos-socket-$runId"

$mysqlId = $null
$phpId = $null
$cleanupFailed = $false
$volumeCreateAttempted = $false
$volumeCreateConfirmed = $false
$mysqlCreateAttempted = $false
$phpCreateAttempted = $false
$stage = 'initialization'

function Invoke-DockerSafe([string[]]$argsList) {
    $output = & docker --host $dockerEndpoint @argsList 2>&1
    if ($LASTEXITCODE -ne 0) {
        throw "Operação Docker falhou (código $LASTEXITCODE)."
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

function Assert-VolumeConsumers([string[]]$expected) {
    $v = @((Invoke-DockerSafe @('volume', 'inspect', $volumeName)) | ConvertFrom-Json)[0]
    $optionsCount = if ($null -eq $v.Options) { 0 } else { @($v.Options.PSObject.Properties).Count }
    if ($v.Name -ne $volumeName -or $v.Driver -ne 'local' -or $optionsCount -ne 0 -or $v.Labels.$label -ne $runId) { throw 'Identidade do volume inválida.' }
    $actual = @(Invoke-DockerSafe @('ps', '-aq', '--no-trunc', '--filter', "volume=$volumeName") | Where-Object { $_ } | Sort-Object)
    $wanted = @($expected | Sort-Object)
    if (($actual -join ',') -ne ($wanted -join ',')) { throw 'Consumidores do socket não correspondem à execução.' }
}

function Assert-RunningMySql {
    $db = Get-OwnedContainer $mysqlId
    $ports = if ($db.HostConfig.PortBindings) { @($db.HostConfig.PortBindings.PSObject.Properties).Count } else { 0 }
    $datadir = [string]$db.HostConfig.Tmpfs.PSObject.Properties['/var/lib/mysql'].Value
    if (-not $db.State.Running -or $db.Image -ne $mysqlImage.Id -or $db.HostConfig.NetworkMode -ne 'none' -or $db.HostConfig.Privileged -or $ports -ne 0 -or $db.Mounts.Count -ne 1 -or $db.Mounts[0].Type -ne 'volume' -or $db.Mounts[0].Name -ne $volumeName -or $db.Mounts[0].Destination -ne '/var/run/mysqld' -or $datadir -notmatch 'size=512m' -or @($db.HostConfig.SecurityOpt) -notcontains 'no-new-privileges=true') { throw 'Topologia MySQL pós-start inválida.' }
}

function Assert-RunningPhp {
    $php = Get-OwnedContainer $phpId
    $ports = if ($php.HostConfig.PortBindings) { @($php.HostConfig.PortBindings.PSObject.Properties).Count } else { 0 }
    $mounts = @($php.Mounts)
    if (-not $php.State.Running -or $php.Image -ne $phpImage.Id -or $php.HostConfig.NetworkMode -ne 'none' -or $php.HostConfig.Privileged -or $ports -ne 0 -or -not $php.HostConfig.ReadonlyRootfs -or @($php.HostConfig.CapDrop) -notcontains 'ALL' -or @($php.HostConfig.SecurityOpt) -notcontains 'no-new-privileges=true' -or $mounts.Count -ne 8) { throw 'Topologia PHP pós-start inválida.' }
    if (@($mounts | Where-Object { $_.Type -eq 'bind' -and $_.Source -eq $appRoot -and $_.Destination -eq '/repo/app' -and -not $_.RW }).Count -ne 1 -or @($mounts | Where-Object { $_.Type -eq 'bind' -and $_.Source -eq $testFile -and $_.Destination -eq '/test/rei69-diretos-mysql.test.php' -and -not $_.RW }).Count -ne 1 -or @($mounts | Where-Object { $_.Type -eq 'volume' -and $_.Name -eq $volumeName -and $_.Destination -eq '/var/run/mysqld' }).Count -ne 1) { throw 'Mount PHP pós-start inválido.' }
    foreach ($source in $endpointFiles) {
        $destination = '/repo/public/api/processos-partes/' + [IO.Path]::GetFileName($source)
        if (@($mounts | Where-Object { $_.Type -eq 'bind' -and $_.Source -eq $source -and $_.Destination -eq $destination -and -not $_.RW }).Count -ne 1) { throw 'Mount de endpoint pós-start inválido.' }
    }
    if (@($mounts | Where-Object { $_.Type -eq 'bind' -and $_.Source -eq $inertConfig -and $_.Destination -eq '/repo/config/config.php' -and -not $_.RW }).Count -ne 1 -or @($mounts | Where-Object { $_.Type -eq 'bind' -and $_.Source -eq $securityConfig -and $_.Destination -eq '/repo/config/security.php' -and -not $_.RW }).Count -ne 1) { throw 'Configuração sintética pós-start inválida.' }
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
    $imageInvalid = -not $mysqlImage.Id -or -not $phpImage.Id -or (@($mysqlImage.RepoDigests) -notcontains $mysqlImageRef)
    $imageInvalid = $imageInvalid -or $phpImage.Id -ne $QaImageId
    if ($imageInvalid) { throw 'Imagens MySQL/PHP não correspondem aos digests aprovados.' }

    $stage = 'volume-create'
    $volumeCreateAttempted = $true
    Invoke-DockerSafe @('volume', 'create', '--label', "$label=$runId", $volumeName) | Out-Null
    $volumeCreateConfirmed = $true
    $volumeInfo = @((Invoke-DockerSafe @('volume', 'inspect', $volumeName)) | ConvertFrom-Json)[0]
    Assert-VolumeConsumers @()

    $stage = 'mysql-create'
    $mysqlCreateAttempted = $true
    $mysqlId = (Invoke-DockerSafe @(
        'create', '--name', $dbName, '--label', "$label=$runId", '--network', 'none',
        '--cpus', '1', '--memory', '1g', '--tmpfs', '/var/lib/mysql:rw,size=512m',
        '--security-opt', 'no-new-privileges=true',
        '--mount', "type=volume,source=$volumeName,target=/var/run/mysqld",
        '--env', 'MYSQL_ALLOW_EMPTY_PASSWORD=1', '--env', 'MYSQL_DATABASE=rei69_diretos_test',
        $mysqlImage.Id, "--lower-case-table-names=$LowerCaseTableNames"
    ) | Select-Object -Last 1).Trim()
    if ($mysqlId -notmatch '^[a-f0-9]{64}$') { throw 'ID MySQL inválido.' }
    $stage = 'mysql-created-topology'
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
    $stage = 'mysql-start'
    Invoke-DockerSafe @('start', $mysqlId) | Out-Null

    $stage = 'mysql-running-topology'
    $dbRunning = Get-OwnedContainer $mysqlId
    if (-not $dbRunning.State.Running -or $dbRunning.Image -ne $mysqlImage.Id -or $dbRunning.HostConfig.NetworkMode -ne 'none' -or $dbRunning.Mounts.Count -ne 1 -or $dbRunning.Mounts[0].Name -ne $volumeName -or $dbRunning.Mounts[0].Destination -ne '/var/run/mysqld') { throw 'Topologia MySQL mudou após start.' }

    $stage = 'mysql-readiness'
    $ready = $false
    for ($i = 0; $i -lt 90; $i++) {
        $probe = $null
        try {
            $pid1 = Invoke-DockerSafe @('exec', $mysqlId, 'sh', '-c', 'cat /proc/1/comm')
            if (($pid1 | Select-Object -Last 1).Trim() -ne 'mysqld') { throw 'MySQL definitivo ainda não iniciou.' }
            $probe = Invoke-DockerSafe @(
                'exec', $mysqlId, 'mysql', '--protocol=socket', '--socket=/var/run/mysqld/mysqld.sock',
                '-uroot', 'rei69_diretos_test', '-N', '-e', 'SELECT 1'
            )
        } catch { }
        if ($null -ne $probe -and $LASTEXITCODE -eq 0 -and ($probe | Select-Object -Last 1).Trim() -eq '1') {
            $ready = $true
            break
        }
        Start-Sleep -Seconds 1
    }
    if (-not $ready) { throw 'MySQL descartável ou banco de teste não ficou pronto.' }

    $stage = 'mysql-pre-sentinel-topology'
    Assert-RunningMySql
    Assert-VolumeConsumers @($mysqlId)

    $stage = 'sentinel'
    $markerSql = "CREATE TABLE rei69_diretos_test_guard (marker CHAR(64) NOT NULL PRIMARY KEY) ENGINE=InnoDB; INSERT INTO rei69_diretos_test_guard (marker) VALUES ('$marker');"
    Invoke-DockerSafe @('exec', $mysqlId, 'mysql', '--protocol=socket', '--socket=/var/run/mysqld/mysqld.sock', '-uroot', 'rei69_diretos_test', '-e', $markerSql) | Out-Null

    $appMount = "type=bind,source=$appRoot,target=/repo/app,readonly"
    $testMount = "type=bind,source=$testFile,target=/test/rei69-diretos-mysql.test.php,readonly"
    $stage = 'php-create'
    $phpCreateAttempted = $true
    $phpId = (Invoke-DockerSafe @(
        'create', '--name', $phpName, '--label', "$label=$runId", '--network', 'none',
        '--cpus', '1', '--memory', '512m', '--read-only', '--cap-drop', 'ALL',
        '--security-opt', 'no-new-privileges=true', '--tmpfs', '/tmp:rw,size=64m,noexec,nosuid,nodev',
        '--mount', $appMount, '--mount', $testMount,
        '--mount', "type=bind,source=$($endpointFiles[0]),target=/repo/public/api/processos-partes/create.php,readonly",
        '--mount', "type=bind,source=$($endpointFiles[1]),target=/repo/public/api/processos-partes/update.php,readonly",
        '--mount', "type=bind,source=$($endpointFiles[2]),target=/repo/public/api/processos-partes/delete.php,readonly",
        '--mount', "type=bind,source=$inertConfig,target=/repo/config/config.php,readonly",
        '--mount', "type=bind,source=$securityConfig,target=/repo/config/security.php,readonly",
        '--mount', "type=volume,source=$volumeName,target=/var/run/mysqld",
        '--env', "REI69DIRETOS_EXPECT_LOWER_CASE=$LowerCaseTableNames", '--env', 'REI69DIRETOS_TEST_DISPOSABLE=1', '--env', 'REI69DIRETOS_TEST_REPO_ROOT=/repo',
        '--env', "REI69DIRETOS_TEST_DISPOSABLE_MARKER=$marker",
        '--env', 'REI69DIRETOS_MYSQL_DSN=mysql:unix_socket=/var/run/mysqld/mysqld.sock;dbname=rei69_diretos_test;charset=utf8mb4',
        '--env', 'REI69DIRETOS_MYSQL_USER=root', '--env', 'APP_ENV=fixture',
        '--entrypoint', 'sleep', $phpImage.Id, '300'
    ) | Select-Object -Last 1).Trim()
    if ($phpId -notmatch '^[a-f0-9]{64}$') { throw 'ID PHP inválido.' }
    $stage = 'php-created-topology'
    $php = Get-OwnedContainer $phpId
    $phpMounts = @($php.Mounts)
    $phpPortCount = if ($php.HostConfig.PortBindings) { @($php.HostConfig.PortBindings.PSObject.Properties).Count } else { 0 }
    $phpInvalid = $php.Image -ne $phpImage.Id -or $php.HostConfig.NetworkMode -ne 'none' -or $php.HostConfig.Privileged
    $phpInvalid = $phpInvalid -or $phpPortCount -ne 0 -or $phpMounts.Count -ne 8
    $phpInvalid = $phpInvalid -or -not $php.HostConfig.ReadonlyRootfs -or @($php.HostConfig.CapDrop) -notcontains 'ALL'
    $phpInvalid = $phpInvalid -or @($php.HostConfig.SecurityOpt) -notcontains 'no-new-privileges=true'
    $phpInvalid = $phpInvalid -or @($phpMounts | Where-Object { $_.Type -eq 'bind' -and $_.Source -eq $appRoot -and $_.Destination -eq '/repo/app' -and -not $_.RW }).Count -ne 1
    $phpInvalid = $phpInvalid -or @($phpMounts | Where-Object { $_.Type -eq 'bind' -and $_.Source -eq $testFile -and $_.Destination -eq '/test/rei69-diretos-mysql.test.php' -and -not $_.RW }).Count -ne 1
    $phpInvalid = $phpInvalid -or @($phpMounts | Where-Object { $_.Type -eq 'bind' -and $_.Source -eq $inertConfig -and $_.Destination -eq '/repo/config/config.php' -and -not $_.RW }).Count -ne 1
    $phpInvalid = $phpInvalid -or @($phpMounts | Where-Object { $_.Type -eq 'bind' -and $_.Source -eq $securityConfig -and $_.Destination -eq '/repo/config/security.php' -and -not $_.RW }).Count -ne 1
    $phpInvalid = $phpInvalid -or @($phpMounts | Where-Object { $_.Type -eq 'volume' -and $_.Name -eq $volumeName -and $_.Destination -eq '/var/run/mysqld' }).Count -ne 1
    foreach ($source in $endpointFiles) {
        $destination = '/repo/public/api/processos-partes/' + [IO.Path]::GetFileName($source)
        $phpInvalid = $phpInvalid -or @($phpMounts | Where-Object { $_.Type -eq 'bind' -and $_.Source -eq $source -and $_.Destination -eq $destination -and -not $_.RW }).Count -ne 1
    }
    if ($phpInvalid) {
        throw 'Topologia PHP recusada: código somente leitura, socket próprio, sem rede/portas são obrigatórios.'
    }
    $stage = 'php-start'
    Invoke-DockerSafe @('start', $phpId) | Out-Null
    $stage = 'php-pre-fixture-topology'
    Assert-RunningMySql
    Assert-RunningPhp
    Assert-VolumeConsumers @($mysqlId, $phpId)
    $stage = 'fixture'
    $fixtureOutput = & docker --host $dockerEndpoint exec $phpId php -d 'error_log=/tmp/rei69diretos-test.log' /test/rei69-diretos-mysql.test.php 2>&1
    $fixtureExit = $LASTEXITCODE
    if ($fixtureExit -ne 0) {
        $codes = @($fixtureOutput | ForEach-Object { [string]$_ } | Where-Object { $_ -match '^REI69_DIRECT_FAILURE code=(setup|guard|positive_create|negative|rollback_create|rollback_update|positive_update|positive_delete|concurrency|child_uncertain|monitor_setup|monitor_positive|monitor_create|monitor_row|monitor_row_prepare|monitor_row_execute|monitor_row_fetch|monitor_tasks|monitor_rollback|c1_setup|c1_wait|c1_finish|c1_check|c2_setup|c2_person_wait|c2_sector_wait|c2_finish|c2_check|c3_setup|c3_person_wait|c3_pauta_wait|c3_finish|c3_check|c3_direct|c3_pauta|c3_tipo|c3_snapshot|c4_setup|c4_link_wait|c4_direct_wait|c4_finish|c4_pauta_finish|c4_direct_finish|c4_check|c5_setup|c5_first_wait|c5_second_wait|c5_finish|c5_check|c6_controls|c6_prepare|c6_wait|c6_finish|c6_check) detail=(general|monitor_sql_42s22|monitor_sql_42s02|monitor_sql_23000|monitor_sql_40001|monitor_sql_hy000|monitor_sql_other|monitor_sql_collation|monitor_invalid|monitor_logic|monitor_runtime|monitor_error|spawn|early_exit|early_401|early_403|early_404|early_500|pauta_connect|pauta_require|pauta_construct|pauta_sql_42s22|pauta_sql_42s02|pauta_sql_23000|pauta_sql_40001|pauta_sql_hy000|pauta_sql_other|pauta_acl|pauta_invalid|pauta_runtime|pauta_noop|pauta_error|pauta_fatal|connection_id|wait_query|wait_timeout|wait_no_edge|wait_other_table|wait_other_index|wait_key_padded|wait_key_other|wait_key_null|finish_timeout|exit|assert|snapshot_pauta|snapshot_other)$' })
        if ($codes.Count -eq 1) { Write-Output $codes[0] } else { Write-Output 'REI69_DIRECT_FAILURE code=setup detail=general' }
        throw 'Fixture falhou.'
    }
    if (@($fixtureOutput | ForEach-Object { [string]$_ } | Where-Object { $_ -eq 'PASS REI69 direct endpoints synthetic MySQL regression' }).Count -ne 1) { throw 'Fixture não confirmou marcador de sucesso.' }
    Write-Output 'PASS REI69 direct endpoints synthetic MySQL regression'
} catch {
    Write-Error "REI69_DIRETOS_FAILURE stage=$stage code=operation_failed" -ErrorAction Continue
    throw 'REI69_DIRETOS_FAILURE'
} finally {
    try {
        if ($phpId -notmatch '^[a-f0-9]{64}$') {
            if ($phpCreateAttempted) { $cleanupFailed = $true; Write-Warning 'Resultado de criação PHP incerto, mesmo que uma busca encontre recurso.' }
            $phpId = Find-OwnedContainerByName $phpName
            if ($phpCreateAttempted -and -not $phpId) { $cleanupFailed = $true; Write-Warning 'Criação PHP teve resultado indeterminado; ausência tardia não comprovada.' }
        }
    } catch {
        $cleanupFailed = $true
        Write-Warning 'Não foi possível localizar com segurança o container PHP desta execução.'
    }
    try {
        if ($mysqlId -notmatch '^[a-f0-9]{64}$') {
            if ($mysqlCreateAttempted) { $cleanupFailed = $true; Write-Warning 'Resultado de criação MySQL incerto, mesmo que uma busca encontre recurso.' }
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
        if ($volumeCreateAttempted -and -not $volumeCreateConfirmed) {
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
    if ($cleanupFailed) { throw 'Limpeza dos recursos próprios não foi totalmente comprovada.' }
}
