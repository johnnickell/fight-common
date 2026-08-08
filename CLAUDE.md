# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Agent Skills and Planning

Read root `CONTEXT.md` for project vocabulary and accepted Event Sourcing boundaries. Durable work tracking lives in `planning/`; use its focused instructions:

- `planning/agents/issue-tracker.md` — canonical ticket resolution and synchronization.
- `planning/agents/triage-labels.md` — allowed workflow states.
- `planning/agents/domain.md` — architecture and Event Sourcing rules.

Release coordination is documented in the canonical [Wayfinder map](planning/wayfinder/fight-common-release-coordination-map.md)
and its linked ticket contracts, including [WF-004 release skill contracts](planning/wayfinder/tickets/WF-004-release-skill-contracts.md).
Keep release policy in those planning artifacts and repository-owned commands; do not duplicate it in
`AGENTS.md` or other agent instructions.

For coordinated builds, use `.runs/<YYYY-MM-DD>-<slug>/` for local plans, spokes, results, and traces. `.runs/` is gitignored and must never be staged. Copy durable outcomes and deviations back to the canonical ticket.

## Commands

All tooling runs inside a PHP 8.5 Docker container (`fight-common`). The `./bin/` scripts are convenience wrappers for interactive terminal use — they all pass `-it` (TTY required) and rebuild the image on every call, so **do not use them from Claude Code**.

### For the user (interactive terminal)

```bash
./bin/phpunit                                          # run full test suite
./bin/phpunit tests/Adapter/Doctrine/UuidDataTypeTest.php  # run a single file
./bin/phpunit --filter test_method_name                # run a single test
./bin/composer require vendor/package                  # manage dependencies
./bin/rector process src/                              # run code modernization
./bin/phpstan                                           # static analysis (level 6 default)
./bin/phpstan --bleeding-edge                           # with bleeding-edge rules
./bin/exec php -r "echo 'hello';"                      # run arbitrary PHP
```

### For Claude Code (non-interactive Docker)

Claude Code must drop the `-it` flag and call `docker run --rm` directly against the pre-built `fight-common` image. The image must already be built (run any `./bin/` command once to build it).

```bash
# Run full test suite with coverage
docker run --rm -v $(pwd):/app:delegated -w /app fight-common \
    php vendor/bin/phpunit

# Run a single test file
docker run --rm -v $(pwd):/app:delegated -w /app fight-common \
    php vendor/bin/phpunit tests/Adapter/Doctrine/UuidDataTypeTest.php

# Run tests matching a name pattern
docker run --rm -v $(pwd):/app:delegated -w /app fight-common \
    php vendor/bin/phpunit --filter test_method_name

# Run rector (dry-run first, then without --dry-run to apply)
docker run --rm -v $(pwd):/app:delegated -w /app fight-common \
    php vendor/bin/rector process src/ --dry-run

# Run static analysis (level 6)
docker run --rm -v $(pwd):/app:delegated -w /app fight-common \
    php vendor/bin/phpstan analyse

# With bleeding-edge rules
docker run --rm -v $(pwd):/app:delegated -w /app fight-common \
    php vendor/bin/phpstan analyse --bleeding-edge

# Run arbitrary PHP
docker run --rm -v $(pwd):/app:delegated -w /app fight-common \
    php -r "echo PHP_VERSION;"
```

### Submit Gate

**Always run the full submit gate before committing or pushing any feature work.** Run these in order and fix all findings before committing:

```bash
# 1. Apply Rector modernisations (apply first, then re-check)
docker run --rm -v $(pwd):/app:delegated -w /app fight-common \
    php vendor/bin/rector process src/ --dry-run

# 2. Static analysis
docker run --rm -v $(pwd):/app:delegated -w /app fight-common \
    php vendor/bin/phpstan analyse

# 3. Code style
docker run --rm -v $(pwd):/app:delegated -w /app fight-common \
    php vendor/bin/phpcs

# 4. Full test suite with coverage
docker run --rm -v $(pwd):/app:delegated -w /app fight-common \
    php vendor/bin/phpunit

# 5. Validate planning metadata and dependency edges
./bin/planning-check
```

All five must be clean before a commit lands on any branch.

### Git Flow

This repo follows [git-flow](https://nvie.com/posts/a-successful-git-branching-model/):

- **`main`** — production-ready code, merges from `develop` only
- **`develop`** — integration branch for completed features
- **`feature/<name>`** — branched from `develop`, merged back via `--no-ff`
- Always create a feature branch before starting work — never commit directly to `develop`

### GitHub CLI Authentication

On macOS, a sandboxed `gh auth status` may falsely report an invalid token when the process cannot access the
keyring. Before asking the user to authenticate again, rerun `gh auth status` with keyring-enabled access. Treat
a successful user-shell or keyring-enabled check as authoritative; do not require the user to repeatedly prove an
existing authenticated session.

Coverage reports are written to `var/reports/coverage/clover.xml` (XML) and `var/reports/coverage/` (HTML) automatically when the full suite runs with Xdebug loaded. Parse clover.xml with Python to check coverage gaps:

```bash
python3 -c "
import xml.etree.ElementTree as ET
tree = ET.parse('var/reports/coverage/clover.xml')
root = tree.getroot()
project = root.find('project')
total_s = total_c = 0
gaps = []
for f in project.findall('.//file'):
    if '/src/' not in f.get('name', ''):
        continue
    m = f.find('metrics')
    s, c = int(m.get('statements', 0)), int(m.get('coveredstatements', 0))
    total_s += s; total_c += c
    if c < s:
        gaps.append((s - c, f.get('name').split('/src/')[-1], c, s))
gaps.sort(reverse=True)
print(f'Overall: {total_c}/{total_s} ({100*total_c/total_s:.2f}%)')
for gap, path, c, s in gaps:
    print(f'  -{gap:3d}  {c}/{s}  {path}')
"
```

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

`GeneratorIterator` supports rewind by re-invoking the generator
function rather than rewinding the generator itself, which is not
possible in PHP.

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
