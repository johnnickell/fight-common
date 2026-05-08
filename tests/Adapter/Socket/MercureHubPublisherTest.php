<?php

declare(strict_types=1);

namespace Fight\Test\Common\Adapter\Socket;

use Fight\Common\Adapter\Socket\MercureHubPublisher;
use Fight\Common\Application\Socket\Exception\SocketException;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use RuntimeException;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;

#[CoversClass(MercureHubPublisher::class)]
class MercureHubPublisherTest extends UnitTestCase
{
    public function test_that_push_publishes_update_with_topic_and_data(): void
    {
        $topic = 'https://example.com/books/1';
        $message = 'Book updated';

        $hub = $this->mock(HubInterface::class);
        $hub->shouldReceive('publish')
            ->once()
            ->andReturnUsing(function (Update $update) use ($topic, $message): string {
                self::assertContains($topic, $update->getTopics());
                self::assertSame($message, $update->getData());

                return 'update-id';
            });

        $publisher = new MercureHubPublisher($hub);

        $publisher->push($topic, $message);
    }

    public function test_that_push_wraps_exception_in_socket_exception(): void
    {
        $hub = $this->mock(HubInterface::class);
        $hub->shouldReceive('publish')
            ->once()
            ->andThrow(new RuntimeException('Hub connection failed'));

        $publisher = new MercureHubPublisher($hub);

        $this->expectException(SocketException::class);
        $this->expectExceptionMessage('Hub connection failed');

        $publisher->push('/topic', 'data');
    }
}
