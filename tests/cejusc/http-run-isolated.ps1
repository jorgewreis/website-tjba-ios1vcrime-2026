$ErrorActionPreference = 'Stop'
$tag = [Guid]::NewGuid().ToString('N')
$password = [Guid]::NewGuid().ToString('N')
$database = "cejusc_$tag"
$repo = (Resolve-Path (Join-Path $PSScriptRoot '../..')).Path
$dbId = $null; $phpId = $null
try {
    $dbId = docker run -d --name "fokus-cejusc-http-db-$tag" --label "fokus.cejusc-http=$tag" --network none --tmpfs /var/lib/mysql --tmpfs /var/run/mysqld -e "MYSQL_ROOT_PASSWORD=$password" -e MYSQL_ROOT_HOST=% -e "MYSQL_DATABASE=$database" mysql:8.4.7
    if ($LASTEXITCODE -ne 0 -or -not $dbId) { throw 'Falha ao criar banco descartável' }
    $ready = $false
    for ($i=0; $i -lt 60; $i++) {
        $ErrorActionPreference='Continue'
        docker exec -e "MYSQL_PWD=$password" $dbId mysqladmin --protocol=socket --socket=/var/run/mysqld/mysqld.sock -uroot ping 2>&1 | Out-Null
        $ErrorActionPreference='Stop'
        if ($LASTEXITCODE -eq 0) { $ready=$true; break }; Start-Sleep -Seconds 1
    }
    if (!$ready) { throw 'Banco não iniciou' }
    $phpId = docker run -d --name "fokus-cejusc-http-php-$tag" --label "fokus.cejusc-http=$tag" --network "container:$dbId" -e CEJUSC_DISPOSABLE_TEST=1 -e "CEJUSC_TEST_PASSWORD=$password" -e "CEJUSC_TEST_DATABASE=$database" -e DB_HOST=127.0.0.1 -e "DB_NAME=$database" -e DB_USER=root -e "DB_PASS=$password" --entrypoint sleep fokus-law-linux-qa:20261107-php857-node2413 300
    if ($LASTEXITCODE -ne 0 -or !$phpId) { throw 'Falha ao criar executor' }
    docker exec $phpId mkdir -p /work/app /work/config /work/tests/cejusc /work/public/api
    foreach ($dir in @('helpers','repositories','services','models','access')) { docker cp (Join-Path $repo "app/$dir") "${phpId}:/work/app/"; if ($LASTEXITCODE -ne 0) { throw "Falha ao copiar $dir" } }
    foreach ($file in @('config.php','security.php')) { docker cp (Join-Path $repo "config/$file") "${phpId}:/work/config/"; if ($LASTEXITCODE -ne 0) { throw 'Falha ao copiar configuração' } }
    docker cp (Join-Path $repo 'public/api/cartorio-cejusc') "${phpId}:/work/public/api/"
    foreach ($file in @('mysql-isolated.test.php','http-fixture.php','http-flow.test.php')) { docker cp (Join-Path $PSScriptRoot $file) "${phpId}:/work/tests/cejusc/"; if ($LASTEXITCODE -ne 0) { throw 'Falha ao copiar teste' } }
    docker exec $phpId php /work/tests/cejusc/http-fixture.php
    if ($LASTEXITCODE -ne 0) { throw 'Falha na fixture' }
    docker exec -d $phpId php -S 127.0.0.1:8765 -t /work/public
    if ($LASTEXITCODE -ne 0) { throw 'Servidor HTTP não iniciou' }
    docker exec $phpId php /work/tests/cejusc/http-flow.test.php
    if ($LASTEXITCODE -ne 0) { throw 'Teste HTTP falhou' }
} finally {
    foreach ($resource in @($phpId,$dbId)) {
        if ($resource) {
            $info = docker inspect $resource | ConvertFrom-Json
            if ($LASTEXITCODE -eq 0 -and $info[0].Config.Labels.'fokus.cejusc-http' -eq $tag) { docker rm -f $resource | Out-Null; if ($LASTEXITCODE -ne 0) { Write-Warning "Falha removendo $resource" } }
            else { Write-Warning "Identidade não confirmada: $resource" }
        }
    }
}
