<?php
$base = dirname(__DIR__) . '/app/views/internal';
$rii = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS)
);
$includeLine = "<?php include __DIR__ . '/_internal_head_styles.php'; ?>";
$patterns = [
    // _theme_init then 5 links (various indents)
    [
        'from' => '/\s*<\?php\s+include\s+__DIR__\s*\.\s*[\'"]\/_theme_init\.php[\'"];\s*\?>\s*/',
        'to'   => "\n        ",
    ],
];
$linkBlock = '<link rel="stylesheet" href="/assets/css/shared/reset.css">
        <link rel="stylesheet" href="/assets/css/shared/color.css">
        <link rel="stylesheet" href="/assets/css/shared/page.css">
        <link rel="stylesheet" href="/assets/css/shared/form.css">
        <link rel="stylesheet" href="/assets/css/shared/table.css">';
$variants = [
    $linkBlock,
    str_replace('        ', '    ', $linkBlock),
    str_replace(["\n        ", "\n    "], ["\n    ", "\n    "], $linkBlock),
];

$count = 0;
foreach ($rii as $f) {
    if (!$f->isFile() || $f->getExtension() !== 'php') {
        continue;
    }
    $path = $f->getPathname();
    if (str_contains($path, DIRECTORY_SEPARATOR . 'errors' . DIRECTORY_SEPARATOR)) {
        continue;
    }
    $bn = basename($path);
    if (in_array($bn, ['_internal_head_styles.php', '_theme_init.php', '_theme_script.php'], true)) {
        continue;
    }
    $c = file_get_contents($path);
    if (strpos($c, '/assets/css/shared/table.css') === false) {
        continue;
    }
    $orig = $c;
    $c = preg_replace($patterns[0]['from'], $patterns[0]['to'], $c);
    foreach ($variants as $vb) {
        if (strpos($c, $vb) !== false) {
            $c = str_replace($vb, '        ' . $includeLine . "\n", $c);
            break;
        }
    }
    // 4-space indent variant for partes-instituicoes
    $vb4 = str_replace('        <link', '    <link', $linkBlock);
    if ($c === $orig || (strpos($orig, $vb4) !== false && strpos($c, $includeLine) === false)) {
        $c = $orig;
        $c = preg_replace($patterns[0]['from'], '', $c);
        $c = str_replace($vb4, '    ' . $includeLine . "\n", $c);
    }
    if ($c !== $orig && strpos($c, $includeLine) !== false) {
        file_put_contents($path, $c);
        echo $path . "\n";
        $count++;
    }
}
echo "Updated: $count\n";
