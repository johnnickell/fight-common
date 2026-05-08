# $FIGHT Common

A shared PHP library for $FIGHT projects implementing Hexagonal (Ports & Adapters) / Clean Architecture. Provides foundational building blocks including value objects, typed collections, CQRS messaging, a composable validation system, and infrastructure adapters.

## Requirements

- PHP 8.5+
- Docker (for local tooling)

## Installation

```bash
composer require johnnickell/fight-common
```

Optional adapters require additional packages — install only what you need:

```bash
composer require doctrine/orm           # Doctrine data types and unit of work
composer require symfony/http-kernel    # HTTP middleware and JSend response
composer require lcobucci/jwt           # JWT encoder and decoder
composer require guzzlehttp/guzzle      # HTTP client adapter
```

## Architecture

Dependencies flow inward only. The Domain has no external dependencies. The Application layer depends on Domain interfaces only. Adapters depend on both.

```
Domain      ← pure business logic, no framework dependencies
Application ← orchestrates domain via interfaces
Adapter     ← concrete infrastructure implementations
```

## What's Inside

### Domain

**Value Objects** — immutable, self-validating objects that model domain concepts:

- `StringObject`, `MbStringObject`, `JsonObject` — string and JSON primitives
- `EmailAddress`, `Uri`, `Url` — internet value types with RFC-compliant validation
- `Uuid`, `UniqueId`, `MessageId` — identifier types with multiple creation strategies

**Specifications** — composable business rules:

```php
$rule = $isActive->and($hasVerifiedEmail)->and($isNotBanned->not());
$rule->isSatisfiedBy($user); // true or false
```

**Collections** — fully typed collection hierarchy:

- `ArrayList` — ordered list with sort, slice, pagination, and predicate search
- `HashSet` — set operations: union, intersection, difference, complement
- `HashTable` — key-value map with typed keys and values
- `SortedSet` / `SortedTable` — ordered structures backed by a Red-Black tree with floor, ceiling, rank, and range operations
- `ArrayStack`, `LinkedStack`, `ArrayQueue`, `LinkedQueue`, `LinkedDeque` — typed stack and queue structures

**Messaging** — CQRS message contracts:

- `CommandMessage`, `QueryMessage`, `EventMessage` with `Meta` support
- Serializable to/from array and JSON

**Repository** — pagination and result set contracts:

- `Pagination` — page, perPage, orderings
- `ResultSet` — paginated records with total count and page metadata

### Application

**Validation** — rule-based field validation:

```php
$service->validate([
    ['field' => 'email',    'label' => 'Email',    'rules' => 'required|email'],
    ['field' => 'username', 'label' => 'Username', 'rules' => 'required|min_length[3]|max_length[20]'],
], $input);
```

**CQRS Buses** — `CommandBus` and `QueryBus` with pipeline middleware support.

**Serializers** — `JsonSerializer` and `PhpSerializer` for message serialization.

**Container** — PSR-11 compatible service container with singleton and factory registration.

### Adapters

| Adapter | Requires |
|---------|----------|
| Doctrine data types (`Uuid`, `Uri`, `Url`, `StringObject`, `JsonObject`, etc.) | `doctrine/dbal` |
| `DoctrineUnitOfWork` | `doctrine/orm` |
| `SimpleEventDispatcher`, `ServiceAwareEventDispatcher` | — |
| `RoutingCommandBus`, `RoutingQueryBus` | — |
| `PhpPasswordHasher`, `PhpPasswordValidator` | — |
| `JwtEncoder`, `JwtDecoder` | `lcobucci/jwt` |
| `JsonRequestMiddleware`, `JSendResponse` | `symfony/http-foundation` |
| `SymfonyFilesystem` | `symfony/filesystem` |
| `EventSubscriberCompilerPass` | `symfony/dependency-injection` |

## Development

All tooling runs inside a PHP 8.5 Docker container via scripts in `./bin/`. Never use `vendor/bin/` directly.

```bash
./bin/phpunit                  # run full test suite with coverage
./bin/phpunit --filter foo     # run a single test by name
./bin/rector process src/      # run code modernization
./bin/composer require pkg     # manage dependencies
```

### Coverage

100% code coverage is required and enforced by PHPUnit configuration. All test classes must declare `#[CoversClass]`.

## License

MIT
