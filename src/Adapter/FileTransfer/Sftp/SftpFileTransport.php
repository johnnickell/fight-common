<?php

declare(strict_types=1);

namespace Fight\Common\Adapter\FileTransfer\Sftp;

use DateTimeImmutable;
use Fight\Common\Application\FileTransfer\Exception\FileTransferException;
use Fight\Common\Application\FileTransfer\Resource\Resource;
use Fight\Common\Application\FileTransfer\Resource\ResourceType;
use Fight\Common\Application\FileTransfer\Transport\FileTransport;
use phpseclib3\Net\SFTP;
use Throwable;

/**
 * Class SftpFileTransport
 */
final readonly class SftpFileTransport implements FileTransport
{
    /**
     * Constructs SftpFileTransport
     */
    public function __construct(private SFTP $sftp)
    {
    }

    /**
     * @inheritDoc
     */
    public function sendFile(string $path, mixed $contents): void
    {
        try {
            if (!$this->sftp->put($path, $contents)) {
                throw new FileTransferException(sprintf('Unable to send file to path: %s', $path));
            }
        } catch (FileTransferException $e) {
            throw $e;
        } catch (Throwable $e) {
            throw new FileTransferException($e->getMessage(), (int) $e->getCode(), $e);
        }
    }

    /**
     * @inheritDoc
     */
    public function retrieveFileContents(string $path): string
    {
        try {
            $contents = $this->sftp->get($path);

            if ($contents === false) {
                throw new FileTransferException(sprintf('Unable to retrieve file at path: %s', $path));
            }

            return $contents;
        } catch (FileTransferException $e) {
            throw $e;
        } catch (Throwable $e) {
            throw new FileTransferException($e->getMessage(), (int) $e->getCode(), $e);
        }
    }

    /**
     * @inheritDoc
     */
    public function retrieveFileResource(string $path): mixed
    {
        $contents = $this->retrieveFileContents($path);

        $stream = fopen('php://memory', 'rb+');
        fwrite($stream, $contents);
        rewind($stream);

        return $stream;
    }

    /**
     * @inheritDoc
     */
    public function readDirectory(string $directory): iterable
    {
        try {
            $list = $this->sftp->rawlist($directory);

            if ($list === false) {
                throw new FileTransferException(
                    sprintf('Unable to read directory: %s', $directory)
                );
            }

            foreach ($list as $name => $data) {
                if ($name === '.') {
                    continue;
                }
                if ($name === '..') {
                    continue;
                }
                $resourceType = match ((int) $data['type']) {
                    1 => ResourceType::FILE,
                    2 => ResourceType::DIR,
                    3 => ResourceType::LINK,
                    default => ResourceType::UNKNOWN,
                };

                yield new Resource(
                    sprintf('%s/%s', rtrim($directory, '/'), $name),
                    (int) $data['size'],
                    (int) $data['uid'],
                    (int) $data['gid'],
                    (int) $data['mode'],
                    new DateTimeImmutable('@'.(int) $data['atime']),
                    new DateTimeImmutable('@'.(int) $data['mtime']),
                    $resourceType
                );
            }
        } catch (FileTransferException $e) {
            throw $e;
        } catch (Throwable $e) {
            throw new FileTransferException($e->getMessage(), (int) $e->getCode(), $e);
        }
    }
}
