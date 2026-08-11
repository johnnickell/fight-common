<?php

declare(strict_types=1);

namespace Fight\Test\Common\Adapter\FileTransfer\Ftp {
    use stdClass;

    /**
     * Controls namespaced FTP functions for deterministic adapter tests
     */
    final class FtpFunctionOverrides
    {
        /** @var array<string, list<mixed>> */
        private static array $results = [];
        /** @var list<array{function: string, arguments: list<mixed>}> */
        private static array $calls = [];
        /** @var array<string, true> */
        private static array $directories = [];
        /** @var array<string, string> */
        private static array $remoteFiles = [];
        /** @var array<string, string> */
        private static array $uploads = [];
        /** @var list<array<string, string|int>> */
        private static array $directoryEntries = [];
        private static string $workingDirectory = '/';
        private static object $connection;
        private static bool $enabled = false;

        /**
         * Enables simulated FTP behavior
         */
        public static function enable(): void
        {
            self::$enabled = true;
        }

        /**
         * Disables simulated FTP behavior
         */
        public static function disable(): void
        {
            self::$enabled = false;
        }

        /**
         * Checks whether simulated FTP behavior is enabled
         */
        public static function isEnabled(): bool
        {
            return self::$enabled;
        }

        /**
         * Resets the simulated FTP server
         */
        public static function reset(): void
        {
            self::$results = [];
            self::$calls = [];
            self::$directories = ['/' => true];
            self::$remoteFiles = [];
            self::$uploads = [];
            self::$directoryEntries = [];
            self::$workingDirectory = '/';
            self::$connection = new stdClass();
        }

        /**
         * Queues exact results for a namespaced function
         */
        public static function queue(string $function, mixed ...$results): void
        {
            self::$results[self::shortName($function)] = $results;
        }

        /**
         * Returns the next queued result or a default
         */
        public static function next(string $function, mixed $default): mixed
        {
            $function = self::shortName($function);
            if ((self::$results[$function] ?? []) === []) {
                return $default;
            }

            return array_shift(self::$results[$function]);
        }

        /**
         * Records a namespaced function call
         *
         * @param list<mixed> $arguments
         */
        public static function record(string $function, array $arguments): void
        {
            self::$calls[] = [
                'function' => self::shortName($function),
                'arguments' => $arguments
            ];
        }

        /**
         * Returns recorded arguments for one function
         *
         * @return list<list<mixed>>
         */
        public static function calls(string $function): array
        {
            $function = self::shortName($function);
            $calls = [];
            foreach (self::$calls as $call) {
                if ($call['function'] === $function) {
                    $calls[] = $call['arguments'];
                }
            }

            return $calls;
        }

        /**
         * Returns the simulated connection
         */
        public static function connection(): object
        {
            return self::$connection;
        }

        /**
         * Adds a directory to the simulated server
         */
        public static function addDirectory(string $path): void
        {
            self::$directories[self::absolute($path)] = true;
        }

        /**
         * Changes the simulated working directory
         */
        public static function changeDirectory(string $path): bool
        {
            self::record('ftp_chdir', [self::$connection, $path]);

            $absolute = self::absolute($path);
            $default = isset(self::$directories[$absolute]);
            $success = (bool) self::next('ftp_chdir', $default);
            if ($success) {
                self::$workingDirectory = $absolute;
            }

            return $success;
        }

        /**
         * Creates a directory on the simulated server
         */
        public static function makeDirectory(string $path): string|false
        {
            self::record('ftp_mkdir', [self::$connection, $path]);

            $absolute = self::absolute($path);
            $result = self::next('ftp_mkdir', $absolute);
            if ($result !== false) {
                self::$directories[$absolute] = true;
            }

            return $result;
        }

        /**
         * Returns the current simulated working directory
         */
        public static function workingDirectory(): string|false
        {
            self::record('ftp_pwd', [self::$connection]);

            return self::next('ftp_pwd', self::$workingDirectory);
        }

        /**
         * Adds remote file contents
         */
        public static function addRemoteFile(string $path, string $contents): void
        {
            self::$remoteFiles[$path] = $contents;
        }

        /**
         * Returns remote file contents
         */
        public static function remoteFile(string $path): string
        {
            return self::$remoteFiles[$path] ?? '';
        }

        /**
         * Records uploaded contents
         */
        public static function upload(string $path, string $contents): void
        {
            self::$uploads[$path] = $contents;
        }

        /**
         * Returns uploaded contents
         */
        public static function uploaded(string $path): ?string
        {
            return self::$uploads[$path] ?? null;
        }

        /**
         * Sets MLSD directory entries
         *
         * @param list<array<string, string|int>> $entries
         */
        public static function setDirectoryEntries(array $entries): void
        {
            self::$directoryEntries = $entries;
        }

        /**
         * Returns MLSD directory entries
         *
         * @return list<array<string, string|int>>
         */
        public static function directoryEntries(): array
        {
            return self::$directoryEntries;
        }

        /**
         * Resolves a path against the simulated working directory
         */
        private static function absolute(string $path): string
        {
            if (str_starts_with($path, '/')) {
                return rtrim($path, '/') ?: '/';
            }

            return rtrim(self::$workingDirectory, '/').'/'.$path;
        }

