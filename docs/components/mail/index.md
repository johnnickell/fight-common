---
template: atlas-article.html
atlas_article: true
title: Mail
atlas_article_heading_id: mail
atlas_component_group: Connect Systems
atlas_component_owner: Application and Adapter
atlas_component_dependencies: MailTransport, MailFactory, Symfony Mailer
atlas_article_context: Application · Adapter
atlas_article_lead: Send email through an application-owned port, then choose the transport at the boundary.
atlas_article_requires: PHP 8.5+
atlas_article_optional: symfony/mailer
atlas_article_package: johnnickell/fight-common
atlas_relationship_source_label: Adapter
atlas_relationship_source: Symfony Mailer
atlas_relationship_target_label: Application port
atlas_relationship_target: MailTransport
atlas_relationship_description: Symfony Mailer adapter depends on the Mail application port
atlas_relationship_caption: Symfony Mailer fulfills the application mail transport port without moving transport concerns into application code.
atlas_consequential_label: Consequential behavior
atlas_consequential_message: Recipient overrides replace every original To, Cc, and Bcc recipient.
atlas_next_steps:
  - label: Choose a delivery path
    href: "#supported-delivery-paths"
  - label: Configure Symfony Mailer
    href: "#symfony-configuration"
  - label: Review usage examples
    href: "#application-code"
  - label: Render email bodies
    href: "../templating/"
  - label: Trace delivery outcomes
    href: "../observability/"
  - label: Coordinate retries
    href: "../messaging/"
  - label: Configure framework support
    href: "../../frameworks/framework-support/"
atlas_local_contents:
  - label: Configuration formats
    href: "#configuration-formats"
  - label: MailMessage
    href: "#mailmessage"
  - label: MailService (Facade)
    href: "#mailservice-facade"
  - label: MailTransport
    href: "#mailtransport"
  - label: MailFactory
    href: "#mailfactory"
  - label: Attachment
    href: "#attachment"
  - label: Priority
    href: "#priority"
  - label: Symfony Configuration
    href: "#symfony-configuration"
  - label: Usage Examples
    href: "#usage-examples"
---

Mail lets an application describe a message and request delivery without choosing a provider,
framework, or SMTP implementation in its use case. Start with the portable port; select a delivery
adapter only in the composition root.

**Ownership.** Mail has no Mail-specific Domain model. The Application layer owns
`MailMessage` and the `MailTransport` and `MailFactory` contracts; the Adapter layer implements
delivery through Symfony Mailer, Laravel Mail, or another transport. Your application's composition
root binds an adapter implementation to each Application contract. Your Domain owns the business
facts and events; the Application layer coordinates the use case and decides when to send.

**Dependencies.** The portable path requires PHP 8.5+ and this package. It has no framework or
provider dependency. Install optional packages only for the delivery path you select.

**Install.**

```bash
composer require johnnickell/fight-common
```

<span id="application-code"></span>
**Start with application code.**

**Sending from a service.**

Application code can build the message directly and depend only on `MailTransport`. The selected
framework or composition root supplies the transport implementation.

```php-inline
use Fight\Common\Application\Mail\Message\MailMessage;
use Fight\Common\Application\Mail\Transport\MailTransport;

final readonly class OrderConfirmationService
{
    public function __construct(private MailTransport $mail) {}

    public function send(Order $order): void
    {
        $subject = sprintf('Order #%d confirmed', $order->id());

        $message = MailMessage::create()
            ->setSubject($subject)
            ->addTo($order->customerEmail(), $order->customerName())
            ->addFrom('orders@example.com', 'Example Store')
            ->addContent(sprintf('<h1>%s</h1>', $subject), MailMessage::CONTENT_TYPE_HTML)
            ->addContent($subject, MailMessage::CONTENT_TYPE_PLAIN);

        $this->mail->send($message);
    }
}
```

This use case is portable: it is unchanged whether the application binds Symfony, Laravel, a
logging decorator, or a deliberate null transport.

**Sending with attachments.**

