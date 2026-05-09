# Mail

A transport-abstraction layer for sending email. Messages are built via a fluent DTO
(`MailMessage`) and sent through any `MailTransport` implementation. A `MailService` facade
combines transport + factory into a single dependency.

```
Application\Mail
├── MailService                         — Facade: MailTransport + MailFactory
├── Message\
│   ├── MailMessage                     — Mutable message DTO (fluent builder)
│   ├── MailFactory (interface)         — createMessage(), createAttachment*(), generateEmbedId()
│   ├── Attachment (interface)          — getId(), getBody(), getFileName(), getContentType(),
│   │                                      getDisposition(), embed()
│   └── Priority (enum: int)            — HIGHEST..LOWEST
├── Transport\
│   └── MailTransport (interface)       — send(MailMessage): void
└── Exception\
    └── MailException                   — extends SystemException

Adapter\Mail
├── Symfony\
│   ├── SymfonyMailTransport            — MailTransport → Symfony MailerInterface
│   ├── SymfonyMailFactory              — MailFactory → SymfonyAttachment
│   └── SymfonyAttachment               — Attachment: fromString / fromPath, inline support
├── Logging\
│   └── LoggingMailTransport            — Decorator: logs metadata then delegates
└── Null\
    └── NullMailTransport               — No-op (tests / dev)
```

---

## Table of Contents

