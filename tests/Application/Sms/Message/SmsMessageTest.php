<?php

declare(strict_types=1);

namespace Fight\Test\Common\Application\Sms\Message;

use Fight\Common\Application\Sms\Message\SmsMessage;
use Fight\Common\Domain\Value\Internet\Url;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(SmsMessage::class)]
class SmsMessageTest extends UnitTestCase
{
    public function test_that_create_returns_instance(): void
    {
        $message = SmsMessage::create('+1234567890', '+0987654321');

        self::assertInstanceOf(SmsMessage::class, $message);
    }

    public function test_that_get_to_returns_constructor_value(): void
    {
        $message = SmsMessage::create('+1234567890', '+0987654321');

        self::assertSame('+1234567890', $message->getTo());
    }

    public function test_that_get_from_returns_constructor_value(): void
    {
        $message = SmsMessage::create('+1234567890', '+0987654321');

        self::assertSame('+0987654321', $message->getFrom());
    }

    public function test_that_get_body_returns_null_by_default(): void
    {
        $message = SmsMessage::create('+1234567890', '+0987654321');

        self::assertNull($message->getBody());
    }

    public function test_that_set_body_stores_and_returns_self(): void
    {
        $message = SmsMessage::create('+1234567890', '+0987654321');

        $result = $message->setBody('Hello World');

        self::assertSame($message, $result);
        self::assertSame('Hello World', $message->getBody());
    }

    public function test_that_get_media_returns_empty_array_by_default(): void
    {
        $message = SmsMessage::create('+1234567890', '+0987654321');

        self::assertSame([], $message->getMedia());
    }

    public function test_that_add_media_appends_url(): void
    {
        $url = Url::parse('https://example.com/image.jpg');
        $message = SmsMessage::create('+1234567890', '+0987654321');

        $result = $message->addMedia($url);

        self::assertSame($message, $result);
        self::assertCount(1, $message->getMedia());
        self::assertSame($url, $message->getMedia()[0]);
    }

    public function test_that_add_media_appends_multiple_urls(): void
    {
        $url1 = Url::parse('https://example.com/image1.jpg');
        $url2 = Url::parse('https://example.com/image2.jpg');
        $message = SmsMessage::create('+1234567890', '+0987654321');

        $message->addMedia($url1)->addMedia($url2);

        self::assertCount(2, $message->getMedia());
    }
}
