<?php
declare(strict_types=1);

/**
 * Raiz da aplicacao PHP (pastas app/, config/, storage/).
 *
 * No servidor com dois niveis de pasta `public` (docroot HTTP em .../public/public/),
 * scripts podem estar em .../public/scripts ou .../public/public/scripts; a raiz PHP
 * permanece .../public (irma do docroot interno).
 *
 * @param string $scriptsDir __DIR__ do script que esta dentro de uma pasta chamada scripts/
 */
function resolveProjectRootFromCliScriptsDir(string $scriptsDir): string
{
    $scriptsDir = rtrim($scriptsDir, "/\\");
    if (basename($scriptsDir) !== 'scripts') {
        return dirname($scriptsDir);
    }

    $parent = dirname($scriptsDir);
    if (basename($parent) === 'public' && basename(dirname($parent)) === 'public') {
        return dirname($parent);
    }

    return $parent;
}
