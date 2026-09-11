<?php

declare(strict_types=1);

namespace Fight\Test\Common\Adapter\Socket\Laravel;

use Fight\Common\Adapter\Socket\Laravel\LaravelPrivatePublisher;
use Fight\Common\Application\Socket\Publisher;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(LaravelPrivatePublisher::class)]
final class LaravelPrivatePublisherTest extends UnitTestCase
{
    public function test_that_push_private_publishes_to_laravels_private_channel_name(): void
    {
        $publisher = $this->mock(Publisher::class);
        $publisher->shouldReceive('push')
            ->once()
            ->with('private-orders.42', '{"status":"ready"}');

        (new LaravelPrivatePublisher($publisher))->pushPrivate('orders.42', '{"status":"ready"}');
    }
}
