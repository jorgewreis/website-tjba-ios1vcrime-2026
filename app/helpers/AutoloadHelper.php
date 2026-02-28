<?php
declare(strict_types=1);

/**
 * Registro simples de autoload para helpers e services sem namespace.
 * Permite incluir arquivos pela convenção de nome da classe (Helper, ClasseManager, etc.).
 */
spl_autoload_register(static function (string $class): void {
    $directories = [
        __DIR__ . '/',
        __DIR__ . '/../services/',
        __DIR__ . '/../repositories/',
        __DIR__ . '/../rules/',
    ];

    $file = str_replace('\\', '/', $class) . '.php';

    foreach ($directories as $directory) {
        $candidate = $directory . $file;
        if (is_file($candidate)) {
            require_once $candidate;
            return;
        }
    }
});
