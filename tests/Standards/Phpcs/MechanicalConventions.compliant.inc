<?php

declare(strict_types=1);

use DateTimeImmutable;
use RuntimeException;

$values = [
    'short'      => 1,
    'longer_key' => 2
];

$left = 'left';
$right = 'right';
$value = (int) $left.$right;

return $value;
