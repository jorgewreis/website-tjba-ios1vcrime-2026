[CmdletBinding()]
param(
    [Parameter(Mandatory=$true)][string]$TestScript,
    [Parameter(Mandatory=$true)][string[]]$Files
)
$ErrorActionPreference = 'Stop'
$root = (Resolve-Path (Join-Path $PSScriptRoot '../..')).Path
$runId = [Guid]::NewGuid().ToString('N')
$label = 'fokus.mysql-php-test'
$db = $null
$php = $null
$cleanupFailed = $false
function Invoke-DockerSafe([string[]]$argsList) {
    $output = & docker @argsList 2>&1
    if ($LASTEXITCODE -ne 0) { throw "Docker falhou: $($argsList[0]); código $LASTEXITCODE." }
    return $output
}
function LocalFile([string]$relative) {
    if ([IO.Path]::IsPathRooted($relative) -or $relative -match '(^|[/\\])\.\.([/\\]|$)' -or $relative -match '(^|[/\\])\.env') { throw 'Caminho recusado.' }
    $current = $root
    foreach ($segment in ($relative -split '[/\\]')) {
        if (-not $segment -or $segment -eq '.') { throw 'Caminho recusado.' }
        $current = Join-Path $current $segment
        $part = Get-Item -LiteralPath $current -Force
        if ($part.Attributes -band [IO.FileAttributes]::ReparsePoint) { throw 'Links não são permitidos no caminho de teste.' }
    }
    # A file below a junction can appear to have an in-tree FullName while its
    # bytes come from elsewhere. Reject reparse points on every component, not
    # only on the leaf, before copying anything into the disposable container.
    $current = $root
    foreach ($part in ($relative -split '[/\\]')) {
        if ([string]::IsNullOrWhiteSpace($part) -or $part -eq '.') { throw 'Caminho recusado.' }
        $current = Join-Path $current $part
        $component = Get-Item -LiteralPath $current -Force -ErrorAction Stop
        if ($component.Attributes -band [IO.FileAttributes]::ReparsePoint) { throw 'Reparse point recusado.' }
    }
    $target = $component
    if ($target.PSIsContainer -or $target.Attributes -band [IO.FileAttributes]::ReparsePoint) { throw 'Somente arquivos regulares são permitidos.' }
    $full = $target.FullName
    if (-not $full.StartsWith($root + [IO.Path]::DirectorySeparatorChar, [StringComparison]::OrdinalIgnoreCase)) { throw 'Arquivo fora do repositório.' }
    return $full
}
try {
    $allFiles = @($Files + $TestScript | Select-Object -Unique)
    foreach ($file in $allFiles) { $null = LocalFile $file }
    $mysqlImage = @((Invoke-DockerSafe @('image','inspect','mysql:8.4.7')) | ConvertFrom-Json)[0]
    $phpImage = @((Invoke-DockerSafe @('image','inspect','fokus-law-linux-qa:20261107-php857-node2413')) | ConvertFrom-Json)[0]
    if (-not $mysqlImage.Id -or -not $phpImage.Id) { throw 'Imagens locais indisponíveis.' }
    $bytes = [byte[]]::new(32)
    [Security.Cryptography.RandomNumberGenerator]::Fill($bytes)
    $password = [Convert]::ToHexString($bytes).ToLowerInvariant()
    $db = (Invoke-DockerSafe @('create','--label',"$label=$runId",'--network','none','--mount','type=tmpfs,destination=/var/lib/mysql','--env',"MYSQL_ROOT_PASSWORD=$password",'--env','MYSQL_DATABASE=auth_test',$mysqlImage.Id) | Select-Object -Last 1).Trim()
    if ($db -notmatch '^[a-f0-9]{64}$') { throw 'ID MySQL inválido.' }
    $di = @((Invoke-DockerSafe @('inspect',$db)) | ConvertFrom-Json)[0]
    if ($di.Config.Labels.$label -ne $runId -or $di.Image -ne $mysqlImage.Id -or $di.HostConfig.NetworkMode -ne 'none' -or $di.HostConfig.Privileged -or @($di.Mounts).Count -ne 1 -or $di.Mounts[0].Type -ne 'tmpfs' -or $di.Mounts[0].Destination -ne '/var/lib/mysql' -or @($di.HostConfig.PortBindings.PSObject.Properties).Count -ne 0) { throw 'Topologia MySQL recusada.' }
    Invoke-DockerSafe @('start',$db) | Out-Null
    $ready = $false
    for ($i=0; $i -lt 60; $i++) {
        & docker exec $db sh -c 'MYSQL_PWD="$MYSQL_ROOT_PASSWORD" mysqladmin --protocol=tcp -h 127.0.0.1 -uroot ping --silent' 2>$null | Out-Null
        if ($LASTEXITCODE -eq 0) { $ready=$true; break }
        Start-Sleep -Seconds 1
    }
    if (-not $ready) { throw 'MySQL não ficou pronto.' }
    $markerBytes = [byte[]]::new(32)
    [Security.Cryptography.RandomNumberGenerator]::Fill($markerBytes)
    $marker = [Convert]::ToHexString($markerBytes).ToLowerInvariant()
    $sql = "CREATE TABLE _sandbox_proof (token CHAR(64) NOT NULL); INSERT INTO _sandbox_proof VALUES ('$marker');"
    $sql | & docker exec -i --env "MYSQL_PWD=$password" $db mysql --protocol=tcp -h 127.0.0.1 -uroot auth_test
    if ($LASTEXITCODE -ne 0) { throw 'Não foi possível criar marcador do sandbox.' }
    $php = (Invoke-DockerSafe @('create','--label',"$label=$runId",'--network',"container:$db",'--entrypoint','sleep',$phpImage.Id,'300') | Select-Object -Last 1).Trim()
    if ($php -notmatch '^[a-f0-9]{64}$') { throw 'ID PHP inválido.' }
    $pi = @((Invoke-DockerSafe @('inspect',$php)) | ConvertFrom-Json)[0]
    if ($pi.Config.Labels.$label -ne $runId -or $pi.Image -ne $phpImage.Id -or $pi.HostConfig.NetworkMode -ne "container:$db" -or $pi.HostConfig.Privileged -or @($pi.Mounts | Where-Object { $null -ne $_ }).Count -ne 0 -or @($pi.HostConfig.Binds | Where-Object { $null -ne $_ }).Count -ne 0) { throw 'Topologia PHP recusada.' }
    Invoke-DockerSafe @('start',$php) | Out-Null
    foreach ($file in $allFiles) {
        $normalized = $file.Replace('\','/')
        $parent = [IO.Path]::GetDirectoryName($normalized).Replace('\','/')
        if ($parent) { Invoke-DockerSafe @('exec',$php,'mkdir','-p',"/workspace/$parent") | Out-Null }
        Invoke-DockerSafe @('cp',(LocalFile $file),"${php}:/workspace/$normalized") | Out-Null
    }
    $test = $TestScript.Replace('\','/')
    $output = & docker exec --workdir /workspace --env 'MYSQL_TEST_DSN=mysql:host=127.0.0.1;port=3306;dbname=auth_test;charset=utf8mb4' --env 'MYSQL_TEST_USER=root' --env "MYSQL_TEST_PASSWORD=$password" --env "MYSQL_TEST_MARKER=$marker" $php php "/workspace/$test"
    if ($LASTEXITCODE -ne 0) { $output; throw 'Teste PHP/MySQL falhou.' }
    $output
} finally {
    foreach ($id in @($php,$db)) {
        if ($id -and $id -match '^[a-f0-9]{64}$') {
            $raw = & docker inspect $id 2>$null
            $item = if ($LASTEXITCODE -eq 0) { @($raw | ConvertFrom-Json)[0] } else { $null }
            if (-not $item -or $item.Config.Labels.$label -ne $runId) { $cleanupFailed=$true; continue }
            & docker rm -f $id 2>$null | Out-Null
            if ($LASTEXITCODE -ne 0) { $cleanupFailed=$true }
            $remaining = & docker ps -aq --no-trunc --filter "id=$id"
            if ($LASTEXITCODE -ne 0 -or @($remaining | Where-Object { $_ }).Count -ne 0) { $cleanupFailed=$true }
        }
    }
    $password=$null
    if ($cleanupFailed) { throw 'Limpeza não comprovada; verifique os recursos etiquetados.' }
}
