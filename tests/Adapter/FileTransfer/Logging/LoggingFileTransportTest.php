<?php

declare(strict_types=1);

namespace Fight\Test\Common\Adapter\FileTransfer\Logging;

use Fight\Common\Adapter\FileTransfer\Logging\LoggingFileTransport;
use Fight\Common\Application\FileTransfer\Transport\FileTransport;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;

#[CoversClass(LoggingFileTransport::class)]
class LoggingFileTransportTest extends UnitTestCase
{
    public function test_that_send_file_logs_then_delegates(): void
    {
        $inner  = $this->mock(FileTransport::class);
        $logger = $this->mock(LoggerInterface::class);

        $inner->shouldReceive('sendFile')->once()->with('/path/file.txt', 'data');
        $logger->shouldReceive('log')->once()->with(LogLevel::DEBUG, '[FileTransfer]: Sending file', ['path' => '/path/file.txt']);

        $transport = new LoggingFileTransport($inner, $logger);
        $transport->sendFile('/path/file.txt', 'data');
    }

    public function test_that_retrieve_file_contents_logs_then_delegates(): void
    {
        $inner  = $this->mock(FileTransport::class);
        $logger = $this->mock(LoggerInterface::class);

        $inner->shouldReceive('retrieveFileContents')->once()->with('/path/file.txt')->andReturn('content');
        $logger->shouldReceive('log')->once()->with(LogLevel::DEBUG, '[FileTransfer]: Retrieving file contents', ['path' => '/path/file.txt']);

        $transport = new LoggingFileTransport($inner, $logger);

        self::assertSame('content', $transport->retrieveFileContents('/path/file.txt'));
    }

    public function test_that_retrieve_file_resource_logs_then_delegates(): void
    {
        $inner    = $this->mock(FileTransport::class);
        $logger   = $this->mock(LoggerInterface::class);
        $resource = fopen('php://memory', 'r');

        $inner->shouldReceive('retrieveFileResource')->once()->with('/path/file.txt')->andReturn($resource);
        $logger->shouldReceive('log')->once()->with(LogLevel::DEBUG, '[FileTransfer]: Retrieving file resource', ['path' => '/path/file.txt']);

        $transport = new LoggingFileTransport($inner, $logger);

        self::assertSame($resource, $transport->retrieveFileResource('/path/file.txt'));
    }

    public function test_that_read_directory_logs_then_delegates(): void
    {
        $inner  = $this->mock(FileTransport::class);
        $logger = $this->mock(LoggerInterface::class);

        $inner->shouldReceive('readDirectory')->once()->with('/path/dir')->andReturn([]);
        $logger->shouldReceive('log')->once()->with(LogLevel::DEBUG, '[FileTransfer]: Reading directory', ['path' => '/path/dir']);

        $transport = new LoggingFileTransport($inner, $logger);

        self::assertSame([], $transport->readDirectory('/path/dir'));
    }

    public function test_that_custom_log_level_is_used(): void
    {
        $inner  = $this->mock(FileTransport::class);
        $logger = $this->mock(LoggerInterface::class);

        $inner->shouldReceive('sendFile')->once();
        $logger->shouldReceive('log')->once()->with(LogLevel::INFO, \Mockery::any(), \Mockery::any());

        $transport = new LoggingFileTransport($inner, $logger, LogLevel::INFO);
        $transport->sendFile('/path/file.txt', 'data');
    }
}
