$ErrorActionPreference = 'Stop'
$tag = [Guid]::NewGuid().ToString('N')
$dbName = "fokus-cejusc-db-$tag"
$phpName = "fokus-cejusc-php-$tag"
$password = [Guid]::NewGuid().ToString('N')
$database = "cejusc_$tag"
$repo = (Resolve-Path (Join-Path $PSScriptRoot '../..')).Path
$dbId = $null
$phpId = $null
try {
    $dbId = docker run -d --name $dbName --label "fokus.cejusc-test=$tag" --network none --tmpfs /var/lib/mysql --tmpfs /var/run/mysqld -e "MYSQL_ROOT_PASSWORD=$password" -e MYSQL_ROOT_HOST=% -e "MYSQL_DATABASE=$database" mysql:8.4.7
    if ($LASTEXITCODE -ne 0 -or -not $dbId) { throw 'Falha criando MySQL efêmero.' }
    $ready = $false
    for ($i = 0; $i -lt 60; $i++) {
        $ErrorActionPreference = 'Continue'
        docker exec -e "MYSQL_PWD=$password" $dbId mysqladmin --protocol=socket --socket=/var/run/mysqld/mysqld.sock -uroot ping 2>&1 | Out-Null
        $ErrorActionPreference = 'Stop'
        if ($LASTEXITCODE -eq 0) { $ready = $true; break }
        Start-Sleep -Seconds 1
    }
    if (-not $ready) { throw 'MySQL efêmero não ficou pronto.' }
    $phpId = docker run -d --name $phpName --label "fokus.cejusc-test=$tag" --network "container:$dbId" --entrypoint sleep fokus-law-linux-qa:20261107-php857-node2413 300
    if ($LASTEXITCODE -ne 0 -or -not $phpId) { throw 'Falha criando executor PHP efêmero.' }
    docker exec $phpId mkdir -p /work/app /work/config /work/tests/cejusc
    if ($LASTEXITCODE -ne 0) { throw 'Falha preparando executor.' }
    foreach ($dir in @('helpers','repositories','services')) {
        docker cp (Join-Path $repo "app/$dir") "${phpId}:/work/app/"
        if ($LASTEXITCODE -ne 0) { throw "Falha copiando $dir." }
    }
    docker cp (Join-Path $repo 'config/security.php') "${phpId}:/work/config/security.php"
    if ($LASTEXITCODE -ne 0) { throw 'Falha copiando classe Security.' }
    docker cp (Join-Path $PSScriptRoot 'mysql-isolated.test.php') "${phpId}:/work/tests/cejusc/"
    if ($LASTEXITCODE -ne 0) { throw 'Falha copiando teste.' }
    docker exec -e CEJUSC_DISPOSABLE_TEST=1 -e "CEJUSC_TEST_PASSWORD=$password" -e "CEJUSC_TEST_DATABASE=$database" $phpId php /work/tests/cejusc/mysql-isolated.test.php
    if ($LASTEXITCODE -ne 0) { throw 'Teste MySQL falhou.' }
} finally {
    foreach ($resource in @($phpId, $dbId)) {
        if ($resource) {
            $resourceInfo = docker inspect $resource | ConvertFrom-Json
            $actualLabel = $resourceInfo[0].Config.Labels.'fokus.cejusc-test'
            if ($LASTEXITCODE -eq 0 -and $actualLabel -eq $tag) {
                docker rm -f $resource | Out-Null
                if ($LASTEXITCODE -ne 0) { Write-Warning "Remoção falhou para container identificado $resource" }
            } else { Write-Warning "Container não removido: identidade não confirmada ($resource)." }
        }
    }
}
