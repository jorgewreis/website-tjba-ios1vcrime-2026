[CmdletBinding()]
param()

$ErrorActionPreference = 'Stop'
$repoRoot = (Resolve-Path (Join-Path $PSScriptRoot '../..')).Path
$appRoot = Join-Path $repoRoot 'app'
$testRoot = $PSScriptRoot
$testFile = Join-Path $testRoot 'rei100-create-atomicity-mysql.test.php'
$mysqlImage = 'mysql@sha256:a81d043930e51745522bd5c430961cf8286f0a23f0a9a2a6e0108f7ea71b0902'
$phpImage = 'fokus-law-linux-qa@sha256:459cc8bd25b38009a8cf9a0ef5399d131f4f2001a1376d89389f673a92de430b'
$label = 'com.fokus.rei100-isolated-test'
$runId = [Guid]::NewGuid().ToString('N').ToLowerInvariant()
$marker = ([Guid]::NewGuid().ToString('N') + [Guid]::NewGuid().ToString('N')).ToLowerInvariant()
$network = "fokus-rei100-$runId"
$mysqlName = "fokus-rei100-mysql-$runId"
$dataVolume = "fokus-rei100-data-$runId"
$passwordRng = [Security.Cryptography.RandomNumberGenerator]::Create()
$passwordBytes = New-Object byte[] 36
$passwordRng.GetBytes($passwordBytes)
$passwordRng.Dispose()
$mysqlPassword = [Convert]::ToBase64String($passwordBytes).TrimEnd('=').Replace('+','a').Replace('/','b')
$dockerEndpoint = 'npipe:////./pipe/dockerDesktopLinuxEngine'
$mysqlCreated = $false
$networkCreated = $false
$volumeCreated = $false
$failure = $null

if (Test-Path -LiteralPath (Join-Path $repoRoot '.env')) { throw 'Runner recusado: checkout contém .env.' }
foreach ($path in @($appRoot, $testRoot, $testFile)) {
    if ((Get-Item -LiteralPath $path -Force).Attributes -band [IO.FileAttributes]::ReparsePoint) { throw 'Runner recusado: origem contém reparse point.' }
}
foreach ($root in @($appRoot, $testRoot)) {
    if (Get-ChildItem -LiteralPath $root -Recurse -Force | Where-Object { $_.Attributes -band [IO.FileAttributes]::ReparsePoint }) { throw 'Runner recusado: origem contém reparse point descendente.' }
}
if (Get-ChildItem -LiteralPath $appRoot -Recurse -Force | Where-Object { $_.Name -like '.env*' }) { throw 'Runner recusado: app contém arquivo de ambiente.' }

function Invoke-DockerSafe([string[]]$DockerArgs) {
    $output = & docker --host $dockerEndpoint @DockerArgs 2>&1
    if ($LASTEXITCODE -ne 0) { throw "Docker falhou em '$($DockerArgs[0])': $($output -join ' ')" }
    return $output
}
function Get-OwnedContainer([string]$Name) {
    $item = @((Invoke-DockerSafe @('inspect', $Name)) | ConvertFrom-Json)[0]
    if (-not $item -or $item.Config.Labels.$label -ne $runId -or $item.Name -ne "/$Name") { throw 'Container não pertence a esta execução.' }
    return $item
}

