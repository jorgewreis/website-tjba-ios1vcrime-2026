#!/usr/bin/env powershell

<#
.SYNOPSIS
    Instalar PHP portátil no Windows (sem dependências)
    
.DESCRIPTION
    Faz download de PHP 8.2 pré-compilado e configura variáveis de ambiente
#>

$phpVersion = "8.2.0"
$phpDir = "$env:USERPROFILE\AppData\Local\PHP"
$phpZip = "$env:TEMP\php-8.2.0-nts-Win32-x64.zip"

Write-Host "`n╔════════════════════════════════════════════════════════════╗" -ForegroundColor Cyan
Write-Host "║          Instalador PHP Portátil (Versão 8.2)           ║" -ForegroundColor Cyan
Write-Host "╚════════════════════════════════════════════════════════════╝`n" -ForegroundColor Cyan

# Verificar se PHP já está instalado
Write-Host "Verificando se PHP já está instalado..." -ForegroundColor Yellow

$phpExe = Get-Command php -ErrorAction SilentlyContinue
if ($phpExe) {
    Write-Host "✅ PHP já está instalado em: $($phpExe.Source)" -ForegroundColor Green
    php -v
    exit 0
}

Write-Host "❌ PHP não encontrado. Tentando instalar..." -ForegroundColor Red

# Criar diretório
if (!(Test-Path $phpDir)) {
    New-Item -ItemType Directory -Path $phpDir -Force | Out-Null
    Write-Host "📁 Criado diretório: $phpDir" -ForegroundColor Green
}

# Tentar múltiplas URLs
$urls = @(
    "https://downloads.php.net/~windows/php-8.2.0-nts-Win32-x64.zip",
    "https://windows.php.net/downloads/releases/php-8.2.26-nts-Win32-x64.zip",
    "https://windows.php.net/downloads/releases/php-8.1.27-nts-Win32-x64.zip"
)

$downloaded = $false
foreach ($url in $urls) {
    Write-Host "`nTentando baixar de: $url" -ForegroundColor Yellow
    try {
        Write-Host "⏳ Baixando... (pode levar alguns minutos)"
        Invoke-WebRequest -Uri $url -OutFile $phpZip -UseBasicParsing -ErrorAction Stop
        $downloaded = $true
        Write-Host "✅ Download concluído" -ForegroundColor Green
        break
    } catch {
        Write-Host "❌ Falha nesta URL" -ForegroundColor Red
        Remove-Item $phpZip -Force -ErrorAction SilentlyContinue
    }
}

if (!$downloaded) {
    Write-Host "`n❌ Não foi possível baixar PHP de nenhuma URL" -ForegroundColor Red
    Write-Host "`n📝 Alternativas:" -ForegroundColor Yellow
    Write-Host "   1. Visitar: https://windows.php.net/download/releases/" -ForegroundColor Cyan
    Write-Host "   2. Baixar manualmente a versão 8.1 ou 8.2 (NTS)" -ForegroundColor Cyan
    Write-Host "   3. Extrair em: $phpDir" -ForegroundColor Cyan
    exit 1
}

# Extrair ZIP
Write-Host "`n⏳ Extraindo arquivos..." -ForegroundColor Yellow
try {
    Expand-Archive -Path $phpZip -DestinationPath $phpDir -Force -ErrorAction Stop
    Write-Host "✅ Arquivos extraídos" -ForegroundColor Green
} catch {
    Write-Host "❌ Erro ao extrair: $_" -ForegroundColor Red
    exit 1
}

# Adicionar ao PATH
Write-Host "`n⏳ Configurando variáveis de ambiente..." -ForegroundColor Yellow

$currentPath = [Environment]::GetEnvironmentVariable("PATH", "User")
if ($currentPath -notmatch [regex]::Escape($phpDir)) {
    [Environment]::SetEnvironmentVariable("PATH", "$phpDir;$currentPath", "User")
    Write-Host "✅ PATH atualizado" -ForegroundColor Green
} else {
    Write-Host "ℹ️  PHP já está no PATH" -ForegroundColor Cyan
}

# Refreshar variáveis de ambiente
$env:PATH = "$phpDir;$env:PATH"

# Verificar instalação
Write-Host "`n⏳ Verificando instalação..." -ForegroundColor Yellow
$phpCheck = & "$phpDir\php.exe" -v 2>$null

if ($phpCheck) {
    Write-Host "`n✅ PHP INSTALADO COM SUCESSO!" -ForegroundColor Green
    Write-Host "`n$phpCheck`n" -ForegroundColor Green
} else {
    Write-Host "❌ Erro ao verificar PHP" -ForegroundColor Red
    exit 1
}

# Limpar ZIP
Remove-Item $phpZip -Force -ErrorAction SilentlyContinue

Write-Host "════════════════════════════════════════════════════════════" -ForegroundColor Green
Write-Host "Instalação concluída! Você pode fechar e reabrir o terminal." -ForegroundColor Green
Write-Host "════════════════════════════════════════════════════════════`n" -ForegroundColor Green
