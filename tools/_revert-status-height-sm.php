<?php
/** Remove height-sm from .status badges exceto em cartorio-*-list.php (subtarefas mantidas manualmente). */
$root = realpath(__DIR__ . '/../app/views/internal');
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root));
foreach ($it as $f) {
    if (!$f->isFile() || $f->getExtension() !== 'php') {
        continue;
    }
    $base = $f->getBasename();
    if (preg_match('/^cartorio-.+-list\.php$/', $base)) {
        continue;
    }
    $p = $f->getPathname();
    $c = file_get_contents($p);
    $o = $c;
    $pairs = [
        ['class="height-sm status status-finished padding-lr-lg"', 'class="status status-finished padding-lr-lg"'],
        ['class="height-sm status status-cancelled padding-lr-lg"', 'class="status status-cancelled padding-lr-lg"'],
        ['class="height-sm status status-primary-light padding-lr-lg"', 'class="status status-primary-light padding-lr-lg"'],
        ['class="height-sm status status-', 'class="status status-'],
        ['class="height-sm status status-placeholder', 'class="status status-placeholder'],
        ["feedback.innerHTML = '<label class=\"height-sm status ", "feedback.innerHTML = '<label class=\"status "],
        ['feedback.innerHTML = `<label class="height-sm status ', 'feedback.innerHTML = `<label class="status '],
        ['class="padding-lr-lg height-sm status ', 'class="padding-lr-lg status '],
        ['<p class="height-sm status ', '<p class="status '],
        ['<span class="height-sm status ', '<span class="status '],
    ];
    foreach ($pairs as $pair) {
        $c = str_replace($pair[0], $pair[1], $c);
    }
    if ($c !== $o) {
        file_put_contents($p, $c);
        echo $p . "\n";
    }
}
