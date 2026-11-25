[CmdletBinding()]
param([ValidateSet(0, 1)][int]$LowerCaseTableNames = 0, [switch]$CiLinux, [string]$QaImageId)

$ErrorActionPreference = 'Stop'
if (-not $CiLinux -or -not $IsLinux -or $env:GITHUB_ACTIONS -ne 'true' -or $env:CI -ne 'true' -or $env:RUNNER_OS -ne 'Linux' -or $env:RUNNER_ENVIRONMENT -ne 'github-hosted') { throw 'Somente runner GitHub-hosted Linux descartável autorizado.' }
$repoRoot = (Resolve-Path (Join-Path $PSScriptRoot '../..')).Path
$appRoot = Join-Path $repoRoot 'app'

$testFile = Join-Path $PSScriptRoot 'rei168-mysql.test.php'
function Get-VerifiedSource([string]$path) {
    if (-not [IO.Path]::IsPathRooted($path) -or -not (Test-Path -LiteralPath $path)) { throw 'Origem de bind indisponível.' }
    $full = [IO.Path]::GetFullPath($path).TrimEnd('/')
    $real = (& realpath -e -- $full 2>$null | Select-Object -Last 1)
    if ($LASTEXITCODE -ne 0 -or [string]::IsNullOrWhiteSpace($real) -or $full -cne $real) { throw 'Origem de bind contém link ou não é canônica.' }
    $cursor = $full
    while ($cursor -and $cursor -ne '/') {
        $entry = Get-Item -LiteralPath $cursor -Force
        if ($entry.Attributes -band [IO.FileAttributes]::ReparsePoint) { throw 'Origem ou ancestral de bind contém link.' }
        $cursor = [IO.Path]::GetDirectoryName($cursor)
    }
    return $real
}
$repoRoot = Get-VerifiedSource $repoRoot
$appRoot = Get-VerifiedSource $appRoot
$testFile = Get-VerifiedSource $testFile
$workspace = Get-VerifiedSource ([string]$env:GITHUB_WORKSPACE)
if ($repoRoot -cne $workspace -or -not $appRoot.StartsWith($workspace + '/', [StringComparison]::Ordinal) -or -not $testFile.StartsWith($workspace + '/', [StringComparison]::Ordinal)) {
    throw 'Origens não pertencem ao workspace CI esperado.'
}
$mysqlImageRef = 'mysql@sha256:0426ec38c7a10aa45ba383887df7878f74ee70e2fd589c7b69207f3577901903'
if ($QaImageId -notmatch '^sha256:[a-f0-9]{64}$') { throw 'ID de imagem CI explicito obrigatorio.' }
$phpImageRef = $QaImageId
if (Test-Path -LiteralPath (Join-Path $repoRoot '.env')) {
    throw 'Runner recusado: não execute a fixture em checkout com .env.'
}
foreach ($path in @($appRoot, $testFile)) {
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
$label = 'com.fokus.rei168-cartas-test'
$dbName = "fokus-rei168-mysql-$runId"
$phpName = "fokus-rei168-php-$runId"
$volumeName = "fokus-rei168-socket-$runId"

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

function Get-VerifiedSocketMountTarget([string]$id) {
    try {
        $resolved = @(& timeout -k 2s 5s docker --host $dockerEndpoint exec $id realpath -e -- /var/run/mysqld 2>&1)
        $exitCode = $LASTEXITCODE
        if ($exitCode -ne 0 -or $resolved.Count -ne 1 -or $resolved[0] -isnot [string] -or $resolved[0] -cnotin @('/var/run/mysqld', '/run/mysqld')) {
            throw 'Resolução de socket não autorizada.'
        }
        $canonical = [string]$resolved[0]
        $stats = @(& timeout -k 2s 5s docker --host $dockerEndpoint exec $id stat -Lc '%d:%i' /var/run/mysqld $canonical 2>&1)
        if ($LASTEXITCODE -ne 0 -or $stats.Count -ne 2 -or $stats[0] -isnot [string] -or $stats[1] -isnot [string] -or $stats[0] -notmatch '^\d+:\d+$' -or $stats[0] -cne $stats[1]) {
            throw 'Identidades do socket divergentes.'
        }
        return $canonical
    } catch {
        # Nunca propagar saída bruta de caminhos ou diagnósticos do container.
        throw 'Resolução ou identidade de socket não comprovada.'
    }
}
function Assert-MountinfoExact([string[]]$lines, [string]$target, [string]$fsType) {
    $targetEntries = @()
    foreach ($line in $lines) {
        $halves = $line -split ' - '
        if ($halves.Count -ne 2) { throw 'Formato mountinfo ambíguo.' }
        $left = $halves[0] -split ' '
        $right = $halves[1] -split ' '
        if ($left.Count -lt 6 -or $right.Count -lt 3 -or $left[0] -notmatch '^\d+$' -or $left[1] -notmatch '^\d+$' -or $left[2] -notmatch '^\d+:\d+$' -or -not $left[4].StartsWith('/') -or $left[4].Contains('\') -or $right[0] -notmatch '^[a-zA-Z0-9_.-]+$') {
            throw 'Formato ou escape de mountinfo não autorizado.'
        }
        if ($left[4] -ceq $target) { $targetEntries += $right[0] }
    }
    if ($targetEntries.Count -ne 1 -or ($fsType -ne '' -and $targetEntries[0] -cne $fsType)) {
        throw 'Mountpoint ativo ou tipo não comprovado.'
    }
}
function Assert-ActiveMount([string]$id, [string]$target, [string]$fsType = '', [string]$phase = '') {
    $labels = @{ '/var/lib/mysql'='mysql-datadir'; '/var/run/mysqld'='socket-dir'; '/tmp'='php-tmp'; '/repo/app'='php-app'; '/test/rei168-mysql.test.php'='php-fixture' }
    $phases = @('mysql-start', 'mysql-before-marker', 'php-start', 'mysql-before-fixture')
    if (-not $labels.ContainsKey($target) -or $phase -notin $phases -or $fsType -notin @('', 'tmpfs')) { throw 'Diagnóstico de mount inválido.' }
    $actualTarget = $target
    if ($target -ceq '/var/run/mysqld') { $actualTarget = Get-VerifiedSocketMountTarget $id }
    $lines = @(Invoke-DockerSafe @('exec', $id, 'cat', '/proc/self/mountinfo'))
    try {
        Assert-MountinfoExact $lines $actualTarget $fsType
    } catch {
        $expected = if ($fsType -eq 'tmpfs') { 'tmpfs' } else { 'any' }
        $resolution = if ($target -cne '/var/run/mysqld') { 'nao-aplicavel' } elseif ($actualTarget -ceq '/run/mysqld') { 'run-mysqld' } else { 'literal' }
        throw ("Mount ativo não comprovado: fase={0}; destino={1}; esperado={2}; resolucao={3}." -f $phase, $labels[$target], $expected, $resolution)
    }
}
function Assert-MountSet($item, [hashtable]$expected, [string]$tmpfsPath, [string]$tmpfsOptions) {
    $mounts = @($item.Mounts)
    if ($mounts.Count -ne $expected.Count) { throw 'Quantidade de mounts divergente.' }
    foreach ($entry in $expected.GetEnumerator()) {
        $found = @($mounts | Where-Object { $_.Destination -ceq $entry.Key })
        $declared = @($item.HostConfig.Mounts | Where-Object { $_.Target -ceq $entry.Key })
        if ($found.Count -ne 1 -or $declared.Count -ne 1) { throw 'Mount ausente ou duplicado.' }
        $mount = $found[0]; $spec = $entry.Value; $declaration = $declared[0]
        if ($mount.Type -cne $spec.Type -or [bool]$mount.RW -ne [bool]$spec.RW -or $declaration.Type -cne $spec.Type -or [bool]$declaration.ReadOnly -ne (-not [bool]$spec.RW)) { throw 'Tipo ou modo de mount divergente.' }
        if ($spec.Type -eq 'bind') {
            if ((Get-VerifiedSource ([string]$mount.Source)) -cne $spec.Source -or (Get-VerifiedSource ([string]$declaration.Source)) -cne $spec.Source) { throw 'Origem efetiva ou declarada de bind divergente.' }
        } else {
            if ($mount.Name -cne $volumeName -or $declaration.Source -cne $volumeName -or -not [IO.Path]::IsPathRooted([string]$mount.Source) -or [string]$mount.Source -cne [string]$volumeInfo.Mountpoint) { throw 'Volume de socket não corresponde ao volume identificado.' }
        }
    }
    $tmpfs = $item.HostConfig.Tmpfs
    if (-not $tmpfs -or @($tmpfs.PSObject.Properties).Count -ne 1 -or [string]$tmpfs.$tmpfsPath -cne $tmpfsOptions) { throw 'Declaração tmpfs divergente.' }
}
function Get-SocketDirIdentity([string]$id) {
    $identity = (Invoke-DockerSafe @('exec', $id, 'stat', '-Lc', '%d:%i', '/var/run/mysqld') | Select-Object -Last 1).Trim()
    if ($identity -notmatch '^\d+:\d+$') { throw 'Identidade de diretório de socket indisponível.' }
    return $identity
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
        '--env', 'MYSQL_ALLOW_EMPTY_PASSWORD=1', '--env', 'MYSQL_DATABASE=rei168_test',
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
    $dbExpected = @{ '/var/run/mysqld' = @{ Type='volume'; RW=$true; Source=$volumeName } }
    Assert-MountSet $db $dbExpected '/var/lib/mysql' 'rw,size=512m'
    Invoke-DockerSafe @('start', $mysqlId) | Out-Null
    $db = Get-OwnedContainer $mysqlId
    if (-not $db.State.Running -or $db.Image -cne $mysqlImage.Id -or $db.HostConfig.NetworkMode -ne 'none' -or $db.HostConfig.Privileged -or @($db.HostConfig.SecurityOpt) -notcontains 'no-new-privileges=true' -or ($db.HostConfig.PortBindings -and @($db.HostConfig.PortBindings.PSObject.Properties).Count -gt 0)) { throw 'Topologia MySQL ativa divergente.' }
    Assert-MountSet $db $dbExpected '/var/lib/mysql' 'rw,size=512m'
    Assert-ActiveMount $mysqlId '/var/lib/mysql' 'tmpfs' 'mysql-start'
    Assert-ActiveMount $mysqlId '/var/run/mysqld' '' 'mysql-start'
    $mysqlSocketIdentity = Get-SocketDirIdentity $mysqlId

    $ready = $false
    for ($i = 0; $i -lt 90; $i++) {
        $probe = $null
        try {
            $probe = Invoke-DockerSafe @(
                'exec', $mysqlId, 'mysql', '--protocol=socket', '--socket=/var/run/mysqld/mysqld.sock',
                '-uroot', 'rei168_test', '-N', '-e', 'SELECT 1'
            )
        } catch { }
        if ($LASTEXITCODE -eq 0 -and ($probe | Select-Object -Last 1).Trim() -eq '1') {
            $ready = $true
            break
        }
        Start-Sleep -Seconds 1
    }
    if (-not $ready) { throw 'MySQL descartável ou banco de teste não ficou pronto.' }

    # Revalidar ambiente e banco vazio imediatamente antes do primeiro DDL da fixture.
    $db = Get-OwnedContainer $mysqlId
    if (-not $db.State.Running) { throw 'MySQL não está ativo.' }
    Assert-MountSet $db $dbExpected '/var/lib/mysql' 'rw,size=512m'
    Assert-ActiveMount $mysqlId '/var/lib/mysql' 'tmpfs' 'mysql-before-marker'
    Assert-ActiveMount $mysqlId '/var/run/mysqld' '' 'mysql-before-marker'
    $identitySql = "SELECT CONCAT(DATABASE(),'|',VERSION(),'|',@@datadir,'|',@@socket,'|',@@lower_case_table_names); SHOW TABLES;"
    $identity = @(Invoke-DockerSafe @('exec', $mysqlId, 'mysql', '--protocol=socket', '--socket=/var/run/mysqld/mysqld.sock', '-uroot', 'rei168_test', '-N', '-B', '-e', $identitySql) | Where-Object { $_ })
    if ($identity.Count -ne 1 -or $identity[0] -notmatch ("^rei168_test\|8\.4\.[^|]+\|/var/lib/mysql/\|/var/run/mysqld/mysqld\.sock\|" + $LowerCaseTableNames + '$')) { throw 'Identidade, versão ou schema vazio do MySQL não comprovados.' }

    $markerSql = "CREATE TABLE rei168_test_guard (marker CHAR(64) NOT NULL PRIMARY KEY) ENGINE=InnoDB; INSERT INTO rei168_test_guard (marker) VALUES ('$marker');"
    Invoke-DockerSafe @('exec', $mysqlId, 'mysql', '--protocol=socket', '--socket=/var/run/mysqld/mysqld.sock', '-uroot', 'rei168_test', '-e', $markerSql) | Out-Null
    $markerRead = @(Invoke-DockerSafe @('exec', $mysqlId, 'mysql', '--protocol=socket', '--socket=/var/run/mysqld/mysqld.sock', '-uroot', 'rei168_test', '-N', '-B', '-e', 'SELECT marker FROM rei168_test_guard; SHOW TABLES;') | Where-Object { $_ })
    if ($markerRead.Count -ne 2 -or $markerRead[0] -cne $marker -or $markerRead[1] -cne 'rei168_test_guard') { throw 'Marker ou schema inicial não conferem.' }

    $appMount = "type=bind,source=$appRoot,target=/repo/app,readonly"
    $testMount = "type=bind,source=$testFile,target=/test/rei168-mysql.test.php,readonly"
    $phpCreateAttempted = $true
    $phpId = (Invoke-DockerSafe @(
        'create', '--name', $phpName, '--label', "$label=$runId", '--network', 'none',
        '--cpus', '1', '--memory', '512m', '--read-only', '--cap-drop', 'ALL',
        '--security-opt', 'no-new-privileges=true', '--tmpfs', '/tmp:rw,size=64m,noexec,nosuid,nodev',
        '--mount', $appMount, '--mount', $testMount,
        '--mount', "type=volume,source=$volumeName,target=/var/run/mysqld",
        '--env', "REI168_EXPECT_LOWER_CASE=$LowerCaseTableNames", '--env', 'REI168_TEST_DISPOSABLE=1', '--env', 'REI168_TEST_REPO_ROOT=/repo',
        '--env', "REI168_TEST_DISPOSABLE_MARKER=$marker",
        '--env', 'REI168_MYSQL_DSN=mysql:unix_socket=/var/run/mysqld/mysqld.sock;dbname=rei168_test;charset=utf8mb4',
        '--env', 'REI168_MYSQL_USER=root', '--env', 'APP_ENV=fixture',
        '--entrypoint', 'sleep', $phpImage.Id, '300'
    ) | Select-Object -Last 1).Trim()
    if ($phpId -notmatch '^[a-f0-9]{64}$') { throw 'ID PHP inválido.' }
    $php = Get-OwnedContainer $phpId
    $phpMounts = @($php.Mounts)
    $phpPortCount = if ($php.HostConfig.PortBindings) { @($php.HostConfig.PortBindings.PSObject.Properties).Count } else { 0 }
    $phpInvalid = $php.Image -ne $phpImage.Id -or $php.HostConfig.NetworkMode -ne 'none' -or $php.HostConfig.Privileged
    $phpInvalid = $phpInvalid -or $phpPortCount -ne 0 -or $phpMounts.Count -ne 3
    $phpInvalid = $phpInvalid -or -not $php.HostConfig.ReadonlyRootfs -or @($php.HostConfig.CapDrop) -notcontains 'ALL'
    $phpInvalid = $phpInvalid -or @($php.HostConfig.SecurityOpt) -notcontains 'no-new-privileges=true'
    $phpInvalid = $phpInvalid -or @($phpMounts | Where-Object { $_.Type -eq 'bind' -and $_.Destination -eq '/repo/app' -and -not $_.RW }).Count -ne 1
    $phpInvalid = $phpInvalid -or @($phpMounts | Where-Object { $_.Type -eq 'bind' -and $_.Destination -eq '/test/rei168-mysql.test.php' -and -not $_.RW }).Count -ne 1
    $phpInvalid = $phpInvalid -or @($phpMounts | Where-Object { $_.Type -eq 'volume' -and $_.Name -eq $volumeName -and $_.Destination -eq '/var/run/mysqld' }).Count -ne 1
    if ($phpInvalid) {
        throw 'Topologia PHP recusada: código somente leitura, socket próprio, sem rede/portas são obrigatórios.'
    }
    $phpExpected = @{
        '/repo/app' = @{ Type='bind'; RW=$false; Source=$appRoot }
        '/test/rei168-mysql.test.php' = @{ Type='bind'; RW=$false; Source=$testFile }
        '/var/run/mysqld' = @{ Type='volume'; RW=$true; Source=$volumeName }
    }
    Assert-MountSet $php $phpExpected '/tmp' 'rw,size=64m,noexec,nosuid,nodev'
    Invoke-DockerSafe @('start', $phpId) | Out-Null
    $php = Get-OwnedContainer $phpId
    if (-not $php.State.Running -or $php.Image -cne $phpImage.Id -or $php.HostConfig.NetworkMode -ne 'none' -or $php.HostConfig.Privileged -or -not $php.HostConfig.ReadonlyRootfs -or @($php.HostConfig.CapDrop) -notcontains 'ALL' -or @($php.HostConfig.SecurityOpt) -notcontains 'no-new-privileges=true' -or ($php.HostConfig.PortBindings -and @($php.HostConfig.PortBindings.PSObject.Properties).Count -gt 0)) { throw 'Topologia PHP ativa divergente.' }
    Assert-MountSet $php $phpExpected '/tmp' 'rw,size=64m,noexec,nosuid,nodev'
    Assert-ActiveMount $phpId '/tmp' 'tmpfs' 'php-start'
    Assert-ActiveMount $phpId '/repo/app' '' 'php-start'
    Assert-ActiveMount $phpId '/test/rei168-mysql.test.php' '' 'php-start'
    Assert-ActiveMount $phpId '/var/run/mysqld' '' 'php-start'
    if ((Get-SocketDirIdentity $phpId) -cne $mysqlSocketIdentity -or (Get-SocketDirIdentity $mysqlId) -cne $mysqlSocketIdentity) { throw 'Diretórios de socket ativos não correspondem.' }
    $db = Get-OwnedContainer $mysqlId
    if (-not $db.State.Running) { throw 'MySQL não está ativo antes da fixture.' }
    Assert-MountSet $db $dbExpected '/var/lib/mysql' 'rw,size=512m'
    Assert-ActiveMount $mysqlId '/var/lib/mysql' 'tmpfs' 'mysql-before-fixture'
    Assert-ActiveMount $mysqlId '/var/run/mysqld' '' 'mysql-before-fixture'
    $markerRead = @(Invoke-DockerSafe @('exec', $mysqlId, 'mysql', '--protocol=socket', '--socket=/var/run/mysqld/mysqld.sock', '-uroot', 'rei168_test', '-N', '-B', '-e', 'SELECT marker FROM rei168_test_guard; SHOW TABLES;') | Where-Object { $_ })
    if ($markerRead.Count -ne 2 -or $markerRead[0] -cne $marker -or $markerRead[1] -cne 'rei168_test_guard') { throw 'Marker ou schema divergiram antes da fixture.' }
    $result = Invoke-DockerSafe @('exec', $phpId, 'php', '-d', 'error_log=/tmp/rei168-test.log', '/test/rei168-mysql.test.php')
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
    if ($cleanupFailed) { throw 'Limpeza dos recursos próprios não foi totalmente comprovada.' }
}
