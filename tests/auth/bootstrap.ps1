[CmdletBinding()]
param(
    [switch]$Start
)

$ErrorActionPreference = 'Stop'
$projectRoot = (Resolve-Path (Join-Path $PSScriptRoot '../..')).Path
$runtimePath = Join-Path $PSScriptRoot '.env.test.runtime'
$randomBytes = [byte[]]::new(24)
$randomGenerator = [System.Security.Cryptography.RandomNumberGenerator]::Create()
try {
    $randomGenerator.GetBytes($randomBytes)
} finally {
    $randomGenerator.Dispose()
}
$password = ([BitConverter]::ToString($randomBytes) -replace '-', '').ToLowerInvariant()

$runtimeContents = @"
APP_ENV=testing
AUTH_TEST_ISOLATION=isolated-auth-test
DB_HOST=127.0.0.1
DB_PORT=33306
DB_NAME=auth_test_ios1vcrime
DB_USER=auth_test
DB_PASS=$password
DB_CHARSET=utf8mb4
MAIL_DRIVER=smtp
MAIL_HOST=127.0.0.1
MAIL_PORT=31025
MAIL_USER=auth-test
MAIL_PASSWORD=$password
MAIL_ENCRYPTION=none
MAIL_FROM_ADDRESS=no-reply@auth-test.invalid
MAIL_FROM_NAME=Teste de autenticacao
APP_URL=https://auth-test.invalid
AUTH_TEST_DB_NAME=auth_test_ios1vcrime
AUTH_TEST_DB_USER=auth_test
AUTH_TEST_DB_PASSWORD=$password
AUTH_TEST_ROOT_PASSWORD=$password
AUTH_TEST_DB_PORT=33306
AUTH_TEST_SMTP_PORT=31025
AUTH_TEST_MAILPIT_HTTP_PORT=38025
"@
$utf8WithoutBom = New-Object System.Text.UTF8Encoding($false)
[System.IO.File]::WriteAllText($runtimePath, $runtimeContents, $utf8WithoutBom)

if ($Start) {
    & docker compose --env-file $runtimePath -f (Join-Path $projectRoot 'compose.auth-test.yml') up -d --wait
    if ($LASTEXITCODE -ne 0) {
        throw 'O sandbox de autenticação não iniciou corretamente.'
    }
}

Write-Output "Arquivo de runtime criado: $runtimePath"
