<?php

declare(strict_types=1);

namespace Fight\Test\Common\Fixture\Mcp {
    final class SapiDouble
    {
        public static bool $headersSent = false;
        public static array $headers = [];
        public static int $buffers = 0;
        public static string $compression = '0';
        public static int $flushes = 0;
        public static ?int $abortAfter = null;
        public static bool $ignoreAbort = false;
        public static function reset(): void
        {
            self::$headersSent = false;
            self::$headers = [];
            self::$buffers = 0;
            self::$compression = '0';
            self::$flushes = 0;
            self::$abortAfter = null;
            self::$ignoreAbort = false;
        }
    }
}

// Runtime-function doubles are loaded only by the unit test, never by the live-server fixture.
namespace Fight\Common\Adapter\Http\Mcp {
    use Fight\Test\Common\Fixture\Mcp\SapiDouble;
    function headers_sent(): bool { return SapiDouble::$headersSent; }
    function header(string $value, bool $replace = true): void { SapiDouble::$headers[] = [$value, $replace]; }
    function ob_get_level(): int { return SapiDouble::$buffers; }
    function ini_get(string $name): string { return SapiDouble::$compression; }
    function flush(): void { ++SapiDouble::$flushes; }
    function connection_aborted(): int { return (int) (SapiDouble::$abortAfter !== null && SapiDouble::$flushes >= SapiDouble::$abortAfter); }
    function ignore_user_abort(?bool $enable = null): int
    {
        $previous = SapiDouble::$ignoreAbort;
        if ($enable !== null) { SapiDouble::$ignoreAbort = $enable; }
        return (int) $previous;
    }
}
