[CmdletBinding()]
param(
    [switch]$CiLinux,
    [string]$QaImageId
)

$ErrorActionPreference = 'Stop'
$repoRoot = (Resolve-Path (Join-Path $PSScriptRoot '../..')).Path
$testScript = Join-Path $PSScriptRoot 'cpf-concurrency-mysql.test.php'
$sourceFiles = @(
    'tests/api-security/cpf-concurrency-mysql.test.php',
    'app/views/internal/usuarios-create.php',
    'public/api/cadastro_estudantes.php',
    'app/helpers/ApiErrorReporter.php'
)
$mysqlImageRef = 'mysql@sha256:0426ec38c7a10aa45ba383887df7878f74ee70e2fd589c7b69207f3577901903'
$phpImageRef = 'fokus-law-linux-qa@sha256:459cc8bd25b38009a8cf9a0ef5399d131f4f2001a1376d89389f673a92de430b'

if ($CiLinux) {
    if (-not $IsLinux -or $env:GITHUB_ACTIONS -ne 'true' -or $QaImageId -notmatch '^sha256:[a-f0-9]{64}$') {
        throw 'Runner CI recusado: requer Linux, GitHub Actions e ID de imagem explícito.'
    }
    $phpImageRef = $QaImageId
} elseif ($env:GITHUB_ACTIONS -eq 'true') {
    throw 'Runner local recusado dentro de GitHub Actions.'
}

$resolvedRoot = (Resolve-Path $repoRoot).Path
foreach ($relative in $sourceFiles) {
    $candidate = Join-Path $resolvedRoot $relative
    $item = Get-Item -LiteralPath $candidate -Force
    if ($item.PSIsContainer -or ($item.Attributes -band [IO.FileAttributes]::ReparsePoint)) {
        throw 'Runner recusado: origem deve ser arquivo regular sem reparse point.'
    }
    $full = (Resolve-Path -LiteralPath $candidate).Path
    if (-not $full.StartsWith($resolvedRoot + [IO.Path]::DirectorySeparatorChar, [StringComparison]::OrdinalIgnoreCase)) {
        throw 'Runner recusado: origem fora do repositório.'
    }
}
foreach ($relative in @('app/views/internal', 'public/api', 'tests/api-security')) {
    $dir = Join-Path $resolvedRoot $relative
    if (Get-ChildItem -LiteralPath $dir -Recurse -Force | Where-Object { $_.Attributes -band [IO.FileAttributes]::ReparsePoint }) {
        throw 'Runner recusado: caminho de aplicação contém reparse point.'
    }
}

if ($CiLinux) {
    $dockerEndpoint = 'unix:///var/run/docker.sock'
} else {
    $contextName = (& docker context show 2>$null | Select-Object -Last 1).Trim()
    if ($LASTEXITCODE -ne 0 -or $contextName -ne 'desktop-linux') { throw 'Runner recusado: somente Docker Desktop local.' }
    $contextInfo = @((& docker context inspect $contextName 2>$null | ConvertFrom-Json))[0]
    $dockerEndpoint = 'npipe:////./pipe/dockerDesktopLinuxEngine'
    if ($LASTEXITCODE -ne 0 -or $contextInfo.Endpoints.docker.Host -ne $dockerEndpoint) { throw 'Runner recusado: endpoint Docker inesperado.' }
}

$runId = [Guid]::NewGuid().ToString('N').ToLowerInvariant()
$label = 'com.fokus.rei66-cpf-isolated-test'
$mysqlName = "fokus-rei66-mysql-$runId"
$phpName = "fokus-rei66-php-$runId"
$mysqlId = $null
$phpId = $null
$marker = ([Guid]::NewGuid().ToString('N') + [Guid]::NewGuid().ToString('N')).ToLowerInvariant()
$testFailure = $null
$cleanupFailure = $null

