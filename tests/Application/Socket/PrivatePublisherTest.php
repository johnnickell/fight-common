<?php

declare(strict_types=1);

namespace Fight\Test\Common\Application\Socket;

use Fight\Common\Application\Socket\PrivatePublisher;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversNothing;

#[CoversNothing]
/**
 * Class PrivatePublisherTest
 */
class PrivatePublisherTest extends UnitTestCase
{
    // phpcs:disable PSR1.Methods.CamelCapsMethodName.NotCamelCaps
    /**
     * Tests that consumers express private publication through a dedicated port.
     */
    public function test_that_private_publication_is_expressed_through_a_dedicated_port(): void
    {
        $publisher = new class implements PrivatePublisher {
            /**
             * @var list<array{topic: string, message: string}>
             */
            public array $privatePublications = [];

            /**
             * Records a private socket message.
             */
            public function pushPrivate(string $topic, string $message): void
            {
                $this->privatePublications[] = [
                    'topic'   => $topic,
                    'message' => $message
                ];
            }
        };

        $publisher->pushPrivate('https://example.com/books/1', 'Book updated privately');

        self::assertSame(
            [[
                'topic'   => 'https://example.com/books/1',
                'message' => 'Book updated privately'
            ]],
            $publisher->privatePublications
        );
    }
    // phpcs:enable
}
