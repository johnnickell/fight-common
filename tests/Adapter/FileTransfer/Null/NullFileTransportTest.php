<?php

declare(strict_types=1);

namespace Fight\Test\Common\Adapter\FileTransfer\Null;

use Fight\Common\Adapter\FileTransfer\Null\NullFileTransport;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(NullFileTransport::class)]
class NullFileTransportTest extends UnitTestCase
{
    private NullFileTransport $transport;

    protected function setUp(): void
    {
        $this->transport = new NullFileTransport();
    }

    public function test_that_send_file_does_nothing(): void
    {
        $this->transport->sendFile('/some/path.txt', 'contents');

        self::assertTrue(true);
    }

    public function test_that_retrieve_file_contents_returns_empty_string(): void
    {
        self::assertSame('', $this->transport->retrieveFileContents('/some/path.txt'));
    }

    public function test_that_retrieve_file_resource_returns_a_stream(): void
    {
        $resource = $this->transport->retrieveFileResource('/some/path.txt');

        self::assertIsResource($resource);
    }

    public function test_that_read_directory_returns_empty_iterable(): void
    {
        $result = $this->transport->readDirectory('/some/dir');

        self::assertSame([], $result);
    }
}
