<?php

declare(strict_types=1);

namespace Fight\Common\Adapter\FileTransfer\Logging;

use Fight\Common\Application\FileTransfer\Transport\FileTransport;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;

/**
 * Class LoggingFileTransport
 */
final readonly class LoggingFileTransport implements FileTransport
{
    private FileTransport $fileTransport;

    /**
     * Constructs LoggingFileTransport
     */
    public function __construct(
        FileTransport $transport,
        private LoggerInterface $logger,
        private string $logLevel = LogLevel::DEBUG
    ) {
        $this->fileTransport = $transport;
    }

    /**
     * @inheritDoc
     */
    public function sendFile(string $path, mixed $contents): void
    {
        $this->logger->log($this->logLevel, '[FileTransfer]: Sending file', [
            'path' => $path
        ]);

        $this->fileTransport->sendFile($path, $contents);
    }

    /**
     * @inheritDoc
     */
    public function retrieveFileContents(string $path): string
    {
        $this->logger->log($this->logLevel, '[FileTransfer]: Retrieving file contents', [
            'path' => $path
        ]);

        return $this->fileTransport->retrieveFileContents($path);
    }

    /**
     * @inheritDoc
     */
    public function retrieveFileResource(string $path): mixed
    {
        $this->logger->log($this->logLevel, '[FileTransfer]: Retrieving file resource', [
            'path' => $path
        ]);

        return $this->fileTransport->retrieveFileResource($path);
    }

    /**
     * @inheritDoc
     */
    public function readDirectory(string $directory): iterable
    {
        $this->logger->log($this->logLevel, '[FileTransfer]: Reading directory', [
            'path' => $directory
        ]);

        return $this->fileTransport->readDirectory($directory);
    }
}