try {
    $contextName = (& docker context show 2>$null | Select-Object -Last 1).Trim()
    if ($LASTEXITCODE -ne 0 -or $contextName -ne 'desktop-linux') { throw 'Runner aceita somente Docker local desktop-linux.' }
    $contextInfo = @((& docker context inspect $contextName 2>$null | ConvertFrom-Json))[0]
    if ($contextInfo.Endpoints.docker.Host -ne $dockerEndpoint) { throw 'Endpoint Docker local inesperado.' }
    foreach ($image in @($mysqlImage, $phpImage)) {
        $info = @((Invoke-DockerSafe @('image', 'inspect', $image)) | ConvertFrom-Json)[0]
        if (@($info.RepoDigests) -notcontains $image) { throw "Imagem fixada não está disponível: $image" }
    }

    Invoke-DockerSafe @('network', 'create', '--internal', '--label', "$label=$runId", $network) | Out-Null
    $networkCreated = $true
    $net = @((Invoke-DockerSafe @('network', 'inspect', $network)) | ConvertFrom-Json)[0]
    if ($net.Labels.$label -ne $runId -or -not $net.Internal) { throw 'Rede interna descartável não foi comprovada.' }

    Invoke-DockerSafe @('volume', 'create', '--label', "$label=$runId", $dataVolume) | Out-Null
    $volumeCreated = $true
    $volume = @((Invoke-DockerSafe @('volume', 'inspect', $dataVolume)) | ConvertFrom-Json)[0]
    if ($volume.Labels.$label -ne $runId) { throw 'Volume não pertence a esta execução.' }

    # Do not set MYSQL_PWD on the server container: the image entrypoint also
    # uses mysql locally before it applies MYSQL_ROOT_PASSWORD.
    Invoke-DockerSafe @('run', '--detach', '--name', $mysqlName, '--network', $network, '--network-alias', 'mysql', '--label', "$label=$runId", '--mount', "type=volume,source=$dataVolume,target=/var/lib/mysql", '--env', "MYSQL_ROOT_PASSWORD=$mysqlPassword", '--env', 'MYSQL_ROOT_HOST=%', '--env', 'MYSQL_DATABASE=rei100_test', $mysqlImage) | Out-Null
    $mysqlCreated = $true
    $mysql = Get-OwnedContainer $mysqlName
    $publishedPorts = if ($mysql.HostConfig.PortBindings) { @($mysql.HostConfig.PortBindings.PSObject.Properties | Where-Object { @($_.Value).Count -gt 0 }).Count } else { 0 }
    $bindMounts = @($mysql.HostConfig.Binds | Where-Object { $_ }).Count
    if ($publishedPorts -ne 0 -or $bindMounts -ne 0) { throw 'MySQL descartável possui porta publicada ou bind mount.' }
    $ready = $false
    for ($attempt = 0; $attempt -lt 300; $attempt++) {
        $previousErrorPreference = $ErrorActionPreference
        $ErrorActionPreference = 'Continue'
        $probe = & docker --host $dockerEndpoint exec $mysqlName mysql -uroot "-p$mysqlPassword" -N -B -e "SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME='rei100_test'" 2>$null
        $probeExitCode = $LASTEXITCODE
        $ErrorActionPreference = $previousErrorPreference
        if ($probeExitCode -eq 0 -and ($probe -join '').Trim() -eq 'rei100_test') { $ready = $true; break }
        Start-Sleep -Seconds 1
    }
    if (-not $ready) { throw 'MySQL descartável não ficou pronto.' }
    $guardSql = "CREATE TABLE rei100_disposable_guard (run_id CHAR(32) PRIMARY KEY, marker CHAR(64) NOT NULL) ENGINE=InnoDB; INSERT INTO rei100_disposable_guard VALUES ('$runId','$marker');"
    Invoke-DockerSafe @('exec', $mysqlName, 'mysql', '-uroot', "-p$mysqlPassword", 'rei100_test', '-e', $guardSql) | Out-Null

    $phpArgs = @(
        'run', '--rm', '--network', $network, '--label', "$label=$runId",
        '--mount', "type=bind,source=$appRoot,target=/workspace/app,readonly",
        '--mount', "type=bind,source=$testRoot,target=/workspace/tests/editais,readonly",
        '--env', 'REI100_TEST_ISOLATION=disposable-rei100-mysql', '--env', "REI100_RUN_ID=$runId", '--env', "REI100_MARKER=$marker",
        '--env', 'REI100_MYSQL_DSN=mysql:host=mysql;port=3306;dbname=rei100_test;charset=utf8mb4', '--env', "REI100_MYSQL_PASSWORD=$mysqlPassword",
        $phpImage, 'php', '/workspace/tests/editais/rei100-create-atomicity-mysql.test.php'
    )
    $testOutput = Invoke-DockerSafe $phpArgs
    $testOutput | Write-Output
    if (($testOutput -join "`n") -notmatch 'PASS REI-100 MySQL atomicity, retry and same-key concurrency') { throw 'Saída de sucesso do teste MySQL REI-100 não foi confirmada.' }
} catch {
    $failure = $_
    if ($mysqlCreated) {
        $previousErrorPreference = $ErrorActionPreference
        $ErrorActionPreference = 'Continue'
        $logs = & docker --host $dockerEndpoint logs $mysqlName 2>&1
        $ErrorActionPreference = $previousErrorPreference
        if ($logs) { Write-Output ('MYSQL_DIAGNOSTIC ' + ($logs -join ' | ')) }
    }
} finally {
    if ($mysqlCreated) {
        try {
            $owned = Get-OwnedContainer $mysqlName
            if ($owned.State.Running) { Invoke-DockerSafe @('stop', '--time', '5', $mysqlName) | Out-Null }
            Invoke-DockerSafe @('rm', $mysqlName) | Out-Null
            if ((Invoke-DockerSafe @('ps', '-aq', '--filter', "name=^/$mysqlName$")).Count -ne 0) { throw 'Container MySQL permaneceu após limpeza.' }
        } catch { Write-Warning 'Não foi possível confirmar a limpeza do container MySQL próprio.'; if (-not $failure) { $failure = $_ } }
    }
    if ($networkCreated) {
        try {
            $net = @((Invoke-DockerSafe @('network', 'inspect', $network)) | ConvertFrom-Json)[0]
            if ($net.Labels.$label -ne $runId -or @($net.Containers.PSObject.Properties).Count -ne 0) { throw 'Rede própria não está vazia ou ownership falhou.' }
            Invoke-DockerSafe @('network', 'rm', $network) | Out-Null
            if ((Invoke-DockerSafe @('network', 'ls', '--quiet', '--filter', "name=^$network$")).Count -ne 0) { throw 'Rede permaneceu após limpeza.' }
        } catch { Write-Warning 'Não foi possível confirmar a limpeza da rede própria.'; if (-not $failure) { $failure = $_ } }
    }
    if ($volumeCreated) {
        try {
            $volume = @((Invoke-DockerSafe @('volume', 'inspect', $dataVolume)) | ConvertFrom-Json)[0]
            if ($volume.Labels.$label -ne $runId) { throw 'Ownership do volume de dados não confere.' }
            if (@(Invoke-DockerSafe @('ps', '-aq', '--filter', "volume=$dataVolume") | Where-Object { $_ }).Count -ne 0) { throw 'Volume permanece montado.' }
            Invoke-DockerSafe @('volume', 'rm', $dataVolume) | Out-Null
            if ((Invoke-DockerSafe @('volume', 'ls', '--quiet', '--filter', "name=^$dataVolume$")).Count -ne 0) { throw 'Volume permaneceu após limpeza.' }
        } catch { Write-Warning 'Não foi possível confirmar a limpeza do volume próprio.'; if (-not $failure) { $failure = $_ } }
    }
}
if ($failure) { throw $failure }
Write-Output 'PASS rei100-isolated-mysql-runner'
