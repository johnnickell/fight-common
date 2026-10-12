<?php

declare(strict_types=1);

namespace Fight\Test\Common\Adapter\Sms\Twilio;

use Fight\Common\Adapter\Sms\Twilio\TwilioSmsTransport;
use Fight\Common\Application\Sms\Exception\SmsException;
use Fight\Common\Application\Sms\Message\SmsMessage;
use Fight\Common\Application\Sms\SmsService;
use Fight\Common\Domain\Value\Internet\E164PhoneNumber;
use Fight\Common\Domain\Value\Internet\Url;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use Twilio\Exceptions\TwilioException;
use Twilio\Http\Response;
use Twilio\Rest\Client;

#[CoversClass(TwilioSmsTransport::class)]
class TwilioSmsTransportTest extends UnitTestCase
{
    #[DataProvider('addressing')]
    public function test_that_send_preserves_addressing_body_and_media_in_provider_payload(
        string $to,
        string $from,
        bool $adoptPhoneValue
    ): void {
        $client = $this->mock(Client::class);
        $client->allows('getAccountSid')->andReturns('ACxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx');
        $service = new SmsService(new TwilioSmsTransport($client));
        // No request expectation exists yet: construction must not contact the provider.
        if ($adoptPhoneValue) {
            $to = E164PhoneNumber::fromString($to)->toString();
            $from = E164PhoneNumber::fromString($from)->toString();
        }
        $message = $service->createMessage(
            $to,
            $from,
            'Hello, World!',
            [Url::parse('https://example.com/image.jpg'), 'https://example.com/other.jpg']
        );

        $client->shouldReceive('request')->once()->withArgs(
            static function (string $method, string $uri, array $params, array $data) use ($to, $from): bool {
                self::assertSame('POST', $method);
                self::assertSame(
                    'https://api.twilio.com/2010-04-01/Accounts/ACxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx/Messages.json',
                    $uri
                );
                self::assertSame([], $params);
                self::assertSame($to, $data['To']);
                self::assertSame($from, $data['From']);
                self::assertSame('Hello, World!', $data['Body']);
                self::assertSame(['https://example.com/image.jpg', 'https://example.com/other.jpg'], $data['MediaUrl']);
                self::assertCount(4, $data);

                return true;
            }
        )->andReturns(new Response(201, '{}'));
        $service->send($message);
    }

    /**
     * @return iterable<string, array{string, string, bool}>
     */
    public static function addressing(): iterable
    {
        yield 'explicit phone value adoption' => ['+15550001234', '+15559998765', true];
        yield 'short code and sender ID strings' => ['12345', 'FIGHT', false];
        yield 'existing leading-zero string' => ['+1234567890', '+0987654321', false];
    }

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
