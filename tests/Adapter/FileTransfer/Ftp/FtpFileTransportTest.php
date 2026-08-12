<?php

declare(strict_types=1);

namespace Fight\Test\Common\Adapter\FileTransfer\Ftp;

use Fight\Common\Adapter\FileTransfer\Ftp\FtpFileTransport;
use Fight\Common\Application\FileTransfer\Exception\FileTransferException;
use Fight\Common\Application\FileTransfer\Resource\Resource;
use Fight\Common\Application\FileTransfer\Resource\ResourceType;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

require_once __DIR__.'/FtpFunctionOverrides.php';

#[CoversClass(FtpFileTransport::class)]
class FtpFileTransportTest extends UnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        FtpFunctionOverrides::reset();
        FtpFunctionOverrides::enable();
    }

    protected function tearDown(): void
    {
        FtpFunctionOverrides::disable();
        FtpFunctionOverrides::reset();
        parent::tearDown();
    }

    public function test_that_disabled_wrapper_delegates_to_global_function(): void
    {
        FtpFunctionOverrides::queue('stream_get_contents', 'simulated contents');
        FtpFunctionOverrides::disable();
        $stream = fopen('php://temp', 'rb+');
        fwrite($stream, 'global contents');
        rewind($stream);

        $contents = \Fight\Common\Adapter\FileTransfer\Ftp\stream_get_contents($stream);

        fclose($stream);
        self::assertSame('global contents', $contents);
    }

    public function test_that_transport_source_has_no_coverage_exclusions(): void
    {
        $source = file_get_contents(
            dirname(__DIR__, 4).'/src/Adapter/FileTransfer/Ftp/FtpFileTransport.php'
        );

        self::assertIsString($source);
        self::assertStringNotContainsString('@codeCoverageIgnore', $source);
    }

    public function test_that_send_string_connects_creates_directories_and_disconnects(): void
    {
        $transport = $this->transport();

        $transport->sendFile('/nested/deep/file.txt', 'payload');

        self::assertSame('payload', FtpFunctionOverrides::uploaded('/nested/deep/file.txt'));
        self::assertSame(
            [['ftp.test', 2121, 12]],
            FtpFunctionOverrides::calls('ftp_connect')
        );
        self::assertSame(
            [[FtpFunctionOverrides::connection(), 'user', 'secret']],
            FtpFunctionOverrides::calls('ftp_login')
        );
        self::assertSame(
            ['nested', 'deep'],
            array_column(FtpFunctionOverrides::calls('ftp_mkdir'), 1)
        );
        self::assertCount(1, FtpFunctionOverrides::calls('ftp_close'));
    }

    public function test_that_send_resource_uses_existing_directory_and_closes_resource(): void
    {
        FtpFunctionOverrides::addDirectory('/existing');
        $contents = fopen('php://temp', 'rb+');
        fwrite($contents, 'resource payload');
        rewind($contents);

        $this->transport()->sendFile('/existing/file.txt', $contents);

        self::assertSame(
            'resource payload',
            FtpFunctionOverrides::uploaded('/existing/file.txt')
        );
        self::assertFalse(is_resource($contents));
        self::assertSame([], FtpFunctionOverrides::calls('ftp_mkdir'));
    }

    public function test_that_send_empty_string_uploads_empty_contents(): void
    {
        FtpFunctionOverrides::addDirectory('/existing');

        $this->transport()->sendFile('/existing/empty.txt', '');

        self::assertSame('', FtpFunctionOverrides::uploaded('/existing/empty.txt'));
    }

    public function test_that_send_failure_disconnects_and_preserves_error_message(): void
    {
        FtpFunctionOverrides::addDirectory('/existing');
        FtpFunctionOverrides::queue('ftp_fput', false);
        $transport = $this->transport();

        try {
            $transport->sendFile('/existing/file.txt', 'payload');
            self::fail('Expected FileTransferException');
        } catch (FileTransferException $exception) {
            self::assertSame(
                'Unable to send file to path: /existing/file.txt',
                $exception->getMessage()
            );
        }

        self::assertCount(1, FtpFunctionOverrides::calls('ftp_close'));
    }

    public function test_that_send_reports_current_directory_failure(): void
    {
        FtpFunctionOverrides::queue('ftp_pwd', false);
        $transport = $this->transport();

        try {
            $transport->sendFile('/file.txt', 'payload');
            self::fail('Expected FileTransferException');
        } catch (FileTransferException $exception) {
            self::assertSame(
                'Unable to resolve the current working directory',
                $exception->getMessage()
            );
        }

        $transport->__destruct();
        self::assertCount(1, FtpFunctionOverrides::calls('ftp_close'));
    }

    public function test_that_send_reports_recursive_directory_creation_failure(): void
    {
        FtpFunctionOverrides::queue('ftp_mkdir', false);

        self::expectException(FileTransferException::class);
        self::expectExceptionMessage('Unable to create directory: /missing');

        $this->transport()->sendFile('/missing/file.txt', 'payload');
    }

    public function test_that_send_covers_whole_path_directory_creation_fallback(): void
    {
        FtpFunctionOverrides::queue('ftp_chdir', false, true, true, true);

        $this->transport()->sendFile('/direct/file.txt', 'payload');

        self::assertSame(
            ['/direct'],
            array_column(FtpFunctionOverrides::calls('ftp_mkdir'), 1)
        );
    }

    public function test_that_send_reports_whole_path_directory_creation_failure(): void
    {
        FtpFunctionOverrides::queue('ftp_chdir', false, true, true, true);
        FtpFunctionOverrides::queue('ftp_mkdir', false);

        self::expectException(FileTransferException::class);
        self::expectExceptionMessage('Unable to create directory: /direct');

        $this->transport()->sendFile('/direct/file.txt', 'payload');
    }

    public function test_that_retrieve_file_contents_returns_remote_contents(): void
    {
        FtpFunctionOverrides::addRemoteFile('/remote/file.txt', 'downloaded contents');

        $contents = $this->transport()->retrieveFileContents('/remote/file.txt');

        self::assertSame('downloaded contents', $contents);
        self::assertCount(1, FtpFunctionOverrides::calls('ftp_close'));
    }

    public function test_that_retrieve_file_contents_reports_transfer_failure(): void
    {
        FtpFunctionOverrides::queue('ftp_fget', false);

        self::expectException(FileTransferException::class);
        self::expectExceptionMessage('Unable to retrieve file at path: /remote/file.txt');

        $this->transport()->retrieveFileContents('/remote/file.txt');
    }

    public function test_that_retrieve_file_contents_reports_stream_read_failure(): void
    {
        FtpFunctionOverrides::addRemoteFile('/remote/file.txt', 'downloaded contents');
        FtpFunctionOverrides::queue('stream_get_contents', false);

        self::expectException(FileTransferException::class);
        self::expectExceptionMessage('Unable to read file contents at path: /remote/file.txt');

        $this->transport()->retrieveFileContents('/remote/file.txt');
    }

    public function test_that_retrieve_file_resource_returns_readable_stream(): void
    {
        FtpFunctionOverrides::addRemoteFile('/remote/file.txt', 'streamed contents');

        $resource = $this->transport()->retrieveFileResource('/remote/file.txt');

        self::assertIsResource($resource);
        self::assertSame('streamed contents', stream_get_contents($resource));
        fclose($resource);
    }

    public function test_that_retrieve_file_resource_reports_transfer_failure(): void
    {
        FtpFunctionOverrides::queue('ftp_fget', false);

        self::expectException(FileTransferException::class);
        self::expectExceptionMessage('Unable to retrieve file at path: /remote/file.txt');

        $this->transport()->retrieveFileResource('/remote/file.txt');
    }

    public function test_that_read_directory_maps_mlsd_entries(): void
    {
        FtpFunctionOverrides::addDirectory('/remote');
        FtpFunctionOverrides::setDirectoryEntries([
            ['name' => '.', 'type' => 'cdir'],
            ['name' => '..', 'type' => 'pdir'],
            [
                'name' => 'file.txt',
                'type' => 'file',
                'size' => '1024',
                'unix.mode' => 0644,
                'modify' => '20240102030405'
            ],
            ['name' => 'directory', 'type' => 'dir', 'modify' => 'not-a-date'],
            ['name' => 'current', 'type' => 'cdir'],
            ['name' => 'parent', 'type' => 'pdir'],
            ['name' => 'link', 'type' => 'link'],
            ['name' => 'unknown'],
        ]);

        $resources = iterator_to_array($this->transport()->readDirectory('/remote/'));

        self::assertCount(6, $resources);
        self::assertContainsOnlyInstancesOf(Resource::class, $resources);
        self::assertSame('/remote/file.txt', $resources[0]->path());
        self::assertSame(1024, $resources[0]->size());
        self::assertSame('0644', $resources[0]->permissions());
        self::assertSame('2024-01-02 03:04:05', $resources[0]->modifyTime()->format('Y-m-d H:i:s'));
        self::assertSame(ResourceType::FILE, $resources[0]->type());
        self::assertSame(ResourceType::DIR, $resources[1]->type());
        self::assertSame(ResourceType::DIR, $resources[2]->type());
        self::assertSame(ResourceType::DIR, $resources[3]->type());
        self::assertSame(ResourceType::LINK, $resources[4]->type());
        self::assertSame(ResourceType::UNKNOWN, $resources[5]->type());
        self::assertSame(0, $resources[5]->size());
    }

    public function test_that_read_directory_reports_missing_directory(): void
    {
        self::expectException(FileTransferException::class);
        self::expectExceptionMessage('Directory does not exist: /missing');

        iterator_to_array($this->transport()->readDirectory('/missing/'));
    }

    public function test_that_read_directory_reports_listing_failure(): void
    {
        FtpFunctionOverrides::addDirectory('/remote');
        FtpFunctionOverrides::queue('ftp_mlsd', false);

        self::expectException(FileTransferException::class);
        self::expectExceptionMessage('Unable to read directory: /remote');

        iterator_to_array($this->transport()->readDirectory('/remote'));
    }

    public function test_that_ssl_passive_connection_uses_credentials_and_disconnects(): void
    {
        FtpFunctionOverrides::addRemoteFile('/remote/file.txt', 'secure contents');
        $transport = new FtpFileTransport(
            'secure.test',
            990,
            'secure-user',
            'secure-password',
            true,
            30,
            true
        );

        self::assertSame(
            'secure contents',
            $transport->retrieveFileContents('/remote/file.txt')
        );
        self::assertSame(
            [['secure.test', 990, 30]],
            FtpFunctionOverrides::calls('ftp_ssl_connect')
        );
        self::assertSame([], FtpFunctionOverrides::calls('ftp_connect'));
        self::assertSame(
            [[FtpFunctionOverrides::connection(), 'secure-user', 'secure-password']],
            FtpFunctionOverrides::calls('ftp_login')
        );
        self::assertSame(
            [[FtpFunctionOverrides::connection(), true]],
            FtpFunctionOverrides::calls('ftp_pasv')
        );
        self::assertCount(1, FtpFunctionOverrides::calls('ftp_close'));
    }

    public function test_that_connection_failure_preserves_error_message(): void
    {
        FtpFunctionOverrides::queue('ftp_connect', false);

        self::expectException(FileTransferException::class);
        self::expectExceptionMessage('Unable to connect to host ftp.test on port 2121');

        $this->transport()->retrieveFileContents('/remote/file.txt');
    }

    public function test_that_authentication_failure_preserves_error_message(): void
    {
        FtpFunctionOverrides::queue('ftp_login', false);

        self::expectException(FileTransferException::class);
        self::expectExceptionMessage('FTP authentication failed');

        $this->transport()->retrieveFileContents('/remote/file.txt');
    }

    public function test_that_passive_mode_failure_preserves_error_message(): void
    {
        FtpFunctionOverrides::queue('ftp_pasv', false);
        $transport = new FtpFileTransport('ftp.test', 2121, passive: true);

        self::expectException(FileTransferException::class);
        self::expectExceptionMessage('Failed to set FTP passive mode');

        $transport->retrieveFileContents('/remote/file.txt');
    }

    public function test_that_connected_transport_reuses_connection(): void
    {
        FtpFunctionOverrides::addDirectory('/remote');
        FtpFunctionOverrides::setDirectoryEntries([
            ['name' => 'first.txt', 'type' => 'file'],
        ]);
        FtpFunctionOverrides::addRemoteFile('/remote/second.txt', 'second');
        $transport = $this->transport();
        $directory = $transport->readDirectory('/remote');

        self::assertInstanceOf(Resource::class, $directory->current());
        self::assertSame('second', $transport->retrieveFileContents('/remote/second.txt'));
        self::assertCount(1, FtpFunctionOverrides::calls('ftp_connect'));
        self::assertCount(1, FtpFunctionOverrides::calls('ftp_close'));
    }

    public function test_that_releasing_paused_directory_read_naturally_disconnects_transport(): void
    {
        FtpFunctionOverrides::addDirectory('/remote');
        FtpFunctionOverrides::setDirectoryEntries([
            ['name' => 'first.txt', 'type' => 'file'],
        ]);
        $transport = $this->transport();
        $directory = $transport->readDirectory('/remote');

        self::assertInstanceOf(Resource::class, $directory->current());
        self::assertSame([], FtpFunctionOverrides::calls('ftp_close'));

        unset($transport);

        self::assertSame([], FtpFunctionOverrides::calls('ftp_close'));

        unset($directory);

        self::assertCount(1, FtpFunctionOverrides::calls('ftp_close'));
    }

    private function transport(): FtpFileTransport
    {
        return new FtpFileTransport('ftp.test', 2121, 'user', 'secret', timeout: 12);
    }
}
