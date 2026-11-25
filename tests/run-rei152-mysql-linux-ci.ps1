[CmdletBinding()]
param([Parameter(Mandatory=$true)][string]$QaImageId)

$ErrorActionPreference = 'Stop'
$repoRoot = (Resolve-Path (Join-Path $PSScriptRoot '..')).Path
$testFile = Join-Path $repoRoot 'tests/rei152-documentos-mysql.test.php'
$workerFile = Join-Path $repoRoot 'tests/rei152-documentos-mysql-worker.php'
$entryFile = Join-Path $repoRoot 'tests/rei152-linux-ci-entry.php'
$gateFile = Join-Path $repoRoot 'tests/rei152-gate-alternate-mysql.test.php'
$createFile = Join-Path $repoRoot 'tests/rei152-create-mysql.test.php'
$appRoot = Join-Path $repoRoot 'app'
$mysqlImageRef = 'mysql@sha256:0426ec38c7a10aa45ba383887df7878f74ee70e2fd589c7b69207f3577901903'
if ($QaImageId -notmatch '^sha256:[a-f0-9]{64}$') { throw 'ID da imagem QA inválido.' }
$phpImageRef = $QaImageId

if (Test-Path -LiteralPath (Join-Path $repoRoot '.env')) { throw 'Runner recusado: checkout contém .env.' }
foreach ($path in @($appRoot, $testFile, $workerFile, $entryFile, $gateFile, $createFile)) {
    $item = Get-Item -LiteralPath $path -Force
    if ($item.Attributes -band [IO.FileAttributes]::ReparsePoint) { throw 'Runner recusado: source contém reparse point.' }
}
foreach ($tree in @($appRoot)) {
    if (Get-ChildItem -LiteralPath $tree -Recurse -Force | Where-Object { $_.Attributes -band [IO.FileAttributes]::ReparsePoint }) {
        throw 'Runner recusado: árvore montada contém reparse point.'
    }
    if (Get-ChildItem -LiteralPath $tree -Recurse -Force | Where-Object { $_.Name -like '.env*' }) {
        throw 'Runner recusado: árvore montada contém arquivo de ambiente.'
    }
}

$contextName = (& docker context show 2>$null | Select-Object -Last 1).Trim()
if ($LASTEXITCODE -ne 0 -or -not $IsLinux -or $env:RUNNER_ENVIRONMENT -ne 'github-hosted') { throw 'Runner recusado: Linux GitHub-hosted obrigatório.' }
$dockerEndpoint = 'unix:///var/run/docker.sock'
$context = @((& docker context inspect $contextName 2>$null | ConvertFrom-Json))[0]
if ($LASTEXITCODE -ne 0 -or $context.Endpoints.docker.Host -ne $dockerEndpoint) { throw 'Runner recusado: endpoint do Docker não é local.' }

$runId = [Guid]::NewGuid().ToString('N').ToLowerInvariant()
$syntheticConfig = Join-Path ([IO.Path]::GetTempPath()) "rei152-synthetic-config-$runId"
$configCreated = $false
$label = 'com.fokus.rei152-isolated-test'
$mysqlName = "fokus-rei152-mysql-$runId"
$phpName = "fokus-rei152-php-$runId"
$volumeName = "fokus-rei152-socket-$runId"
$mysqlId = $null
$phpId = $null
$volumeCreated = $false
$cleanupFailed = $false
$mysqlAttempted = $false
$phpAttempted = $false
$volumeAttempted = $false
$marker = ([Guid]::NewGuid().ToString('N') + [Guid]::NewGuid().ToString('N')).ToLowerInvariant()

function Invoke-DockerSafe([string[]]$arguments) {
    $previousErrorActionPreference = $ErrorActionPreference
    try {
        # Failure-injection diagnostics from PHP are expected on stderr.
        # Prevent PowerShell from promoting native stderr to a terminating
        # error; the Docker exit code remains the authoritative result.
        $ErrorActionPreference = 'Continue'
        $output = & docker --host $dockerEndpoint @arguments 2>&1
        $dockerExitCode = $LASTEXITCODE
    } finally {
        $ErrorActionPreference = $previousErrorActionPreference
    }
    if ($dockerExitCode -ne 0) { throw "Docker falhou em '$($arguments[0])': $($output -join ' ')" }
    return $output
}

