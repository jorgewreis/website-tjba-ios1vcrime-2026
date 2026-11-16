<?php
declare(strict_types=1);

$socket = @fsockopen('app', 8080, $errno, $error, 3);
if ($socket === false) {
    fwrite(STDERR, "app indisponível: $errno $error\n");
    exit(1);
}

fclose($socket);
exit(0);