        /**
         * Removes a namespace from a function name
         */
        private static function shortName(string $function): string
        {
            $position = strrpos($function, '\\');

            return $position === false ? $function : substr($function, $position + 1);
        }
    }

    FtpFunctionOverrides::reset();
}

namespace {
    if (!defined('FTP_BINARY')) {
        define('FTP_BINARY', 2);
    }
}

namespace Fight\Common\Adapter\FileTransfer\Ftp {
    use Fight\Test\Common\Adapter\FileTransfer\Ftp\FtpFunctionOverrides;

    function ftp_connect(string $host, int $port = 21, int $timeout = 90): mixed
    {
        if (!FtpFunctionOverrides::isEnabled()) {
            return \ftp_connect($host, $port, $timeout);
        }

        FtpFunctionOverrides::record(__FUNCTION__, [$host, $port, $timeout]);

        return FtpFunctionOverrides::next(__FUNCTION__, FtpFunctionOverrides::connection());
    }

    function ftp_ssl_connect(string $host, int $port = 21, int $timeout = 90): mixed
    {
        if (!FtpFunctionOverrides::isEnabled()) {
            return \ftp_ssl_connect($host, $port, $timeout);
        }

        FtpFunctionOverrides::record(__FUNCTION__, [$host, $port, $timeout]);

        return FtpFunctionOverrides::next(__FUNCTION__, FtpFunctionOverrides::connection());
    }

    function ftp_login(mixed $connection, string $username, string $password): bool
    {
        if (!FtpFunctionOverrides::isEnabled()) {
            return \ftp_login($connection, $username, $password);
        }

        FtpFunctionOverrides::record(__FUNCTION__, [$connection, $username, $password]);

        return (bool) FtpFunctionOverrides::next(__FUNCTION__, true);
    }

    function ftp_pasv(mixed $connection, bool $enable): bool
    {
        if (!FtpFunctionOverrides::isEnabled()) {
            return \ftp_pasv($connection, $enable);
        }

        FtpFunctionOverrides::record(__FUNCTION__, [$connection, $enable]);

        return (bool) FtpFunctionOverrides::next(__FUNCTION__, true);
    }

    function ftp_close(mixed $connection): bool
    {
        if (!FtpFunctionOverrides::isEnabled()) {
            return \ftp_close($connection);
        }

        FtpFunctionOverrides::record(__FUNCTION__, [$connection]);

        return (bool) FtpFunctionOverrides::next(__FUNCTION__, true);
    }

    function ftp_fput(
        mixed $connection,
        string $remoteFilename,
        mixed $stream,
        int $mode = FTP_BINARY,
        int $offset = 0
    ): bool {
        if (!FtpFunctionOverrides::isEnabled()) {
            return \ftp_fput($connection, $remoteFilename, $stream, $mode, $offset);
        }

        FtpFunctionOverrides::record(
            __FUNCTION__,
            [$connection, $remoteFilename, $stream, $mode, $offset]
        );

        $contents = stream_get_contents($stream);
        FtpFunctionOverrides::upload($remoteFilename, $contents === false ? '' : $contents);

        return (bool) FtpFunctionOverrides::next(__FUNCTION__, true);
    }

    function ftp_fget(
        mixed $connection,
        mixed $stream,
        string $remoteFilename,
        int $mode = FTP_BINARY,
        int $offset = 0
    ): bool {
        if (!FtpFunctionOverrides::isEnabled()) {
            return \ftp_fget($connection, $stream, $remoteFilename, $mode, $offset);
        }

        FtpFunctionOverrides::record(
            __FUNCTION__,
            [$connection, $stream, $remoteFilename, $mode, $offset]
        );

        $success = (bool) FtpFunctionOverrides::next(__FUNCTION__, true);
        if ($success) {
            fwrite($stream, FtpFunctionOverrides::remoteFile($remoteFilename));
        }

        return $success;
    }

    function ftp_pwd(mixed $connection): string|false
    {
        if (!FtpFunctionOverrides::isEnabled()) {
            return \ftp_pwd($connection);
        }

        return FtpFunctionOverrides::workingDirectory();
    }

    function ftp_chdir(mixed $connection, string $directory): bool
    {
        if (!FtpFunctionOverrides::isEnabled()) {
            return \ftp_chdir($connection, $directory);
        }

        return FtpFunctionOverrides::changeDirectory($directory);
    }

    function ftp_mkdir(mixed $connection, string $directory): string|false
    {
        if (!FtpFunctionOverrides::isEnabled()) {
            return \ftp_mkdir($connection, $directory);
        }

        return FtpFunctionOverrides::makeDirectory($directory);
    }

    function ftp_mlsd(mixed $connection, string $directory): array|false
    {
        if (!FtpFunctionOverrides::isEnabled()) {
            return \ftp_mlsd($connection, $directory);
        }

        FtpFunctionOverrides::record(__FUNCTION__, [$connection, $directory]);

        return FtpFunctionOverrides::next(__FUNCTION__, FtpFunctionOverrides::directoryEntries());
    }

    function stream_get_contents(mixed $stream, ?int $length = null, int $offset = -1): string|false
    {
        if (!FtpFunctionOverrides::isEnabled()) {
            return \stream_get_contents($stream, $length, $offset);
        }

        return FtpFunctionOverrides::next(
            __FUNCTION__,
            \stream_get_contents($stream, $length, $offset)
        );
    }
}
