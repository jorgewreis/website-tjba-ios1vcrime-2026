<?php
$root = dirname(__DIR__);
$msgs = [
    'cartorio-guias' => ['Guia cancelada.', 'Guia excluída.'],
    'cartorio-cartas-expedidas' => ['Carta expedida cancelada.', 'Carta expedida excluída.'],
    'cartorio-oficios' => ['Ofício cancelado.', 'Ofício excluído.'],
    'cartorio-editais' => ['Edital cancelado.', 'Edital excluído.'],
    'cartorio-cartas-recebidas' => ['Carta recebida cancelada.', 'Carta recebida excluída.'],
];
function folderFromPath(string $root, string $path): string {
    $normRoot = rtrim(str_replace('\\', '/', $root), '/');
    $normPath = str_replace('\\', '/', $path);
    $prefix = $normRoot . '/public/api/';
    if (strpos($normPath, $prefix) !== 0) {
        return '';
    }
    $rel = substr($normPath, strlen($prefix));
    $parts = explode('/', $rel);
    return $parts[0] ?? '';
}
function patchCancel(string $path, string $folder, string $msgCancel): bool {
    $c = str_replace("\r\n", "\n", file_get_contents($path));
    if (strpos($c, 'CartorioProdutividade::mirrorEstornoSaldoSession') !== false) {
        return true;
    }
    $inj = "    if (\$__mir) {\n        require_once \$basePath . '/app/helpers/CartorioProdutividade.php';\n        CartorioProdutividade::mirrorEstornoSaldoSession(\$connection, \$id, '{$folder}', " . var_export($msgCancel, true) . ");\n    }\n    ";
    $patterns = [
        ["    \$service->cancel(\$id);\n    echo json_encode(['success' => true], JSON_UNESCAPED_UNICODE);", "    \$service->cancel(\$id);\n    \$__mir = true;\n{$inj}echo json_encode(['success' => true], JSON_UNESCAPED_UNICODE);"],
        ["    \$cancelled = \$service->cancel(\$id);\n    echo json_encode(['success' => \$cancelled], JSON_UNESCAPED_UNICODE);", "    \$cancelled = \$service->cancel(\$id);\n    \$__mir = \$cancelled;\n{$inj}echo json_encode(['success' => \$cancelled], JSON_UNESCAPED_UNICODE);"],
        ["    \$updated = \$service->cancel(\$id);\n    echo json_encode(['success' => \$updated], JSON_UNESCAPED_UNICODE);", "    \$updated = \$service->cancel(\$id);\n    \$__mir = \$updated;\n{$inj}echo json_encode(['success' => \$updated], JSON_UNESCAPED_UNICODE);"],
        ["    \$updated = \$service->cancel(\$id, \$uid !== '' ? \$uid : null);\n    echo json_encode(['success' => \$updated], JSON_UNESCAPED_UNICODE);", "    \$updated = \$service->cancel(\$id, \$uid !== '' ? \$uid : null);\n    \$__mir = \$updated;\n{$inj}echo json_encode(['success' => \$updated], JSON_UNESCAPED_UNICODE);"],
    ];
    foreach ($patterns as [$a, $b]) {
        if (strpos($c, $a) !== false) {
            $c2 = str_replace($a, $b, $c, $n);
            if ($n === 1) {
                file_put_contents($path, $c2);
                return true;
            }
        }
    }
    fwrite(STDERR, "no pattern: $path\n");
    return false;
}
function patchDelete(string $path, string $folder, string $msgDel): bool {
    $c = str_replace("\r\n", "\n", file_get_contents($path));
    if (strpos($c, 'CartorioProdutividade::mirrorEstornoSaldoSession') !== false) {
        return true;
    }
    $needle = "    \$resultado = \$service->delete(\$id);\n    echo json_encode(is_array(\$resultado) ? \$resultado : ['deleted' => (bool) \$resultado], JSON_UNESCAPED_UNICODE);";
    if (strpos($c, $needle) === false) {
        fwrite(STDERR, "delete pattern missing: $path\n");
        return false;
    }
    $rep = "    \$resultado = \$service->delete(\$id);\n    require_once \$basePath . '/app/helpers/CartorioProdutividade.php';\n    CartorioProdutividade::mirrorEstornoSaldoSession(\$connection, \$id, '{$folder}', " . var_export($msgDel, true) . ");\n    echo json_encode(is_array(\$resultado) ? \$resultado : ['deleted' => (bool) \$resultado], JSON_UNESCAPED_UNICODE);";
    file_put_contents($path, str_replace($needle, $rep, $c, $n));
    return $n === 1;
}
foreach (glob($root . '/public/api/cartorio-*/cancel.php') as $f) {
    $folder = folderFromPath($root, $f);
    if (!isset($msgs[$folder])) {
        continue;
    }
    patchCancel($f, $folder, $msgs[$folder][0]);
}
foreach (glob($root . '/public/api/cartorio-*/delete.php') as $f) {
    $folder = folderFromPath($root, $f);
    if (!isset($msgs[$folder])) {
        continue;
    }
    patchDelete($f, $folder, $msgs[$folder][1]);
}
echo "apis done\n";
