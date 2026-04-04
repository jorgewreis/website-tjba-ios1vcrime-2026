<?php
$root = dirname(__DIR__);
foreach (glob($root . '/public/api/cartorio-*/cancel.php') as $f) {
    echo $f, PHP_EOL;
    $rel = str_replace($root . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'api' . DIRECTORY_SEPARATOR, '', $f);
    echo ' rel=', $rel, PHP_EOL;
    $parts = explode(DIRECTORY_SEPARATOR, $rel);
    echo ' folder=', ($parts[0] ?? '?'), PHP_EOL;
}
