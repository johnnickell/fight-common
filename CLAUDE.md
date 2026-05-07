# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Commands

All tooling runs through bash scripts in `./bin/` that execute inside a PHP 8.5 Docker container. Never use `vendor/bin/` directly.

```bash
./bin/phpunit                                         # run full test suite
./bin/phpunit tests/Domain/Specification/FooTest.php  # run a single test file
./bin/phpunit --filter test_method_name               # run a single test by name
./bin/composer require vendor/package                 # manage dependencies
./bin/rector process src/                             # run code modernization
./bin/exec php -r "echo 'hello';"                     # run arbitrary commands
```

The `./bin/phpunit` script uses `docker run -it`, so it requires a TTY and must be run by the user in their terminal — it cannot be invoked non-interactively from within Claude Code.

## Architecture

This is a shared PHP library (`johnnickell/fight-common`) implementing **Hexagonal (Ports & Adapters) / Clean Architecture** with strict layer separation. Dependencies flow inward only — Domain has no dependencies, Application depends on Domain, Adapter depends on Application and Domain

- **`src/Domain/`** — pure business logic, no framework dependencies
- **`src/Application/`** — orchestrates domain, depends on domain interfaces only
- **`src/Adapter/`** — infrastructure implementations, depends on application + domain

### Layer Contents

**`src/Domain/`**
- Value objects (`Value/`) — immutable, self-validating, implement `Value` interface, extend `ValueObject`
- Specifications (`Specification/`) — composable business rules via `CompositeSpecification`
- Collections (`Collection/`) — typed collection hierarchy: `ArrayList`, `HashSet`, `HashTable`, `SortedSet`, `SortedTable`, stacks, queues, deque; backed by `RedBlackSearchTree` for ordered structures
- Messaging contracts (`Messaging/`) — `CommandMessage`, `QueryMessage`, `EventMessage`, `Meta`, `MessageId`
- Repository interfaces (`Repository/`) — `Pagination`, `ResultSet`, `UnitOfWork`
- Domain exceptions (`Exception/`)
- Type system (`Type/`) — `Comparator`, `Comparable`, `Equatable`, `Arrayable`, `Type`

**`src/Application/`**
- CQRS buses — `CommandBus`, `QueryBus` with pipeline support
- Validation — `ValidationService`, `ValidationCoordinator`, `RulesParser`, rule classes, specifications
- Service contracts — `Container`, `Filesystem`, event dispatching
- Serializers — `JsonSerializer`, `PhpSerializer`

**`src/Adapter/`**
- Doctrine ORM — data types, unit of work, entity listener
- Symfony — HTTP kernel, filesystem, DI compiler pass
- Messaging — `SimpleEventDispatcher`, `ServiceAwareEventDispatcher`
- Auth — `PhpPasswordHasher`, `JwtEncoder`/`JwtDecoder`
- HTTP — Guzzle client adapter

### Namespaces

| Path | Namespace |
|------|-----------|
| `src/` | `Fight\Common\` |
| `tests/` | `Fight\Test\Common\` |

Test namespaces mirror source namespaces exactly. `src/Domain/Specification/AndSpecification.php` → `tests/Domain/Specification/AndSpecificationTest.php`.

### Key Patterns

**Specification pattern** (`src/Domain/Specification/`): `Specification` interface → `CompositeSpecification` abstract base (provides `and()`, `or()`, `not()` combinators) → concrete `AndSpecification`, `OrSpecification`, `NotSpecification`. Domain rules are expressed by extending `CompositeSpecification` and implementing `isSatisfiedBy(mixed $candidate): bool`.

**CQRS messaging**: Commands go through `CommandBus`, queries through `QueryBus`. Each has `execute`/`fetch` for direct dispatch and `dispatch` for message-wrapped dispatch. Events flow through `EventDispatcher` — `trigger()` wraps an `Event` in an `EventMessage` and dispatches it.

**Value objects**: Immutable. Always validate on construction and throw `DomainException` for invalid input. Named constructors (`fromString()`, `fromArray()`, `create()`) are the public API — constructors may be private or protected.

**Repository pattern**: Interfaces accept `Pagination` (page/perPage/orderings) and return `ResultSet` (implements `Collection`, `Arrayable`, `JsonSerializable`).

**Collections**: All collections are typed. Use `ArrayList::of(string $type)` or `HashTable::of(string $keyType, string $valueType)` to create typed collections. Ordered collections (`SortedSet`, `SortedTable`) use a `RedBlackSearchTree` internally and require a `Comparator`.

## Tests

### Coverage Requirement

**100% code coverage is required and must be maintained.** PHPUnit `requireCoverageMetadata` is enforced — every test class must declare which class it covers.

### Test Structure

Every test class must:
- Extend `Fight\Test\Common\TestCase\UnitTestCase`
- Carry `#[CoversClass(ClassName::class)]` attribute
- Use snake_case method names: `test_that_<subject>_<condition>()`
- Assert with `self::assertTrue` / `self::assertFalse` / `self::assertSame` / etc.

### Stubs vs Mocks

- **Anonymous classes** extending `CompositeSpecification` or implementing the relevant interface are preferred for simple behavioral stubs
- **`$this->mock(ClassName::class)`** (wraps Mockery) is used when you need to assert call expectations or stub return values on a dependency
- Avoid mocking value objects or domain primitives — construct them directly

### Test File Template

```php
<?php

declare(strict_types=1);

namespace Fight\Test\Common\Domain\Specification;

use Fight\Common\Domain\Specification\AndSpecification;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(AndSpecification::class)]
class AndSpecificationTest extends UnitTestCase
{
    public function test_that_is_satisfied_by_returns_true_when_both_specs_are_satisfied(): void
    {
        // ...
    }
}
```

### Test Plan

Phases in priority order:

1. **Domain Specifications** — `AndSpecification`, `OrSpecification`, `NotSpecification`, `CompositeSpecification`
2. **Domain Value Objects** — `Uuid`, `UniqueId`, `MessageId`, `MbStringObject`, `Uri`, `Url`
3. **Domain Collections** — iterators → bucket chains → `ArrayList`, `HashSet`, `HashTable`, `SortedSet`, `SortedTable`, stacks, queues, `LinkedDeque`, comparators, `RedBlackSearchTree`
4. **Domain Messaging** — `Meta`, `CommandMessage`, `EventMessage`, `QueryMessage`, `CommandFailedEvent`
5. **Domain Serializers** — `JsonSerializer`, `PhpSerializer`
6. **Application Validation** — `RulesParser`, `ValidationCoordinator`, `ValidationService`, all rule classes, `ValidationResult`
7. **Application Infrastructure** — `Container`, `ValidationException`, `InputData`, `ApplicationData`, `ErrorData`
8. **Adapter Layer** — Doctrine data types, `SimpleEventDispatcher`, `ServiceAwareEventDispatcher`, command/query buses, auth adapters, `SymfonyFilesystem`

## Future: Event Sourcing

Planned addition: **CLI Projectors** for building transient read state from event streams. When implemented, projectors will live in `src/Application/Projection/` and follow these conventions:
- A `Projector` interface with a `project(EventMessage $event): void` method
- CLI entry points in `src/Adapter/Cli/`
- Projectors depend only on domain event types and application-layer read models
- State is transient (rebuilt on demand) — no persistent projection store in v1
- Tests for projectors use real `EventMessage` instances constructed from domain events, not mocks
