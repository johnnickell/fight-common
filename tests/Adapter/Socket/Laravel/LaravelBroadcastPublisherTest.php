<?php

declare(strict_types=1);

namespace Fight\Test\Common\Adapter\Socket\Laravel;

use Fight\Common\Adapter\Socket\Laravel\LaravelBroadcastPublisher;
use Fight\Common\Application\Socket\Exception\SocketException;
use Fight\Test\Common\TestCase\UnitTestCase;
use Illuminate\Contracts\Broadcasting\Broadcaster;
use PHPUnit\Framework\Attributes\CoversClass;
use RuntimeException;

#[CoversClass(LaravelBroadcastPublisher::class)]
final class LaravelBroadcastPublisherTest extends UnitTestCase
{
    public function test_that_push_broadcasts_the_fight_topic_with_the_fixed_event_and_exact_message_payload(): void
    {
        $broadcaster = $this->mock(Broadcaster::class);
        $broadcaster->shouldReceive('broadcast')
            ->once()
            ->with(['/books/42'], 'fight.socket.message', ['message' => 'Book updated']);

        $publisher = new LaravelBroadcastPublisher($broadcaster, 'fight.socket.message');

        $publisher->push('/books/42', 'Book updated');
    }

    public function test_that_push_translates_native_broadcast_failures_to_socket_exception(): void
    {
        $broadcaster = $this->mock(Broadcaster::class);
        $broadcaster->shouldReceive('broadcast')
            ->once()
            ->andThrow(new RuntimeException('Broadcast driver unavailable'));

        $publisher = new LaravelBroadcastPublisher($broadcaster, 'fight.socket.message');

        $this->expectException(SocketException::class);
        $this->expectExceptionMessage('Broadcast driver unavailable');

        $publisher->push('/books/42', 'Book updated');
    }
}
