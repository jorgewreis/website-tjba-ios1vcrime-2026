$ErrorActionPreference = 'Stop'
$tag = [Guid]::NewGuid().ToString('N')
$dbName = "rei85-db-$tag"
$phpName = "rei85-php-$tag"
$password = [Guid]::NewGuid().ToString('N')
$database = "rei85_$tag"
$repo = (Resolve-Path (Join-Path $PSScriptRoot '..')).Path
$dbId = $null
$phpId = $null
$image = 'fokus-law-linux-qa:20261107-php857-node2413'
try {
    $images = docker image ls --format '{{.Repository}}:{{.Tag}}'
    if ($LASTEXITCODE -ne 0 -or $images -notcontains $image) { throw "Imagem PHP isolada ausente: $image" }
    $dbId = docker run -d --name $dbName --label "fokus.rei85-test=$tag" --network none --tmpfs /var/lib/mysql --tmpfs /var/run/mysqld -e "MYSQL_ROOT_PASSWORD=$password" -e MYSQL_ROOT_HOST=% -e "MYSQL_DATABASE=$database" mysql:8.4.7
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
    $phpId = docker run -d --name $phpName --label "fokus.rei85-test=$tag" --network "container:$dbId" --entrypoint sleep $image 300
    if ($LASTEXITCODE -ne 0 -or -not $phpId) { throw 'Falha criando executor PHP efêmero.' }
    for ($i = 0; $i -lt 30; $i++) {
        $ErrorActionPreference = 'Continue'
        docker exec -e "MYSQL_PWD=$password" $phpId mysqladmin --protocol=tcp --host=127.0.0.1 --port=3306 -uroot ping 2>&1 | Out-Null
        $ErrorActionPreference = 'Stop'
        if ($LASTEXITCODE -eq 0) { break }
        Start-Sleep -Seconds 1
    }
    if ($LASTEXITCODE -ne 0) { throw 'MySQL não ficou pronto em TCP para o executor PHP.' }
    docker exec $phpId mkdir -p /work/app /work/tests /work/config
    if ($LASTEXITCODE -ne 0) { throw 'Falha preparando executor.' }
    foreach ($dir in @('helpers', 'repositories', 'services')) {
        docker cp (Join-Path $repo "app/$dir") "${phpId}:/work/app/"
        if ($LASTEXITCODE -ne 0) { throw "Falha copiando $dir." }
    }
    docker cp (Join-Path $repo 'config/security.php') "${phpId}:/work/config/"
    if ($LASTEXITCODE -ne 0) { throw 'Falha copiando configuração de segurança.' }
    docker cp (Join-Path $repo 'tests/rei85-instituicao-mysql.test.php') "${phpId}:/work/tests/"
    if ($LASTEXITCODE -ne 0) { throw 'Falha copiando teste.' }
    $dsn = "mysql:host=127.0.0.1;dbname=$database;charset=utf8mb4"
    docker exec -e REI85_TEST_ISOLATION=isolated-rei85-test -e "REI85_TEST_DSN=$dsn" -e REI85_TEST_USER=root -e "REI85_TEST_PASSWORD=$password" $phpId php /work/tests/rei85-instituicao-mysql.test.php
    if ($LASTEXITCODE -ne 0) { throw 'Teste MySQL/InnoDB REI-85 falhou.' }
} finally {
    foreach ($resource in @($phpId, $dbId)) {
        if ($resource) {
            $resourceInfo = docker inspect $resource | ConvertFrom-Json
            $actualLabel = $resourceInfo[0].Config.Labels.'fokus.rei85-test'
            if ($LASTEXITCODE -eq 0 -and $actualLabel -eq $tag) {
                docker rm -f $resource | Out-Null
                if ($LASTEXITCODE -ne 0) { Write-Warning "Remoção falhou para container identificado $resource" }
            } else { Write-Warning "Container não removido: identidade não confirmada ($resource)." }
        }
    }
}
