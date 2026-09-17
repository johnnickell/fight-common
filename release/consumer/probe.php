<?php

declare(strict_types=1);

require __DIR__.'/functions.php';

$arguments = $_SERVER['argv'] ?? [];
if (count($arguments) !== 2 || !is_file($arguments[1])) {
    fwrite(STDERR, "Usage: probe.php <installed-autoload.php>\n");
    exit(2);
}

exit(certification_consumer_probe($arguments[1]) ? 0 : 1);
