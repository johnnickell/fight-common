<?php

declare(strict_types=1);

namespace Fight\Test\Common\Adapter\Sms\Twilio;

use Fight\Common\Adapter\Sms\Twilio\TwilioSmsTransport;
use Fight\Common\Application\Sms\Exception\SmsException;
use Fight\Common\Application\Sms\Message\SmsMessage;
use Fight\Common\Domain\Value\Internet\Url;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use Twilio\Exceptions\TwilioException;
use Twilio\Http\Response;
use Twilio\Rest\Client;

#[CoversClass(TwilioSmsTransport::class)]
class TwilioSmsTransportTest extends UnitTestCase
{
    public function test_that_send_sends_via_twilio(): void
    {
        $message = SmsMessage::create('+1234567890', '+0987654321')
            ->setBody('Hello, World!');

        $client = $this->mock(Client::class);
        $client->allows('getAccountSid')->andReturns('ACxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx');
        $client->shouldReceive('request')->once()->andReturns(new Response(201, '{}'));

        $transport = new TwilioSmsTransport($client);
        $transport->send($message);
    }

    public function test_that_send_includes_media_urls(): void
    {
        $mediaUrl = Url::parse('https://example.com/image.jpg');
        $message = SmsMessage::create('+1234567890', '+0987654321')
            ->setBody('Check this out!')
            ->addMedia($mediaUrl);

        $client = $this->mock(Client::class);
        $client->allows('getAccountSid')->andReturns('ACxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx');
        $client->shouldReceive('request')->once()->andReturns(new Response(201, '{}'));

        $transport = new TwilioSmsTransport($client);
        $transport->send($message);
    }

    public function test_that_send_throws_sms_exception_on_twilio_error(): void
    {
        $message = SmsMessage::create('+1234567890', '+0987654321');

        $client = $this->mock(Client::class);
        $client->allows('getAccountSid')->andReturns('ACxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx');
        $client->allows('request')->andThrows(new TwilioException('Twilio API error', 42));

        $transport = new TwilioSmsTransport($client);

        $this->expectException(SmsException::class);
        $this->expectExceptionMessage('Twilio API error');
        $this->expectExceptionCode(42);

        $transport->send($message);
    }
}
