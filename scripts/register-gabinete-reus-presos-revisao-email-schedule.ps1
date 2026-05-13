#Requires -Version 5.1
<#
.SYNOPSIS
    Registra no Agendador de Tarefas do Windows o envio do resumo de reavaliacao (reus presos) por e-mail.

.DESCRIPTION
    Executa toda QUINTA-FEIRA as 18:00, chamando o PHP CLI neste repositorio.
    Execute uma vez no servidor (de preferencia como Administrador).

.EXAMPLE
    powershell -ExecutionPolicy Bypass -File .\scripts\register-gabinete-reus-presos-revisao-email-schedule.ps1
#>
$ErrorActionPreference = 'Stop'

$scriptsDir = Split-Path -Parent $MyInvocation.MyCommand.Path
$repoRoot = Split-Path -Parent $scriptsDir
$sendScript = Join-Path $scriptsDir 'send-gabinete-reus-presos-revisao-email.php'

if (-not (Test-Path -LiteralPath $sendScript)) {
    Write-Error "Arquivo nao encontrado: $sendScript"
    exit 1
}

$phpCmd = Get-Command php -ErrorAction SilentlyContinue
if (-not $phpCmd) {
    Write-Error 'PHP nao esta no PATH. Instale o PHP ou configure o PATH do sistema e execute novamente.'
    exit 1
}
$phpExe = $phpCmd.Source

$taskName = 'TJBA-ReusPresos-ResumoRevisaoEmail'
$description = 'Resumo semanal (e-mail): reus presos / reavaliacao - quinta-feira 18:00.'

Unregister-ScheduledTask -TaskName $taskName -Confirm:$false -ErrorAction SilentlyContinue

$argument = '"' + $sendScript + '"'
$action = New-ScheduledTaskAction -Execute $phpExe -Argument $argument -WorkingDirectory $repoRoot
$trigger = New-ScheduledTaskTrigger -Weekly -WeeksInterval 1 -DaysOfWeek Thursday -At '18:00'
$settings = New-ScheduledTaskSettingsSet -AllowStartIfOnBatteries -DontStopIfGoingOnBatteries -StartWhenAvailable -ExecutionTimeLimit (New-TimeSpan -Hours 2)

Register-ScheduledTask `
    -TaskName $taskName `
    -Description $description `
    -Action $action `
    -Trigger $trigger `
    -Settings $settings `
    -User $env:USERNAME `
    -RunLevel Limited `
    -Force | Out-Null

Write-Host "OK: tarefa '$taskName' criada - toda quinta-feira as 18:00."
Write-Host "PHP: $phpExe"
Write-Host "Script: $sendScript"
Write-Host "Diretorio de trabalho: $repoRoot"
exit 0
