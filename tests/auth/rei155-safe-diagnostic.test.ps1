$ErrorActionPreference = 'Stop'
. (Join-Path $PSScriptRoot 'rei155-safe-diagnostic.ps1')
$sentinel = 'SYNTHETIC-SECRET-' + [Guid]::NewGuid().ToString('N')
$malicious = @("HTTP_DIAG: mail_json JsonException code=4 $sentinel", "REI155_DIAG: $sentinel", "token=$sentinel")
$result = Format-AuthDockerFailure $sentinel 255 $malicious
if ($result.Contains($sentinel) -or $result.Contains('HTTP_DIAG:') -or $result.Contains('REI155_DIAG:')) { throw 'Diagnóstico incluiu valor arbitrário.' }
$safe = Format-AuthDockerFailure 'exec' 255 @('HTTP_DIAG: mail_json JsonException code=4')
if (-not $safe.Contains('HTTP_DIAG: mail_json JsonException code=4')) { throw 'Diagnóstico permitido foi perdido.' }
Write-Output 'PASS sanitização adversarial de diagnóstico'
