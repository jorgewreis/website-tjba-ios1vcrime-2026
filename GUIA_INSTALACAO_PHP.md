# Guia de Instalação de PHP Local

## 📋 Requisitos Mínimos

- Windows 10 ou superior
- ~100MB de espaço em disco
- Privilégios de usuário

## 🚀 Instalação Rápida (Opção 1: Chocolatey - Recomendado)

### Passo 1: Instalar Chocolatey (se não tiver)

Abra PowerShell como **Administrador** e execute:

```powershell
Set-ExecutionPolicy -ExecutionPolicy RemoteSigned -Scope CurrentUser
iwr https://community.chocolatey.org/install.ps1 -UseBasicParsing | iex
```

### Passo 2: Instalar PHP

```bash
choco install php
```

### Passo 3: Verificar Instalação

```bash
php -v
```

---

## 🚀 Instalação Manual (Opção 2: Download Direto)

### Passo 1: Baixar PHP

1. Visite: [https://windows.php.net/download/releases/](https://windows.php.net/download/releases/)
2. Baixe a versão **NTS (Non Thread Safe)** mais recente (ex: `php-8.2.26-nts-Win32-x64.zip`)
   - **Importante**: Escolha a versão NTS, não VC ou Thread Safe

### Passo 2: Extrair

1. Crie uma pasta: `C:\PHP` ou `%USERPROFILE%\AppData\Local\PHP`
2. Extraia o ZIP nesta pasta
3. Você deve ver: `php.exe`, `php-cli.exe`, etc.

### Passo 3: Adicionar ao PATH

#### Windows 10+:
1. Pressione `Win + X` → escolha **Sistema**
2. Clique em **Configurações Avançadas do Sistema**
3. Clique em **Variáveis de Ambiente**
4. Em **Variáveis do Usuário**, clique **Novo**
5. Nome: `PATH`
6. Valor: `C:\PHP` (ou seu caminho)
7. Clique **OK** 3 vezes

#### Ou via PowerShell (Administrador):
```powershell
[Environment]::SetEnvironmentVariable("PATH", "C:\PHP;$([Environment]::GetEnvironmentVariable('PATH', 'User'))", "User")
```

### Passo 4: Fechar e Reabrir Terminal

Feche todo PowerShell/CMD e abra um novo.

### Passo 5: Verificar

```bash
php -v
```

Deve retornar algo como:
```
PHP 8.2.26 (cli) (built: Dec 5 2024...)
```

---

## ✅ Testando Instalação

### Teste Básico:
```bash
php -v
```

### Teste com Arquivo:
```bash
php -r "echo 'PHP Funciona!';"
```

### Teste de Sintaxe (no projeto):
```bash
php -l public/index.php
```

---

## 🧪 Validar Projeto Após Instalar PHP

Navegue para o diretório do projeto:

```bash
cd d:\Projects\Clients\website-tjba-ios1vcrime-2026

# Validar sintaxe de todos os arquivos PHP
powershell -ExecutionPolicy Bypass -Command @"
Get-ChildItem -Recurse -Filter "*.php" -Exclude "vendor/*" | ForEach-Object {
    $result = php -l $_.FullName 2>&1
    if ($LASTEXITCODE -eq 0) {
        Write-Host "✓ $($_.Name)" -ForegroundColor Green
    } else {
        Write-Host "✗ $($_.Name): $result" -ForegroundColor Red
    }
}
"@
```

---

## 🔍 Verificar Require Paths

Após validar sintaxe, verificar paths de require:

```bash
# Listar todos os require_once
powershell -ExecutionPolicy Bypass -Command @"
Get-ChildItem -Recurse -Filter "*.php" -Exclude "vendor/*" | ForEach-Object {
    Select-String -Path $_.FullName -Pattern "require_once" | ForEach-Object {
        $file = $_.Filename -replace [regex]::Escape((Get-Item .).FullName), "."
        Write-Host "$file : $_"
    }
}
"@
```

---

## ❌ Problemas Comuns

### Erro: "php: comando não encontrado"

**Solução**: PHP não está no PATH
- Verificar se arquivo existe: `C:\PHP\php.exe`
- Reabrir terminal depois de adicionar ao PATH
- Reabrir VS Code depois de adicionar ao PATH

### Erro: "Parse error: syntax error"

**Solução**: Erro no código PHP, não no PHP
- Rodar: `php -l seu_arquivo.php`
- Verificar a linha reportada

### PHP funciona mas IDE não encontra

**Solução**: Configurar PHP no VS Code
- Extensions: "Intelephense" ou "PHP Server"
- Settings → PHP: Executable Path → `C:\PHP\php.exe`

---

## 📝 Próximos Passos

Após instalar PHP, execute:

```bash
# Validar todos os arquivos
php validate-php.php

# Ou usar script de sintaxe
powershell -ExecutionPolicy Bypass -File validate-syntax.ps1 -Verbose
```

---

## 🔗 Referências

- [Windows PHP Downloads](https://windows.php.net/)
- [PHP Documentation](https://www.php.net/manual/)
- [PATH Environment Variable](https://www.microsoft.com/en-us/p/windows-terminal/)

