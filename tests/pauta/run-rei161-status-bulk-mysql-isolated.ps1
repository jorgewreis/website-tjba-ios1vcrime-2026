[CmdletBinding()]
param([switch]$CiLinux, [string]$QaImageId = '')

$ErrorActionPreference = 'Stop'
$repoRoot = (Resolve-Path (Join-Path $PSScriptRoot '../..')).Path
$testFile = Join-Path $PSScriptRoot 'rei161-status-bulk-mysql.test.php'
$endpointFile = Join-Path $repoRoot 'public/api/sala-audiencias-pauta/status-bulk.php'
$privacyFile = Join-Path $repoRoot 'app/helpers/SalaAudienciasEstudantePrivacidade.php'
$mysqlImage = 'mysql@sha256:0426ec38c7a10aa45ba383887df7878f74ee70e2fd589c7b69207f3577901903'
$phpImage = 'fokus-law-linux-qa@sha256:459cc8bd25b38009a8cf9a0ef5399d131f4f2001a1376d89389f673a92de430b'
if ($CiLinux) {
    if (-not $IsLinux -or $env:GITHUB_ACTIONS -cne 'true' -or $env:GITHUB_RUN_ID -notmatch '^[0-9]+$' -or $env:GITHUB_RUN_ATTEMPT -notmatch '^[0-9]+$' -or $QaImageId -cnotmatch '^sha256:[a-f0-9]{64}$' -or -not $env:RUNNER_TEMP) { throw 'Contexto ou imagem CI inválidos.' }
    $iidFile = Join-Path $env:RUNNER_TEMP 'rei161-qa.id'
    if (-not (Test-Path -LiteralPath $iidFile -PathType Leaf)) { throw 'Identidade da imagem construída no job ausente.' }
    $iid = (Get-Content -LiteralPath $iidFile -Raw).Trim()
    if ($iid -cne $QaImageId) { throw 'Imagem não corresponde à construção deste job.' }
    $phpImage = $QaImageId
} elseif ($QaImageId -ne '') {
    throw 'Imagem QA arbitrária recusada fora do CI.'
}
$runId = [Guid]::NewGuid().ToString('N')
$label = 'com.fokus.rei161-status-bulk-mysql-test'
$dbName = "fokus-rei161-mysql-$runId"
$phpName = "fokus-rei161-php-$runId"
$password = ([Guid]::NewGuid().ToString('N') + [Guid]::NewGuid().ToString('N')).ToLowerInvariant()
$marker = ([Guid]::NewGuid().ToString('N') + [Guid]::NewGuid().ToString('N')).ToLowerInvariant()
$dbId = $null
$phpId = $null
$creationPending = $false
$pendingName = $null
$cleanupFailed = $false

function Invoke-DockerSafe([string[]]$Arguments) {
    $output = & docker --host $script:dockerHost @Arguments 2>&1
    if ($LASTEXITCODE -ne 0) {
        $detail = (@($output) -join ' ').Replace([string]$script:password, '[REDACTED]').Replace([string]$script:marker, '[REDACTED]')
        throw "Docker falhou em $($Arguments[0]): $detail"
    }
    return $output
}

