<?php

declare(strict_types=1);

namespace Fight\Test\Common\Application\Scheduler {
    final class LockLifecycleFunctionController
    {
        private static ?string $controlledPath = null;
        private static bool $failCreate = false;
        private static bool $failOpen = false;

        public static function control(string $path, bool $failCreate = false, bool $failOpen = false): void
        {
            self::$controlledPath = $path;
            self::$failCreate = $failCreate;
            self::$failOpen = $failOpen;
        }

        public static function reset(): void
        {
            self::$controlledPath = null;
            self::$failCreate = false;
            self::$failOpen = false;
        }

        public static function touch(string $filename, ?int $mtime = null, ?int $atime = null): bool
        {
            if ($filename === self::$controlledPath && self::$failCreate) {
                return false;
            }

            if ($mtime === null) {
                return \touch($filename);
            }

            return \touch($filename, $mtime, $atime);
        }

        public static function open(string $filename, string $mode): mixed
        {
            if ($filename === self::$controlledPath && self::$failOpen) {
                return false;
            }

            return \fopen($filename, $mode);
        }
    }
}

namespace Fight\Common\Application\Scheduler {
    use Fight\Test\Common\Application\Scheduler\LockLifecycleFunctionController;

    function touch(string $filename, ?int $mtime = null, ?int $atime = null): bool
    {
        return LockLifecycleFunctionController::touch($filename, $mtime, $atime);
    }

    function fopen(string $filename, string $mode): mixed
    {
        return LockLifecycleFunctionController::open($filename, $mode);
    }
}
