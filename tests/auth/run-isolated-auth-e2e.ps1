[CmdletBinding()]
param([string]$QaImageId = '')

# Deliberately has no host ports, bind mounts, or compose dependency. It is
# opt-in: invoking this script is the only action that creates Docker resources.
$ErrorActionPreference = 'Stop'
$root = (Resolve-Path (Join-Path $PSScriptRoot '../..')).Path
$runId = [Guid]::NewGuid().ToString('N')
$label = 'fokus.auth-e2e'
$network = "fokus-auth-e2e-$runId"
$mysqlImage = 'mysql@sha256:0426ec38c7a10aa45ba383887df7878f74ee70e2fd589c7b69207f3577901903'
$mailpitImage = 'axllent/mailpit@sha256:df6c2541907e1be6fac21f509927cf6ed771617a1f4b361ef66d97bd05593d2d'
$qaImage = 'fokus-law-linux-qa@sha256:459cc8bd25b38009a8cf9a0ef5399d131f4f2001a1376d89389f673a92de430b'
if ($QaImageId -ne '') {
    if ($QaImageId -notmatch '^sha256:[a-f0-9]{64}$') { throw 'ID de imagem QA inválido.' }
    $qaImage = $QaImageId
}
if ($env:DOCKER_HOST -or $env:DOCKER_CONTEXT) { throw 'Seleção de daemon por variável de ambiente recusada.' }
$dockerContext = (& docker context show).Trim()
if ($LASTEXITCODE -ne 0 -or -not $dockerContext) { throw 'Contexto Docker indisponível.' }
$contextInfo = @((& docker context inspect $dockerContext | ConvertFrom-Json))[0]
if ($LASTEXITCODE -ne 0 -or $contextInfo.Endpoints.docker.Host -notin @('npipe:////./pipe/dockerDesktopLinuxEngine', 'unix:///var/run/docker.sock')) { throw 'Daemon Docker não local recusado.' }
$mysql = $mailpit = $app = $client = $null; $cleanupFailed = $false; $networkAttempted = $false; $creationPending = $false;
. (Join-Path $PSScriptRoot 'cleanup-owned.ps1')
$executionFailure = $null; $guardToken = $null
function D([string[]]$a) { $prior = $ErrorActionPreference; $ErrorActionPreference = 'Continue'; try { $o = & docker --context $dockerContext @a 2>&1; $code = $LASTEXITCODE } finally { $ErrorActionPreference = $prior }; if ($code -ne 0) { foreach ($line in $o) { if ([string]$line -match '^REI158_DIAG code-(initial|workers|success|timeout|storage)-(other|[0-3])$') { Write-Host ([string]$line) } }; throw 'Docker falhou; detalhes suprimidos.' }; return $o }
function Id([string]$v, [string]$what) { if ($v -notmatch '^[a-f0-9]{64}$') { throw "ID inválido: $what" }; $v }
function Secret {
    $b = [byte[]]::new(32)
    $rng = [Security.Cryptography.RandomNumberGenerator]::Create()
    try { $rng.GetBytes($b) } finally { $rng.Dispose() }
    return (([BitConverter]::ToString($b) -replace '-', '').ToLowerInvariant())
}
function Label-Value($labels, [string]$key) { $property = $labels.PSObject.Properties[$key]; if ($null -eq $property) { return $null }; return [string]$property.Value }
function Assert-SafeTree([string]$path) {
    $i=Get-Item -LiteralPath $path -Force; if ($i.Attributes -band [IO.FileAttributes]::ReparsePoint) { throw "Reparse point recusado: $path" }
    foreach($f in Get-ChildItem -LiteralPath $path -Force -Recurse) { if (($f.Attributes -band [IO.FileAttributes]::ReparsePoint) -or ($f.Name -like '.env*' -and $f.Name -ne '.env.example')) { throw "Fonte insegura recusada: $($f.FullName)" } }
}
function Assert-Topology([string]$id, [string]$image, [bool]$mysqlRole) {
    $x=@((D @('inspect',$id))|ConvertFrom-Json)[0]
    $issues = [Collections.Generic.List[string]]::new()
    if ((Label-Value $x.Config.Labels $label) -ne $runId) { $issues.Add('rótulo') }
    if ($x.Image -ne $image) { $issues.Add('imagem') }
    $bindCount = if ($null -eq $x.HostConfig.Binds) { 0 } else { @($x.HostConfig.Binds).Count }
    $portBindingCount = @($x.HostConfig.PortBindings.PSObject.Properties | Where-Object { $_.MemberType -eq 'NoteProperty' }).Count
    $mounts = @($x.Mounts | Where-Object { $null -ne $_ })
    if ($x.HostConfig.Privileged) { $issues.Add('privileged') }
    if ($bindCount -ne 0) { $issues.Add('bind') }
    if ($portBindingCount -ne 0) { $issues.Add('porta') }
    if ($null -eq $x.NetworkSettings.Networks.PSObject.Properties[$network]) { $issues.Add('rede') }
    if ($mysqlRole) {
        if ($mounts.Count -ne 1 -or $mounts[0].Type -ne 'tmpfs' -or $mounts[0].Destination -ne '/var/lib/mysql') { $issues.Add('tmpfs MySQL') }
    } elseif ($mounts.Count -ne 0) { $issues.Add('mount') }
    if ($issues.Count -ne 0) { throw "Topologia recusada ($($issues -join ', ')): $id" }
}
function Copy-Tree([string]$source) { Assert-SafeTree $source; D @('cp',$source,"${app}:/workspace/") | Out-Null }
function App([string[]]$a) {
    $dockerArgs = @('exec','--env','APP_ENV=testing','--env','AUTH_TEST_ISOLATION=isolated-auth-test','--env','AUTH_TEST_ENV_FILE=/workspace/tests/auth/.env.test.runtime','--env','AUTH_TEST_MAILPIT_URL=http://mailpit:8025','--env','AUTH_TEST_GUARD_FILE=/workspace/tests/auth/.isolated-runner',"--env","AUTH_TEST_GUARD_TOKEN=$guardToken",$app) + $a
    return D $dockerArgs
}
function Run-AppTest([string]$testFile, [string[]]$arguments = @()) {
    Write-Output "STEP $testFile"
    if ($testFile -eq 'password-reset-states.test.php') {
        $previousErrorActionPreference = $ErrorActionPreference
        $ErrorActionPreference = 'Continue'
        try {
            & docker --context $dockerContext exec --env APP_ENV=testing --env AUTH_TEST_ISOLATION=isolated-auth-test --env AUTH_TEST_ENV_FILE=/workspace/tests/auth/.env.test.runtime --env AUTH_TEST_MAILPIT_URL=http://mailpit:8025 --env AUTH_TEST_GUARD_FILE=/workspace/tests/auth/.isolated-runner "--env" "AUTH_TEST_GUARD_TOKEN=$guardToken" $app php "/workspace/tests/auth/$testFile" @arguments 2>$null | Out-Null
            $testExitCode = $LASTEXITCODE
        } finally {
            $ErrorActionPreference = $previousErrorActionPreference
        }
        if ($testExitCode -ne 0) { throw "Falha em $testFile (exit=$testExitCode; detalhes suprimidos para evitar exposição de tokens)." }
        return
    }
    App (@('php', "/workspace/tests/auth/$testFile") + $arguments) | Out-Null
}
try {
    $required=@('app','config','public','vendor','services','tests/auth')
    foreach($item in $required) { if(-not(Test-Path -LiteralPath (Join-Path $root $item))) { throw "Fonte exigida ausente: $item" } }
    if(-not(Test-Path -LiteralPath (Join-Path $root 'vendor/autoload.php'))) { throw 'Dependências PHP ausentes: execute composer install a partir do composer.lock antes da suíte E2E.' }
    $my=@((D @('image','inspect',$mysqlImage))|ConvertFrom-Json)[0]; $qa=@((D @('image','inspect',$qaImage))|ConvertFrom-Json)[0]; $mp=@((D @('image','inspect',$mailpitImage))|ConvertFrom-Json)[0]
    if(-not $my.Id -or -not $qa.Id -or -not $mp.Id) { throw 'Imagens locais exigidas não estão disponíveis.' }
    $networkAttempted = $true
    D @('network','create','--internal','--label',"$label=$runId",$network)|Out-Null
    $n=@((D @('network','inspect',$network))|ConvertFrom-Json)[0]; if(-not $n.Internal -or (Label-Value $n.Labels $label) -ne $runId) { throw 'Rede interna recusada.' }
    $pass=Secret
    $creationPending = $true
    $mysql=Id ((D @('create','--label',"$label=$runId",'--network',$network,'--network-alias','mysql','--mount','type=tmpfs,destination=/var/lib/mysql','--env',"MYSQL_ROOT_PASSWORD=$pass",'--env','MYSQL_DATABASE=auth_test_e2e',$my.Id)|Select-Object -Last 1).Trim()) 'mysql'
    $creationPending = $false
    $creationPending = $true
    $mailpit=Id ((D @('create','--label',"$label=$runId",'--network',$network,'--network-alias','mailpit',$mp.Id)|Select-Object -Last 1).Trim()) 'mailpit'
    $creationPending = $false
    $creationPending = $true
    $app=Id ((D @('create','--label',"$label=$runId",'--network',$network,'--network-alias','app',
        '--env','APP_ENV=testing','--env','AUTH_TEST_ISOLATION=isolated-auth-test','--env','AUTH_TEST_ENV_FILE=/workspace/tests/auth/.env.test.runtime',
        '--env','DB_HOST=mysql','--env','DB_PORT=3306','--env','DB_NAME=auth_test_e2e','--env','DB_USER=root',"--env","DB_PASS=$pass",'--env','DB_CHARSET=utf8mb4',
        '--env','MAIL_DRIVER=smtp','--env','MAIL_HOST=mailpit','--env','MAIL_PORT=1025','--env','MAIL_USER=auth-test',"--env","MAIL_PASSWORD=$pass",'--env','MAIL_ENCRYPTION=none',
        '--env','MAIL_FROM_ADDRESS=no-reply@auth-test.invalid','--env','MAIL_FROM_NAME=Auth E2E','--env','APP_URL=https://auth-test.invalid',
        '--entrypoint','sleep',$qa.Id,'600')|Select-Object -Last 1).Trim()) 'app'
    $creationPending = $false
    $creationPending = $true
    $client=Id ((D @('create','--label',"$label=$runId",'--network',$network,'--network-alias','client','--entrypoint','sleep',$qa.Id,'600')|Select-Object -Last 1).Trim()) 'client'
    $creationPending = $false
    Assert-Topology $mysql $my.Id $true; Assert-Topology $mailpit $mp.Id $false; Assert-Topology $app $qa.Id $false; Assert-Topology $client $qa.Id $false
    foreach($id in @($mysql,$mailpit,$app,$client)) { D @('start',$id)|Out-Null }
    for($i=0; $i -lt 60; $i++) { & docker --context $dockerContext exec $mysql sh -c 'MYSQL_PWD="$MYSQL_ROOT_PASSWORD" mysqladmin -h 127.0.0.1 -uroot ping --silent' 2>$null|Out-Null; if($LASTEXITCODE -eq 0){break}; Start-Sleep -Seconds 1 }; if($i -eq 60){throw 'MySQL não ficou pronto.'}
    for($i=0; $i -lt 30; $i++) { & docker --context $dockerContext exec $mailpit sh -c 'wget -qO- http://127.0.0.1:8025/api/v1/messages >/dev/null' 2>$null|Out-Null; if($LASTEXITCODE -eq 0){break}; Start-Sleep -Seconds 1 }; if($i -eq 30){throw 'Mailpit não ficou pronto.'}
    D @('exec',$app,'mkdir','-p','/workspace/tests')|Out-Null
    foreach($item in $required){
        $source = Join-Path $root $item
        if($item -eq 'tests/auth') { Assert-SafeTree $source; D @('cp',$source,"${app}:/workspace/tests/")|Out-Null }
        else { Copy-Tree $source }
    }
    D @('exec',$client,'mkdir','-p','/workspace/tests/auth')|Out-Null
    foreach($clientTest in @('http-auth-flow.test.php', 'http-ready.test.php', 'rei158-http-concurrency.test.php', 'sandbox-guard.php')) { D @('cp',(Join-Path $root "tests/auth/$clientTest"),"${client}:/workspace/tests/auth/$clientTest")|Out-Null }
    $runtime="APP_ENV=testing`nAUTH_TEST_ISOLATION=isolated-auth-test`nDB_HOST=mysql`nDB_PORT=3306`nDB_NAME=auth_test_e2e`nDB_USER=root`nDB_PASS=$pass`nDB_CHARSET=utf8mb4`nMAIL_DRIVER=smtp`nMAIL_HOST=mailpit`nMAIL_PORT=1025`nMAIL_USER=auth-test`nMAIL_PASSWORD=$pass`nMAIL_ENCRYPTION=none`nMAIL_FROM_ADDRESS=no-reply@auth-test.invalid`nMAIL_FROM_NAME=Auth E2E`nAPP_URL=https://auth-test.invalid`n"
    $runtime|& docker --context $dockerContext exec -i $app sh -c 'umask 077; cat > /workspace/tests/auth/.env.test.runtime'; if($LASTEXITCODE -ne 0){throw 'Não foi possível criar configuração interna.'}
    $guardToken=Secret
    $guardToken|& docker --context $dockerContext exec -i $app sh -c 'umask 077; cat > /workspace/tests/auth/.isolated-runner'; if($LASTEXITCODE -ne 0){throw 'Não foi possível criar o marcador interno de isolamento.'}
    $guardToken|& docker --context $dockerContext exec -i $client sh -c 'umask 077; cat > /workspace/tests/auth/.isolated-runner'; if($LASTEXITCODE -ne 0){throw 'Não foi possível criar o marcador interno do cliente.'}
    $clientGuard = (D @('exec',$client,'cat','/workspace/tests/auth/.isolated-runner') | Select-Object -Last 1).Trim(); if($clientGuard -ne $guardToken){throw 'Marcador do cliente inválido.'}
    & docker --context $dockerContext exec $app sh -c "grep -qx 'AUTH_TEST_ISOLATION=isolated-auth-test' /workspace/tests/auth/.env.test.runtime" 2>$null
    if($LASTEXITCODE -ne 0){throw 'A configuração interna não contém o marcador de isolamento exigido.'}
    $guardFileValue = (D @('exec',$app,'cat','/workspace/tests/auth/.isolated-runner') | Select-Object -Last 1).Trim()
    if($guardFileValue -ne $guardToken){throw 'O marcador de isolamento não chegou ao processo do contêiner.'}
    Run-AppTest 'config-environment-selection.test.php'
    Run-AppTest 'trusted-post-origin.test.php'
    Run-AppTest 'sandbox-connectivity.test.php'
    Run-AppTest 'fixture-setup.test.php'
    Run-AppTest 'auth-fixtures.test.php'
    Run-AppTest 'login-rate-limit.test.php'
    Run-AppTest 'login-rate-limit-concurrency.test.php'
    Run-AppTest 'rei171-login-reset-race.test.php'
    Run-AppTest 'reset-token-concurrency.test.php'
    Run-AppTest 'smtp-capture.test.php'
    Run-AppTest 'login-rate-limit.fixture.php' @('seed')
    $token=(App @('php','/workspace/tests/auth/http-reset-token.fixture.php')|Select-Object -Last 1).Trim(); if($token -notmatch '^[a-f0-9]{64}$'){throw 'Token sintético inválido.'}
    D @('exec','-d','--env','PHP_CLI_SERVER_WORKERS=4',$app,'sh','-c','cd /workspace && exec php -S 0.0.0.0:8080 -t public >/tmp/auth-e2e-app.log 2>&1')|Out-Null
    for($i=0; $i -lt 30; $i++) {
        $probe = Start-Process -FilePath 'docker' -ArgumentList @('--context', $dockerContext, 'exec', '--env', 'APP_ENV=testing', '--env', 'AUTH_TEST_ISOLATION=isolated-auth-test', '--env', 'AUTH_TEST_GUARD_FILE=/workspace/tests/auth/.isolated-runner', '--env', "AUTH_TEST_GUARD_TOKEN=$guardToken", $client, 'php', '/workspace/tests/auth/http-ready.test.php') -Wait -PassThru -NoNewWindow
        if($probe.ExitCode -eq 0){break}
        Start-Sleep -Seconds 1
    }
    if($i -eq 30){throw 'Aplicação HTTP não ficou pronta.'}
    D @('exec','--workdir','/workspace','--env','APP_ENV=testing','--env','AUTH_TEST_ISOLATION=isolated-auth-test','--env','AUTH_TEST_GUARD_FILE=/workspace/tests/auth/.isolated-runner','--env',"AUTH_TEST_GUARD_TOKEN=$guardToken",'--env',"E2E_RESET_TOKEN=$token",$client,'php','tests/auth/http-auth-flow.test.php')|Write-Output
    D @('exec','--workdir','/workspace','--env','APP_ENV=testing','--env','AUTH_TEST_ISOLATION=isolated-auth-test','--env','AUTH_TEST_GUARD_FILE=/workspace/tests/auth/.isolated-runner','--env',"AUTH_TEST_GUARD_TOKEN=$guardToken",$client,'php','tests/auth/rei158-http-concurrency.test.php')|Write-Output
    Run-AppTest 'password-reset-states.test.php'
} catch {
    # Dados de erro do Docker, testes e servidor podem conter tokens; nunca os imprima.
    $executionFailure = $true
    Write-Output 'RUNNER_FAILURE: verifique a última etapa STEP exibida; detalhes sensíveis omitidos.'
} finally {
    if ($creationPending) {
        $knownIds = @($client, $app, $mailpit, $mysql | Where-Object { $_ })
        $recovered = Recover-LostAuthCreate $dockerContext $label $runId $knownIds
        if ($recovered) { Write-Output 'Candidato único da criação sem resposta foi removido após validar rótulo.' }
        # Um pedido ainda em trânsito pode concluir após a consulta: o resultado permanece incerto.
        $cleanupFailed = $true
    }
    foreach ($id in @($client, $app, $mailpit, $mysql)) {
        if ($id -and $id -match '^[a-f0-9]{64}$' -and -not (Remove-OwnedAuthContainer $dockerContext $id $label $runId)) { $cleanupFailed = $true }
    }
    if ($networkAttempted -and -not (Remove-OwnedAuthNetwork $dockerContext $network $label $runId)) { $cleanupFailed = $true }
    $pass=$null;$guardToken=$null;if($cleanupFailed){throw 'Limpeza não comprovada; recursos etiquetados foram preservados para inspeção.'}
}
if($executionFailure){throw 'Runner de autenticação falhou; detalhes sensíveis omitidos.'}
Write-Output 'PASS auth-test-runner'
