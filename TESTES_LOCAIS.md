TESTES LOCAIS - VALIDAÇÃO DE PHP
=================================

## 📋 Status da Instalação de PHP

Você tem **3 opções** para testar o código PHP localmente:

### ✅ Opção 1: Instalar PHP Localmente (Recomendado)

**Passos**:
1. Consulte `GUIA_INSTALACAO_PHP.md` para instruções completas
2. Execute: `php -v` para verificar instalação
3. Depois execute validações (veja próxima seção)

**Vantagens**:
- Testa código real antes de deploy
- Encontra erros de sintaxe imediatamente
- Valida include/require paths

**Tempo**: ~15-30 minutos (primeira vez)

---

### ✅ Opção 2: Usar Docker (Alternativa)

Se preferir não instalar nada localmente:

```bash
# Instalar Docker Desktop do Windows

# Criar arquivo docker-compose.yml na raiz do projeto:
version: '3.8'
services:
  php:
    image: php:8.2-cli
    volumes:
      - .:/app
    working_dir: /app
    command: php -S localhost:8000 -t public

# Executar:
docker-compose up

# Acessar: http://localhost:8000
```

---

### ✅ Opção 3: Usar Servidor Web Existente

Se você já tem um servidor (Apache/Nginx/XAMPP):

1. Copiar projeto para `htdocs` ou `www`
2. Acessar via URL do servidor
3. Verificar erros no navegador

---

## 🧪 Validações de Teste

### A. Validação de Sintaxe PHP (Após instalar PHP)

**Windows PowerShell**:
```powershell
# Validar todos os arquivos
Get-ChildItem -Recurse -Filter "*.php" -Exclude "vendor/*" | ForEach-Object {
    php -l $_.FullName
}
```

**Windows CMD**:
```batch
for /r . %%F in (*.php) do php -l "%%F"
```

**Linux/macOS**:
```bash
bash validate.sh
```

### B. Validação de Require Paths

Verificar se todos os `require_once` estão corretos:

```bash
# Listar todos os requires
grep -r "require_once" --include="*.php" . --exclude-dir=vendor

# Esperado: todos devem seguir o padrão:
# - public/*.php:       require_once __DIR__ . '/../config'
# - admin/api/*.php:    require_once __DIR__ . '/../../config'
# - internal/pages/*:   require_once __DIR__ . '/../../../config'
```

### C. Teste de Funcionamento Básico

```bash
# Testar se arquivo PHP executa sem erros
php public/index.php

# Testar se classe pode ser carregada
php -r "require 'bd/Conection.php'; echo 'OK';"

# Testar se função de segurança funciona
php -r "require 'config/security.php'; echo Security::hashPassword('teste');"
```

---

## 🎯 Checklist de Testes

Após instalar PHP, execute nesta ordem:

- [ ] `php -v` - Confirmar PHP instalado
- [ ] `php -l public/index.php` - Validar sintaxe
- [ ] `php -l public/home.php` - Validar sintaxe
- [ ] `php -l public/frame.php` - Validar sintaxe
- [ ] `php -l admin/api/getPerfis.php` - Validar API nova
- [ ] `php -l internal/pages/dashboard.php` - Validar página interna
- [ ] Rodar `validate.sh` ou PowerShell script - Validar todos
- [ ] Verificar paths com grep (veja Opção B acima)

---

## 📝 Arquivos de Teste Disponíveis

No projeto você tem:

1. **validate-php.php** - Script PHP para validar (requer PHP instalado)
2. **validate-syntax.ps1** - Script PowerShell para validar
3. **validate.sh** - Script Bash/Linux para validar
4. **install-php.bat** - Guia de instalação manual (Windows)
5. **GUIA_INSTALACAO_PHP.md** - Documentação completa

---

## 🔍 Erros Comuns e Soluções

### "Parse error: syntax error"
→ Arquivo PHP tem erro de sintaxe
→ Solução: `php -l arquivo.php` mostra a linha exata

### "Failed opening required 'path/to/file.php'"
→ Caminho de require_once está errado
→ Solução: Verificar se arquivo existe naquele caminho

### "Call to undefined function Security::"
→ Arquivo `config/security.php` não foi carregado
→ Solução: Verificar `require_once __DIR__ . '/../config/security.php'`

### "Cannot redeclare class Usuario"
→ Arquivo `Usuario.php` foi incluído 2 vezes
→ Solução: Usar `require_once` ao invés de `require`

---

## ✅ Próximos Passos

1. **Instalar PHP** (siga GUIA_INSTALACAO_PHP.md)
2. **Executar validações** (use scripts acima)
3. **Corrigir erros encontrados**
4. **Deploy para servidor** (DEPLOYMENT_GUIDE.md)

---

**IMPORTANTE**: 
- PHP local é para **desenvolvimento e testes**
- Servidor em produção usará versão diferente (provável)
- Sempre testar em ambiente similar ao servidor antes de deploy

