<?php

declare(strict_types=1);

namespace Fight\Test\Common\Adapter\Socket;

use Fight\Common\Adapter\Socket\PrivateMercureHubPublisher;
use Fight\Common\Application\Socket\Exception\SocketException;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use RuntimeException;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;

#[CoversClass(PrivateMercureHubPublisher::class)]
class PrivateMercureHubPublisherTest extends UnitTestCase
{
    public function test_that_push_private_publishes_private_update_with_unchanged_topic_and_data(): void
    {
        $topic   = 'https://example.com/books/1';
        $message = 'Book updated privately';
        $hub     = $this->mock(HubInterface::class);

        $hub->shouldReceive('publish')
            ->once()
            ->andReturnUsing(function (Update $update) use ($topic, $message): string {
                self::assertSame([$topic], $update->getTopics());
                self::assertSame($message, $update->getData());
                self::assertTrue($update->isPrivate());
                self::assertNull($update->getId());
                self::assertNull($update->getType());
                self::assertNull($update->getRetry());

                return 'update-id';
            });

        (new PrivateMercureHubPublisher($hub))->pushPrivate($topic, $message);
    }

    public function test_that_push_private_wraps_transport_failure_with_original_throwable(): void
    {
        $failure = new RuntimeException('Hub connection failed');
        $hub     = $this->mock(HubInterface::class);
        $hub->shouldReceive('publish')->once()->andThrow($failure);

        try {
            (new PrivateMercureHubPublisher($hub))->pushPrivate('/topic', 'data');
            self::fail('Expected the transport failure to be wrapped.');
        } catch (SocketException $exception) {
            self::assertSame($failure, $exception->getPrevious());
        }
    }
}
