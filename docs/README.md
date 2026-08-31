# Documentation

This directory contains the full documentation for `johnnickell/fight-common`, a shared PHP
library for projects implementing Hexagonal (Ports & Adapters) / Clean Architecture.
Each component is documented in its own file with API references, Symfony configuration, and
usage examples.

---

## Table of Contents

1. [Quick Start](quickstart.md) — Bootstrap a Symfony project with fight-common in 15 minutes
2. [Installation](#installation)
3. [Symfony Wiring](#symfony-wiring)
4. [Component Catalog](#component-catalog)
5. [Framework support and activation](framework-support.md) — Support windows, capability matrix, and opt-in framework composition

---

## Installation

```bash
composer require johnnickell/fight-common
```

PHP 8.5+ is required. The library depends on PSR-7 (`psr/http-message`), PSR-17
(`psr/http-factory`), PSR-18 (`psr/http-client`), PSR-15 (`psr/http-server-handler` and
`psr/http-server-middleware`), PSR-3 (`psr/log`), PSR-6 (`psr/cache`), and PSR-11
(`psr/container`) interfaces. Optional adapters and tooling require additional packages:

| Package | Enables | Doc |
|---|---|---|
| `deptrac/deptrac` | Optional architecture enforcement in consuming repositories | [architecture](architecture.md) |
| `doctrine/dbal` | Custom Doctrine data types for value objects | [values](values.md) |
| `doctrine/orm` | Doctrine unit of work | [repositories](repositories.md) |
| `guzzlehttp/guzzle` `guzzlehttp/psr7` | HTTP client adapter | [http-client](http-client.md) |
| `psr/simple-cache` | PSR-16 read-through cache adapter | [architecture](architecture.md) |
| `slim/slim` | Slim named-route URL generator and explicit PSR composition | [architecture](architecture.md) |
| `lcobucci/jwt` | JWT encoder and decoder | [auth](auth.md) |
| `league/flysystem` | File storage adapter (Flysystem) | [files](files.md) |
| `symfony/dependency-injection` | Compiler passes for auto-wiring handlers | [messaging](messaging.md) |
| `symfony/event-dispatcher` | Validation event subscriber | [validation](validation.md) |
| `symfony/filesystem` | Local filesystem adapter | [files](files.md) |
| `dragonmantank/cron-expression` | Cron expression parsing for Scheduler | [scheduler](scheduler.md) |
| `phpseclib/phpseclib` | SFTP file transport adapter | [file-transfer](file-transfer.md) |
| `twilio/sdk` | Twilio SMS transport adapter | [sms](sms.md) |
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

Register only the capability-specific compiler passes your application uses, from
`Fight\Common\Adapter\ServiceContainer\Symfony`, to auto-wire command handlers,
filters, query handlers, event subscribers, and template helpers. Matching
`Fight\Common\Adapter\DependencyInjection` FQCNs remain deprecated 1.x compatibility identities:

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
identities for existing consumers; register the canonical paths above in new configuration.

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
   (`Pagination` input, `ResultSet` output) and the `TransactionalUnitOfWork` interface with its
   canonical `Fight\Common\Adapter\Persistence\Doctrine\DoctrineTransactionalUnitOfWork` adapter.
   `UnitOfWork::commit()` and `Fight\Common\Adapter\Repository\DoctrineUnitOfWork` are documented only as
   deprecated 1.x compatibility.

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

14. **[codeigniter](codeigniter.md)** — Native cache, JSend response, and routing
    adapters plus independently selectable mail, template, and filesystem fallbacks.

14. **[auth](auth.md)** — Two subsystems: HMAC request signing/validation and
    Security (password hashing via `password_hash()`, JWT via `lcobucci/jwt`).

15. **[dependency-injection](dependency-injection.md)** — Lightweight PSR-11
    compatible container for no-framework contexts (CLI, daemons, testing).

16. **[serialization](serialization.md)** — `JsonSerializer` and `PhpSerializer`
    using the `Serializable` interface for domain object serialization.

17. **[utilities](utilities.md)** — Static utility classes: `ClassName`,
    `FastHasher`, `Validate`, `VarPrinter`, and `Type` for common cross-cutting
    operations.

18. **[sms](sms.md)** — SMS/MMS transport abstraction with `SmsMessage`, `SmsService`
    facade, and adapters for Twilio, PSR-3 logging, and no-op null.

19. **[file-transfer](file-transfer.md)** — Remote file transfer port (`FileTransport`)
    with `FileTransferService` registry and adapters for SFTP (phpseclib3), FTP, logging,
    and null.

20. **[process](process.md)** — Concurrent shell process runner with configurable
    concurrency, retry logic, output streaming, and PSR-3 logging via
    `SymfonyProcessRunner`.

21. **[scheduler](scheduler.md)** — Cron-style job scheduler with file-based locking,
    cron/datetime/callable schedules, output capture, failure notification, and max
    runtime guard.

22. **[architecture](architecture.md)** — Enforced inward dependency direction, exact external allowances,
    and optional consumer Deptrac guidance.

23. **[coding-standard](coding-standard.md)** — Optional `FightCommon` PHP_CodeSniffer standard,
    copy-ready consumer configuration, stable identifiers, configurable properties, and parity evidence.
