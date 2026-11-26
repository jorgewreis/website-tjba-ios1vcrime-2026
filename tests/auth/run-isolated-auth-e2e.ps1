[CmdletBinding()]
param([string]$QaImageId = '', [switch]$Browser)

# Deliberately has no host ports, bind mounts, or compose dependency. It is
# opt-in: invoking this script is the only action that creates Docker resources.
$ErrorActionPreference = 'Stop'
$root = (Resolve-Path (Join-Path $PSScriptRoot '../..')).Path
$runId = [Guid]::NewGuid().ToString('N')
$label = 'fokus.auth-e2e'
$network = "fokus-auth-e2e-$runId"
$browserImage = 'mcr.microsoft.com/playwright@sha256:ee15712262f017b8157421b212b12099146645f44b1bc5c0514cbbe6a229b1d0'
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
$mysql = $mailpit = $app = $client = $browserContainer = $null; $cleanupFailed = $false; $networkAttempted = $false; $creationPending = $false;
. (Join-Path $PSScriptRoot 'cleanup-owned.ps1')
. (Join-Path $PSScriptRoot 'docker-topology-validation.ps1')
$executionFailure = $null; $guardToken = $null; $script:failurePhase = 'setup'
function D([string[]]$a) {
    $prior = $ErrorActionPreference
    $ErrorActionPreference = 'Continue'
    try { $o = & docker --context $dockerContext @a 2>&1; $code = $LASTEXITCODE }
    finally { $ErrorActionPreference = $prior }
    if ($code -ne 0) {
        $safePatterns = @(
            'REI158_DIAG code-(?:initial|workers|success|timeout|storage)-(?:other|[0-3])',
            'AUTH_FIXTURE_DIAG stage=(?:initial|clear|register|count) class=(?:PDOException|RuntimeException|InvalidArgumentException|Throwable) sqlstate=(?:none|[A-Z0-9]{5}) native=(?:none|[0-9]{1,5})',
            'HTTP_DIAG: (?:unknown|login|institution_access|profile_access|password_change|logout_race|http_rate_limit|password_reset|reset_token_mail|reset_shell|reset_invalid_csrf|reset_valid_stage|reset_staged_form|legacy_reset_link|password_update|password_login|reset_mail|mail_json|response_json) (?:PDOException|JsonException|RuntimeException|InvalidArgumentException|Error|TypeError|ValueError|Throwable) code=-?[0-9]{1,5}',
            'HTTP_FAIL phase=reset_valid_stage status=[0-9]{1,3}',
            'HTTP_FAIL phase=(?:unknown|login|institution_access|profile_access|password_change|logout_race|http_rate_limit|password_reset|reset_token_mail|reset_shell|reset_invalid_csrf|reset_valid_stage|reset_staged_form|legacy_reset_link|password_update|password_login|reset_mail|mail_json|response_json)',
            'FAIL navegador etapa=(?:init|browser-start|websocket|cdp-setup|form|invalid-login|valid-login)',
            'DIAG navegador: (?:net::ERR_(?:NAME_NOT_RESOLVED|CONNECTION_REFUSED|CONNECTION_CLOSED|BLOCKED_BY_CLIENT|ADDRESS_UNREACHABLE|ABORTED)|erro de rede nao classificado)',
            'DIAG navegador: bloqueado \{"http":(?:true|false),"app":(?:true|false),"port":(?:true|false)\}',
            'DIAG navegador: login status=(?:none|[0-9]{3}) exceptions=[0-9]{1,4} flags=\{"message":(?:true|false),"visible":(?:true|false),"enabled":(?:true|false)\}'
        )
        foreach ($line in $o) {
            foreach ($pattern in $safePatterns) {
                $match = [regex]::Match([string]$line, $pattern)
                if ($match.Success) { Write-Host $match.Value; break }
            }
        }
        throw 'Docker falhou; detalhes suprimidos.'
    }
    return $o
}
function Id([string]$v, [string]$what) { if ($v -notmatch '^[a-f0-9]{64}$') { throw "ID inválido: $what" }; $v }
function Secret {
    $b = [byte[]]::new(32)
    $rng = [Security.Cryptography.RandomNumberGenerator]::Create()
    try { $rng.GetBytes($b) } finally { $rng.Dispose() }
    return (([BitConverter]::ToString($b) -replace '-', '').ToLowerInvariant())
}
function Label-Value($labels, [string]$key) { $property = $labels.PSObject.Properties[$key]; if ($null -eq $property) { return $null }; return [string]$property.Value }
function Get-DockerNetworkInspectObject {
    param([object]$Inspection, [string]$ExpectedName, [string]$ExpectedLabelKey)
    if ($null -eq $Inspection -or $Inspection.ExitCode -ne 0 -or @($Inspection.Output).Count -eq 0) { throw 'Inspect de rede indisponível.' }
    try { $items = @(($Inspection.Output -join "`n") | ConvertFrom-Json) } catch { throw 'Inspect de rede inválido.' }
    if ($items.Count -ne 1 -or $null -eq $items[0]) { throw 'Inspect de rede ambíguo.' }
    $item = $items[0]
    if ([string]$item.Name -ne $ExpectedName -or $null -eq $item.Containers -or $null -eq $item.Labels -or $null -eq $item.Labels.PSObject.Properties[$ExpectedLabelKey]) {
        throw 'Inspect de rede incompleto ou divergente.'
    }
    return $item
}
function Assert-SafeTree([string]$path) {
    $i=Get-Item -LiteralPath $path -Force; if ($i.Attributes -band [IO.FileAttributes]::ReparsePoint) { throw "Reparse point recusado: $path" }
    foreach($f in Get-ChildItem -LiteralPath $path -Force -Recurse) { if (($f.Attributes -band [IO.FileAttributes]::ReparsePoint) -or ($f.Name -like '.env*' -and $f.Name -ne '.env.example')) { throw "Fonte insegura recusada: $($f.FullName)" } }
}
function Assert-Topology([string]$id, [string]$image, [bool]$mysqlRole, [string]$expectedNetworkId = '', [string]$expectedEndpointId = '') {
    $inspection = @((D @('inspect',$id)) | ConvertFrom-Json)
    if ($inspection.Count -ne 1 -or $null -eq $inspection[0]) { throw 'Inspect de contêiner incompleto ou ambíguo.' }
    Assert-AuthDockerTopology -Inspection $inspection[0] -ExpectedId $id -ExpectedImage $image -LabelKey $label -RunId $runId -Network $network -MySqlRole $mysqlRole -ExpectedNetworkId $expectedNetworkId -ExpectedEndpointId $expectedEndpointId
}
# Repeated snapshots reduce accidental drift; they are not an atomic lock on the daemon.
function Assert-ActiveTopology {
    $networkOutput = D @('network','inspect',$networkId)
    $activeNetwork = Get-DockerNetworkInspectObject -Inspection ([pscustomobject]@{ ExitCode = 0; Output = $networkOutput }) -ExpectedName $network -ExpectedLabelKey $label
    $members = @($mysql,$mailpit,$app,$client)
    if ($Browser) { $members += $browserContainer }
    Assert-AuthDockerNetwork -Inspection $activeNetwork -ExpectedId $networkId -ExpectedName $network -LabelKey $label -RunId $runId -ExpectedMembers $members
    Assert-Topology $mysql $my.Id $true $networkId ([string]$activeNetwork.Containers.PSObject.Properties[$mysql].Value.EndpointID)
    Assert-Topology $mailpit $mp.Id $false $networkId ([string]$activeNetwork.Containers.PSObject.Properties[$mailpit].Value.EndpointID)
    Assert-Topology $app $qa.Id $false $networkId ([string]$activeNetwork.Containers.PSObject.Properties[$app].Value.EndpointID)
    Assert-Topology $client $qa.Id $false $networkId ([string]$activeNetwork.Containers.PSObject.Properties[$client].Value.EndpointID)
    if ($Browser) {
        Assert-Topology $browserContainer $br.Id $false $networkId ([string]$activeNetwork.Containers.PSObject.Properties[$browserContainer].Value.EndpointID)
        $appInspection = @((D @('inspect',$app)) | ConvertFrom-Json)[0]
        $appAliases = @($appInspection.NetworkSettings.Networks.PSObject.Properties[$network].Value.Aliases)
        if ('app' -cnotin $appAliases -or 'auth-sandbox.test' -cnotin $appAliases) { throw 'Aliases do app divergentes.' }
    }
}
function Copy-Tree([string]$source) { Assert-SafeTree $source; D @('cp',$source,"${app}:/workspace/") | Out-Null }
function App([string[]]$a) {
    $script:failurePhase = 'app-command-topology'
    Assert-ActiveTopology
    $dockerArgs = @('exec','--env','APP_ENV=testing','--env','AUTH_TEST_ISOLATION=isolated-auth-test','--env','AUTH_TEST_ENV_FILE=/workspace/tests/auth/.env.test.runtime','--env','AUTH_TEST_MAILPIT_URL=http://mailpit:8025','--env','AUTH_TEST_GUARD_FILE=/workspace/tests/auth/.isolated-runner',"--env","AUTH_TEST_GUARD_TOKEN=$guardToken",$app) + $a
    $script:failurePhase = 'app-command-execution'
    return D $dockerArgs
}
function Run-AppTest([string]$testFile, [string[]]$arguments = @()) {
    $script:failurePhase = 'app-test-topology'
    Assert-ActiveTopology
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
        $script:failurePhase = 'app-test-complete'
        return
    }
    $script:failurePhase = 'app-test-command'
    App (@('php', '-d', 'error_log=/tmp/auth-fixture-error.log', "/workspace/tests/auth/$testFile") + $arguments) | Out-Null
    $script:failurePhase = 'app-test-complete'
}
try {
    $required=@('app','config','public','vendor','services','tests/auth')
    foreach($item in $required) { if(-not(Test-Path -LiteralPath (Join-Path $root $item))) { throw "Fonte exigida ausente: $item" } }
    if(-not(Test-Path -LiteralPath (Join-Path $root 'vendor/autoload.php'))) { throw 'Dependências PHP ausentes: execute composer install a partir do composer.lock antes da suíte E2E.' }
    $my=@((D @('image','inspect',$mysqlImage))|ConvertFrom-Json)[0]; $qa=@((D @('image','inspect',$qaImage))|ConvertFrom-Json)[0]; $mp=@((D @('image','inspect',$mailpitImage))|ConvertFrom-Json)[0]
    if ($Browser) { $br=@((D @('image','inspect',$browserImage))|ConvertFrom-Json)[0]; if (-not $br.Id) { throw 'Imagem local do navegador ausente.' } }
    if(-not $my.Id -or -not $qa.Id -or -not $mp.Id) { throw 'Imagens locais exigidas não estão disponíveis.' }
    $networkAttempted = $true
    $networkId = Id ((D @('network','create','--internal','--label',"$label=$runId",$network) | Select-Object -Last 1).Trim()) 'network'
    $networkOutput = D @('network','inspect',$network)
    $n = Get-DockerNetworkInspectObject -Inspection ([pscustomobject]@{ ExitCode = 0; Output = $networkOutput }) -ExpectedName $network -ExpectedLabelKey $label
    if ($n.Internal -isnot [bool] -or -not $n.Internal -or (Label-Value $n.Labels $label) -ne $runId) { throw 'Rede interna recusada.' }
    Assert-AuthDockerNetwork -Inspection $n -ExpectedId $networkId -ExpectedName $network -LabelKey $label -RunId $runId -ExpectedMembers @()
    $pass=Secret
    $creationPending = $true
    $mysql=Id ((D @('create','--label',"$label=$runId",'--network',$network,'--network-alias','mysql','--mount','type=tmpfs,destination=/var/lib/mysql','--env',"MYSQL_ROOT_PASSWORD=$pass",'--env','MYSQL_DATABASE=auth_test_e2e',$my.Id)|Select-Object -Last 1).Trim()) 'mysql'
    $creationPending = $false
    $creationPending = $true
    $mailpit=Id ((D @('create','--label',"$label=$runId",'--network',$network,'--network-alias','mailpit',$mp.Id)|Select-Object -Last 1).Trim()) 'mailpit'
    $creationPending = $false
    $creationPending = $true
    $appAliases = @('--network-alias','app')
    if ($Browser) { $appAliases += @('--network-alias','auth-sandbox.test') }
    $app=Id ((D (@('create','--label',"$label=$runId",'--network',$network) + $appAliases + @(
        '--env','APP_ENV=testing','--env','AUTH_TEST_ISOLATION=isolated-auth-test','--env','AUTH_TEST_ENV_FILE=/workspace/tests/auth/.env.test.runtime',
        '--env','DB_HOST=mysql','--env','DB_PORT=3306','--env','DB_NAME=auth_test_e2e','--env','DB_USER=root',"--env","DB_PASS=$pass",'--env','DB_CHARSET=utf8mb4',
        '--env','MAIL_DRIVER=smtp','--env','MAIL_HOST=mailpit','--env','MAIL_PORT=1025','--env','MAIL_USER=auth-test',"--env","MAIL_PASSWORD=$pass",'--env','MAIL_ENCRYPTION=none',
        '--env','MAIL_FROM_ADDRESS=no-reply@auth-test.invalid','--env','MAIL_FROM_NAME=Auth E2E','--env','APP_URL=https://auth-test.invalid',
        '--entrypoint','sleep',$qa.Id,'600'))|Select-Object -Last 1).Trim()) 'app'
    $creationPending = $false
    $creationPending = $true
    $client=Id ((D @('create','--label',"$label=$runId",'--network',$network,'--network-alias','client','--entrypoint','sleep',$qa.Id,'600')|Select-Object -Last 1).Trim()) 'client'
    $creationPending = $false
    if ($Browser) {
        $creationPending = $true
        $browserContainer=Id ((D @('create','--label',"$label=$runId",'--network',$network,'--network-alias','browser','--entrypoint','sleep',$br.Id,'600')|Select-Object -Last 1).Trim()) 'browser'
        $creationPending = $false
        Assert-Topology $browserContainer $br.Id $false
    }
    Assert-Topology $mysql $my.Id $true; Assert-Topology $mailpit $mp.Id $false; Assert-Topology $app $qa.Id $false; Assert-Topology $client $qa.Id $false
    $membersToStart = @($mysql,$mailpit,$app,$client)
    if ($Browser) { $membersToStart += $browserContainer }
    foreach($id in $membersToStart) { D @('start',$id)|Out-Null }
    Assert-ActiveTopology
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
    Assert-ActiveTopology
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
    Run-AppTest 'rei73-instituicoes-fixture.test.php'
    Run-AppTest 'login-rate-limit.test.php'
    Run-AppTest 'login-rate-limit-concurrency.test.php'
    Run-AppTest 'rei171-login-reset-race.test.php'
    Run-AppTest 'reset-token-concurrency.test.php'
    Run-AppTest 'smtp-capture.test.php'
    if (-not $Browser) { Run-AppTest 'login-rate-limit.fixture.php' @('seed') }
    $token=(App @('php','/workspace/tests/auth/http-reset-token.fixture.php')|Select-Object -Last 1).Trim(); if($token -notmatch '^[a-f0-9]{64}$'){throw 'Token sintético inválido.'}
    D @('exec','-d','--env','PHP_CLI_SERVER_WORKERS=4',$app,'sh','-c','cd /workspace && exec php -S 0.0.0.0:8080 -t public >/tmp/auth-e2e-app.log 2>&1')|Out-Null
    for($i=0; $i -lt 30; $i++) {
        Assert-ActiveTopology
        $probe = Start-Process -FilePath 'docker' -ArgumentList @('--context', $dockerContext, 'exec', '--env', 'APP_ENV=testing', '--env', 'AUTH_TEST_ISOLATION=isolated-auth-test', '--env', 'AUTH_TEST_GUARD_FILE=/workspace/tests/auth/.isolated-runner', '--env', "AUTH_TEST_GUARD_TOKEN=$guardToken", $client, 'php', '/workspace/tests/auth/http-ready.test.php') -Wait -PassThru -NoNewWindow
        if($probe.ExitCode -eq 0){break}
        Start-Sleep -Seconds 1
    }
    if($i -eq 30){throw 'Aplicação HTTP não ficou pronta.'}
    Assert-ActiveTopology
    if ($Browser) {
        $script:failurePhase = 'browser-topology'
        Write-Output 'STEP navegador'
        Assert-ActiveTopology
        $script:failurePhase = 'browser-copy'
        D @('cp',(Join-Path $root 'tests/auth/rei73-browser-cdp.cjs'),"${browserContainer}:/tmp/rei73-browser-cdp.cjs") | Out-Null
        Assert-ActiveTopology
        $script:failurePhase = 'browser-command'
        D @('exec',$browserContainer,'node','/tmp/rei73-browser-cdp.cjs') | Write-Output
        $script:failurePhase = 'browser-after'
        Assert-ActiveTopology
    }
    if ($Browser) { Run-AppTest 'login-rate-limit.fixture.php' @('seed') }
    D @('exec','-d','--env','PHP_CLI_SERVER_WORKERS=4',$app,'sh','-c','cd /workspace && exec php -S 0.0.0.0:8080 -t public >/tmp/auth-e2e-app.log 2>&1')|Out-Null
    $script:failurePhase = 'http-readiness'
    for($i=0; $i -lt 30; $i++) {
        Assert-ActiveTopology
        $probe = Start-Process -FilePath 'docker' -ArgumentList @('--context', $dockerContext, 'exec', '--env', 'APP_ENV=testing', '--env', 'AUTH_TEST_ISOLATION=isolated-auth-test', '--env', 'AUTH_TEST_GUARD_FILE=/workspace/tests/auth/.isolated-runner', '--env', "AUTH_TEST_GUARD_TOKEN=$guardToken", $client, 'php', '/workspace/tests/auth/http-ready.test.php') -Wait -PassThru -NoNewWindow
        if($probe.ExitCode -eq 0){break}
        Start-Sleep -Seconds 1
    }
    if($i -eq 30){throw 'Aplicação HTTP não ficou pronta.'}
    $script:failurePhase = 'http-ready'
    Assert-ActiveTopology
    $script:failurePhase = 'http-flow'
    D @('exec','--workdir','/workspace','--env','APP_ENV=testing','--env','AUTH_TEST_ISOLATION=isolated-auth-test','--env','AUTH_TEST_GUARD_FILE=/workspace/tests/auth/.isolated-runner','--env',"AUTH_TEST_GUARD_TOKEN=$guardToken",$client,'php','tests/auth/http-auth-flow.test.php')|Write-Output
    $script:failurePhase = 'http-concurrency'
    D @('exec','--workdir','/workspace','--env','APP_ENV=testing','--env','AUTH_TEST_ISOLATION=isolated-auth-test','--env','AUTH_TEST_GUARD_FILE=/workspace/tests/auth/.isolated-runner','--env',"AUTH_TEST_GUARD_TOKEN=$guardToken",$client,'php','tests/auth/rei158-http-concurrency.test.php')|Write-Output
    $script:failurePhase = 'lock-wait-test'
    Run-AppTest 'rei158-http-lock-wait.test.php'
    $script:failurePhase = 'password-reset-test'
    Run-AppTest 'password-reset-states.test.php'
} catch {
    # Dados de erro do Docker, testes e servidor podem conter tokens; nunca os imprima.
    $executionFailure = $true
    if ($script:failurePhase -match '^(setup|app-test-topology|app-test-command|app-test-complete|app-command-topology|app-command-execution|reset-token-fixture|reset-token-ready|http-readiness|http-ready|http-flow|http-concurrency|lock-wait-test|password-reset-test|browser-topology|browser-copy|browser-command|browser-after)$') {
        Write-Output "RUNNER_FAILURE phase=$script:failurePhase; detalhes sensíveis omitidos."
    } else {
        Write-Output 'RUNNER_FAILURE phase=unknown; detalhes sensíveis omitidos.'
    }
} finally {
    if ($creationPending) {
        $knownIds = @($browserContainer, $client, $app, $mailpit, $mysql | Where-Object { $_ })
        $recovered = Recover-LostAuthCreate $dockerContext $label $runId $knownIds
        if ($recovered) { Write-Output 'Candidato único da criação sem resposta foi removido após validar rótulo.' }
        # Um pedido ainda em trânsito pode concluir após a consulta: o resultado permanece incerto.
        $cleanupFailed = $true
    }
    foreach ($id in @($browserContainer, $client, $app, $mailpit, $mysql)) {
        if ($id -and $id -match '^[a-f0-9]{64}$' -and -not (Remove-OwnedAuthContainer $dockerContext $id $label $runId)) { $cleanupFailed = $true }
    }
    if ($networkAttempted -and -not (Remove-OwnedAuthNetwork $dockerContext $network $label $runId)) { $cleanupFailed = $true }
    $pass=$null;$guardToken=$null;if($cleanupFailed){throw 'Limpeza não comprovada; recursos etiquetados foram preservados para inspeção.'}
}
if($executionFailure){throw 'Runner de autenticação falhou; detalhes sensíveis omitidos.'}
Write-Output 'PASS auth-test-runner'
