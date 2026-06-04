<?php

declare(strict_types=1);

namespace Fight\Test\Common\Application\Sms;

use Fight\Common\Application\Sms\Exception\SmsException;
use Fight\Common\Application\Sms\Message\SmsMessage;
use Fight\Common\Application\Sms\SmsService;
use Fight\Common\Application\Sms\Transport\SmsTransport;
use Fight\Common\Domain\Exception\DomainException;
use Fight\Common\Domain\Value\Internet\Url;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(SmsService::class)]
class SmsServiceTest extends UnitTestCase
{
    public function test_that_send_delegates_to_transport(): void
    {
        $message = SmsMessage::create('+1234567890', '+0987654321');
        $transport = $this->mock(SmsTransport::class);
        $transport->shouldReceive('send')->once()->with($message);

        $service = new SmsService($transport);
        $service->send($message);
    }

    public function test_that_create_message_returns_configured_sms_message(): void
    {
        $transport = $this->mock(SmsTransport::class);
        $service = new SmsService($transport);

        $message = $service->createMessage('+1234567890', '+0987654321');

        self::assertInstanceOf(SmsMessage::class, $message);
        self::assertSame('+1234567890', $message->getTo());
        self::assertSame('+0987654321', $message->getFrom());
    }

    public function test_that_create_message_with_body_sets_body(): void
    {
        $transport = $this->mock(SmsTransport::class);
        $service = new SmsService($transport);

        $message = $service->createMessage('+1234567890', '+0987654321', 'Hello World');

        self::assertSame('Hello World', $message->getBody());
    }

    public function test_that_create_message_accepts_url_object_directly(): void
    {
        $url = Url::parse('https://example.com/image.jpg');
        $transport = $this->mock(SmsTransport::class);
        $service = new SmsService($transport);

        $message = $service->createMessage('+1234567890', '+0987654321', null, [$url]);

        self::assertCount(1, $message->getMedia());
        self::assertSame($url, $message->getMedia()[0]);
    }

    public function test_that_create_message_accepts_url_string_and_parses_it(): void
    {
        $transport = $this->mock(SmsTransport::class);
        $service = new SmsService($transport);

        $message = $service->createMessage(
            '+1234567890',
            '+0987654321',
            null,
            ['https://example.com/image.jpg']
        );

        self::assertCount(1, $message->getMedia());
        self::assertInstanceOf(Url::class, $message->getMedia()[0]);
        self::assertSame('https://example.com/image.jpg', (string) $message->getMedia()[0]);
    }

    public function test_that_create_media_url_parses_and_returns_url(): void
    {
        $transport = $this->mock(SmsTransport::class);
        $service = new SmsService($transport);

        $url = $service->createMediaUrl('https://example.com/image.jpg');

        self::assertInstanceOf(Url::class, $url);
        self::assertSame('https://example.com/image.jpg', (string) $url);
    }

    public function test_that_create_media_url_throws_domain_exception_for_invalid_urls(): void
    {
        $transport = $this->mock(SmsTransport::class);
        $service = new SmsService($transport);

        $this->expectException(DomainException::class);
        $service->createMediaUrl('not-a-valid-url');
    }

    public function test_that_send_throws_sms_exception_when_transport_fails(): void
    {
        $message = SmsMessage::create('+1234567890', '+0987654321');
        $transport = $this->mock(SmsTransport::class);
        $transport->shouldReceive('send')->once()->andThrow(
            new SmsException('Transport failure')
        );

        $service = new SmsService($transport);

        $this->expectException(SmsException::class);
        $this->expectExceptionMessage('Transport failure');
        $service->send($message);
    }
}
