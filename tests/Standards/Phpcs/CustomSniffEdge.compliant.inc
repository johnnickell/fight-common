<?php

declare(strict_types=1);

$legacy = array(
    'short'      => 1,
    'longer_key' => array(
        'nested'      => 1,
        'nested_long' => 2
    )
);

$short = [
    'short'      => 1,
    'longer_key' => [
        'nested'      => 1,
        'nested_long' => 2
    ]
];

$compact = [
    'short'=> 1, 'longer_key'=> 2
];

$indexed = [
    1,
    2
];
