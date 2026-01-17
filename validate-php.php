#!/usr/bin/env php
<?php
/**
 * Script de Validação de Sintaxe PHP
 * Verifica todos os arquivos .php do projeto
 */

declare(strict_types=1);

$projectRoot = __DIR__;
$errors = [];
$warnings = [];
$validated = 0;

// Padrões a ignorar
$ignorePatterns = [
    'vendor/',
    '.git/',
    'node_modules/',
    '.vscode/',
];

/**
 * Verifica se arquivo deve ser ignorado
 */
function shouldIgnore(string $path, array $patterns): bool {
    foreach ($patterns as $pattern) {
        if (strpos($path, $pattern) !== false) {
            return true;
        }
    }
    return false;
}

/**
 * Valida sintaxe de arquivo PHP
 */
function validatePHP(string $filePath): ?array {
    // Usar php -l para validar sintaxe
    $output = shell_exec("php -l " . escapeshellarg($filePath) . " 2>&1");
    
    if (strpos($output, "No syntax errors detected") !== false) {
        return null; // Sem erros
    }
    
    return [
        'file' => $filePath,
        'output' => trim($output)
    ];
}

/**
 * Recursivamente validar arquivos PHP
 */
function validateDirectory(string $dir, array $ignorePatterns, array &$errors, array &$warnings, int &$validated): void {
    $files = scandir($dir);
    
    foreach ($files as $file) {
        if (in_array($file, ['.', '..'])) continue;
        
        $filePath = $dir . DIRECTORY_SEPARATOR . $file;
        
        // Ignorar padrões
        if (shouldIgnore($filePath, $ignorePatterns)) {
            continue;
        }
        
        if (is_dir($filePath)) {
            validateDirectory($filePath, $ignorePatterns, $errors, $warnings, $validated);
        } elseif (pathinfo($filePath, PATHINFO_EXTENSION) === 'php') {
            echo "Validando: $file ... ";
            
            $result = validatePHP($filePath);
            if ($result === null) {
                echo "✅ OK\n";
                $validated++;
            } else {
                echo "❌ ERRO\n";
                $errors[] = $result;
            }
        }
    }
}

echo "═══════════════════════════════════════════════════════════\n";
echo "Validador de Sintaxe PHP\n";
echo "═══════════════════════════════════════════════════════════\n\n";

// Validar projeto
validateDirectory($projectRoot, $ignorePatterns, $errors, $warnings, $validated);

echo "\n═══════════════════════════════════════════════════════════\n";
echo "RESULTADO\n";
echo "═══════════════════════════════════════════════════════════\n\n";

echo "✅ Arquivos validados com sucesso: $validated\n";
echo "❌ Erros encontrados: " . count($errors) . "\n";
echo "⚠️  Avisos: " . count($warnings) . "\n\n";

if (!empty($errors)) {
    echo "ERROS ENCONTRADOS:\n";
    echo "─────────────────────────────────────────────────────────\n";
    foreach ($errors as $error) {
        echo "\nArquivo: {$error['file']}\n";
        echo "Erro:\n{$error['output']}\n";
    }
}

if (!empty($warnings)) {
    echo "\nAVISOS:\n";
    echo "─────────────────────────────────────────────────────────\n";
    foreach ($warnings as $warning) {
        echo "$warning\n";
    }
}

echo "\n═══════════════════════════════════════════════════════════\n";
if (empty($errors)) {
    echo "✅ TODOS OS ARQUIVOS ESTÃO CORRETOS!\n";
    exit(0);
} else {
    echo "❌ EXISTEM ERROS A CORRIGIR\n";
    exit(1);
}