function Invoke-DockerSafe([string[]]$argsList) {
    $output = & docker --host $dockerEndpoint @argsList 2>&1
    if ($LASTEXITCODE -ne 0) { throw "Docker falhou ($($argsList[0])): $($output -join ' ')" }
    return $output
}
function Get-Owned([string]$id) {
    if ($id -notmatch '^[a-f0-9]{64}$') { throw 'ID Docker inválido.' }
    $item = @((Invoke-DockerSafe @('inspect', $id)) | ConvertFrom-Json)[0]
    if (-not $item -or $item.Config.Labels.$label -ne $runId) { throw 'Recurso Docker sem ownership comprovado.' }
    return $item
}
function Remove-Owned([string]$id) {
    if (-not $id) { return }
    try {
        $item = Get-Owned $id
        if ($item.State.Running) { Invoke-DockerSafe @('stop', $id) | Out-Null }
        Invoke-DockerSafe @('rm', $id) | Out-Null
        if (@(Invoke-DockerSafe @('ps', '-aq', '--no-trunc', '--filter', "id=$id") | Where-Object { $_ }).Count -ne 0) { throw 'Container ainda existe.' }
    } catch { $script:cleanupFailure = $_; Write-Warning 'Container não removido sem validar ownership.' }
}

try {
    $mysqlImage = @((Invoke-DockerSafe @('image', 'inspect', $mysqlImageRef)) | ConvertFrom-Json)[0]
    $phpImage = @((Invoke-DockerSafe @('image', 'inspect', $phpImageRef)) | ConvertFrom-Json)[0]
    if (-not $mysqlImage.Id -or @($mysqlImage.RepoDigests) -notcontains $mysqlImageRef -or -not $phpImage.Id) {
        throw 'Runner recusado: imagens locais sem digest/ID esperado.'
    }
    if ($CiLinux -and $phpImage.Id -ne $QaImageId) { throw 'Runner CI recusado: runtime PHP não corresponde ao ID construído.' }

    $mysqlId = (Invoke-DockerSafe @('create', '--name', $mysqlName, '--label', "$label=$runId", '--network', 'none', '--cpus', '1', '--memory', '1g', '--tmpfs', '/var/lib/mysql:rw,size=512m', '--security-opt', 'no-new-privileges=true', '--env', 'MYSQL_ALLOW_EMPTY_PASSWORD=yes', '--env', 'MYSQL_DATABASE=auth_test', $mysqlImage.Id) | Select-Object -Last 1).Trim()
    $mysql = Get-Owned $mysqlId
    $mysqlPorts = if ($mysql.HostConfig.PortBindings) { @($mysql.HostConfig.PortBindings.PSObject.Properties).Count } else { 0 }
    $mysqlTmpfs = $mysql.HostConfig.Tmpfs.PSObject.Properties['/var/lib/mysql'].Value
    if ($mysql.Image -ne $mysqlImage.Id -or $mysql.HostConfig.NetworkMode -ne 'none' -or $mysql.HostConfig.Privileged -or $mysqlPorts -ne 0 -or
        $mysql.HostConfig.ReadonlyRootfs -or [string]::IsNullOrWhiteSpace([string]$mysqlTmpfs) -or $mysqlTmpfs -notmatch 'size=512m' -or @($mysql.Mounts).Count -ne 0) {
        throw 'Topologia MySQL descartável inválida.'
    }
    Invoke-DockerSafe @('start', $mysqlId) | Out-Null
    $ready = $false
    for ($i = 0; $i -lt 90; $i++) {
        try {
            $pid1 = Invoke-DockerSafe @('exec', $mysqlId, 'cat', '/proc/1/comm')
            if (($pid1 | Select-Object -Last 1).Trim() -cne 'mysqld') { throw 'Servidor MySQL definitivo ainda não iniciou.' }
            $probe = Invoke-DockerSafe @('exec', $mysqlId, 'mysql', '--protocol=tcp', '--host=127.0.0.1', '--port=3306', '-uroot', 'auth_test', '-N', '-B', '-e', 'SELECT 1')
            if (($probe | Select-Object -Last 1).Trim() -eq '1') { $ready = $true; break }
        } catch { }
        Start-Sleep -Seconds 1
    }
    if (-not $ready) { throw 'MySQL descartável não ficou pronto via socket.' }
    $proofSql = "CREATE TABLE _sandbox_proof (token CHAR(64) NOT NULL); INSERT INTO _sandbox_proof VALUES ('$marker');"
    Invoke-DockerSafe @('exec', $mysqlId, 'mysql', '--protocol=socket', '--socket=/var/run/mysqld/mysqld.sock', '-uroot', 'auth_test', '-e', $proofSql) | Out-Null

    $phpId = (Invoke-DockerSafe @('create', '--name', $phpName, '--label', "$label=$runId", '--network', "container:$mysqlId", '--cpus', '1', '--memory', '512m', '--cap-drop', 'ALL', '--security-opt', 'no-new-privileges=true', '--tmpfs', '/tmp:rw,size=32m,noexec,nosuid,nodev', '--entrypoint', 'sleep', $phpImage.Id, '300') | Select-Object -Last 1).Trim()
    $php = Get-Owned $phpId
    $phpPorts = if ($php.HostConfig.PortBindings) { @($php.HostConfig.PortBindings.PSObject.Properties).Count } else { 0 }
    if ($php.Image -ne $phpImage.Id -or $php.HostConfig.NetworkMode -ne "container:$mysqlId" -or $php.HostConfig.Privileged -or $phpPorts -ne 0 -or
        $php.HostConfig.ReadonlyRootfs -or @($php.HostConfig.CapDrop) -notcontains 'ALL' -or @($php.HostConfig.SecurityOpt) -notcontains 'no-new-privileges=true' -or @($php.Mounts).Count -ne 0) {
        throw 'Topologia PHP descartável inválida.'
    }
    Invoke-DockerSafe @('start', $phpId) | Out-Null
    Invoke-DockerSafe @('exec', $phpId, 'mkdir', '-p', '/workspace/config', '/workspace/app/helpers', '/workspace/app/views/internal', '/workspace/public/api', '/workspace/tests/api-security') | Out-Null
    foreach ($relative in $sourceFiles) {
        $destination = '/workspace/' + $relative.Replace('\', '/')
        Invoke-DockerSafe @('cp', (Join-Path $resolvedRoot $relative), "${phpId}:$destination") | Out-Null
    }

    $runOutput = Invoke-DockerSafe @('exec', '--workdir', '/workspace', '--env', 'MYSQL_TEST_DSN=mysql:host=127.0.0.1;port=3306;dbname=auth_test;charset=utf8mb4', '--env', 'MYSQL_TEST_USER=root', '--env', 'MYSQL_TEST_PASSWORD=', '--env', "MYSQL_TEST_MARKER=$marker", $phpId, 'php', '/workspace/tests/api-security/cpf-concurrency-mysql.test.php')
    $runOutput
    $proof = Invoke-DockerSafe @('exec', $mysqlId, 'mysql', '--protocol=socket', '--socket=/var/run/mysqld/mysqld.sock', '-uroot', 'auth_test', '-N', '-B', '-e', 'SELECT token FROM _sandbox_proof')
    if (($proof | Select-Object -Last 1).Trim() -ne $marker) { throw 'Prova do banco descartável não confere após o teste.' }
} catch { $testFailure = $_ } finally {
    Remove-Owned $phpId
    Remove-Owned $mysqlId
    try {
        $left = @(Invoke-DockerSafe @('ps', '-aq', '--no-trunc', '--filter', "label=$label=$runId") | Where-Object { $_ })
        if ($left.Count -ne 0) { throw 'Containers deste teste ainda existem.' }
    } catch { if (-not $cleanupFailure) { $cleanupFailure = $_ } }
}
if ($testFailure) { if ($cleanupFailure) { Write-Error $cleanupFailure -ErrorAction Continue }; $PSCmdlet.ThrowTerminatingError($testFailure) }
if ($cleanupFailure) { $PSCmdlet.ThrowTerminatingError($cleanupFailure) }
Write-Output 'PASS runner MySQL CPF isolado e recursos limpos'