function Remove-OwnedContainer([string]$Id) {
    if (-not $Id -or $Id -notmatch '^[a-f0-9]{64}$') { return }
    try {
        $info = @((Invoke-DockerSafe @('inspect', $Id)) | ConvertFrom-Json)[0]
        if ($info.Config.Labels.$label -ne $runId -or $info.Name.TrimStart('/') -notin @($dbName, $phpName)) { throw 'Identidade do container não confere.' }
        if (@($info.HostConfig.Binds | Where-Object { $null -ne $_ }).Count -ne 0 -or @($info.HostConfig.Mounts | Where-Object { $null -ne $_ }).Count -ne 0 -or @($info.HostConfig.VolumesFrom | Where-Object { $null -ne $_ }).Count -ne 0) { throw 'Container possui vínculo externo; limpeza recusada.' }
        $anonymousVolumes = @()
        foreach ($mount in @($info.Mounts)) {
            if ($null -eq $mount -or $mount.Type -eq 'tmpfs') { continue }
            if ($mount.Type -cne 'volume' -or $mount.Name -cnotmatch '^[a-f0-9]{64}$' -or $mount.Destination -cne '/var/lib/mysql' -or $info.Name.TrimStart('/') -cne $dbName -or $info.Image -cne $mysqlImageId) { throw 'Volume não reconhecido; limpeza recusada.' }
            $anonymousVolumes += [string]$mount.Name
        }
        Invoke-DockerSafe @('rm', '--force', '--volumes', $Id) | Out-Null
        $left = @(Invoke-DockerSafe @('ps', '-aq', '--no-trunc', '--filter', "id=$Id"))
        if ($left.Count -gt 0) { throw 'Container permaneceu após remoção.' }
        foreach ($volume in $anonymousVolumes) {
            $remaining = @(Invoke-DockerSafe @('volume','ls','-q','--filter',"name=^${volume}$"))
            if ($remaining.Count -ne 0) { throw 'Volume anônimo próprio permaneceu após remoção.' }
        }
    } catch {
        $script:cleanupFailed = $true
        Write-Warning "Não foi possível confirmar a limpeza do container próprio ($Id)."
    }
}

function Assert-OwnedMounts($Info, [string[]]$ExpectedTmpfs) {
    $tmpfsPaths = @($Info.HostConfig.Tmpfs.PSObject.Properties.Name)
    if ($tmpfsPaths.Count -ne $ExpectedTmpfs.Count) { throw 'Quantidade inesperada de tmpfs.' }
    foreach ($expected in $ExpectedTmpfs) {
        if ($tmpfsPaths -cnotcontains $expected) { throw 'Tmpfs esperado ausente.' }
    }
    $seen = @{}
    foreach ($mount in @($Info.Mounts)) {
        if ($null -eq $mount) { continue }
        if ($mount.Type -cne 'tmpfs' -or $ExpectedTmpfs -cnotcontains $mount.Destination -or $mount.Source -or $seen.ContainsKey([string]$mount.Destination)) {
            throw 'Mount efetivo inesperado; runner recusado.'
        }
        $seen[[string]$mount.Destination] = $true
    }
    if (@($Info.HostConfig.Binds | Where-Object { $null -ne $_ }).Count -ne 0 -or @($Info.HostConfig.Mounts | Where-Object { $null -ne $_ }).Count -ne 0 -or @($Info.HostConfig.VolumesFrom | Where-Object { $null -ne $_ }).Count -ne 0) {
        throw 'Bind, mount ou volume herdado recusado.'
    }
}

