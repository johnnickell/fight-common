<?php

declare(strict_types=1);

use RuntimeException;
use DateTimeImmutable;

$combine = static function (
    string $left,
    string $right,
): string {
    return sprintf('%s%s', $left, $right,);
};

$values = [
    'short'=> 1,
    'longer_key' => 2,
];

$value = 1;
return $value;
