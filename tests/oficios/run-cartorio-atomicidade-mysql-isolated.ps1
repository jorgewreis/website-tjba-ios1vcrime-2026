$ErrorActionPreference = 'Stop'
$tag = [Guid]::NewGuid().ToString('N')
$dbName = "rei89-db-$tag"
$phpName = "rei89-php-$tag"
$database = "rei89_$tag"
$password = [Guid]::NewGuid().ToString('N')
$repo = (Resolve-Path (Join-Path $PSScriptRoot '../..')).Path
$dbId = $null
$phpId = $null
$label = 'fokus.rei89-test'
$image = 'fokus-law-linux-qa:20261107-php857-node2413'

try {
    $images = docker image ls --format '{{.Repository}}:{{.Tag}}'
    if ($LASTEXITCODE -ne 0 -or $images -notcontains $image) { throw "Imagem PHP isolada ausente: $image" }

    $dbId = docker run -d --name $dbName --label "$label=$tag" --network none --tmpfs /var/lib/mysql --tmpfs /var/run/mysqld -e "MYSQL_ROOT_PASSWORD=$password" -e MYSQL_ROOT_HOST=% -e "MYSQL_DATABASE=$database" mysql:8.4.7
    if ($LASTEXITCODE -ne 0 -or -not $dbId) { throw 'Falha ao criar MySQL efêmero.' }

    $ready = $false
    for ($i = 0; $i -lt 60; $i++) {
        $ErrorActionPreference = 'Continue'
        docker exec -e "MYSQL_PWD=$password" $dbId mysqladmin --protocol=socket --socket=/var/run/mysqld/mysqld.sock -uroot ping 2>&1 | Out-Null
        $ErrorActionPreference = 'Stop'
        if ($LASTEXITCODE -eq 0) { $ready = $true; break }
        Start-Sleep -Seconds 1
    }
    if (-not $ready) { throw 'MySQL efêmero não ficou pronto.' }

    $phpId = docker run -d --name $phpName --label "$label=$tag" --network "container:$dbId" --entrypoint sleep $image 300
    if ($LASTEXITCODE -ne 0 -or -not $phpId) { throw 'Falha ao criar executor PHP efêmero.' }
    for ($i = 0; $i -lt 30; $i++) {
        $ErrorActionPreference = 'Continue'
        docker exec -e "MYSQL_PWD=$password" $phpId mysqladmin --protocol=tcp --host=127.0.0.1 --port=3306 -uroot ping 2>&1 | Out-Null
        $ErrorActionPreference = 'Stop'
        if ($LASTEXITCODE -eq 0) { break }
        Start-Sleep -Seconds 1
    }
    if ($LASTEXITCODE -ne 0) { throw 'MySQL não respondeu ao executor PHP.' }

    docker exec $phpId mkdir -p /work/app /work/tests/oficios /work/config
    if ($LASTEXITCODE -ne 0) { throw 'Falha ao preparar executor.' }
    foreach ($dir in @('helpers', 'repositories', 'services', 'models')) {
        docker cp (Join-Path $repo "app/$dir") "${phpId}:/work/app/"
        if ($LASTEXITCODE -ne 0) { throw "Falha ao copiar $dir." }
    }
    foreach ($file in @('config.php', 'security.php')) {
        docker cp (Join-Path $repo "config/$file") "${phpId}:/work/config/"
        if ($LASTEXITCODE -ne 0) { throw "Falha ao copiar config/$file." }
    }
    docker cp (Join-Path $PSScriptRoot 'cartorio-atomicidade-mysql.test.php') "${phpId}:/work/tests/oficios/"
    if ($LASTEXITCODE -ne 0) { throw 'Falha ao copiar o teste.' }

    $dsn = "mysql:host=127.0.0.1;dbname=$database;charset=utf8mb4"
    docker exec -e "DB_HOST=127.0.0.1" -e "DB_NAME=$database" -e DB_USER=root -e "DB_PASS=$password" -e REI89_TEST_ISOLATION=isolated-rei89-test -e "REI89_TEST_DSN=$dsn" -e REI89_TEST_USER=root -e "REI89_TEST_PASSWORD=$password" $phpId php /work/tests/oficios/cartorio-atomicidade-mysql.test.php
    if ($LASTEXITCODE -ne 0) { throw 'Teste de atomicidade MySQL/InnoDB REI-89 falhou.' }
}
finally {
    foreach ($resource in @($phpId, $dbId)) {
        if ($resource) {
            $resourceInfo = docker inspect $resource | ConvertFrom-Json
            $actualLabel = $resourceInfo[0].Config.Labels.$label
            if ($LASTEXITCODE -eq 0 -and $actualLabel -eq $tag) {
                docker rm -f $resource | Out-Null
                if ($LASTEXITCODE -ne 0) { Write-Warning "Falha ao remover recurso próprio $resource." }
            } else {
                Write-Warning "Recurso mantido: não foi possível confirmar identidade ($resource)."
            }
        }
    }
}
