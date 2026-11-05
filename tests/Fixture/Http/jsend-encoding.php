<?php

declare(strict_types=1);

namespace Fight\Common\Application\Http\JSend;

use Fight\Test\Common\Adapter\Http\Laravel\SafeLaravelExceptionHandlerTest;
use JsonException;

// Loaded only in isolated processes, before the first envelope is encoded.
function json_encode(mixed $value, int $flags = 0, int $depth = 512): string|false
{
    SafeLaravelExceptionHandlerTest::$encodingCalls++;
    if (SafeLaravelExceptionHandlerTest::$encodingFailures !== 0) {
        if (SafeLaravelExceptionHandlerTest::$encodingFailures !== null) {
            SafeLaravelExceptionHandlerTest::$encodingFailures--;
        }
        throw new JsonException('encoder=password=synthetic-secret');
    }

    return \json_encode($value, $flags, $depth);
}
