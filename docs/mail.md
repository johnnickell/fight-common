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
├── Laravel\
│   ├── LaravelMailTransport            — MailTransport → Laravel Mailer
│   ├── LaravelMailFactory              — MailFactory → SymfonyAttachment
│   └── FightMailMailable               — Laravel Mailable → Symfony Email
├── Logging\
│   └── LoggingMailTransport            — Decorator: logs metadata then delegates
└── Null\
    └── NullMailTransport               — No-op (tests / dev)
```

## MailMessage

`Fight\Common\Application\Mail\Message\MailMessage`

A mutable, fluent DTO for building email messages. Use `MailMessage::create()` then chain
setters.

```php-inline
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

```php-inline
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

```php-inline
final readonly class MailService implements MailTransport, MailFactory
{
    public function __construct(
        private MailTransport $transport,
        private MailFactory $factory,
    ) {}
}
```

```php-inline
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

```php-inline
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
| `LaravelMailTransport` | `Adapter\Mail\Laravel` | Production — wraps Laravel `Mailer` |
| `LoggingMailTransport` | `Adapter\Mail\Logging` | Dev — logs message metadata then delegates |
| `NullMailTransport` | `Adapter\Mail\Null` | Test — silent no-op |

### SymfonyMailTransport

`Fight\Common\Adapter\Mail\Symfony\SymfonyMailTransport`

Maps every `MailMessage` field to Symfony Mime `Email`. Supports address overrides for
dev/staging:

```php-inline
$transport = new SymfonyMailTransport(
    $symfonyMailer,
    ['to' => ['dev@example.com'], 'cc' => [], 'bcc' => []]
);
```

When overrides are set, all `To`/`Cc`/`Bcc` from the message are **replaced** with the
override addresses. Each override accepts a comma-separated string or an array of strings.

### Delivery, failures, and safe operation

`SymfonyMailTransport` builds the Symfony `Email` and calls `MailerInterface::send()` inline. The
Fight adapter owns no queue, retry, worker, or durable outbox. A selected Symfony Mailer
configuration may dispatch the mail through Messenger and enqueue it before delivery, however.
The application owns retry policy, delayed delivery, worker supervision, idempotency, and any
durable outbox; queue a use case or message descriptor rather than assuming this transport
supplies those concerns.

`SymfonyMailTransport` translates failures thrown while building an email or handing it to
`MailerInterface::send()` into `MailException`. Attachment creation is also a failure boundary:
`SymfonyAttachment::fromPath()` throws `MailException` when the file cannot be opened, and
attachment conversion during `send()` is translated in the same way. If Messenger accepts the
mail for later delivery, a worker delivery failure cannot surface to the original caller as
`MailException`; the application owns how it observes and handles that outcome. Catch and
classify `MailException` at the application boundary where the business outcome is known.

Recipient overrides are a consequential safety switch, not an additive routing rule. Any
non-empty override map removes every original `To`, `Cc`, and `Bcc` recipient before applying
the supplied values. Supplying only `to` therefore sends to the override `To` list and leaves
`Cc` and `Bcc` empty; use explicit values for every recipient class required in the target
environment.

### LoggingMailTransport

`Fight\Common\Adapter\Mail\Logging\LoggingMailTransport`

Decorator that logs message metadata via PSR-3 before calling the inner transport:

```php-inline
$transport = new LoggingMailTransport(
    new SymfonyMailTransport($symfonyMailer),
    $logger,
    LogLevel::INFO   // default DEBUG
);
```

It logs subject, sender, recipient, reply-to, return-path, and other message metadata before delegation. Those fields
can be personal or sensitive even though message bodies and attachments are not logged here.
Choose a protected log sink, apply retention/redaction policy, and do not wrap a production
transport with this decorator by default merely for delivery diagnostics.

### NullMailTransport

`Fight\Common\Adapter\Mail\Null\NullMailTransport`

Silent no-op. `send()` does nothing. Useful in tests.

```php-inline
$transport = new NullMailTransport();
```

It is appropriate for tests and deliberate development suppression, but it provides no delivery
evidence and must not be used to model a successful production mail path.

---

## MailFactory

`Fight\Common\Application\Mail\Message\MailFactory`

```php-inline
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

The included adapter implementations are `SymfonyMailFactory` (`Adapter\Mail\Symfony`) and
`LaravelMailFactory` (`Adapter\Mail\Laravel`).

```php-inline
$factory = new SymfonyMailFactory();

$message   = $factory->createMessage();
$attachment = $factory->createAttachmentFromString($pdf, 'invoice.pdf', 'application/pdf');
$embedId    = $factory->generateEmbedId();
```

---

## Attachment

`Fight\Common\Application\Mail\Message\Attachment`

```php-inline
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

```php-inline
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

```php-inline
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

```php-inline
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

```php-inline
$priority = Priority::HIGH;
$priority->value;  // 2

$message->setPriority(Priority::HIGHEST);
```