When an application chooses the `MailService` facade, the same dependency can create attachments,
create messages, and send them:

```php-inline
final readonly class InvoiceService
{
    public function __construct(private MailService $mail) {}

    public function send(Invoice $invoice): void
    {
        $attachment = $this->mail->createAttachmentFromString(
            $this->generatePdf($invoice),
            sprintf('invoice-%d.pdf', $invoice->number()),
            'application/pdf',
        );

        $message = $this->mail->createMessage()
            ->setSubject('Your Invoice')
            ->addTo($invoice->customerEmail())
            ->addFrom('billing@example.com')
            ->addContent('Please find your invoice attached.', MailMessage::CONTENT_TYPE_PLAIN)
            ->addAttachment($attachment);

        $this->mail->send($message);
    }
}
```

**Testing without delivery.**

Bind `NullMailTransport` explicitly when a test should suppress delivery. It returns no delivery
evidence, so tests that need to assert the message should provide a consumer-owned spy instead.

```php-inline
use Fight\Common\Adapter\Mail\Null\NullMailTransport;

$transport = new NullMailTransport();
$transport->send($message);
```

## Configuration formats

### Supported delivery paths

Select the adapter at the application boundary. The Application layer owns the two
ports—`MailTransport` for delivery and `MailFactory` for messages and attachments. Framework
integration binds its adapter implementations to those contracts. It does **not** bind
`MailService`: create that facade in your application's composition root only when a single
dependency that combines both ports is useful.

| Application | Delivery adapter | Factory | Composition boundary |
| --- | --- | --- | --- |
| Symfony | `SymfonyMailTransport` over Symfony `MailerInterface` | `SymfonyMailFactory` | Your Symfony container definitions; the equivalent examples are below. |
| Laravel | `LaravelMailTransport` over Laravel `Illuminate\Contracts\Mail\Mailer` | `LaravelMailFactory` | Register `Fight\Common\Adapter\ServiceContainer\Laravel\MailServiceProvider`. |
| Yii | Proven Symfony fallback: `SymfonyMailTransport` | `SymfonyMailFactory` | Define the selected `MailerInterface` in the application and add the bounded Yii `MailServiceProvider`. |
| CodeIgniter | Proven Symfony fallback: `SymfonyMailTransport` | `SymfonyMailFactory` | Delegate from the application's `app/Config/Services.php` to `MailServices::mailFactory()` and `MailServices::mailTransport()`. |
| Slim or framework-free | Explicit Symfony composition | `SymfonyMailFactory` | Construct the two ports in the application's PSR-11 container or bootstrap code. |

Laravel's `MailServiceProvider` binds `MailFactory` to `LaravelMailFactory` and `MailTransport`
to `LaravelMailTransport`; it deliberately leaves `MailService` application-owned. Yii's bounded
provider similarly binds the two ports to the application-defined Symfony `MailerInterface`. CodeIgniter's `MailServices` delegate returns those same two Symfony
fallbacks because its native email API has not proven the full Fight mail contract.

Before configuring a path, install its optional dependencies: Symfony uses `symfony/mailer`;
Laravel uses `laravel/framework`; Yii's Symfony fallback uses `yiisoft/di` and
`symfony/mailer`; and CodeIgniter's Symfony fallback uses `codeigniter4/framework` and
`symfony/mailer`. These are Composer suggestions, not Fight Common production requirements;
they match the selected capability seams in `composer.json` and the framework-support contract.

### Laravel native adapter

Laravel applications use the native adapter rather than the Symfony container definitions below.
Register Fight's provider in the application's provider list (`bootstrap/providers.php` on current
Laravel releases, or `config/app.php` on older releases):

```php
<?php

use Fight\Common\Adapter\ServiceContainer\Laravel\MailServiceProvider;

return [
    App\Providers\AppServiceProvider::class,
    MailServiceProvider::class,
];
```

