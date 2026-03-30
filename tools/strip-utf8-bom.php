<?php
declare(strict_types=1);

/**
 * Remove UTF-8 BOM (EF BB BF) do início de arquivos, se presente.
 */
$root = dirname(__DIR__) . '/app/views/internal';
$it = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
);
$bom = "\xEF\xBB\xBF";
$fixed = 0;
foreach ($it as $file) {
    if (!$file->isFile() || strtolower($file->getExtension()) !== 'php') {
        continue;
    }
    $path = $file->getPathname();
    $raw = file_get_contents($path);
    if ($raw === false || $raw === '' || strncmp($raw, $bom, 3) !== 0) {
        continue;
    }
    $new = substr($raw, 3);
    if (file_put_contents($path, $new) !== false) {
        echo $path . "\n";
        $fixed++;
    }
}
echo "Total: $fixed\n";
