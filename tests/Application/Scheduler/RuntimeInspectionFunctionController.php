<?php

declare(strict_types=1);

namespace Fight\Test\Common\Application\Scheduler {
    use Throwable;

    final class RuntimeInspectionFunctionController
    {
        private static ?string $controlledPath = null;
        private static ?string $inspectedPath = null;
        private static ?bool $processActive = null;
        private static ?int $currentTime = null;
        private static ?Throwable $readFailure = null;
        private static ?bool $classExists = null;

        public static function control(
            string $path,
            ?bool $processActive = null,
            ?int $currentTime = null,
            ?Throwable $readFailure = null,
            ?bool $classExists = null
        ): void {
            self::$controlledPath = $path;
            self::$processActive = $processActive;
            self::$currentTime = $currentTime;
            self::$readFailure = $readFailure;
            self::$classExists = $classExists;
        }

        public static function reset(): void
        {
            self::$controlledPath = null;
            self::$inspectedPath = null;
            self::$processActive = null;
            self::$currentTime = null;
            self::$readFailure = null;
            self::$classExists = null;
        }

        public static function read(string $path): string|false
        {
            if ($path !== self::$controlledPath) {
                return \file_get_contents($path);
            }

            self::$inspectedPath = $path;

            if (self::$readFailure instanceof Throwable) {
                throw self::$readFailure;
            }

            return \file_get_contents($path);
        }

        public static function processIsActive(int $pid, int $signal): bool
        {
            if (self::$inspectedPath === self::$controlledPath && self::$processActive !== null) {
                return self::$processActive;
            }

            return \posix_kill($pid, $signal);
        }

        public static function time(): int
        {
            if (self::$inspectedPath === self::$controlledPath && self::$currentTime !== null) {
                return self::$currentTime;
            }

            return \time();
        }

        public static function classExists(string $class, bool $autoload): bool
        {
            if (self::$classExists !== null) {
                return self::$classExists;
            }

            return \class_exists($class, $autoload);
        }
    }
}

namespace Fight\Common\Application\Scheduler {
    use Fight\Test\Common\Application\Scheduler\RuntimeInspectionFunctionController;

    function file_get_contents(string $filename): string|false
    {
        return RuntimeInspectionFunctionController::read($filename);
    }

    function posix_kill(int $processId, int $signal): bool
    {
        return RuntimeInspectionFunctionController::processIsActive($processId, $signal);
    }

    function time(): int
    {
        return RuntimeInspectionFunctionController::time();
    }

    function class_exists(string $class, bool $autoload = true): bool
    {
        return RuntimeInspectionFunctionController::classExists($class, $autoload);
    }
}
