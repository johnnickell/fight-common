<?php

declare(strict_types=1);

namespace Fight\Test\Common\Adapter\Mail\Null;

use Fight\Common\Adapter\Mail\Null\NullMailTransport;
use Fight\Common\Application\Mail\Message\MailMessage;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(NullMailTransport::class)]
class NullMailTransportTest extends UnitTestCase
{
    public function test_that_send_does_not_throw(): void
    {
        $transport = new NullMailTransport();

        $transport->send(new MailMessage());

        self::assertTrue(true);
    }
}
