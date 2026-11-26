[CmdletBinding()]
param()

$ErrorActionPreference = 'Stop'
$repoRoot = (Resolve-Path (Join-Path $PSScriptRoot '../..')).Path
$appRoot = Join-Path $repoRoot 'app'
$configRoot = Join-Path $repoRoot 'config'
$testRoot = $PSScriptRoot
$testFile = Join-Path $testRoot 'rei90-conclusao-mysql.test.php'
$workerFile = Join-Path $testRoot 'rei90-concurrency-worker.php'
$mysqlImage = 'mysql@sha256:a81d043930e51745522bd5c430961cf8286f0a23f0a9a2a6e0108f7ea71b0902'
$phpImage = 'fokus-law-linux-qa@sha256:459cc8bd25b38009a8cf9a0ef5399d131f4f2001a1376d89389f673a92de430b'
$label = 'com.fokus.rei90-isolated-test'
$runId = [Guid]::NewGuid().ToString('N').ToLowerInvariant()
$marker = ([Guid]::NewGuid().ToString('N') + [Guid]::NewGuid().ToString('N')).ToLowerInvariant()
$network = "fokus-rei90-$runId"
$mysqlName = "fokus-rei90-mysql-$runId"
$dataVolume = "fokus-rei90-data-$runId"
$mysqlPassword = [Convert]::ToBase64String([Security.Cryptography.RandomNumberGenerator]::GetBytes(36)).TrimEnd('=').Replace('+','a').Replace('/','b')
$dockerEndpoint = 'npipe:////./pipe/dockerDesktopLinuxEngine'
$mysqlCreated = $false
$networkCreated = $false
$volumeCreated = $false
$failure = $null

if (Test-Path -LiteralPath (Join-Path $repoRoot '.env')) { throw 'Runner recusado: checkout contém .env.' }
foreach ($path in @($appRoot, $testRoot, $testFile, $workerFile)) {
    $item = Get-Item -LiteralPath $path -Force
    if ($item.Attributes -band [IO.FileAttributes]::ReparsePoint) { throw 'Runner recusado: caminho de origem contém reparse point.' }
}
foreach ($path in @($appRoot, $configRoot, $testRoot)) {
    if (Get-ChildItem -LiteralPath $path -Recurse -Force | Where-Object { $_.Attributes -band [IO.FileAttributes]::ReparsePoint }) {
        throw 'Runner recusado: árvore de origem contém reparse point.'
    }
}
if (Get-ChildItem -LiteralPath $appRoot -Recurse -Force | Where-Object { $_.Name -like '.env*' }) {
    throw 'Runner recusado: app contém arquivo de ambiente.'
}
if (Get-ChildItem -LiteralPath $configRoot -Recurse -Force | Where-Object { $_.Name -like '.env*' }) {
    throw 'Runner recusado: config contém arquivo de ambiente.'
}

function Invoke-Docker([string[]]$DockerArgs) {
    $out = & docker --host $dockerEndpoint @DockerArgs 2>&1
    if ($LASTEXITCODE -ne 0) { throw "Docker falhou em '$($DockerArgs[0])': $($out -join ' ')" }
    return $out
}
function Assert-OwnedContainer([string]$Name) {
    $raw = Invoke-Docker @('inspect', $Name)
    $item = @($raw | ConvertFrom-Json)[0]
    if ($item.Config.Labels.$label -ne $runId -or $item.Name -ne "/$Name") { throw 'Container não pertence à execução atual.' }
    return $item
}

