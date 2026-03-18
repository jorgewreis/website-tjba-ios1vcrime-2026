<?php
$root = realpath(__DIR__ . '/..');
$skip = ['.git', 'node_modules', 'vendor', 'mcps'];
$ext = ['php', 'html', 'htm', 'js', 'css'];

$map = [
    'width-800' => 'width-800',
    'width-600' => 'width-600',
    'width-400' => 'width-400',
    'width-200' => 'width-200',
    'width-100' => 'width-100',
];

$it = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($root, RecursiveDirectoryIterator::SKIP_DOTS),
    RecursiveIteratorIterator::SELF_FIRST
);

foreach ($it as $f) {
    if (!$f->isFile()) {
        continue;
    }
    $path = $f->getPathname();
    $rel = str_replace('\\', '/', substr($path, strlen($root) + 1));
    foreach ($skip as $s) {
        if (str_contains($rel, $s)) {
            continue 2;
        }
    }
    if (str_ends_with($rel, 'public/assets/css/shared/layout.css')) {
        continue;
    }
    $e = strtolower($f->getExtension());
    if (!in_array($e, $ext, true)) {
        continue;
    }
    $c = file_get_contents($path);
    $o = $c;
    foreach ($map as $from => $to) {
        $c = str_replace($from, $to, $c);
    }
    if ($c !== $o) {
        file_put_contents($path, $c);
        echo $rel . "\n";
    }
}
echo "OK\n";
