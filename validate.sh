#!/usr/bin/env bash
# Script para validar sintaxe PHP com relatório detalhado

PROJECT_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ERRORS=0
SUCCESS=0
IGNORED=0

echo ""
echo "════════════════════════════════════════════════════════════"
echo "Validador de Sintaxe PHP"
echo "════════════════════════════════════════════════════════════"
echo ""

# Verificar se php está instalado
if ! command -v php &> /dev/null; then
    echo "❌ PHP não está instalado ou não está no PATH"
    echo ""
    echo "Para instalar PHP:"
    echo "  Windows: Consulte GUIA_INSTALACAO_PHP.md"
    echo "  macOS: brew install php@8.2"
    echo "  Linux (Ubuntu): sudo apt install php-cli"
    echo ""
    exit 1
fi

echo "✅ PHP encontrado:"
php -v
echo ""

# Validar arquivos
echo "Validando arquivos PHP..."
echo "─────────────────────────────────────────────────────────────"

while IFS= read -r file; do
    # Ignorar vendor e .git
    if [[ "$file" == *"vendor"* ]] || [[ "$file" == *".git"* ]]; then
        ((IGNORED++))
        continue
    fi
    
    filename=$(basename "$file")
    
    # Rodar validação
    output=$(php -l "$file" 2>&1)
    
    if [ $? -eq 0 ]; then
        echo "✅ $filename"
        ((SUCCESS++))
    else
        echo "❌ $filename"
        echo "   $output"
        ((ERRORS++))
    fi
done < <(find "$PROJECT_ROOT" -name "*.php" -type f)

# Resultado
echo ""
echo "════════════════════════════════════════════════════════════"
echo "RESULTADO"
echo "════════════════════════════════════════════════════════════"
echo "✅ Validados com sucesso: $SUCCESS"
echo "❌ Erros encontrados: $ERRORS"
echo "⊘  Ignorados: $IGNORED"
echo "════════════════════════════════════════════════════════════"
echo ""

if [ $ERRORS -eq 0 ]; then
    echo "✅ TODOS OS ARQUIVOS ESTÃO CORRETOS!"
    exit 0
else
    echo "❌ EXISTEM ERROS A CORRIGIR"
    exit 1
fi
