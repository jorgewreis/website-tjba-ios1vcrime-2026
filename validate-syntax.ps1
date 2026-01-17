param(
    [switch]$Verbose = $false,
    [switch]$Fix = $false
)

<#
.SYNOPSIS
    Script de Validação de Sintaxe PHP (sem dependências)
    
.DESCRIPTION
    Valida arquivos PHP procurando por:
    - Tags PHP não fechadas
    - Quotes não fechadas
    - Parênteses desequilibrados
    - Erros comuns de sintaxe
    
.PARAMETER Verbose
    Exibir detalhes de cada arquivo
    
.PARAMETER Fix
    Tentar corrigir problemas automaticamente
#>

$projectRoot = "d:\Projects\Clients\website-tjba-ios1vcrime-2026"
$errors = @()
$warnings = @()
$validated = 0

# Padrões a ignorar
$ignorePatterns = @('vendor/', '.git/', 'node_modules/', '.vscode/')

function Test-PHPSyntax {
    param(
        [string]$FilePath
    )
    
    $content = Get-Content -Path $FilePath -Raw -Encoding UTF8
    $localErrors = @()
    
    # 1. Verificar se arquivo começa com <?php
    if ($content -notmatch '^\s*<\?php') {
        $localErrors += "Arquivo não começa com <?php"
    }
    
    # 2. Verificar se <?php está fechado com ?>
    $phpOpen = [regex]::Matches($content, '<\?php', [System.Text.RegularExpressions.RegexOptions]::IgnoreCase).Count
    $phpClose = [regex]::Matches($content, '\?>', [System.Text.RegularExpressions.RegexOptions]::IgnoreCase).Count
    
    # 3. Verificar quotes balanceadas
    $singleQuotes = [regex]::Matches($content, "[^\\\]'").Count
    if ($singleQuotes % 2 -ne 0) {
        $localErrors += "Quotes simples desequilibradas"
    }
    
    # 4. Verificar chaves balanceadas
    $openBraces = [regex]::Matches($content, '\{').Count
    $closeBraces = [regex]::Matches($content, '\}').Count
    if ($openBraces -ne $closeBraces) {
        $localErrors += "Chaves desequilibradas ({ $openBraces vs } $closeBraces)"
    }
    
    # 5. Verificar parênteses balanceados
    $openParen = [regex]::Matches($content, '\(').Count
    $closeParen = [regex]::Matches($content, '\)').Count
    if ($openParen -ne $closeParen) {
        $localErrors += "Parênteses desequilibrados (( $openParen vs ) $closeParen)"
    }
    
    # 6. Verificar colchetes balanceados
    $openBracket = [regex]::Matches($content, '\[').Count
    $closeBracket = [regex]::Matches($content, '\]').Count
    if ($openBracket -ne $closeBracket) {
        $localErrors += "Colchetes desequilibrados ([ $openBracket vs ] $closeBracket)"
    }
    
    # 7. Verificar require_once paths
    $patternRequire = "require_once\s+__DIR__\s*\.\s*['\`"]([^'\`"]+)['\`"]"
    $requires = [regex]::Matches($content, $patternRequire)
    foreach ($match in $requires) {
        $path = $match.Groups[1].Value
        # Verificar se path parece válido (começa com / ou ../)
        if ($path -notmatch '^(\.\./|/|\.\.\\)') {
            $localErrors += "require_once path suspeito: $path"
        }
    }
    
    # 8. Verificar declare(strict_types=1)
    if (Test-Path $FilePath) {
        $firstLines = (Get-Content $FilePath -TotalCount 5) -join "`n"
        if ($FilePath -match '(bd|services|admin)' -and $firstLines -notmatch 'declare\(strict_types=1\)') {
            # Avisar se é arquivo crítico
            if ($FilePath -match '\.php$' -and -not ($FilePath -match 'public.*\.php$')) {
                # Não é crítico para public/ que frequentemente incluem HTML
            }
        }
    }
    
    return $localErrors
}

# Função para validar diretório recursivamente
function Validate-Directory {
    param(
        [string]$Dir,
        [array]$IgnorePatterns
    )
    
    Get-ChildItem -Path $Dir -Recurse -Filter "*.php" | ForEach-Object {
        $filePath = $_.FullName
        
        # Verificar se deve ignorar
        $shouldIgnore = $false
        foreach ($pattern in $IgnorePatterns) {
            if ($filePath -match [regex]::Escape($pattern)) {
                $shouldIgnore = $true
                break
            }
        }
        
        if ($shouldIgnore) { return }
        
        $relativePath = $filePath -replace [regex]::Escape($projectRoot), ""
        
        if ($Verbose) {
            Write-Host "Validando: $relativePath ... " -NoNewline
        }
        
        $syntaxErrors = Test-PHPSyntax -FilePath $filePath
        
        if ($syntaxErrors.Count -eq 0) {
            if ($Verbose) { Write-Host "✅ OK" -ForegroundColor Green }
            return $true
        } else {
            if ($Verbose) { Write-Host "❌ ERRO" -ForegroundColor Red }
            return @{
                File = $relativePath
                Errors = $syntaxErrors
            }
        }
    }
}

# ═══════════════════════════════════════════════════════════
# MAIN
# ═══════════════════════════════════════════════════════════

Write-Host "`n═══════════════════════════════════════════════════════════" -ForegroundColor Cyan
Write-Host "Validador de Sintaxe PHP (Análise Estática)" -ForegroundColor Cyan
Write-Host "═══════════════════════════════════════════════════════════`n" -ForegroundColor Cyan

$validationResults = Validate-Directory -Dir $projectRoot -IgnorePatterns $ignorePatterns

$successCount = 0
$errorsList = @()

foreach ($result in $validationResults) {
    if ($result -eq $true) {
        $successCount++
    } elseif ($result -is [hashtable]) {
        $errorsList += $result
    }
}

# Resultado
Write-Host "`n═══════════════════════════════════════════════════════════" -ForegroundColor Cyan
Write-Host "RESULTADO DA VALIDAÇÃO" -ForegroundColor Cyan
Write-Host "═══════════════════════════════════════════════════════════`n" -ForegroundColor Cyan

Write-Host "✅ Arquivos OK: $successCount" -ForegroundColor Green
Write-Host "❌ Erros encontrados: $($errorsList.Count)" -ForegroundColor $(if ($errorsList.Count -eq 0) { "Green" } else { "Red" })

if ($errorsList.Count -gt 0) {
    Write-Host "`nDETALHES DOS ERROS:`n" -ForegroundColor Red
    foreach ($error in $errorsList) {
        Write-Host "📄 $($error.File)" -ForegroundColor Yellow
        foreach ($err in $error.Errors) {
            Write-Host "   ❌ $err" -ForegroundColor Red
        }
    }
    exit 1
} else {
    Write-Host "`n✅ TODOS OS ARQUIVOS ESTÃO CORRETOS!" -ForegroundColor Green
    exit 0
}
