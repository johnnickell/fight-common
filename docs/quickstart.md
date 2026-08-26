# Quick Start — Symfony

This guide walks through creating a new Symfony 7 application with `fight-common` fully wired up: CQRS buses, Doctrine types, validation, and a working command/handler pair in under 15 minutes.

---

## Prerequisites

- PHP 8.5+
- [Composer](https://getcomposer.org/)
- [Symfony CLI](https://symfony.com/download) (optional but recommended)

---

## 1. Create a New Symfony Project

```bash
symfony new my-app --version="7.*" --webapp
cd my-app
```

Or with plain Composer:

```bash
composer create-project symfony/skeleton:"7.*" my-app
cd my-app
```

---

## 2. Install fight-common

```bash
composer require johnnickell/fight-common
```

Then install the optional adapters you need:

```bash
# Doctrine ORM + DBAL custom types
composer require doctrine/orm doctrine/dbal

# Symfony full stack (already included in --webapp, add if using skeleton)
composer require symfony/http-kernel symfony/event-dispatcher \
    symfony/dependency-injection symfony/routing symfony/mailer

# JWT authentication
composer require lcobucci/jwt

# Guzzle HTTP client
composer require guzzlehttp/guzzle guzzlehttp/psr7

# Flysystem file storage
composer require league/flysystem
```

---

## 3. Wire the Kernel

Register the six compiler passes and configure autoconfiguration so Symfony auto-tags your handlers and subscribers:

```php
// src/Kernel.php
namespace App;

use Fight\Common\Adapter\ServiceContainer\Symfony\CommandFilterCompilerPass;
use Fight\Common\Adapter\ServiceContainer\Symfony\CommandHandlerCompilerPass;
use Fight\Common\Adapter\ServiceContainer\Symfony\EventSubscriberCompilerPass;
use Fight\Common\Adapter\ServiceContainer\Symfony\QueryFilterCompilerPass;
use Fight\Common\Adapter\ServiceContainer\Symfony\QueryHandlerCompilerPass;
use Fight\Common\Adapter\ServiceContainer\Symfony\TemplateHelperCompilerPass;
use Fight\Common\Application\Messaging\Command\CommandFilter;
use Fight\Common\Application\Messaging\Command\CommandHandler;
use Fight\Common\Application\Messaging\Event\EventSubscriber;
use Fight\Common\Application\Messaging\Query\QueryFilter;
use Fight\Common\Application\Messaging\Query\QueryHandler;
use Fight\Common\Application\Templating\TemplateHelper;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;

class Kernel extends BaseKernel
{
    use MicroKernelTrait;

    protected function build(ContainerBuilder $container): void
    {
        // Autoconfigure tags so classes are picked up automatically
        $container->registerForAutoconfiguration(CommandHandler::class)
            ->addTag('common.command_handler');
        $container->registerForAutoconfiguration(CommandFilter::class)
            ->addTag('common.command_filter');
        $container->registerForAutoconfiguration(QueryHandler::class)
            ->addTag('common.query_handler');
        $container->registerForAutoconfiguration(QueryFilter::class)
            ->addTag('common.query_filter');
        $container->registerForAutoconfiguration(EventSubscriber::class)
            ->addTag('common.event_subscriber');
        $container->registerForAutoconfiguration(TemplateHelper::class)
            ->addTag('common.template_helper');

        // Compiler passes wire handlers into the buses
        $container->addCompilerPass(new CommandHandlerCompilerPass());
        $container->addCompilerPass(new CommandFilterCompilerPass());
        $container->addCompilerPass(new QueryHandlerCompilerPass());
        $container->addCompilerPass(new QueryFilterCompilerPass());
        $container->addCompilerPass(new EventSubscriberCompilerPass());
        $container->addCompilerPass(new TemplateHelperCompilerPass());
    }
}
```

---

## 4. Register Core Services

Bind the library's interfaces to their Symfony adapter implementations in `config/services.yaml`:

```yaml
# config/services.yaml
services:
    _defaults:
        autowire: true
        autoconfigure: true

    App\:
        resource: '../src/'
        exclude: '../src/{DependencyInjection,Entity,Kernel.php}'

    # CQRS buses
    Fight\Common\Application\Messaging\Command\CommandBus:
        class: Fight\Common\Adapter\Messaging\Command\Sync\SynchronousCommandBusAdapter

    Fight\Common\Application\Messaging\Query\QueryBus:
        class: Fight\Common\Adapter\Messaging\Query\QueryHandlerProcessor

    Fight\Common\Application\Messaging\Event\EventDispatcher:
        class: Fight\Common\Adapter\Messaging\Event\Sync\SynchronousEventDispatcherAdapter

    # Validation subscriber
    Fight\Common\Adapter\Http\Symfony\EventSubscriber\SymfonyValidationSubscriber:
        tags:
            - { name: kernel.event_subscriber }

    # Filesystem (local)
    Fight\Common\Application\Filesystem\Filesystem:
        class: Fight\Common\Adapter\Filesystem\Symfony\SymfonyFilesystem
```

---

## 5. Register Doctrine Types

Add the custom DBAL types to `config/packages/doctrine.yaml`:

```yaml
# config/packages/doctrine.yaml
doctrine:
    dbal:
        types:
            audit_entry_id:         Fight\Common\Adapter\Persistence\Doctrine\Type\AuditEntryIdDataType
            common_uuid:            Fight\Common\Adapter\Persistence\Doctrine\Type\UuidDataType
            common_email_address:   Fight\Common\Adapter\Persistence\Doctrine\Type\EmailAddressDataType
            common_uri:             Fight\Common\Adapter\Persistence\Doctrine\Type\UriDataType
            common_url:             Fight\Common\Adapter\Persistence\Doctrine\Type\UrlDataType
            common_string:          Fight\Common\Adapter\Persistence\Doctrine\Type\StringObjectDataType
            common_string_text:     Fight\Common\Adapter\Persistence\Doctrine\Type\StringTextDataType
            common_mb_string:       Fight\Common\Adapter\Persistence\Doctrine\Type\MbStringObjectDataType
            common_mb_string_text:  Fight\Common\Adapter\Persistence\Doctrine\Type\MbStringTextDataType
            common_json:            Fight\Common\Adapter\Persistence\Doctrine\Type\JsonObjectDataType
            common_meta:            Fight\Common\Adapter\Persistence\Doctrine\Type\MetaDataType
            common_type:            Fight\Common\Adapter\Persistence\Doctrine\Type\TypeDataType
            common_message:         Fight\Common\Adapter\Persistence\Doctrine\Type\MessageDataType
```

The former `Fight\Common\Adapter\Doctrine\*DataType` paths remain silent deprecated 1.x
identities for existing consumers; use the canonical paths above for new configuration.

Then use the types in your entities:

```php
use Doctrine\ORM\Mapping as ORM;
use Fight\Common\Domain\Value\Identifier\Uuid;
use Fight\Common\Domain\Value\Internet\EmailAddress;

#[ORM\Entity]
class User
{
    #[ORM\Id]
    #[ORM\Column(type: 'common_uuid')]
    private Uuid $id;

    #[ORM\Column(type: 'common_email_address')]
    private EmailAddress $email;
}
```

---

## 6. Your First Command + Handler

Define a command (a plain data object implementing the `Command` interface):

```php
// src/User/Command/CreateUser.php
namespace App\User\Command;

use Fight\Common\Domain\Messaging\Command\Command;

final readonly class CreateUser implements Command
{
    public function __construct(
        public string $name,
        public string $email,
    ) {}
}
```

Implement the handler (it will be auto-tagged and auto-wired by the kernel setup above):

```php
// src/User/Handler/CreateUserHandler.php
namespace App\User\Handler;

use App\User\Command\CreateUser;
use Fight\Common\Application\Messaging\Command\CommandHandler;
use Fight\Common\Domain\Messaging\Command\CommandMessage;

final class CreateUserHandler implements CommandHandler
{
    public static function commandRegistration(): string
    {
        return CreateUser::class;
    }

    public function handle(CommandMessage $message): void
    {
        /** @var CreateUser $command */
        $command = $message->payload()->data();

        // your domain logic here
    }
}
```

Dispatch the command from a controller:

```php
// src/Controller/UserController.php
namespace App\Controller;

use App\User\Command\CreateUser;
use Fight\Common\Application\Messaging\Command\CommandBus;
use Fight\Common\Domain\Messaging\CommandMessage;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class UserController extends AbstractController
{
    public function __construct(private readonly CommandBus $commandBus) {}

    #[Route('/users', methods: ['POST'])]
    public function create(): Response
    {
        $command = new CreateUser(name: 'Alice', email: 'alice@example.com');
        $this->commandBus->execute($command);

        return $this->json(['status' => 'ok'], 201);
    }
}
```

---

## 7. Validation with `#[Validation]`

Use the `#[Validation]` attribute on any controller action to validate the incoming JSON request automatically. If validation fails, a `ValidationException` is thrown and handled by the subscriber you registered in step 4.

```php
use Fight\Common\Application\Attribute\Validation;

#[Route('/users', methods: ['POST'])]
#[Validation([
    ['field' => 'name',  'label' => 'Name',  'rules' => 'required|min_length[2]|max_length[100]'],
    ['field' => 'email', 'label' => 'Email', 'rules' => 'required|email'],
])]
public function create(): Response
{
    // request data is already validated here
    $data = json_decode($this->container->get('request_stack')->getCurrentRequest()->getContent(), true);
    $command = new CreateUser(name: $data['name'], email: $data['email']);
    $this->commandBus->execute($command);

    return $this->json(['status' => 'ok'], 201);
}
```

See [validation](validation.md) for all 60+ available rules.

---

## 8. Listening to Events

Implement `EventSubscriber` to react to domain events:

```php
// src/User/Subscriber/UserCreatedSubscriber.php
namespace App\User\Subscriber;

use App\User\Event\UserCreated;
use Fight\Common\Application\Messaging\Event\EventSubscriber;
use Fight\Common\Domain\Messaging\Event\EventMessage;

final class UserCreatedSubscriber implements EventSubscriber
{
    public static function eventRegistration(): string
    {
        return UserCreated::class;
    }

    public function handle(EventMessage $message): void
    {
        // send welcome email, log, etc.
    }
}
```

The kernel's `EventSubscriberCompilerPass` auto-wires it — no YAML registration needed.

---

## 9. What's Next

| Topic | Doc |
|-------|-----|
| Full CQRS reference (async buses, filters) | [messaging](messaging.md) |
| All validation rules | [validation](validation.md) |
| Collections and value objects | [collections](collections.md), [values](values.md) |
| File storage (local + Flysystem) | [files](files.md) |
| Authentication (HMAC + JWT) | [auth](auth.md) |
| Branching and release process | [contributing](contributing.md) |
