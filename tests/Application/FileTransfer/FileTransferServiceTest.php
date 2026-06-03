<?php

declare(strict_types=1);

namespace Fight\Test\Common\Application\FileTransfer;

use Fight\Common\Application\FileTransfer\Exception\FileTransferException;
use Fight\Common\Application\FileTransfer\FileTransferService;
use Fight\Common\Application\FileTransfer\Transport\FileTransport;
use Fight\Common\Domain\Exception\KeyException;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(FileTransferService::class)]
class FileTransferServiceTest extends UnitTestCase
{
    public function test_that_transport_can_be_added_and_retrieved(): void
    {
        $transport = $this->mock(FileTransport::class);
        $service   = new FileTransferService();

        $service->addTransport('sftp', $transport);

        self::assertSame($transport, $service->getTransport('sftp'));
    }

    public function test_that_multiple_transports_can_be_added(): void
    {
        $sftp = $this->mock(FileTransport::class);
        $ftp  = $this->mock(FileTransport::class);
        $service = new FileTransferService();

        $service->addTransport('sftp', $sftp);
        $service->addTransport('ftp', $ftp);

        self::assertSame($sftp, $service->getTransport('sftp'));
        self::assertSame($ftp, $service->getTransport('ftp'));
    }

    public function test_that_get_transport_throws_key_exception_when_not_found(): void
    {
        $service = new FileTransferService();

        self::expectException(KeyException::class);

        $service->getTransport('missing');
    }

    public function test_that_add_transport_throws_file_transfer_exception_on_duplicate_key(): void
    {
        $transport = $this->mock(FileTransport::class);
        $service   = new FileTransferService();

        $service->addTransport('sftp', $transport);

        self::expectException(FileTransferException::class);

        $service->addTransport('sftp', $transport);
    }
}
