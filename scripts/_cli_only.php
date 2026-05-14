<?php
declare(strict_types=1);

if (PHP_SAPI === 'cli') {
    return;
}

if (!headers_sent()) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=UTF-8');
}

echo "Not found.\n";
exit(1);
