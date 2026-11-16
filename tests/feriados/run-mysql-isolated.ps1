$ErrorActionPreference = 'Stop'
$tag = [Guid]::NewGuid().ToString('N')
$dbName = "fokus-feriados-db-$tag"
$phpName = "fokus-feriados-php-$tag"
$password = [Guid]::NewGuid().ToString('N')
$database = "feriados_$tag"
$repo = (Resolve-Path (Join-Path $PSScriptRoot '../..')).Path
$dbId = $null
$phpId = $null
$image = 'fokus-law-linux-qa:20261107-php857-node2413'
try {
    $images = docker image ls --format '{{.Repository}}:{{.Tag}}'
    if ($LASTEXITCODE -ne 0 -or $images -notcontains $image) { throw "Imagem PHP isolada ausente: $image" }
    $dbId = docker run -d --name $dbName --label "fokus.feriados-test=$tag" --network none --tmpfs /var/lib/mysql --tmpfs /var/run/mysqld -e "MYSQL_ROOT_PASSWORD=$password" -e MYSQL_ROOT_HOST=% -e "MYSQL_DATABASE=$database" mysql:8.4.7
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
    $phpId = docker run -d --name $phpName --label "fokus.feriados-test=$tag" --network "container:$dbId" --entrypoint sleep $image 300
    if ($LASTEXITCODE -ne 0 -or -not $phpId) { throw 'Falha criando executor PHP efêmero.' }
    $tcpReady = $false
    for ($i = 0; $i -lt 30; $i++) {
        $ErrorActionPreference = 'Continue'
        docker exec -e "MYSQL_PWD=$password" $phpId mysqladmin --protocol=tcp --host=127.0.0.1 --port=3306 -uroot ping 2>&1 | Out-Null
        $ErrorActionPreference = 'Stop'
        if ($LASTEXITCODE -eq 0) { $tcpReady = $true; break }
        Start-Sleep -Seconds 1
    }
    if (-not $tcpReady) { throw 'MySQL efêmero não ficou pronto em TCP para o executor PHP.' }
    docker exec $phpId mkdir -p /work/app /work/tests/feriados
    if ($LASTEXITCODE -ne 0) { throw 'Falha preparando executor.' }
    foreach ($dir in @('helpers','repositories','services')) {
        docker cp (Join-Path $repo "app/$dir") "${phpId}:/work/app/"
        if ($LASTEXITCODE -ne 0) { throw "Falha copiando $dir." }
    }
    docker cp (Join-Path $PSScriptRoot 'rei122-135-atomicidade.test.php') "${phpId}:/work/tests/feriados/"
    if ($LASTEXITCODE -ne 0) { throw 'Falha copiando teste.' }
    docker cp (Join-Path $PSScriptRoot 'rei114-lock-holder.php') "${phpId}:/work/tests/feriados/"
    if ($LASTEXITCODE -ne 0) { throw 'Falha copiando worker de contenção REI-114.' }
    $dsn = "mysql:host=127.0.0.1;dbname=$database;charset=utf8mb4"
    docker exec -e FERIADOS_TEST_ISOLATION=isolated-feriados-test -e "FERIADOS_TEST_DSN=$dsn" -e FERIADOS_TEST_USER=root -e "FERIADOS_TEST_PASSWORD=$password" $phpId php /work/tests/feriados/rei122-135-atomicidade.test.php
    if ($LASTEXITCODE -ne 0) { throw 'Teste MySQL/InnoDB falhou.' }
} finally {
    foreach ($resource in @($phpId, $dbId)) {
        if ($resource) {
            $resourceInfo = docker inspect $resource | ConvertFrom-Json
            $actualLabel = $resourceInfo[0].Config.Labels.'fokus.feriados-test'
            if ($LASTEXITCODE -eq 0 -and $actualLabel -eq $tag) {
                docker rm -f $resource | Out-Null
                if ($LASTEXITCODE -ne 0) { Write-Warning "Remoção falhou para container identificado $resource" }
            } else { Write-Warning "Container não removido: identidade não confirmada ($resource)." }
        }
    }
}
