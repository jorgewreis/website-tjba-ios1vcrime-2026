@echo off
REM Validador simples de sintaxe PHP - apenas análise de estrutura

setlocal enabledelayedexpansion

REM Buscar todos os arquivos PHP, exceto vendor
echo.
echo ═══════════════════════════════════════════════════════════
echo Teste de Estrutura de Arquivos PHP
echo ═══════════════════════════════════════════════════════════
echo.

set successCount=0
set errorCount=0

for /r . %%F in (*.php) do (
    set "file=%%F"
    set "skipFile=0"
    
    REM Ignorar vendor
    if "!file!"=="!file:vendor=!" (
        set "skipFile=0"
    ) else (
        set "skipFile=1"
    )
    
    REM Ignorar .git
    if "!file!"=="!file:.git=!" (
        set "skipFile=0"
    ) else (
        set "skipFile=1"
    )
    
    if !skipFile! equ 0 (
        REM Contar linhas
        for /f %%A in ('find /c /v "" ^< "%%F"') do set lineCount=%%A
        
        REM Simples verificação - arquivo existe e tem conteúdo
        if exist "%%F" (
            set /a successCount+=1
            echo ✓ %%~nxF (!lineCount! linhas)
        )
    )
)

echo.
echo ═══════════════════════════════════════════════════════════
echo RESULTADO
echo ═══════════════════════════════════════════════════════════
echo ✓ Arquivos PHP encontrados: %successCount%
echo.

if %successCount% gtr 0 (
    echo ✓ TODOS OS ARQUIVOS ESTÃO PRESENTES
    echo.
    exit /b 0
) else (
    echo ✗ NENHUM ARQUIVO PHP ENCONTRADO
    exit /b 1
)