try {
    if (-not (Test-Path -LiteralPath $testFile) -or -not (Test-Path -LiteralPath $endpointFile) -or -not (Test-Path -LiteralPath $privacyFile)) { throw 'Arquivo de teste ou fonte ausente.' }
    if (Test-Path -LiteralPath (Join-Path $repoRoot '.env')) { throw 'Runner recusado: checkout contém .env.' }
    foreach ($path in @($repoRoot, $testFile, $endpointFile, $privacyFile)) {
        $item = Get-Item -LiteralPath $path -Force
        if ($item.Attributes -band [IO.FileAttributes]::ReparsePoint) { throw 'Origem do teste contém reparse point.' }
    }
    if ($env:DOCKER_HOST -or $env:DOCKER_CONTEXT) { throw 'Seleção de daemon Docker por ambiente recusada.' }
    $dockerContext = (& docker context show).Trim()
    if ($LASTEXITCODE -ne 0 -or -not $dockerContext) { throw 'Contexto Docker indisponível.' }
    $context = @((& docker context inspect $dockerContext 2>$null | ConvertFrom-Json))[0]
    if ($LASTEXITCODE -ne 0 -or -not $context) { throw 'Contexto Docker inválido.' }
    $dockerHost = [string]$context.Endpoints.docker.Host
    if ($dockerHost -notin @('npipe:////./pipe/dockerDesktopLinuxEngine', 'unix:///var/run/docker.sock')) { throw 'Daemon Docker não local recusado.' }
    $mysqlImageId = (Invoke-DockerSafe @('image', 'inspect', '--format', '{{.Id}}', $mysqlImage) | Select-Object -Last 1).Trim()
    $phpImageId = (Invoke-DockerSafe @('image', 'inspect', '--format', '{{.Id}}', $phpImage) | Select-Object -Last 1).Trim()
    if (-not $mysqlImageId -or -not $phpImageId) { throw 'Imagem pinada MySQL/PHP QA ausente.' }
    if ($CiLinux) {
        $imageInfo = @((Invoke-DockerSafe @('image','inspect',$phpImageId)) | ConvertFrom-Json)[0]
        $expectedImageLabel = "$($env:GITHUB_RUN_ID)-$($env:GITHUB_RUN_ATTEMPT)"
        if ($phpImageId -cne $QaImageId -or $imageInfo.Id -cne $QaImageId -or $imageInfo.Config.Labels.'com.fokus.rei161-ci-run' -cne $expectedImageLabel) { throw 'Origem ou identidade da imagem CI não confere.' }
    }

    $creationPending = $true
    $pendingName = $dbName
    $dbId = (Invoke-DockerSafe @('create', '--name', $dbName, '--label', "$label=$runId", '--network', 'none', '--memory', '1g', '--cpus', '1', '--tmpfs', '/var/lib/mysql:rw,size=512m', '--tmpfs', '/var/run/mysqld:rw,size=32m', '--env', "MYSQL_ROOT_PASSWORD=$password", '--env', 'MYSQL_DATABASE=rei161_test', $mysqlImageId) | Select-Object -Last 1).Trim()
    if ($dbId -notmatch '^[a-f0-9]{64}$') { throw 'ID MySQL descartável inválido.' }
    $creationPending = $false
    $pendingName = $null
    $dbInfo = @((Invoke-DockerSafe @('inspect', $dbId)) | ConvertFrom-Json)[0]
    if ($dbInfo.Config.Labels.$label -ne $runId -or $dbInfo.HostConfig.NetworkMode -ne 'none' -or @($dbInfo.HostConfig.PortBindings.PSObject.Properties).Count -ne 0 -or $dbInfo.HostConfig.Privileged -or -not $dbInfo.HostConfig.Tmpfs.PSObject.Properties['/var/lib/mysql']) { throw 'Topologia MySQL descartável recusada.' }
    Assert-OwnedMounts $dbInfo @('/var/lib/mysql','/var/run/mysqld')
    Invoke-DockerSafe @('start', $dbId) | Out-Null
    $dbInfo = @((Invoke-DockerSafe @('inspect', $dbId)) | ConvertFrom-Json)[0]
    Assert-OwnedMounts $dbInfo @('/var/lib/mysql','/var/run/mysqld')
    $ready = $false
    for ($i = 0; $i -lt 90; $i++) {
        try { Invoke-DockerSafe @('exec', '--env', "MYSQL_PWD=$password", $dbId, 'mysql', '--protocol=socket', '--socket=/var/run/mysqld/mysqld.sock', '-uroot', 'rei161_test', '-N', '-e', 'SELECT 1') | Out-Null; $ready = $true; break } catch { Start-Sleep -Seconds 1 }
    }
    if (-not $ready) { throw 'MySQL 8.4 descartável não iniciou.' }
    $guardSql = "CREATE TABLE rei161_runner_guard (marker CHAR(64) NOT NULL PRIMARY KEY) ENGINE=InnoDB; INSERT INTO rei161_runner_guard VALUES ('$marker');"
    Invoke-DockerSafe @('exec', '--env', "MYSQL_PWD=$password", $dbId, 'mysql', '--protocol=socket', '--socket=/var/run/mysqld/mysqld.sock', '-uroot', 'rei161_test', '-e', $guardSql) | Out-Null

    $creationPending = $true
    $pendingName = $phpName
    $phpId = (Invoke-DockerSafe @('create', '--name', $phpName, '--label', "$label=$runId", '--network', "container:$dbId", '--memory', '768m', '--cpus', '1', '--cap-drop', 'ALL', '--security-opt', 'no-new-privileges=true', '--tmpfs', '/tmp:rw,size=128m,noexec,nosuid,nodev', '--entrypoint', 'sleep', $phpImageId, '300') | Select-Object -Last 1).Trim()
    if ($phpId -notmatch '^[a-f0-9]{64}$') { throw 'ID PHP QA descartável inválido.' }
    $creationPending = $false
    $pendingName = $null
    $phpInfo = @((Invoke-DockerSafe @('inspect', $phpId)) | ConvertFrom-Json)[0]
    if ($phpInfo.Config.Labels.$label -ne $runId -or $phpInfo.Image -ne $phpImageId -or $phpInfo.HostConfig.NetworkMode -ne "container:$dbId" -or @($phpInfo.HostConfig.PortBindings.PSObject.Properties).Count -ne 0 -or $phpInfo.HostConfig.Privileged -or @($phpInfo.HostConfig.CapDrop) -notcontains 'ALL' -or -not $phpInfo.HostConfig.Tmpfs.PSObject.Properties['/tmp']) { throw 'Topologia PHP QA descartável recusada.' }
    Assert-OwnedMounts $phpInfo @('/tmp')
    Invoke-DockerSafe @('start', $phpId) | Out-Null
    $phpInfo = @((Invoke-DockerSafe @('inspect', $phpId)) | ConvertFrom-Json)[0]
    Assert-OwnedMounts $phpInfo @('/tmp')
    Invoke-DockerSafe @('exec', $phpId, 'mkdir', '-p', '/workspace/tests/pauta', '/workspace/public/api/sala-audiencias-pauta', '/workspace/app/helpers') | Out-Null
    # Only selected tracked application classes enter the disconnected PHP container.
    foreach ($relative in @('config/security.php','app/models/Usuario.php','app/helpers/UsuarioPerfisAutorizacao.php','app/helpers/EffectiveAccessProfiles.php','app/helpers/UserAccessHelper.php','app/helpers/ApiErrorReporter.php','app/access/AccessEvaluator.php','app/access/ProfileNormalizer.php','app/access/PageAccessRegistry.php','app/access/AccessAreas.php','app/access/AccessPolicyVersion.php', 'app/services/SalaAudienciasAtaService.php','app/services/SalaAudienciasAtaStorage.php','app/services/ProcessoDataJudSyncQueue.php','app/helpers/SalaAudienciasAtaSigiloHelper.php','app/helpers/PrioridadesProcessuaisCatalog.php','app/helpers/ProcessoBlocoListagemHelper.php','app/helpers/ClasseProcessualListagemHelper.php','app/helpers/IdGenerator.php','app/helpers/ProcessoPrioridadeOrigem.php','app/helpers/ProcessoAssuntoOrigem.php','app/helpers/RegistroMetadataHelper.php','app/repositories/SalaAudienciasPautaRepository.php','app/repositories/SalaAudienciasAtaRepository.php','app/repositories/SalaAudienciasEstudanteInscricaoRepository.php','app/repositories/ProcessoPrioridadesRepository.php','app/repositories/ProcessosRepository.php','app/repositories/ClassesRepository.php','app/repositories/ProcessoAssuntosRepository.php','app/repositories/ProcessosPartesRepository.php','app/helpers/CnjValidator.php','app/helpers/ParentRecordLock.php','app/helpers/TextFormatter.php','app/helpers/CartasExpedidasEstadoFluxo.php','app/helpers/CartasExpedidasUiCopy.php','app/helpers/CartasRecebidasUiCopy.php','app/helpers/CartorioTimelineHelper.php','app/helpers/EditaisUiCopy.php','app/helpers/OficioRegistroMetadataHelper.php','app/helpers/SalaAudienciasInscricaoIndisponivel.php','app/repositories/CartasExpedidasSubtarefasRepository.php','app/repositories/CartasRecebidasSubtarefasRepository.php','app/repositories/EditaisSubtarefasRepository.php','app/config/prioridades_processuais_catalog.php')) {
        $source = Join-Path $repoRoot $relative
        $item = Get-Item -LiteralPath $source -Force
        if ($item.Attributes -band [IO.FileAttributes]::ReparsePoint) { throw 'Dependência da aplicação é reparse point.' }
        $destDir = [IO.Path]::GetDirectoryName($relative).Replace('\','/')
        Invoke-DockerSafe @('exec', $phpId, 'mkdir', '-p', "/workspace/$destDir") | Out-Null
        Invoke-DockerSafe @('cp', $source, "${phpId}:/workspace/$relative") | Out-Null
    }
    Invoke-DockerSafe @('cp', $testFile, "${phpId}:/workspace/tests/pauta/rei161-status-bulk-mysql.test.php") | Out-Null
    Invoke-DockerSafe @('cp', $endpointFile, "${phpId}:/workspace/public/api/sala-audiencias-pauta/status-bulk.php") | Out-Null
    Invoke-DockerSafe @('cp', $privacyFile, "${phpId}:/workspace/app/helpers/SalaAudienciasEstudantePrivacidade.php") | Out-Null
    $output = Invoke-DockerSafe @('exec', '--workdir', '/workspace', '--env', 'REI161_MYSQL_DSN=mysql:host=127.0.0.1;port=3306;dbname=rei161_test;charset=utf8mb4', '--env', "REI161_MYSQL_PASSWORD=$password", '--env', "REI161_RUN_MARKER=$marker", '--env', 'REI161_REPO_SOURCE=/workspace', '--env', 'REI161_ENDPOINT_SOURCE=/workspace/public/api/sala-audiencias-pauta/status-bulk.php', '--env', 'REI161_PRIVACY_SOURCE=/workspace/app/helpers/SalaAudienciasEstudantePrivacidade.php', $phpId, 'php', '-d', 'date.timezone=America/Bahia', '/workspace/tests/pauta/rei161-status-bulk-mysql.test.php')
    foreach ($line in $output) { ([string]$line).Replace($password, '[REDACTED]').Replace($marker, '[REDACTED]') }
} finally {
    if ($creationPending) {
        try {
            $candidates = @(Invoke-DockerSafe @('ps', '-aq', '--no-trunc', '--filter', "label=$label=$runId"))
            $pendingFound = $false
            foreach ($candidate in $candidates) {
                $candidate = ([string]$candidate).Trim()
                if (-not $candidate) { continue }
                if ($candidate -cnotmatch '^[a-f0-9]{64}$') { throw 'ID de candidato inválido.' }
                $info = @((Invoke-DockerSafe @('inspect', $candidate)) | ConvertFrom-Json)[0]
                $name = $info.Name.TrimStart('/')
                if ($info.Config.Labels.$label -ne $runId -or $name -notin @($dbName, $phpName)) { throw 'Container órfão não pertence comprovadamente a esta execução.' }
                if ($name -ceq $pendingName) { $pendingFound = $true }
                if ($name -ceq $phpName) {
                    if ($phpId -and $phpId -cne $candidate) { throw 'IDs PHP próprios divergentes.' }
                    $phpId = $candidate
                } elseif ($name -ceq $dbName) {
                    if ($dbId -and $dbId -cne $candidate) { throw 'IDs MySQL próprios divergentes.' }
                    $dbId = $candidate
                }
            }
            # A failed create may leave an unlabelled volume before a container
            # exists. An empty lookup cannot prove that no artifact was made.
            if (-not $pendingFound) { throw 'Criação ambígua sem container identificável; volumes órfãos não descartados.' }
        } catch {
            $cleanupFailed = $true
            Write-Warning 'Criação ambígua: não foi possível comprovar todos os recursos próprios; limpeza não confirmada.'
        }
    }
    Remove-OwnedContainer $phpId
    Remove-OwnedContainer $dbId
    $password = $null
    if ($cleanupFailed) { throw 'A limpeza dos containers próprios não pôde ser confirmada.' }
}
