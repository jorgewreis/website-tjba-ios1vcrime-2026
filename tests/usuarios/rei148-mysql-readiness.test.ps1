$ErrorActionPreference = 'Stop'
. (Join-Path $PSScriptRoot 'rei148-mysql-readiness.ps1')

$script:probes = 0
$probe = { $script:probes++; '1' }
if (Test-Rei148MySqlReady { 'docker-entrypoi' } $probe) { throw 'Servidor temporário aceito.' }
if ($script:probes -ne 0) { throw 'Consulta executada antes do servidor definitivo.' }
if (Test-Rei148MySqlReady { 'mysqld' } { throw 'socket indisponível' }) { throw 'Socket indisponível aceito.' }
if (Test-Rei148MySqlReady { throw 'container indisponível' } $probe) { throw 'PID indisponível aceito.' }
if (Test-Rei148MySqlReady { 'mysqld' } { '0' }) { throw 'Resultado SQL incorreto aceito.' }
if (Test-Rei148MySqlReady { 'mysqld'; 'mysqld' } $probe) { throw 'PID ambíguo aceito.' }
if (-not (Test-Rei148MySqlReady { 'mysqld' } $probe)) { throw 'Servidor definitivo pronto foi recusado.' }
if ($script:probes -ne 1) { throw 'Quantidade inesperada de consultas.' }
Write-Host 'Prontidão MySQL: cenários simulados passaram.'
