# Documentation

This directory contains the full documentation for `johnnickell/fight-common`, a shared PHP
library for $FIGHT projects implementing Hexagonal (Ports & Adapters) / Clean Architecture.
Each component is documented in its own file with API references, Symfony configuration, and
usage examples.

---

## Table of Contents

1. [Installation](#installation)
2. [Symfony Wiring](#symfony-wiring)
3. [Component Catalog](#component-catalog)

---

## Installation

```bash
composer require johnnickell/fight-common
```

PHP 8.5+ is required. The library depends on PSR-7 (`psr/http-message`), PSR-17
(`psr/http-factory`), PSR-18 (`psr/http-client`), PSR-3 (`psr/log`), PSR-20 (`psr/cache`),
and PSR-11 (`psr/container`) interfaces. Optional adapters require additional packages:

| Package | Enables | Doc |
|---|---|---|
| `doctrine/dbal` | Custom Doctrine data types for value objects | [values](values.md) |
| `doctrine/orm` | Doctrine unit of work | [repositories](repositories.md) |
| `guzzlehttp/guzzle` `guzzlehttp/psr7` | HTTP client adapter | [http-client](http-client.md) |
| `lcobucci/jwt` | JWT encoder and decoder | [auth](auth.md) |
| `league/flysystem` | File storage adapter (Flysystem) | [files](files.md) |
| `symfony/dependency-injection` | Compiler passes for auto-wiring handlers | [messaging](messaging.md) |
| `symfony/event-dispatcher` | Validation event subscriber | [validation](validation.md) |
| `symfony/filesystem` | Local filesystem adapter | [files](files.md) |
| `symfony/http-foundation` | JSend response, JSON middleware | [utilities](utilities.md) |
| `symfony/http-kernel` | Request middleware, error controller | [http-client](http-client.md) |
| `symfony/mercure` | Mercure hub publisher | [sockets](sockets.md) |
| `symfony/messenger` | Async command bus and event dispatcher | [messaging](messaging.md) |
| `symfony/routing` | URL generator adapter | [routing](routing.md) |

```bash
# Install everything for development
composer require --dev doctrine/orm guzzlehttp/guzzle guzzlehttp/psr7 \
    lcobucci/jwt league/flysystem symfony/http-kernel symfony/messenger \
    symfony/mercure symfony/routing
```

---

## Symfony Wiring

### Compiler Passes

Register the six compiler passes in your application kernel to auto-wire command handlers,
filters, query handlers, event subscribers, and template helpers:

```php
// src/Kernel.php
use Symfony\Component\HttpKernel\Kernel as BaseKernel;
use …;

class Kernel extends BaseKernel
{
    use MicroKernelTrait;

    protected function build(ContainerBuilder $container): void
    {
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

        $container->addCompilerPass(new CommandHandlerCompilerPass());
        $container->addCompilerPass(new CommandFilterCompilerPass());
        $container->addCompilerPass(new QueryHandlerCompilerPass());
        $container->addCompilerPass(new QueryFilterCompilerPass());
        $container->addCompilerPass(new EventSubscriberCompilerPass());
        $container->addCompilerPass(new TemplateHelperCompilerPass());
    }
}
```

### Tag Reference

| Tag | Interface | Purpose |
|---|---|---|
| `common.command_handler` | `CommandHandler` | Routes commands to their handler |
| `common.command_filter` | `CommandFilter` | Middleware before/after command execution |
| `common.query_handler` | `QueryHandler` | Routes queries to their handler |
| `common.query_filter` | `QueryFilter` | Middleware before/after query execution |
| `common.event_subscriber` | `EventSubscriber` | Receives dispatched events |
| `common.template_helper` | `TemplateHelper` | Injects helpers into template engines |

### Doctrine Types

Register the custom data types in `config/packages/doctrine.yaml`:

```yaml
doctrine:
    dbal:
        types:
            common_uuid:            Fight\Common\Adapter\Doctrine\UuidDataType
            common_email_address:   Fight\Common\Adapter\Doctrine\EmailAddressDataType
            common_uri:             Fight\Common\Adapter\Doctrine\UriDataType
            common_url:             Fight\Common\Adapter\Doctrine\UrlDataType
            common_string:          Fight\Common\Adapter\Doctrine\StringObjectDataType
            common_string_text:     Fight\Common\Adapter\Doctrine\StringTextDataType
            common_mb_string:       Fight\Common\Adapter\Doctrine\MbStringObjectDataType
            common_mb_string_text:  Fight\Common\Adapter\Doctrine\MbStringTextDataType
            common_json:            Fight\Common\Adapter\Doctrine\JsonObjectDataType
            common_type:            Fight\Common\Adapter\Doctrine\TypeDataType
            common_message:         Fight\Common\Adapter\Doctrine\MessageDataType
```

See [values](values.md#doctrine-data-types) for details and entity usage examples.

### Validation

Register the validation event subscriber in `config/services.yaml`:

```yaml
Fight\Common\Adapter\Validation\ValidationEventSubscriber:
    tags:
        - { name: kernel.event_subscriber }
```

See [validation](validation.md) for rule definitions and usage.

---

## Component Catalog

1. **[values](values.md)** — Immutable, self-validating domain primitives (`StringObject`,
   `EmailAddress`, `Uri`, `Url`, `Uuid`, `UniqueId`, etc.) with helper function API and
   Doctrine data type mappings.

2. **[collections](collections.md)** — Fully typed collection hierarchy: `ArrayList`,
   `HashSet`, `HashTable`, `SortedSet`, `SortedTable`, stacks, queues, `LinkedDeque`,
   and the `RedBlackSearchTree` that backs ordered structures.

3. **[messaging](messaging.md)** — Full CQRS architecture with commands, queries, and
   events spanning Domain message primitives, Application service contracts, and Adapter
   implementations including sync buses, async Messenger bridges, and auto-wiring
   compiler passes.

4. **[validation](validation.md)** — Declarative, attribute-driven input validation for
   controller actions using `#[Validation]`, built-in rules (`required`, `email`,
   `min_length`, etc.), and `ValidationException` response handling.

5. **[specifications](specifications.md)** — Composable business rules via
   `CompositeSpecification` with `and()`, `or()`, `not()` combinators for clean,
   testable domain logic.

6. **[repositories](repositories.md)** — Standard DTOs for paginated queries
   (`Pagination` input, `ResultSet` output) and the `UnitOfWork` interface with its
   `DoctrineUnitOfWork` adapter.

7. **[templating](templating.md)** — Template engine abstraction (`PhpEngine`,
   `TwigEngine`, `DelegatingEngine`) with inheritance, blocks, injectable helpers,
   and escaping strategies.

8. **[http-client](http-client.md)** — PSR-7 message factories, transport contract,
   promise interface, Guzzle adapter, PSR-3 logging decorator, and `HttpService`
   facade combining all factories.

9. **[mail](mail.md)** — Email transport abstraction with fluent `MailMessage`,
   `Attachment` (with inline embedding), `MailService` facade, Symfony and
   logging/null transport adapters.

10. **[cache](cache.md)** — Cache-through abstraction (`Cache::read()` with loader
    callback). Single `PsrCache` adapter wrapping any PSR-6 cache pool.

11. **[routing](routing.md)** — `UrlGenerator` interface for framework-agnostic URL
    generation, with a `SymfonyUrlGenerator` adapter.

12. **[sockets](sockets.md)** — Real-time messaging via Mercure hub publishing,
    with the `Socket\Publisher` port and Mercure adapter.

13. **[files](files.md)** — Two components: `FileStorage` (abstract file operations
    via Flysystem) and `Filesystem` (local OS operations via Symfony), with a
    `StorageService` registry for multi-storage scenarios.

14. **[auth](auth.md)** — Two subsystems: HMAC request signing/validation and
    Security (password hashing via `password_hash()`, JWT via `lcobucci/jwt`).

15. **[dependency-injection](dependency-injection.md)** — Lightweight PSR-11
    compatible container for no-framework contexts (CLI, daemons, testing).

16. **[serialization](serialization.md)** — `JsonSerializer` and `PhpSerializer`
    using the `Serializable` interface for domain object serialization.

17. **[utilities](utilities.md)** — Static utility classes: `ClassName`,
    `FastHasher`, `Validate`, `VarPrinter`, and `Type` for common cross-cutting
    operations.