1. [MailMessage](#mailmessage)
2. [MailService (Facade)](#mailservice-facade)
3. [MailTransport](#mailtransport)
4. [MailFactory](#mailfactory)
5. [Attachment](#attachment)
6. [Priority](#priority)
7. [Symfony Configuration](#symfony-configuration)
8. [Usage Examples](#usage-examples)

---

## MailMessage

`Fight\Common\Application\Mail\Message\MailMessage`

A mutable, fluent DTO for building email messages. Use `MailMessage::create()` then chain
setters.

```php
use Fight\Common\Application\Mail\Message\MailMessage;
use Fight\Common\Application\Mail\Message\Priority;

$message = MailMessage::create()
    ->setSubject('Welcome!')
    ->addFrom('noreply@example.com', 'Example App')
    ->addTo('user@example.com', 'Alice')
    ->addContent('<h1>Hello</h1>', MailMessage::CONTENT_TYPE_HTML)
    ->addContent('Hello', MailMessage::CONTENT_TYPE_PLAIN)
    ->setPriority(Priority::HIGH);
```

### Fields

| Method | Signature | Description |
|---|---|---|
| `setSubject` | `(string $subject)` | Email subject line |
| `addFrom` | `(string $address, ?string $name)` | Sender address |
| `addTo` | `(string $address, ?string $name)` | Primary recipient |
| `addReplyTo` | `(string $address, ?string $name)` | Reply-To header |
| `addCc` | `(string $address, ?string $name)` | Carbon copy |
| `addBcc` | `(string $address, ?string $name)` | Blind carbon copy |
| `addContent` | `(string $body, string $contentType, ?string $charset)` | Body part (HTML or plain) |
| `setSender` | `(string $address, ?string $name)` | Sender header (overrides From for delivery) |
| `setReturnPath` | `(string $address)` | Bounce address |
| `setCharset` | `(string $charset)` | Character set (default `utf-8`) |
| `setPriority` | `(Priority $priority)` | Priority (default NORMAL) |
| `setTimestamp` | `(int $timestamp)` | UNIX timestamp for Date header |
| `setMaxLineLength` | `(int $maxLineLength)` | RFC 5322 line length (clamped to 998) |
| `addAttachment` | `(Attachment $attachment)` | File attachment |

Every setter returns `static` for fluent chaining. Every field has a corresponding getter
(`getSubject()`, `getTo()`, etc.).

### Content Parts

Call `addContent()` multiple times to build a multipart message. The Symfony transport maps
`CONTENT_TYPE_HTML` (`text/html`) to `$email->html()` and `CONTENT_TYPE_PLAIN` (`text/plain`)
to `$email->text()`.

```php
$message
    ->addContent('<h1>Hello</h1>', MailMessage::CONTENT_TYPE_HTML)
    ->addContent('Hello', MailMessage::CONTENT_TYPE_PLAIN);
```

Each content part stores `content`, `content_type`, and `charset` (defaults to the message's
charset if not specified).

### Constants

| Constant | Value |
|---|---|
| `MailMessage::DEFAULT_CHARSET` | `'utf-8'` |
| `MailMessage::CONTENT_TYPE_HTML` | `'text/html'` |
| `MailMessage::CONTENT_TYPE_PLAIN` | `'text/plain'` |

---

## MailService (Facade)

`Fight\Common\Application\Mail\MailService`

Implements both `MailTransport` and `MailFactory`, delegating to injected implementations.
This is the recommended way to depend on mail in application services — one dependency gives
you `send()`, `createMessage()`, and attachment creation.

```php
final readonly class MailService implements MailTransport, MailFactory
{
    public function __construct(
        private MailTransport $transport,
        private MailFactory $factory,
    ) {}
}
```

```php
class WelcomeEmailService
{
    public function __construct(private MailService $mailer) {}

    public function send(User $user): void
    {
        $message = $this->mailer->createMessage()
            ->setSubject('Welcome!')
            ->addTo($user->email(), $user->name())
            ->addFrom('noreply@example.com')
            ->addContent('<h1>Welcome</h1>', MailMessage::CONTENT_TYPE_HTML);

        $this->mailer->send($message);
    }
}
```

---

## MailTransport

`Fight\Common\Application\Mail\Transport\MailTransport`

```php
interface MailTransport
{
    /** @throws MailException */
    public function send(MailMessage $message): void;
}
```

### Implementations

| Implementation | Namespace | Purpose |
|---|---|---|
| `SymfonyMailTransport` | `Adapter\Mail\Symfony` | Production — wraps Symfony `MailerInterface` |
| `LoggingMailTransport` | `Adapter\Mail\Logging` | Dev — logs message metadata then delegates |
| `NullMailTransport` | `Adapter\Mail\Null` | Test — silent no-op |

### SymfonyMailTransport

`Fight\Common\Adapter\Mail\Symfony\SymfonyMailTransport`

Maps every `MailMessage` field to Symfony Mime `Email`. Supports address overrides for
dev/staging:

```php
$transport = new SymfonyMailTransport(
    $symfonyMailer,
    ['to' => ['dev@example.com'], 'cc' => [], 'bcc' => []]
);
```

When overrides are set, all `To`/`Cc`/`Bcc` from the message are **replaced** with the
override addresses. Each override accepts a comma-separated string or an array of strings.

### LoggingMailTransport

`Fight\Common\Adapter\Mail\Logging\LoggingMailTransport`

Decorator that logs message metadata via PSR-3 before calling the inner transport:

```php
$transport = new LoggingMailTransport(
    new SymfonyMailTransport($symfonyMailer),
    $logger,
    LogLevel::INFO   // default DEBUG
);
```

### NullMailTransport

`Fight\Common\Adapter\Mail\Null\NullMailTransport`

Silent no-op. `send()` does nothing. Useful in tests.

```php
$transport = new NullMailTransport();
```

---

## MailFactory

`Fight\Common\Application\Mail\Message\MailFactory`

```php
interface MailFactory
{
    public function createMessage(): MailMessage;
    public function createAttachmentFromString(
        string $body,
        string $fileName,
        string $contentType,
        ?string $embedId = null
    ): Attachment;
    public function createAttachmentFromPath(
        string $path,
        string $fileName,
        string $contentType,
        ?string $embedId = null
    ): Attachment;
    public function generateEmbedId(): string;
}
```

`SymfonyMailFactory` (`Adapter\Mail\Symfony`) is the sole adapter implementation.

```php
$factory = new SymfonyMailFactory();

$message   = $factory->createMessage();
$attachment = $factory->createAttachmentFromString($pdf, 'invoice.pdf', 'application/pdf');
$embedId    = $factory->generateEmbedId();
```

---

## Attachment

`Fight\Common\Application\Mail\Message\Attachment`

```php
interface Attachment
{
    public function getId(): string;
    public function getBody(): mixed;       // string | resource
    public function getFileName(): string;
    public function getContentType(): string;
    public function getDisposition(): string;  // 'inline' | 'attachment'
    public function embed(): string;           // 'cid:<id>'
}
```

`SymfonyAttachment` (`Adapter\Mail\Symfony`) is the sole implementation.

### Creating Attachments

```php
use Fight\Common\Adapter\Mail\Symfony\SymfonyAttachment;

// From a content string
$attachment = SymfonyAttachment::fromString(
    $pdfBinary,
    'invoice.pdf',
    'application/pdf'
);

// From a file path
$attachment = SymfonyAttachment::fromPath(
    '/tmp/receipt.pdf',
    'receipt.pdf',
    'application/pdf'
);
```

### Inline vs Regular

The disposition is determined by whether `$embedId` is provided:

- **`$embedId` is null** — regular attachment (disposition: `attachment`). A random embed ID
  is generated internally but the attachment is not marked as inline.
- **`$embedId` is provided** — inline attachment (disposition: `inline`). Use with `embed()`
  for CID references in HTML.

```php
// Inline — for embedding in HTML
$image = SymfonyAttachment::fromString(
    $pngData,
    'logo.png',
    'image/png',
    $embedId  // provided → inline
);

// Use in HTML template: <img src="<?= $image->embed() ?>">
// Output: <img src="cid:abc123...">
```

---

## Priority

`Fight\Common\Application\Mail\Message\Priority`

A backed integer enum matching RFC priorities:

```php
enum Priority: int
{
    case HIGHEST = 1;
    case HIGH    = 2;
    case NORMAL  = 3;
    case LOW     = 4;
    case LOWEST  = 5;
}
```

Access the integer value via `->value` (PHP backed-enum property):

```php
$priority = Priority::HIGH;
$priority->value;  // 2

$message->setPriority(Priority::HIGHEST);
```

---

## Symfony Configuration

```yaml
# config/packages/common_mail.yaml

services:
    _defaults:
        autowire: true
        autoconfigure: true

    # --- Factory ---
    Fight\Common\Adapter\Mail\Symfony\SymfonyMailFactory: ~

    # --- Transports ---
    Fight\Common\Adapter\Mail\Symfony\SymfonyMailTransport:
        arguments:
            - '@mailer.mailer'
            - []   # overrides — empty in production

    Fight\Common\Adapter\Mail\Logging\LoggingMailTransport:
        decorates: Fight\Common\Adapter\Mail\Symfony\SymfonyMailTransport
        arguments:
            - '@.inner'
            - '@logger'
            - 'info'

    Fight\Common\Adapter\Mail\Null\NullMailTransport: ~

    # --- Facade ---
    Fight\Common\Application\Mail\MailService:
        arguments:
            - '@Fight\Common\Adapter\Mail\Symfony\SymfonyMailTransport'
            - '@Fight\Common\Adapter\Mail\Symfony\SymfonyMailFactory'

    # --- Interface aliases ---
    Fight\Common\Application\Mail\Transport\MailTransport:
        alias: Fight\Common\Adapter\Mail\Symfony\SymfonyMailTransport

    Fight\Common\Application\Mail\Message\MailFactory:
        alias: Fight\Common\Adapter\Mail\Symfony\SymfonyMailFactory
```

Environment-specific overrides:

```yaml
# config/packages/dev/common_mail.yaml
services:
    Fight\Common\Adapter\Mail\Symfony\SymfonyMailTransport:
        arguments:
            - '@mailer.mailer'
            - to: ['dev-team@example.com']

# config/packages/test/common_mail.yaml
services:
    Fight\Common\Application\Mail\Transport\MailTransport:
        alias: Fight\Common\Adapter\Mail\Null\NullMailTransport
```

---

## Usage Examples

### Sending from a Service

```php
use Fight\Common\Application\Mail\MailService;
use Fight\Common\Application\Mail\Message\MailMessage;

class OrderConfirmationService
{
    public function __construct(private MailService $mailer) {}

    public function send(Order $order): void
    {
        $html = sprintf('<h1>Order #%d confirmed</h1>', $order->id());
        $text = sprintf('Order #%d confirmed', $order->id());

        $message = $this->mailer->createMessage()
            ->setSubject('Order Confirmed')
            ->addTo($order->customerEmail(), $order->customerName())
            ->addFrom('orders@example.com', 'Example Store')
            ->addContent($html, MailMessage::CONTENT_TYPE_HTML)
            ->addContent($text, MailMessage::CONTENT_TYPE_PLAIN);

        $this->mailer->send($message);
    }
}
```

### Sending with Attachments

```php
class InvoiceService
{
    public function __construct(private MailService $mailer) {}

    public function send(Invoice $invoice): void
    {
        $pdf = $this->generatePdf($invoice);

        $attachment = $this->mailer->createAttachmentFromString(
            $pdf,
            sprintf('invoice-%d.pdf', $invoice->number()),
            'application/pdf'
        );

        $message = $this->mailer->createMessage()
            ->setSubject('Your Invoice')
            ->addTo($invoice->customerEmail())
            ->addFrom('billing@example.com')
            ->addContent('Please find your invoice attached.', MailMessage::CONTENT_TYPE_PLAIN)
            ->addAttachment($attachment);

        $this->mailer->send($message);
    }
}
```

### Sending with Inline Image

```php
class BrandedMailService
{
    public function __construct(private MailService $mailer) {}

    public function send(string $email): void
    {
        $embedId = $this->mailer->generateEmbedId();
        $logo    = $this->mailer->createAttachmentFromPath(
            '/assets/logo.png',
            'logo.png',
            'image/png',
            $embedId  // inline
        );

        $html = sprintf(
            '<img src="%s" alt="Logo"><h1>Welcome</h1>',
            $logo->embed()   // cid:<embedId>
        );

        $message = $this->mailer->createMessage()
            ->setSubject('Welcome')
            ->addTo($email)
            ->addFrom('noreply@example.com')
            ->addContent($html, MailMessage::CONTENT_TYPE_HTML)
            ->addAttachment($logo);

        $this->mailer->send($message);
    }
}
```

### Testing with NullMailTransport

```php
use Fight\Common\Adapter\Mail\Null\NullMailTransport;
use Fight\Common\Adapter\Mail\Symfony\SymfonyMailFactory;

$service = new MailService(
    new NullMailTransport(),
    new SymfonyMailFactory()
);

$service->send($message);  // no-op, no exception
```

### Development with LoggingMailTransport

```php
$transport = new LoggingMailTransport(
    new NullMailTransport(),
    $logger,
    LogLevel::DEBUG
);

// Logs subject, from, to, cc, bcc, reply-to, sender, return-path,
// charset, priority, timestamp, and max-line-length to the logger
// before delegating to NullMailTransport
```
