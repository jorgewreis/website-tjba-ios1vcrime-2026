$ErrorActionPreference = 'Stop'
$runner = Join-Path $PSScriptRoot 'run-rei88-carta-mysql-ci-isolated.ps1'
$tokens = $null; $parseErrors = $null
$tree = [System.Management.Automation.Language.Parser]::ParseFile($runner, [ref]$tokens, [ref]$parseErrors)
if ($parseErrors.Count) { throw 'Runner não compila.' }
foreach ($name in @('Assert-MountinfoExact', 'Assert-ActiveMount')) {
    $functions = @($tree.FindAll({ param($node) $node -is [System.Management.Automation.Language.FunctionDefinitionAst] -and $node.Name -eq $name }, $true))
    if ($functions.Count -ne 1) { throw "Função $name não encontrada." }
    Invoke-Expression $functions[0].Extent.Text
}
function Reject-Mount([string[]]$lines, [string]$target, [string]$type) {
    $rejected = $false
    try { Assert-MountinfoExact $lines $target $type } catch { $rejected = $true }
    if (-not $rejected) { throw 'Entrada inválida aceita.' }
}
$valid = '25 10 0:18 / /run/mysqld rw,relatime - ext4 /dev/sda1 rw'
Assert-MountinfoExact @($valid) '/run/mysqld' ''
Assert-MountinfoExact @('26 10 0:19 / /var/lib/mysql rw - tmpfs tmpfs rw') '/var/lib/mysql' 'tmpfs'
Reject-Mount @('25 10 0:18 /run/mysqld /outra rw - ext4 /dev/sda1 rw') '/run/mysqld' ''
Reject-Mount @($valid, $valid) '/run/mysqld' ''
Reject-Mount @('26 10 0:19 / /var/lib/mysql rw - ext4 tmpfs rw') '/var/lib/mysql' 'tmpfs'
Reject-Mount @('25 10 0:18 / /run\057mysqld rw - ext4 /dev/sda1 rw') '/run/mysqld' ''
Reject-Mount @('25 10 0:18 / /run/mysqld rw ext4 /dev/sda1 rw') '/run/mysqld' ''

# Stub fornece mountinfo sintético; não invoca Docker nem usa recursos externos.
$migration = '/repo/database/migrations/20261116_add_cartas_expedidas_idempotency.sql'
function Invoke-DockerSafe([string[]]$argsList) { return "27 10 0:20 / $migration ro - ext4 /dev/sda1 rw" }
Assert-ActiveMount 'synthetic' $migration '' 'php-start'
$rejected = $false
try { Assert-ActiveMount 'synthetic' '/repo/database/migrations/other.sql' '' 'php-start' } catch { $rejected = $true }
if (-not $rejected) { throw 'Destino não autorizado foi aceito.' }
$rejected = $false
try { Assert-ActiveMount 'synthetic' $migration '' 'unexpected-phase' } catch { $rejected = $true }
if (-not $rejected) { throw 'Fase não autorizada foi aceita.' }
Write-Output 'Mountinfo e lista de destinos: testes sintéticos passaram.'