function Assert-OwnedContainer([string]$id, [string]$name) {
    if ($id -notmatch '^[a-f0-9]{64}$') { throw 'ID de container inválido.' }
    $item = @((Invoke-DockerSafe @('inspect', $id)) | ConvertFrom-Json)[0]
    if ($item.Config.Labels.$label -ne $runId -or $item.Name -ne "/$name") { throw 'Identidade/propriedade do container não confere.' }
    return $item
}

function Convert-VerifiedBindSource([string]$path) {
    $normalized = $path.TrimEnd('/')
    if (-not [IO.Path]::IsPathRooted($normalized) -or $normalized -match '(^|/)\.\.?(/|$)' -or (Resolve-Path -LiteralPath $normalized).Path -cne $normalized) {
        throw 'Topologia recusada: origem efetiva do bind não reconhecida.'
    }
    return $normalized
}

function Assert-Mounts($item, [hashtable]$expected, [string]$tmpfsPath) {
    $mounts = @($item.Mounts)
    if ($mounts.Count -ne $expected.Count) { throw 'Topologia recusada: quantidade inesperada de mounts.' }
    foreach ($mount in $mounts) {
        if (-not $expected.ContainsKey($mount.Destination)) { throw 'Topologia recusada: destino de mount inesperado.' }
        $entry = $expected[$mount.Destination]
        if ($mount.Type -ne $entry.Type -or [bool]$mount.RW -ne [bool]$entry.RW) {
            throw 'Topologia recusada: tipo ou modo do mount divergente.'
        }
        if ($entry.Type -eq 'volume' -and $mount.Name -ne $volumeName) { throw 'Topologia recusada: volume diferente do criado nesta execução.' }
        if ($entry.Type -eq 'bind' -and (Convert-VerifiedBindSource ([string]$mount.Source)) -cne (Convert-VerifiedBindSource ([string]$entry.Source))) {
            throw 'Topologia recusada: origem efetiva do bind não corresponde à autorizada.'
        }
        $declared = @($item.HostConfig.Mounts | Where-Object { $_.Target -eq $mount.Destination })
        if ($declared.Count -ne 1 -or $declared[0].Source -ne $entry.Source -or [bool]$declared[0].ReadOnly -ne (-not [bool]$entry.RW)) {
            throw 'Topologia recusada: origem declarada ou modo do mount divergente.'
        }
    }
    $tmpfs = $item.HostConfig.Tmpfs
    if ($null -eq $tmpfs -or @($tmpfs.PSObject.Properties).Count -ne 1 -or -not $tmpfs.PSObject.Properties[$tmpfsPath]) {
        throw 'Topologia recusada: tmpfs ausente ou inesperado.'
    }
    $expectedOptions = if ($tmpfsPath -eq '/var/lib/mysql') { 'rw,size=512m' } else { 'rw,size=64m' }
    if ([string]$tmpfs.$tmpfsPath -ne $expectedOptions) { throw 'Topologia recusada: opções declaradas de tmpfs divergentes.' }
}

function Assert-ActiveTmpfs([string]$id, [string]$target) {
    $lines = @(Invoke-DockerSafe @('exec', $id, 'cat', '/proc/self/mountinfo'))
    $pattern = '\s' + [regex]::Escape($target) + '\s.*\s-\stmpfs\s'
    if (@($lines | Where-Object { $_ -match $pattern }).Count -ne 1) {
        throw 'Topologia recusada: mount tmpfs ativo não confirmado.'
    }
}

