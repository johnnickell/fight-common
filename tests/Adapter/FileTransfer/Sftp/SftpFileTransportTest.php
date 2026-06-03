<?php

declare(strict_types=1);

namespace Fight\Test\Common\Adapter\FileTransfer\Sftp;

use Fight\Common\Adapter\FileTransfer\Sftp\SftpFileTransport;
use Fight\Common\Application\FileTransfer\Exception\FileTransferException;
use Fight\Common\Application\FileTransfer\Resource\Resource;
use Fight\Common\Application\FileTransfer\Resource\ResourceType;
use Fight\Test\Common\TestCase\UnitTestCase;
use phpseclib3\Net\SFTP;
use PHPUnit\Framework\Attributes\CoversClass;
use RuntimeException;

#[CoversClass(SftpFileTransport::class)]
class SftpFileTransportTest extends UnitTestCase
{
    public function test_that_send_file_delegates_to_sftp(): void
    {
        $sftp = $this->mock(SFTP::class);
        $sftp->shouldReceive('put')->once()->with('/remote/file.txt', 'contents')->andReturn(true);

        $transport = new SftpFileTransport($sftp);
        $transport->sendFile('/remote/file.txt', 'contents');
    }

    public function test_that_send_file_throws_when_put_returns_false(): void
    {
        $sftp = $this->mock(SFTP::class);
        $sftp->shouldReceive('put')->once()->andReturn(false);

        $transport = new SftpFileTransport($sftp);

        self::expectException(FileTransferException::class);
        $transport->sendFile('/remote/file.txt', 'contents');
    }

    public function test_that_send_file_wraps_throwable_as_file_transfer_exception(): void
    {
        $sftp = $this->mock(SFTP::class);
        $sftp->shouldReceive('put')->once()->andThrow(new RuntimeException('Connection lost', 5));

        $transport = new SftpFileTransport($sftp);

        self::expectException(FileTransferException::class);
        self::expectExceptionMessage('Connection lost');
        $transport->sendFile('/remote/file.txt', 'contents');
    }

    public function test_that_retrieve_file_contents_returns_string(): void
    {
        $sftp = $this->mock(SFTP::class);
        $sftp->shouldReceive('get')->once()->with('/remote/file.txt')->andReturn('file content');

        $transport = new SftpFileTransport($sftp);

        self::assertSame('file content', $transport->retrieveFileContents('/remote/file.txt'));
    }

    public function test_that_retrieve_file_contents_throws_when_get_returns_false(): void
    {
        $sftp = $this->mock(SFTP::class);
        $sftp->shouldReceive('get')->once()->andReturn(false);

        $transport = new SftpFileTransport($sftp);

        self::expectException(FileTransferException::class);
        $transport->retrieveFileContents('/remote/file.txt');
    }

    public function test_that_retrieve_file_contents_wraps_throwable(): void
    {
        $sftp = $this->mock(SFTP::class);
        $sftp->shouldReceive('get')->once()->andThrow(new RuntimeException('Timeout'));

        $transport = new SftpFileTransport($sftp);

        self::expectException(FileTransferException::class);
        $transport->retrieveFileContents('/remote/file.txt');
    }

    public function test_that_retrieve_file_resource_returns_readable_stream(): void
    {
        $sftp = $this->mock(SFTP::class);
        $sftp->shouldReceive('get')->once()->andReturn('file content');

        $transport = new SftpFileTransport($sftp);
        $resource  = $transport->retrieveFileResource('/remote/file.txt');

        self::assertIsResource($resource);
        self::assertSame('file content', stream_get_contents($resource));
    }

    public function test_that_retrieve_file_resource_propagates_exception(): void
    {
        $sftp = $this->mock(SFTP::class);
        $sftp->shouldReceive('get')->once()->andReturn(false);

        $transport = new SftpFileTransport($sftp);

        self::expectException(FileTransferException::class);
        $transport->retrieveFileResource('/remote/file.txt');
    }

    public function test_that_read_directory_yields_resources(): void
    {
        $sftp = $this->mock(SFTP::class);
        $sftp->shouldReceive('rawlist')->once()->with('/remote')->andReturn([
            '.'        => ['type' => 2, 'size' => 0, 'uid' => 0, 'gid' => 0, 'mode' => 0755, 'atime' => 0, 'mtime' => 0],
            '..'       => ['type' => 2, 'size' => 0, 'uid' => 0, 'gid' => 0, 'mode' => 0755, 'atime' => 0, 'mtime' => 0],
            'file.txt' => ['type' => 1, 'size' => 1024, 'uid' => 1000, 'gid' => 1001, 'mode' => 0644, 'atime' => 1704067200, 'mtime' => 1717243200],
            'subdir'   => ['type' => 2, 'size' => 4096, 'uid' => 1000, 'gid' => 1001, 'mode' => 0755, 'atime' => 1704067200, 'mtime' => 1717243200],
            'link'     => ['type' => 3, 'size' => 0,    'uid' => 1000, 'gid' => 1001, 'mode' => 0777, 'atime' => 1704067200, 'mtime' => 1717243200],
            'other'    => ['type' => 9, 'size' => 0,    'uid' => 0,    'gid' => 0,    'mode' => 0,    'atime' => 0,          'mtime' => 0],
        ]);

        $transport = new SftpFileTransport($sftp);
        $results   = iterator_to_array($transport->readDirectory('/remote'));

        self::assertCount(4, $results);

        $file = $results[0];
        self::assertInstanceOf(Resource::class, $file);
        self::assertSame('/remote/file.txt', $file->path());
        self::assertSame(1024, $file->size());
        self::assertSame(1000, $file->userId());
        self::assertSame(1001, $file->groupId());
        self::assertSame(ResourceType::FILE, $file->type());

        self::assertSame(ResourceType::DIR, $results[1]->type());
        self::assertSame(ResourceType::LINK, $results[2]->type());
        self::assertSame(ResourceType::UNKNOWN, $results[3]->type());
    }

    public function test_that_read_directory_throws_when_rawlist_returns_false(): void
    {
        $sftp = $this->mock(SFTP::class);
        $sftp->shouldReceive('rawlist')->once()->andReturn(false);

        $transport = new SftpFileTransport($sftp);

        self::expectException(FileTransferException::class);
        iterator_to_array($transport->readDirectory('/remote'));
    }

    public function test_that_read_directory_wraps_throwable(): void
    {
        $sftp = $this->mock(SFTP::class);
        $sftp->shouldReceive('rawlist')->once()->andThrow(new RuntimeException('SSH error'));

        $transport = new SftpFileTransport($sftp);

        self::expectException(FileTransferException::class);
        iterator_to_array($transport->readDirectory('/remote'));
    }
}
