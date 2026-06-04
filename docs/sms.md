# SMS

A transport-abstraction layer for sending SMS and MMS messages. Messages are built via a
fluent `SmsMessage` DTO and sent through any `SmsTransport` implementation. An `SmsService`
facade combines transport + factory into a single dependency.

```
Application\Sms
├── SmsService                          — Facade: SmsTransport + SmsFactory
├── Message\
│   ├── SmsMessage                      — Mutable message DTO (fluent builder)
│   └── SmsFactory (interface)          — createMessage(), createMediaUrl()
├── Transport\
│   └── SmsTransport (interface)        — send(SmsMessage): void
└── Exception\
    └── SmsException                    — extends SystemException

Adapter\Sms
├── Twilio\
│   └── TwilioSmsTransport              — SmsTransport → Twilio REST API
├── Logging\
│   └── LoggingSmsTransport             — Decorator: logs metadata then delegates
└── Null\
    └── NullSmsTransport                — No-op (tests / dev)
```

---

## Table of Contents

1. [SmsMessage](#smsmessage)
2. [SmsService (Facade)](#smsservice-facade)
3. [SmsTransport](#smstransport)
4. [SmsFactory](#smsfactory)
5. [Symfony Configuration](#symfony-configuration)
6. [Usage Examples](#usage-examples)

---

## SmsMessage

`Fight\Common\Application\Sms\Message\SmsMessage`

A mutable, fluent DTO for building SMS/MMS messages. Constructed with `to` and `from`
phone numbers; body and media are optional.

```php
use Fight\Common\Application\Sms\Message\SmsMessage;

$message = SmsMessage::create('+15550001234', '+15559998765')
    ->setBody('Your verification code is 123456');
```

### Fields

| Method | Signature | Description |
|---|---|---|
| `getTo` | `(): string` | Recipient phone number |
| `getFrom` | `(): string` | Sender phone number |
| `setBody` | `(string $body): static` | Message body text |
| `getBody` | `(): ?string` | Returns null if not set |
| `addMedia` | `(Url $url): static` | Adds a media URL for MMS |
| `getMedia` | `(): array<int, Url>` | Returns all attached media URLs |

`addMedia()` accepts a `Fight\Common\Domain\Value\Internet\Url` value object. Use
`SmsService::createMediaUrl()` to build one from a plain string.

---

## SmsService (Facade)

`Fight\Common\Application\Sms\SmsService`

Implements both `SmsTransport` and `SmsFactory`, wrapping a `SmsTransport` delegate.
This is the recommended single dependency for application services.

```php
final readonly class SmsService implements SmsTransport, SmsFactory
{
    public function __construct(private SmsTransport $transport) {}
}
```

```php
class VerificationService
{
    public function __construct(private SmsService $sms) {}

    public function sendCode(string $phone, string $code): void
    {
        $message = $this->sms->createMessage(
            to:   $phone,
            from: '+15550000000',
            body: "Your code is {$code}"
        );

        $this->sms->send($message);
    }
}
```

---

## SmsTransport

`Fight\Common\Application\Sms\Transport\SmsTransport`

```php
interface SmsTransport
{
    /** @throws SmsException */
    public function send(SmsMessage $message): void;
}
```

### Implementations

| Implementation | Namespace | Purpose |
|---|---|---|
| `TwilioSmsTransport` | `Adapter\Sms\Twilio` | Production — wraps Twilio REST client |
| `LoggingSmsTransport` | `Adapter\Sms\Logging` | Dev — logs metadata then delegates |
| `NullSmsTransport` | `Adapter\Sms\Null` | Test — silent no-op |

### TwilioSmsTransport

`Fight\Common\Adapter\Sms\Twilio\TwilioSmsTransport`

Wraps the Twilio `Client` SDK. Maps `SmsMessage` to `$client->messages->create()`.
Wraps any `Throwable` from the Twilio SDK in a `SmsException`.

```php
use Twilio\Rest\Client;
use Fight\Common\Adapter\Sms\Twilio\TwilioSmsTransport;

$transport = new TwilioSmsTransport(new Client($accountSid, $authToken));
```

### LoggingSmsTransport

`Fight\Common\Adapter\Sms\Logging\LoggingSmsTransport`

Decorator that logs message metadata (`to`, `from`, `body`, `media_count`) via PSR-3
before calling the inner transport:

```php
$transport = new LoggingSmsTransport(
    new TwilioSmsTransport($client),
    $logger,
    LogLevel::INFO   // default DEBUG
);
```

### NullSmsTransport

`Fight\Common\Adapter\Sms\Null\NullSmsTransport`

Silent no-op. `send()` does nothing and throws no exceptions.

```php
$transport = new NullSmsTransport();
```

---

## SmsFactory

`Fight\Common\Application\Sms\Message\SmsFactory`

```php
interface SmsFactory
{
    public function createMessage(
        string $to,
        string $from,
        ?string $body = null,
        array $mediaUrls = []    // Url objects or plain strings
    ): SmsMessage;

    public function createMediaUrl(string $url): Url;
}
```

`createMessage()` accepts `$mediaUrls` as a mixed array of `Url` objects or plain URL
strings — it coerces strings via `createMediaUrl()` automatically.

`SmsService` is the sole implementation.

```php
$message = $smsService->createMessage(
    to:        '+15550001234',
    from:      '+15559998765',
    body:      'Here is your photo',
    mediaUrls: ['https://example.com/photo.jpg']
);
```

---

## Symfony Configuration

```yaml
# config/packages/common_sms.yaml

services:
    _defaults:
        autowire: true
        autoconfigure: true

    # --- Transport ---
    Fight\Common\Adapter\Sms\Twilio\TwilioSmsTransport:
        arguments:
            - '@twilio.client'   # Twilio\Rest\Client

    Fight\Common\Adapter\Sms\Logging\LoggingSmsTransport:
        decorates: Fight\Common\Adapter\Sms\Twilio\TwilioSmsTransport
        arguments:
            - '@.inner'
            - '@logger'
            - 'info'

    Fight\Common\Adapter\Sms\Null\NullSmsTransport: ~

    # --- Facade ---
    Fight\Common\Application\Sms\SmsService:
        arguments:
            - '@Fight\Common\Adapter\Sms\Twilio\TwilioSmsTransport'

    # --- Interface aliases ---
    Fight\Common\Application\Sms\Transport\SmsTransport:
        alias: Fight\Common\Adapter\Sms\Twilio\TwilioSmsTransport
```

Environment overrides:

```yaml
# config/packages/test/common_sms.yaml
services:
    Fight\Common\Application\Sms\Transport\SmsTransport:
        alias: Fight\Common\Adapter\Sms\Null\NullSmsTransport
```

---

## Usage Examples

### Sending a Text Message

```php
use Fight\Common\Application\Sms\SmsService;

class OrderShippedNotifier
{
    public function __construct(private SmsService $sms) {}

    public function notify(Order $order): void
    {
        $message = $this->sms->createMessage(
            to:   $order->customerPhone(),
            from: '+15550000000',
            body: sprintf('Your order #%d has shipped!', $order->id())
        );

        $this->sms->send($message);
    }
}
```

### Sending MMS with Media

```php
$message = $this->sms->createMessage(
    to:        $recipient,
    from:      '+15550000000',
    body:      'Here is your receipt.',
    mediaUrls: ['https://example.com/receipts/1234.pdf']
);

$this->sms->send($message);
```

### Building a Message Manually

```php
use Fight\Common\Application\Sms\Message\SmsMessage;

$mediaUrl = $this->sms->createMediaUrl('https://example.com/image.jpg');

$message = SmsMessage::create('+15550001234', '+15550000000')
    ->setBody('Check this out')
    ->addMedia($mediaUrl);

$this->sms->send($message);
```

### Testing with NullSmsTransport

```php
use Fight\Common\Adapter\Sms\Null\NullSmsTransport;
use Fight\Common\Application\Sms\SmsService;

$service = new SmsService(new NullSmsTransport());
$service->send($message);  // no-op, no exception
```