try {
    $context = (& docker context show 2>$null | Select-Object -Last 1).Trim()
    if ($LASTEXITCODE -ne 0 -or $context -ne 'desktop-linux') { throw 'Runner aceita somente Docker local desktop-linux.' }
    $contextInfo = @((& docker context inspect $context 2>$null | ConvertFrom-Json))[0]
    if ($contextInfo.Endpoints.docker.Host -ne $dockerEndpoint) { throw 'Endpoint Docker local inesperado.' }
    foreach ($image in @($mysqlImage, $phpImage)) {
        $raw = Invoke-Docker @('image','inspect',$image)
        $info = @($raw | ConvertFrom-Json)[0]
        if (@($info.RepoDigests) -notcontains $image) { throw "Imagem local não corresponde ao digest fixado: $image" }
    }

    Invoke-Docker @('network','create','--internal','--label',"$label=$runId",$network) | Out-Null
    $networkCreated = $true
    $net = @((Invoke-Docker @('network','inspect',$network) | ConvertFrom-Json))[0]
    if ($net.Labels.$label -ne $runId -or -not $net.Internal) { throw 'Rede interna descartável não foi comprovada.' }

    Invoke-Docker @('volume','create','--label',"$label=$runId",$dataVolume) | Out-Null
    $volumeCreated = $true
    $volume = @((Invoke-Docker @('volume','inspect',$dataVolume) | ConvertFrom-Json))[0]
    if ($volume.Labels.$label -ne $runId) { throw 'Volume de dados MySQL não pertence à execução atual.' }

    Invoke-Docker @('run','--detach','--name',$mysqlName,'--network',$network,'--network-alias','mysql','--label',"$label=$runId",'--mount',"type=volume,source=$dataVolume,target=/var/lib/mysql",'--env',"MYSQL_ROOT_PASSWORD=$mysqlPassword",'--env','MYSQL_ROOT_HOST=%','--env','MYSQL_DATABASE=rei90_test',$mysqlImage) | Out-Null
    $mysqlCreated = $true
    $mysql = Assert-OwnedContainer $mysqlName
    $publishedPorts = @($mysql.HostConfig.PortBindings.PSObject.Properties | Where-Object { @($_.Value).Count -gt 0 }).Count
    $bindMounts = @($mysql.HostConfig.Binds | Where-Object { $_ }).Count
    if ($publishedPorts -ne 0 -or $bindMounts -ne 0) { throw 'MySQL descartável possui porta publicada ou bind mount.' }
    $ready = $false
    for ($attempt = 0; $attempt -lt 150; $attempt++) {
        $probe = & docker --host $dockerEndpoint exec $mysqlName mysql -uroot "-p$mysqlPassword" -N -B -e "SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME='rei90_test'" 2>$null
        if ($LASTEXITCODE -eq 0 -and ($probe -join '').Trim() -eq 'rei90_test') { $ready = $true; break }
        Start-Sleep -Seconds 1
    }
    if (-not $ready) { throw 'MySQL descartável não ficou pronto.' }
    $guardSql = "CREATE TABLE rei90_disposable_guard (run_id CHAR(32) PRIMARY KEY, marker CHAR(64) NOT NULL) ENGINE=InnoDB; INSERT INTO rei90_disposable_guard VALUES ('$runId','$marker');"
    Invoke-Docker @('exec',$mysqlName,'mysql','-uroot',"-p$mysqlPassword",'rei90_test','-e',$guardSql) | Out-Null

    $phpArgs = @(
        'run','--rm','--network',$network,'--label',"$label=$runId",
        '--mount',"type=bind,source=$appRoot,target=/workspace/app,readonly",
        '--mount',"type=bind,source=$configRoot,target=/workspace/config,readonly",
        '--mount',"type=bind,source=$testRoot,target=/workspace/tests/oficios,readonly",
        '--env','REI90_TEST_ISOLATION=disposable-rei90-mysql','--env',"REI90_RUN_ID=$runId",'--env',"REI90_MARKER=$marker",
        '--env','REI90_MYSQL_DSN=mysql:host=mysql;port=3306;dbname=rei90_test;charset=utf8mb4',
        '--env','REI90_MYSQL_USER=root','--env',"REI90_MYSQL_PASSWORD=$mysqlPassword",
        '--env','DB_HOST=mysql','--env','DB_PORT=3306','--env','DB_NAME=rei90_test','--env','DB_USER=root','--env',"DB_PASS=$mysqlPassword",'--env','DB_CHARSET=utf8mb4',
        $phpImage,'php','/workspace/tests/oficios/rei90-conclusao-mysql.test.php'
    )
    $testOutput = Invoke-Docker $phpArgs
    $testOutput | Write-Output
    if (($testOutput -join "`n") -notmatch 'PASS REI-90 ordering and atomicity') { throw 'Saída de sucesso do teste REI-90 não foi confirmada.' }
} catch {
    $failure = $_
    if ($mysqlCreated) {
        $mysqlLogs = & docker --host $dockerEndpoint logs $mysqlName 2>&1
        if ($mysqlLogs) { Write-Output ('MYSQL_DIAGNOSTIC ' + ($mysqlLogs -join ' | ')) }
    }
} finally {
    if ($mysqlCreated) {
        try {
            $owned = Assert-OwnedContainer $mysqlName
            if ($owned.State.Running) { Invoke-Docker @('stop',$mysqlName) | Out-Null }
            Invoke-Docker @('rm',$mysqlName) | Out-Null
            if ((Invoke-Docker @('ps','-aq','--filter',"name=^/$mysqlName$")).Count -ne 0) { throw 'Container MySQL permaneceu após limpeza.' }
        } catch { Write-Warning 'Não foi possível confirmar a limpeza do container MySQL próprio.'; if (-not $failure) { $failure = $_ } }
    }
    if ($networkCreated) {
        try {
            $net = @((Invoke-Docker @('network','inspect',$network) | ConvertFrom-Json))[0]
            if ($net.Labels.$label -ne $runId) { throw 'Ownership da rede não confere.' }
            if (@($net.Containers.PSObject.Properties).Count -ne 0) { throw 'Rede ainda contém containers.' }
            Invoke-Docker @('network','rm',$network) | Out-Null
            if ((Invoke-Docker @('network','ls','--quiet','--filter',"name=^$network$")).Count -ne 0) { throw 'Rede permaneceu após limpeza.' }
        } catch { Write-Warning 'Não foi possível confirmar a limpeza da rede própria.'; if (-not $failure) { $failure = $_ } }
    }
    if ($volumeCreated) {
        try {
            $volume = @((Invoke-Docker @('volume','inspect',$dataVolume) | ConvertFrom-Json))[0]
            if ($volume.Labels.$label -ne $runId) { throw 'Ownership do volume de dados não confere.' }
            $users = Invoke-Docker @('ps','-aq','--filter',"volume=$dataVolume")
            if (@($users | Where-Object { $_ }).Count -ne 0) { throw 'Volume de dados continua montado.' }
            Invoke-Docker @('volume','rm',$dataVolume) | Out-Null
            $remaining = Invoke-Docker @('volume','ls','--quiet','--filter',"name=^$dataVolume$")
            if (@($remaining | Where-Object { $_ }).Count -ne 0) { throw 'Volume permaneceu após limpeza.' }
        } catch { Write-Warning 'Não foi possível confirmar a limpeza do volume MySQL próprio.'; if (-not $failure) { $failure = $_ } }
    }
}
if ($failure) { throw $failure }
Write-Output 'PASS rei90-isolated-mysql-runner'
