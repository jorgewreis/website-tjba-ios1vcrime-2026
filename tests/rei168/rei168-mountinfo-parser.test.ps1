$ErrorActionPreference = 'Stop'
$runner = Join-Path $PSScriptRoot 'run-rei168-mysql-isolated.ps1'
$tokens = $null; $parseErrors = $null
$tree = [System.Management.Automation.Language.Parser]::ParseFile($runner, [ref]$tokens, [ref]$parseErrors)
if ($parseErrors.Count) { throw 'Runner não compila.' }
$functions = @($tree.FindAll({ param($node) $node -is [System.Management.Automation.Language.FunctionDefinitionAst] -and $node.Name -eq 'Assert-MountinfoExact' }, $true))
if ($functions.Count -ne 1) { throw 'Função mountinfo não encontrada.' }
Invoke-Expression $functions[0].Extent.Text
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
Write-Output 'Mountinfo estrito: testes sintéticos passaram.'