The provider resolves Laravel's configured `mailer`, binds `MailTransport` to
`LaravelMailTransport`, and binds `MailFactory` to `LaravelMailFactory`. The portable service above
then receives the Laravel transport without changing its application code. Laravel delivery keeps
the Fight message contract—including To, Cc, Bcc, content parts, priority, and attachments—through
`FightMailMailable`.

Slim has no branded mail provider. A Slim or framework-free application chooses its Symfony
`MailerInterface`, then composes the ports explicitly. The same composition also makes the facade
choice visible:

```php
<?php

use Fight\Common\Adapter\Mail\Symfony\SymfonyMailFactory;
use Fight\Common\Adapter\Mail\Symfony\SymfonyMailTransport;
use Fight\Common\Application\Mail\MailService;

$factory = new SymfonyMailFactory();
$transport = new SymfonyMailTransport($mailer);
$mail = new MailService($transport, $factory);
```

`$mailer` is the application-selected `Symfony\Component\Mailer\MailerInterface`. If a use case
needs only delivery or message creation, inject the corresponding port instead of the facade.

--8<-- "docs/mail.md"

## Symfony Configuration

These are Symfony-container definitions only. Choose the format already used by the application;
all three wire the same factory, transport, application-owned facade, and port aliases. Laravel,
Yii, CodeIgniter, Slim, and framework-free applications use the delivery paths above rather than
translating these definitions into an unrelated container format.

