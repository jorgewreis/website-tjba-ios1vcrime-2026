[CmdletBinding()]
param()

$ErrorActionPreference = 'Stop'
$runtimePath = Join-Path $PSScriptRoot '.env.test.runtime'
if (-not (Test-Path -LiteralPath $runtimePath)) {
    throw 'Execute bootstrap.ps1 antes dos testes HTTP de autenticação.'
}

$env:APP_ENV = 'testing'
$env:AUTH_TEST_ENV_FILE = $runtimePath
$projectRoot = (Resolve-Path (Join-Path $PSScriptRoot '../..')).Path
$portReservation = [System.Net.Sockets.TcpListener]::new([System.Net.IPAddress]::Loopback, 0)
$portReservation.Start()
$httpPort = ([System.Net.IPEndPoint]$portReservation.LocalEndpoint).Port
$portReservation.Stop()
$baseUri = "http://127.0.0.1:$httpPort"
$serverLog = Join-Path $PSScriptRoot '.http-server.runtime.log'
Remove-Item -LiteralPath $serverLog -Force -ErrorAction SilentlyContinue

function Get-CsrfToken([string]$html) {
    $match = [regex]::Match($html, 'name="csrf_token"\s+value="([^"]+)"')
    if (-not $match.Success) {
        throw 'Token CSRF não encontrado no formulário público.'
    }
    return [System.Net.WebUtility]::HtmlDecode($match.Groups[1].Value)
}

function Assert-Http([bool]$condition, [string]$message) {
    if (-not $condition) {
        throw $message
    }
}

