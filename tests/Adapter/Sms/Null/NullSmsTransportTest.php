<?php

declare(strict_types=1);

namespace Fight\Test\Common\Adapter\Sms\Null;

use Fight\Common\Adapter\Sms\Null\NullSmsTransport;
use Fight\Common\Application\Sms\Message\SmsMessage;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(NullSmsTransport::class)]
class NullSmsTransportTest extends UnitTestCase
{
    public function test_that_send_does_not_throw(): void
    {
        $transport = new NullSmsTransport();

        $transport->send(SmsMessage::create('+1234567890', '+0987654321'));

        self::assertTrue(true);
    }
}
