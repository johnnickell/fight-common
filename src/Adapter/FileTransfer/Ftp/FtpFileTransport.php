<?php

declare(strict_types=1);

namespace Fight\Common\Adapter\FileTransfer\Ftp;

use DateTimeImmutable;
use Fight\Common\Application\FileTransfer\Exception\FileTransferException;
use Fight\Common\Application\FileTransfer\Resource\Resource;
use Fight\Common\Application\FileTransfer\Resource\ResourceType;
use Fight\Common\Application\FileTransfer\Transport\FileTransport;

/**
 * Class FtpFileTransport
 *
 * Requires the PHP FTP extension and libssl for SSL connections.
 *
 * @codeCoverageIgnore Requires FTP connection to test
 */
final class FtpFileTransport implements FileTransport
{
    private mixed $connection = null;

    /**
     * Constructs FtpFileTransport
     */
    public function __construct(
        private readonly string $host,
        private readonly int $port,
        private readonly string $username = 'anonymous',
        private readonly string $password = '',
        private readonly bool $ssl = false,
        private readonly int $timeout = 90,
        private readonly bool $passive = false
    ) {
    }

    /**
     * @inheritDoc
     */
    public function sendFile(string $path, mixed $contents): void
    {
        $this->connect();

        if (is_string($contents)) {
            $stream = fopen('php://temp', 'rb+');
            if ($contents !== '') {
                fwrite($stream, $contents);
                fseek($stream, 0);
            }

            $contents = $stream;
        }

        $dir = dirname($path);
        if (!$this->isDirectory($dir)) {
            $this->makeDirectory($dir);
        }

        $success = @ftp_fput($this->connection, $path, $contents, FTP_BINARY);

        fclose($contents);

        if (!$success) {
            $this->disconnect();
            throw new FileTransferException(sprintf('Unable to send file to path: %s', $path));
        }

        $this->disconnect();
    }

    /**
     * @inheritDoc
     */
    public function retrieveFileContents(string $path): string
    {
        $this->connect();

        $handle = fopen('php://temp', 'rb+');

        $success = @ftp_fget($this->connection, $handle, $path, FTP_BINARY);

        if (!$success) {
            $this->disconnect();
            throw new FileTransferException(sprintf('Unable to retrieve file at path: %s', $path));
        }

        rewind($handle);
        $contents = stream_get_contents($handle);
        fclose($handle);

        if ($contents === false) {
            $this->disconnect();
            throw new FileTransferException(sprintf('Unable to read file contents at path: %s', $path));
        }

        $this->disconnect();

        return $contents;
    }

    /**
     * @inheritDoc
     */
    public function retrieveFileResource(string $path): mixed
    {
        $this->connect();

        $handle = tmpfile();

        $success = @ftp_fget($this->connection, $handle, $path, FTP_BINARY);

        if (!$success) {
            $this->disconnect();
            throw new FileTransferException(sprintf('Unable to retrieve file at path: %s', $path));
        }

        rewind($handle);

        $this->disconnect();

        return $handle;
    }

    /**
     * @inheritDoc
     */
    public function readDirectory(string $directory): iterable
    {
        $this->connect();

        $directory = rtrim($directory, '/');

        if (!$this->isDirectory($directory)) {
            $this->disconnect();
            throw new FileTransferException(sprintf('Directory does not exist: %s', $directory));
        }

        $entries = @ftp_mlsd($this->connection, $directory);

        if ($entries === false) {
            $this->disconnect();
            throw new FileTransferException(sprintf('Unable to read directory: %s', $directory));
        }

        foreach ($entries as $entry) {
            $name = $entry['name'] ?? '';
            if ($name === '.') {
                continue;
            }

            if ($name === '..') {
                continue;
            }

            $resourceType = match ($entry['type'] ?? '') {
                'file'                   => ResourceType::FILE,
                'dir', 'cdir', 'pdir'   => ResourceType::DIR,
                'link'                  => ResourceType::LINK,
                default                 => ResourceType::UNKNOWN,
            };

            $size    = (int) ($entry['size'] ?? 0);
            $rawMode = isset($entry['unix.mode']) ? (int) $entry['unix.mode'] : 0;
            $modify  = $entry['modify'] ?? '';
            $dt      = new DateTimeImmutable();
            if ($modify !== '') {
                $parsed = DateTimeImmutable::createFromFormat('YmdHis', $modify);
                if ($parsed !== false) {
                    $dt = $parsed;
                }
            }

            yield new Resource(
                sprintf('%s/%s', $directory, $name),
                $size,
                0,
                0,
                $rawMode,
                $dt,
                $dt,
                $resourceType
            );
        }

        $this->disconnect();
    }

    /**
     * Opens the FTP connection
     *
     * @throws FileTransferException When connection or login fails
     */
    private function connect(): void
    {
        if ($this->connection !== null) {
            return;
        }

        if ($this->ssl) {
            $connection = @ftp_ssl_connect($this->host, $this->port, $this->timeout);
        } else {
            $connection = @ftp_connect($this->host, $this->port, $this->timeout);
        }

        if ($connection === false) {
            throw new FileTransferException(
                sprintf('Unable to connect to host %s on port %d', $this->host, $this->port)
            );
        }

        if (!@ftp_login($connection, $this->username, $this->password)) {
            throw new FileTransferException('FTP authentication failed');
        }

        if ($this->passive && !@ftp_pasv($connection, true)) {
            throw new FileTransferException('Failed to set FTP passive mode');
        }

        $this->connection = $connection;
    }

    /**
     * Ends the FTP connection
     */
    private function disconnect(): void
    {
        if ($this->connection !== null) {
            ftp_close($this->connection);
            $this->connection = null;
        }
    }

    /**
     * Checks whether a path is a directory
     *
     * @throws FileTransferException When current directory cannot be resolved
     */
    private function isDirectory(string $path): bool
    {
        $pwd = @ftp_pwd($this->connection);

        if ($pwd === false) {
            throw new FileTransferException('Unable to resolve the current working directory');
        }

        if (@ftp_chdir($this->connection, $path)) {
            ftp_chdir($this->connection, $pwd);

            return true;
        }

        ftp_chdir($this->connection, $pwd);

        return false;
    }

    /**
     * Creates a directory, recursively by default
     *
     * @throws FileTransferException When directory creation fails
     */
    private function makeDirectory(string $path, bool $recursive = true): void
    {
        if (!$recursive || $this->isDirectory($path)) {
            if (!@ftp_mkdir($this->connection, $path)) {
                throw new FileTransferException(sprintf('Unable to create directory: %s', $path));
            }

            return;
        }

        $pwd   = ftp_pwd($this->connection);
        $parts = explode('/', $path);

        foreach ($parts as $part) {
            if ($part === '') {
                continue;
            }

            if (!@ftp_chdir($this->connection, $part)) {
                if (!@ftp_mkdir($this->connection, $part)) {
                    throw new FileTransferException(sprintf('Unable to create directory: %s', $path));
                }

                ftp_chdir($this->connection, $part);
            }
        }

        ftp_chdir($this->connection, $pwd);
    }

    /**
     * Handles FtpFileTransport destruct
     */
    public function __destruct()
    {
        $this->disconnect();
    }
}