try {
    if (Test-Path -LiteralPath $syntheticConfig) { throw 'Diretório sintético já existe.' }
    $directory = New-Item -ItemType Directory -Path $syntheticConfig
    $configCreated = $true
    if ($directory.Attributes -band [IO.FileAttributes]::ReparsePoint) { throw 'Config sintética não pode ser reparse point.' }
    $securityFixture = @'
<?php
declare(strict_types=1);
// Fixture sintética: não substitui validação ou configuração de produção.
final class Security
{
    public static function validarCPF(string $cpf): bool
    {
        $digits = preg_replace('/\D+/', '', $cpf) ?? '';
        if (strlen($digits) !== 11 || preg_match('/^(\d)\1{10}$/', $digits)) return false;
        for ($position = 9; $position <= 10; $position++) {
            $sum = 0;
            for ($i = 0; $i < $position; $i++) $sum += (int) $digits[$i] * ($position + 1 - $i);
            $check = ($sum * 10) % 11;
            if ($check === 10) $check = 0;
            if ($check !== (int) $digits[$position]) return false;
        }
        return true;
    }
    public static function formatCPF(string $cpf): string
    {
        return substr($cpf, 0, 3) . '.' . substr($cpf, 3, 3) . '.' . substr($cpf, 6, 3) . '-' . substr($cpf, 9, 2);
    }
}
'@
    $configFixture = @'
<?php
declare(strict_types=1);
throw new RuntimeException('A fixture não permite abrir conexão pela configuração da aplicação.');
'@
    [IO.File]::WriteAllText((Join-Path $syntheticConfig 'security.php'), $securityFixture, [Text.UTF8Encoding]::new($false))
    [IO.File]::WriteAllText((Join-Path $syntheticConfig 'config.php'), $configFixture, [Text.UTF8Encoding]::new($false))
    $configEntries = @(Get-ChildItem -LiteralPath $syntheticConfig -Force)
    if ($configEntries.Count -ne 2 -or @($configEntries | Where-Object { $_.Name -notin @('security.php', 'config.php') -or $_.PSIsContainer -or ($_.Attributes -band [IO.FileAttributes]::ReparsePoint) }).Count -ne 0) {
        throw 'Conteúdo da config sintética é inesperado.'
    }
    $mysqlImage = @((Invoke-DockerSafe @('image', 'inspect', $mysqlImageRef)) | ConvertFrom-Json)[0]
    $phpImage = @((Invoke-DockerSafe @('image', 'inspect', $phpImageRef)) | ConvertFrom-Json)[0]
    if (-not $mysqlImage.Id -or @($mysqlImage.RepoDigests) -notcontains $mysqlImageRef -or $phpImage.Id -cne $QaImageId) { throw 'Imagens imutáveis não conferem.' }

    $volumeAttempted = $true
    Invoke-DockerSafe @('volume', 'create', '--label', "$label=$runId", $volumeName) | Out-Null
    $volumeCreated = $true
    $volume = @((Invoke-DockerSafe @('volume', 'inspect', $volumeName)) | ConvertFrom-Json)[0]
    if ($volume.Labels.$label -ne $runId) { throw 'Volume de socket não pertence a esta execução.' }

    $mysqlAttempted = $true
    $mysqlId = (Invoke-DockerSafe @(
        'create', '--name', $mysqlName, '--label', "$label=$runId", '--network', 'none',
        '--tmpfs', '/var/lib/mysql:rw,size=512m', '--security-opt', 'no-new-privileges=true',
        '--mount', "type=volume,source=$volumeName,target=/var/run/mysqld",
        '--env', 'MYSQL_ALLOW_EMPTY_PASSWORD=1', '--env', 'MYSQL_DATABASE=rei152_test', $mysqlImageRef
    ) | Select-Object -Last 1).Trim()
    $mysql = Assert-OwnedContainer $mysqlId $mysqlName
    $mysqlPortBindings = if ($mysql.HostConfig.PortBindings) { @($mysql.HostConfig.PortBindings.PSObject.Properties).Count } else { 0 }
    if ($mysql.Image -cne $mysqlImage.Id -or $mysql.HostConfig.NetworkMode -ne 'none' -or $mysql.HostConfig.Privileged -or $mysqlPortBindings -ne 0) {
        throw 'Topologia MySQL recusada: rede, privilégio ou porta exposta.'
    }
    Assert-Mounts $mysql @{ '/var/run/mysqld' = @{ Type='volume'; RW=$true; Source=$volumeName } } '/var/lib/mysql'
    Invoke-DockerSafe @('start', $mysqlId) | Out-Null
    $mysql = Assert-OwnedContainer $mysqlId $mysqlName
    Assert-Mounts $mysql @{ '/var/run/mysqld' = @{ Type='volume'; RW=$true; Source=$volumeName } } '/var/lib/mysql'
    Assert-ActiveTmpfs $mysqlId '/var/lib/mysql'
    $ready = $false
    for ($i = 0; $i -lt 90; $i++) {
        try {
            Invoke-DockerSafe @('exec', $mysqlId, 'mysql', '--protocol=socket', '--socket=/var/run/mysqld/mysqld.sock', '-uroot', 'rei152_test', '-N', '-B', '-e', 'SELECT 1') | Out-Null
            $ready = $true
            break
        } catch { Start-Sleep -Seconds 1 }
    }
    if (-not $ready) { throw 'MySQL descartável não ficou pronto.' }
    $emptyTables = @(Invoke-DockerSafe @('exec', $mysqlId, 'mysql', '--protocol=socket', '--socket=/var/run/mysqld/mysqld.sock', '-uroot', 'rei152_test', '-N', '-B', '-e', 'SHOW TABLES'))
    if (@($emptyTables | Where-Object { $_ }).Count -ne 0) { throw 'Banco descartável não está vazio.' }
    Invoke-DockerSafe @('exec', $mysqlId, 'mysql', '--protocol=socket', '--socket=/var/run/mysqld/mysqld.sock', '-uroot', 'rei152_test', '-e', "CREATE TABLE _sandbox_proof (token CHAR(64) NOT NULL); INSERT INTO _sandbox_proof VALUES ('$marker')") | Out-Null

    $phpAttempted = $true
    $phpId = (Invoke-DockerSafe @(
        'create', '--name', $phpName, '--label', "$label=$runId", '--network', 'none', '--read-only',
        '--cap-drop', 'ALL', '--security-opt', 'no-new-privileges=true', '--tmpfs', '/tmp:rw,size=64m',
        '--mount', "type=bind,source=$appRoot,target=/repo/app,readonly",
        '--mount', "type=bind,source=$syntheticConfig,target=/repo/config,readonly",
        '--mount', "type=bind,source=$testFile,target=/repo/tests/rei152-documentos-mysql.test.php,readonly",
        '--mount', "type=bind,source=$workerFile,target=/repo/tests/rei152-documentos-mysql-worker.php,readonly",
        '--mount', "type=bind,source=$entryFile,target=/repo/tests/rei152-linux-ci-entry.php,readonly",
        '--mount', "type=bind,source=$gateFile,target=/repo/tests/rei152-gate-alternate-mysql.test.php,readonly",
        '--mount', "type=bind,source=$createFile,target=/repo/tests/rei152-create-mysql.test.php,readonly",
        '--mount', "type=volume,source=$volumeName,target=/var/run/mysqld",
        '--env', 'REI152_TEST_ISOLATION=rei152-disposable-mysql',
        '--env', 'REI152_MYSQL_DSN=mysql:unix_socket=/var/run/mysqld/mysqld.sock;dbname=rei152_test;charset=utf8mb4',
        '--env', 'REI152_MYSQL_USER=root', '--entrypoint', 'sleep', $phpImageRef, '300'
    ) | Select-Object -Last 1).Trim()
    $php = Assert-OwnedContainer $phpId $phpName
    $phpPortBindings = if ($php.HostConfig.PortBindings) { @($php.HostConfig.PortBindings.PSObject.Properties).Count } else { 0 }
    if ($php.Image -cne $phpImage.Id -or $php.HostConfig.NetworkMode -ne 'none' -or $php.HostConfig.Privileged -or $phpPortBindings -ne 0 -or
        @($php.HostConfig.CapDrop) -notcontains 'ALL' -or -not $php.HostConfig.ReadonlyRootfs) {
        throw 'Topologia PHP recusada: isolamento, rootfs, capacidades ou portas inválidas.'
    }
    Assert-Mounts $php @{
        '/repo/app' = @{ Type='bind'; RW=$false; Source=$appRoot }
        '/repo/config' = @{ Type='bind'; RW=$false; Source=$syntheticConfig }
        '/repo/tests/rei152-documentos-mysql.test.php' = @{ Type='bind'; RW=$false; Source=$testFile }
        '/repo/tests/rei152-documentos-mysql-worker.php' = @{ Type='bind'; RW=$false; Source=$workerFile }
        '/repo/tests/rei152-linux-ci-entry.php' = @{ Type='bind'; RW=$false; Source=$entryFile }
        '/repo/tests/rei152-gate-alternate-mysql.test.php' = @{ Type='bind'; RW=$false; Source=$gateFile }
        '/repo/tests/rei152-create-mysql.test.php' = @{ Type='bind'; RW=$false; Source=$createFile }
        '/var/run/mysqld' = @{ Type='volume'; RW=$true; Source=$volumeName }
    } '/tmp'
    Invoke-DockerSafe @('start', $phpId) | Out-Null
    $php = Assert-OwnedContainer $phpId $phpName
    Assert-Mounts $php @{
        '/repo/app' = @{ Type='bind'; RW=$false; Source=$appRoot }
        '/repo/config' = @{ Type='bind'; RW=$false; Source=$syntheticConfig }
        '/repo/tests/rei152-documentos-mysql.test.php' = @{ Type='bind'; RW=$false; Source=$testFile }
        '/repo/tests/rei152-documentos-mysql-worker.php' = @{ Type='bind'; RW=$false; Source=$workerFile }
        '/repo/tests/rei152-linux-ci-entry.php' = @{ Type='bind'; RW=$false; Source=$entryFile }
        '/repo/tests/rei152-gate-alternate-mysql.test.php' = @{ Type='bind'; RW=$false; Source=$gateFile }
        '/repo/tests/rei152-create-mysql.test.php' = @{ Type='bind'; RW=$false; Source=$createFile }
        '/var/run/mysqld' = @{ Type='volume'; RW=$true; Source=$volumeName }
    } '/tmp'
    Assert-ActiveTmpfs $phpId '/tmp'
    Invoke-DockerSafe @('exec', '--env', 'REI152_CI_REQUIRED=1', '--env', "REI152_CI_MARKER=$marker", $phpId, 'php', '/repo/tests/rei152-linux-ci-entry.php')
} finally {
    foreach ($pending in @(@{ Attempted=$phpAttempted; Id=$phpId; Name=$phpName }, @{ Attempted=$mysqlAttempted; Id=$mysqlId; Name=$mysqlName })) {
        if ($pending.Attempted -and -not $pending.Id) { $cleanupFailed = $true; Write-Warning "Criação com resultado indeterminado ($($pending.Name)); não assumir ausência." }
    }
    if ($volumeAttempted -and -not $volumeCreated) { $cleanupFailed = $true; Write-Warning 'Criação do volume teve resultado indeterminado.' }
    foreach ($entry in @(@{ Id = $phpId; Name = $phpName }, @{ Id = $mysqlId; Name = $mysqlName })) {
        if (-not $entry.Id) { continue }
        try {
            $item = Assert-OwnedContainer ([string]$entry.Id) ([string]$entry.Name)
            if ($item.State.Running) { Invoke-DockerSafe @('stop', [string]$entry.Id) | Out-Null }
            Invoke-DockerSafe @('rm', [string]$entry.Id) | Out-Null
            if (@(Invoke-DockerSafe @('ps', '-aq', '--no-trunc', '--filter', "id=$($entry.Id)")).Count -ne 0) { throw 'Container ainda existe.' }
        } catch {
            $cleanupFailed = $true
            Write-Warning "Container não removido sem prova de identidade/limpeza ($($entry.Name))."
        }
    }
    if ($volumeCreated) {
        try {
            $volume = @((Invoke-DockerSafe @('volume', 'inspect', $volumeName)) | ConvertFrom-Json)[0]
            if ($volume.Labels.$label -ne $runId) { throw 'Volume sem propriedade confirmada.' }
            Invoke-DockerSafe @('volume', 'rm', $volumeName) | Out-Null
            if (@(Invoke-DockerSafe @('volume', 'ls', '-q', '--filter', "name=^$volumeName$" )).Count -ne 0) { throw 'Volume ainda existe.' }
        } catch {
            $cleanupFailed = $true
            Write-Warning "Volume não removido sem prova de identidade/limpeza ($volumeName)."
        }
    }
    if ($configCreated -and -not $cleanupFailed) {
        try {
            $directory = Get-Item -LiteralPath $syntheticConfig -Force
            if ($directory.Attributes -band [IO.FileAttributes]::ReparsePoint) { throw 'Config sintética virou reparse point.' }
            $entries = @(Get-ChildItem -LiteralPath $syntheticConfig -Force)
            if (@($entries | Where-Object { $_.Name -notin @('security.php', 'config.php') -or ($_.Attributes -band [IO.FileAttributes]::ReparsePoint) -or $_.PSIsContainer }).Count -ne 0) {
                throw 'Config sintética contém itens não reconhecidos.'
            }
            foreach ($entry in $entries) { Remove-Item -LiteralPath $entry.FullName }
            Remove-Item -LiteralPath $syntheticConfig
        } catch {
            $cleanupFailed = $true
            Write-Warning 'Config sintética não removida sem confirmar identidade e conteúdo.'
        }
    }
    if ($configCreated -and $cleanupFailed) { Write-Warning 'Config sintética preservada devido a falha de limpeza.' }
    if ($cleanupFailed) { throw 'Limpeza dos recursos descartáveis não foi confirmada.' }
}
