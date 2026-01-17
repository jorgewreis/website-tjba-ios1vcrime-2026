@echo off
REM Instalador PHP Portátil - Versão Batch

setlocal enabledelayedexpansion

cls
echo.
echo ════════════════════════════════════════════════════════════
echo               Instalador PHP Portatil (8.2)
echo ════════════════════════════════════════════════════════════
echo.

REM Verificar se PHP já está instalado
echo Verificando se PHP já está instalado...
php -v >nul 2>&1
if !errorlevel! equ 0 (
    echo ✓ PHP já está instalado!
    echo.
    php -v
    exit /b 0
)

echo ✗ PHP não encontrado

REM Alternativa manual
echo.
echo Para instalar PHP manualmente:
echo.
echo 1. Acesse: https://windows.php.net/download/releases/
echo 2. Baixe a versão "nts" (Non Thread Safe) para Windows x64
echo 3. Extraia em: %%USERPROFILE%%\AppData\Local\PHP
echo 4. Adicione ao PATH:
echo    - Pressione Win + X, escolha "Sistema"
echo    - Variáveis de Ambiente
echo    - Adicione: %%USERPROFILE%%\AppData\Local\PHP
echo 5. Reinicie o terminal e execute: php -v
echo.
echo ════════════════════════════════════════════════════════════
echo.

pause