$server = Start-Process -FilePath 'php' -ArgumentList @('-S', "127.0.0.1:$httpPort", '-t', 'public') -WorkingDirectory $projectRoot -RedirectStandardError $serverLog -WindowStyle Hidden -PassThru
try {
    $deadline = (Get-Date).AddSeconds(15)
    do {
        try {
            $null = Invoke-WebRequest -Uri "$baseUri/index.php" -TimeoutSec 2
            $serverReady = $true
        } catch {
            Start-Sleep -Milliseconds 200
        }
    } while (-not $serverReady -and (Get-Date) -lt $deadline)
    Assert-Http $serverReady 'Servidor HTTP isolado não iniciou.'
    $listener = Get-NetTCPConnection -LocalAddress '127.0.0.1' -LocalPort $httpPort -State Listen -ErrorAction SilentlyContinue
    Assert-Http (-not $server.HasExited -and $null -ne $listener -and $listener.OwningProcess -eq $server.Id) 'A porta HTTP deve pertencer ao processo PHP iniciado pelo teste.'

    $loginSession = [Microsoft.PowerShell.Commands.WebRequestSession]::new()
    $loginForm = Invoke-WebRequest -Uri "$baseUri/views/public/login-form.php" -WebSession $loginSession
    $loginCsrf = Get-CsrfToken $loginForm.Content

    $invalidCsrf = Invoke-WebRequest -Uri "$baseUri/login.php" -Method Post -WebSession $loginSession -Body @{ cpf = '529.982.247-25'; senha = 'Ativo!12345'; csrf_token = 'invalido' }
    Assert-Http ((($invalidCsrf.Content | ConvertFrom-Json).success) -eq $false) 'Login com CSRF inválido deve falhar.'

    & php (Join-Path $PSScriptRoot 'login-rate-limit.fixture.php') seed
    if ($LASTEXITCODE -ne 0) { throw 'Não foi possível preparar os escopos de rate limit sintéticos.' }

    $login = Invoke-WebRequest -Uri "$baseUri/login.php" -Method Post -WebSession $loginSession -Body @{ cpf = '529.982.247-25'; senha = 'Ativo!12345'; csrf_token = $loginCsrf }
    Assert-Http ((($login.Content | ConvertFrom-Json).success) -eq $true) 'Login sintético válido deve autenticar.'
    Assert-Http (@($loginSession.Cookies.GetCookies($baseUri) | Where-Object { $_.Name -eq 'PHPSESSID' }).Count -gt 0) 'Login deve manter cookie de sessão no cookie jar.'
    $remainingRateLimitScopes = (& php (Join-Path $PSScriptRoot 'login-rate-limit.fixture.php') count).Trim()
    Assert-Http ($remainingRateLimitScopes -eq '0') 'Login válido deve limpar os escopos CPF e IP do rate limit.'

    try {
        Invoke-WebRequest -Uri "$baseUri/logout.php" -Method Post -WebSession $loginSession -Body @{ csrf_token = 'invalido' } -ErrorAction Stop | Out-Null
        throw 'Logout com CSRF inválido não pode retornar sucesso.'
    } catch {
        $statusCode = [int]$_.Exception.Response.StatusCode
        Assert-Http ($statusCode -eq 403) 'Logout com CSRF inválido deve retornar 403.'
    }

    $sessionCookie = @($loginSession.Cookies.GetCookies($baseUri) | Where-Object { $_.Name -eq 'PHPSESSID' })[0]
    $logoutHeaders = & curl.exe -sS -D - -o NUL -X POST "$baseUri/logout.php" -H "Cookie: PHPSESSID=$($sessionCookie.Value)" --data-urlencode "csrf_token=$loginCsrf"
    Assert-Http (@($logoutHeaders -match '(?m)^HTTP/\S+ 302').Count -gt 0) 'Logout válido deve retornar 302.'
    Assert-Http (@($logoutHeaders -match '(?mi)^Location: /index\.php\s*$').Count -gt 0) 'Logout válido deve redirecionar para a página pública.'
    try {
        Invoke-WebRequest -Uri "$baseUri/home.php" -WebSession $loginSession -ErrorAction Stop | Out-Null
        throw 'Sessão encerrada não pode acessar a página autenticada.'
    } catch {
        Assert-Http ([int]$_.Exception.Response.StatusCode -eq 401) 'Logout deve encerrar a sessão autenticada.'
    }

    & php (Join-Path $PSScriptRoot 'login-rate-limit-storage.fixture.php') disable
    if ($LASTEXITCODE -ne 0) {
        throw 'Não foi possível desabilitar o armazenamento de limite de login no fixture isolado.'
    }
    try {
        $storageSession = [Microsoft.PowerShell.Commands.WebRequestSession]::new()
        $storageForm = Invoke-WebRequest -Uri "$baseUri/views/public/login-form.php" -WebSession $storageSession
        $storageCsrf = Get-CsrfToken $storageForm.Content
        try {
            Invoke-WebRequest -Uri "$baseUri/login.php" -Method Post -WebSession $storageSession -Body @{ cpf = '529.982.247-25'; senha = 'Ativo!12345'; csrf_token = $storageCsrf } -ErrorAction Stop | Out-Null
            throw 'Login não pode prosseguir quando o armazenamento de limite está indisponível.'
        } catch {
            $statusCode = [int]$_.Exception.Response.StatusCode
            Assert-Http ($statusCode -eq 503) 'Armazenamento indisponível deve retornar 503 no login.'
        }
    } finally {
        & php (Join-Path $PSScriptRoot 'login-rate-limit-storage.fixture.php') restore
        if ($LASTEXITCODE -ne 0) {
            throw 'Não foi possível restaurar o armazenamento de limite de login no fixture isolado.'
        }
    }

    $resetSession = [Microsoft.PowerShell.Commands.WebRequestSession]::new()
    $resetForm = Invoke-WebRequest -Uri "$baseUri/views/public/recupera-form.php" -WebSession $resetSession
    $resetCsrf = Get-CsrfToken $resetForm.Content
    $mailpitBeforeInvalidCsrf = (Invoke-RestMethod -Uri 'http://127.0.0.1:38025/api/v1/messages' -Method Get).messages.Count
    try {
        Invoke-WebRequest -Uri "$baseUri/envia_mail.php" -Method Post -WebSession $resetSession -ContentType 'application/json' -Body (@{ cpf = '52998224725'; email = 'ativo@auth-test.invalid'; csrf_token = 'invalido' } | ConvertTo-Json) -ErrorAction Stop | Out-Null
        throw 'Solicitação de reset com CSRF inválido não pode retornar sucesso.'
    } catch {
        Assert-Http ([int]$_.Exception.Response.StatusCode -eq 419) 'Reset com CSRF inválido deve retornar 419.'
    }
    $mailpitAfterInvalidCsrf = (Invoke-RestMethod -Uri 'http://127.0.0.1:38025/api/v1/messages' -Method Get).messages.Count
    Assert-Http ($mailpitAfterInvalidCsrf -eq $mailpitBeforeInvalidCsrf) 'CSRF inválido não pode enviar e-mail de reset.'
    $knownReset = Invoke-WebRequest -Uri "$baseUri/envia_mail.php" -Method Post -WebSession $resetSession -ContentType 'application/json' -Body (@{ cpf = '52998224725'; email = 'ativo@auth-test.invalid'; csrf_token = $resetCsrf } | ConvertTo-Json)
    $unknownReset = Invoke-WebRequest -Uri "$baseUri/envia_mail.php" -Method Post -WebSession $resetSession -ContentType 'application/json' -Body (@{ cpf = '12345678909'; email = 'ausente@auth-test.invalid'; csrf_token = $resetCsrf } | ConvertTo-Json)
    $knownJson = $knownReset.Content | ConvertFrom-Json
    $unknownJson = $unknownReset.Content | ConvertFrom-Json
    Assert-Http ($knownJson.success -eq $unknownJson.success) 'Solicitações de reset conhecida e desconhecida precisam ter o mesmo status público.'
    Assert-Http ($knownJson.emailSent -eq $unknownJson.emailSent) 'Solicitações de reset conhecida e desconhecida precisam ter o mesmo indicador público.'
    Assert-Http ($knownJson.message -eq $unknownJson.message) 'Solicitações de reset conhecida e desconhecida precisam ser indistinguíveis.'

    $plainToken = (& php (Join-Path $PSScriptRoot 'http-reset-token.fixture.php')).Trim()
    Assert-Http ($plainToken.Length -eq 64) 'Fixture HTTP deve fornecer um token sintético.'
    $resetPasswordForm = Invoke-WebRequest -Uri "$baseUri/views/public/redefinir-senha-form.php?token=$plainToken" -WebSession $resetSession
    $passwordCsrf = Get-CsrfToken $resetPasswordForm.Content
    $invalidPasswordReset = Invoke-WebRequest -Uri "$baseUri/views/public/redefinir-senha-form.php?token=$plainToken" -Method Post -WebSession $resetSession -Body @{ csrf_token = 'invalido'; token = $plainToken; nova_senha = 'HttpNova!123'; confirmar_senha = 'HttpNova!123' }
    Assert-Http ($invalidPasswordReset.Content -match 'formRedefinirSenha') 'CSRF inválido não pode redefinir a senha.'
    $tokenStillValid = Invoke-WebRequest -Uri "$baseUri/views/public/redefinir-senha-form.php?token=$plainToken" -WebSession $resetSession
    Assert-Http ($tokenStillValid.Content -match 'formRedefinirSenha') 'CSRF inválido não pode consumir token de redefinição.'
    $passwordCsrf = Get-CsrfToken $tokenStillValid.Content
    $resetPassword = Invoke-WebRequest -Uri "$baseUri/views/public/redefinir-senha-form.php?token=$plainToken" -Method Post -WebSession $resetSession -Body @{ csrf_token = $passwordCsrf; token = $plainToken; nova_senha = 'HttpNova!123'; confirmar_senha = 'HttpNova!123' }
    Assert-Http ($resetPassword.Content -match 'Senha redefinida com sucesso') 'Formulário HTTP deve redefinir a senha com token válido.'

    Write-Output 'PASS http-auth-flow'
} finally {
    if ($null -ne $server -and -not $server.HasExited) {
        Stop-Process -Id $server.Id -Force
    }
}
