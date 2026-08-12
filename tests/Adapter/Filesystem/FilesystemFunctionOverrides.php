<?php

declare(strict_types=1);

namespace Fight\Test\Common\Adapter\Filesystem {
    /**
     * Class FilesystemFunctionOverrides
     */
    final class FilesystemFunctionOverrides
    {
        private static ?string $function = null;
        private static ?string $path = null;

        /**
         * Configures a filesystem function failure
         */
        public static function fail(string $function, string $path): void
        {
            self::$function = $function;
            self::$path = $path;
        }

        /**
         * Clears the configured filesystem function failure
         */
        public static function reset(): void
        {
            self::$function = null;
            self::$path = null;
        }

        /**
         * Determines whether a filesystem function should fail
         */
        public static function shouldFail(string $function, string $path): bool
        {
            return self::$function === $function && self::$path === $path;
        }

        /**
         * Determines whether a path is controlled by the test fixture
         */
        public static function isControlled(string $path): bool
        {
            return self::$path === $path;
        }
    }
}

namespace Fight\Common\Adapter\Filesystem {
    use Fight\Test\Common\Adapter\Filesystem\FilesystemFunctionOverrides;
    use finfo;

    /**
     * Overrides is_file() for a controlled test path
     */
    function is_file(string $filename): bool
    {
        if (FilesystemFunctionOverrides::isControlled($filename)) {
            return true;
        }

        return \is_file($filename);
    }

    /**
     * Overrides filemtime() for a controlled test path
     */
    function filemtime(string $filename): int|false
    {
        if (FilesystemFunctionOverrides::shouldFail(__FUNCTION__, $filename)) {
            return false;
        }

        return \filemtime($filename);
    }

    /**
     * Overrides fileatime() for a controlled test path
     */
    function fileatime(string $filename): int|false
    {
        if (FilesystemFunctionOverrides::shouldFail(__FUNCTION__, $filename)) {
            return false;
        }

        return \fileatime($filename);
    }

    /**
     * Overrides filesize() for a controlled test path
     */
    function filesize(string $filename): int|false
    {
        if (FilesystemFunctionOverrides::shouldFail(__FUNCTION__, $filename)) {
            return false;
        }

        return \filesize($filename);
    }

    /**
     * Overrides finfo_file() for a controlled test path
     */
    function finfo_file(finfo $finfo, string $filename, int $flags = FILEINFO_NONE, mixed $context = null): string|false
    {
        if (FilesystemFunctionOverrides::shouldFail(__FUNCTION__, $filename)) {
            return false;
        }

        return \finfo_file($finfo, $filename, $flags, $context);
    }
}
