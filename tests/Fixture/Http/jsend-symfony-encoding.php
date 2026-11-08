<?php

declare(strict_types=1);

namespace Fight\Common\Application\Http\JSend;

use Fight\Test\Common\Adapter\Http\Symfony\SafeSymfonyErrorTest;
use JsonException;

// Loaded only in isolated processes, before the first envelope is encoded.
function json_encode(mixed $value, int $flags = 0, int $depth = 512): string|false
{
    SafeSymfonyErrorTest::$encodingCalls++;
    if (SafeSymfonyErrorTest::$encodingFailures !== 0) {
        if (SafeSymfonyErrorTest::$encodingFailures !== null) {
            SafeSymfonyErrorTest::$encodingFailures--;
        }
        throw new JsonException('encoder=password=synthetic-secret');
    }

    return \json_encode($value, $flags, $depth);
}