<section class="atlas-format-tabs" data-atlas-format-tabs aria-label="Symfony Mailer configuration formats">
  <div class="atlas-format-tabs__tabs" role="tablist" aria-label="Configuration format">
    <button type="button" role="tab" id="atlas-mail-config-yaml-tab" aria-controls="atlas-mail-config-yaml-panel" aria-selected="true" tabindex="0" data-atlas-format="yaml">YAML</button>
    <button type="button" role="tab" id="atlas-mail-config-xml-tab" aria-controls="atlas-mail-config-xml-panel" aria-selected="false" tabindex="-1" data-atlas-format="xml">XML</button>
    <button type="button" role="tab" id="atlas-mail-config-php-tab" aria-controls="atlas-mail-config-php-panel" aria-selected="false" tabindex="-1" data-atlas-format="php">PHP</button>
  </div>
  <span data-atlas-copy-status role="status" aria-live="polite" aria-atomic="true" hidden></span>

  <section class="atlas-format-tabs__panel" role="tabpanel" id="atlas-mail-config-yaml-panel" aria-labelledby="atlas-mail-config-yaml-tab" data-atlas-format-panel="yaml" markdown="1">
    <div class="atlas-format-tabs__panel-head">
      <span>YAML</span>
      <code data-atlas-filename="config/services.yaml">config/services.yaml</code>
      <button class="atlas-format-tabs__copy" type="button" data-atlas-copy aria-label="Copy YAML configuration" hidden>Copy</button>
    </div>

    ```yaml
    services:
        Fight\Common\Adapter\Mail\Symfony\SymfonyMailFactory: ~

        Fight\Common\Adapter\Mail\Symfony\SymfonyMailTransport:
            arguments:
                $mailer: '@mailer.mailer'
                $overrides: []

        Fight\Common\Application\Mail\MailService:
            arguments:
                $transport: '@Fight\Common\Adapter\Mail\Symfony\SymfonyMailTransport'
                $factory: '@Fight\Common\Adapter\Mail\Symfony\SymfonyMailFactory'

        Fight\Common\Application\Mail\Transport\MailTransport:
            alias: Fight\Common\Adapter\Mail\Symfony\SymfonyMailTransport

        Fight\Common\Application\Mail\Message\MailFactory:
            alias: Fight\Common\Adapter\Mail\Symfony\SymfonyMailFactory
    ```
  </section>

  <section class="atlas-format-tabs__panel" role="tabpanel" id="atlas-mail-config-xml-panel" aria-labelledby="atlas-mail-config-xml-tab" data-atlas-format-panel="xml" hidden markdown="1">
    <div class="atlas-format-tabs__panel-head">
      <span>XML</span>
      <code data-atlas-filename="config/services.xml">config/services.xml</code>
      <button class="atlas-format-tabs__copy" type="button" data-atlas-copy aria-label="Copy XML configuration" hidden>Copy</button>
    </div>

    ```xml
    <container xmlns="http://symfony.com/schema/dic/services"
        xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
        xsi:schemaLocation="http://symfony.com/schema/dic/services https://symfony.com/schema/dic/services/services-1.0.xsd">
        <services>
            <service id="Fight\Common\Adapter\Mail\Symfony\SymfonyMailFactory" />
            <service id="Fight\Common\Adapter\Mail\Symfony\SymfonyMailTransport">
                <argument type="service" id="mailer.mailer" />
                <argument type="collection" />
            </service>
            <service id="Fight\Common\Application\Mail\MailService">
                <argument type="service" id="Fight\Common\Adapter\Mail\Symfony\SymfonyMailTransport" />
                <argument type="service" id="Fight\Common\Adapter\Mail\Symfony\SymfonyMailFactory" />
            </service>
            <service id="Fight\Common\Application\Mail\Transport\MailTransport" alias="Fight\Common\Adapter\Mail\Symfony\SymfonyMailTransport" />
            <service id="Fight\Common\Application\Mail\Message\MailFactory" alias="Fight\Common\Adapter\Mail\Symfony\SymfonyMailFactory" />
        </services>
    </container>
    ```
  </section>

  <section class="atlas-format-tabs__panel" role="tabpanel" id="atlas-mail-config-php-panel" aria-labelledby="atlas-mail-config-php-tab" data-atlas-format-panel="php" hidden markdown="1">
    <div class="atlas-format-tabs__panel-head">
      <span>PHP</span>
      <code data-atlas-filename="config/services.php">config/services.php</code>
      <button class="atlas-format-tabs__copy" type="button" data-atlas-copy aria-label="Copy PHP configuration" hidden>Copy</button>
    </div>

    ```php
    <?php

    use Fight\Common\Adapter\Mail\Symfony\SymfonyMailFactory;
    use Fight\Common\Adapter\Mail\Symfony\SymfonyMailTransport;
    use Fight\Common\Application\Mail\MailService;
    use Fight\Common\Application\Mail\Message\MailFactory;
    use Fight\Common\Application\Mail\Transport\MailTransport;
    use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

    use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

    return static function (ContainerConfigurator $container): void {
        $services = $container->services();
        $services->set(SymfonyMailFactory::class);
        $services->set(SymfonyMailTransport::class)
            ->arg('$mailer', service('mailer.mailer'))
            ->arg('$overrides', []);
        $services->set(MailService::class)
            ->arg('$transport', service(SymfonyMailTransport::class))
            ->arg('$factory', service(SymfonyMailFactory::class));
        $services->alias(MailTransport::class, SymfonyMailTransport::class);
        $services->alias(MailFactory::class, SymfonyMailFactory::class);
    };
    ```
  </section>
</section>

### Environment-specific overrides

Symfony applications can replace recipients in development or suppress delivery in tests without
changing application code:

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

## Usage Examples

The primary service, attachment, and test examples are intentionally at the start of this guide.
These additional variations cover inline images and development diagnostics.

### Sending with an inline image

```php-inline
$embedId = $mail->generateEmbedId();
$logo = $mail->createAttachmentFromPath(
    '/assets/logo.png',
    'logo.png',
    'image/png',
    $embedId,
);

$message = $mail->createMessage()
    ->setSubject('Welcome')
    ->addTo($email)
    ->addFrom('noreply@example.com')
    ->addContent(sprintf('<img src="%s" alt="Logo">', $logo->embed()), MailMessage::CONTENT_TYPE_HTML)
    ->addAttachment($logo);

$mail->send($message);
```

### Development diagnostics

```php-inline
$transport = new LoggingMailTransport(
    new NullMailTransport(),
    $logger,
    LogLevel::DEBUG,
);
```

`LoggingMailTransport` records message metadata before delegation; it is not delivery evidence.
