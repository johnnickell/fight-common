# AGENTS.md

Agent instructions for `johnnickell/fight-common`.

## Work Routing

**Frontier:** When `/ask-matt` is invoked without a task or additional context, read
`planning/tickets/BOARD.md` and `planning/CONVENTIONS.md` before responding. Use the board's
“What's Next?” contract to return the current human decision and first ready implementation ticket,
and use the conventions to interpret ticket status and ordering.

## Run and Worktree Isolation

Coordinate-build evidence belongs in `.runs/<YYYY-MM-DD>-<slug>/`. It is gitignored and must never be staged.
Use that run directory as the parent for any disposable linked worktree required by the approved task:

```bash
git worktree add -b feature/<slug> .runs/<YYYY-MM-DD>-<slug>/worktree develop
```

Run all commands from that linked worktree, preserve other worktrees and services, and remove only the
task-owned worktree after separate cleanup authorization. Durable decisions and outcomes belong in the canonical
planning artifact, not the ignored run directory.

Tooling runs in the isolated `fight-common` PHP container. For focused iteration, run the documented
noninteractive container command from the target checkout:

```bash
docker container run --rm -v "$PWD:/app:delegated" -w /app fight-common \
  php vendor/bin/phpunit tests/Domain/Specification/AndSpecificationTest.php
```

`./bin/build` is the canonical completion gate.

For a long non-interactive build, run `screen -dmS <ticket>-build /bin/zsh -lc './bin/build > /private/tmp/<ticket>-build.log 2>&1; print -r -- $? > /private/tmp/<ticket>-build.exit'`, then inspect the log and require an exit file containing `0`; never treat foreground timeout output as a build result.

## Architecture

Hexagonal (Ports & Adapters) with strict layer separation:

```
Adapter → Application → Domain
```

| Layer | Location | Rules |
|-------|----------|-------|
| **Domain** | `src/Domain/` | Pure business logic. No framework or application dependencies. |
| **Application** | `src/Application/` | Use-case coordination. Depends on Domain, PHP internals, PSR contracts, and the allowlisted scheduler expression contract. Never depends on Adapter. |
| **Adapter** | `src/Adapter/` | Framework/infrastructure integrations. Depends on Application + Domain. |
| **Standards** | `src/Standards/` | Orthogonal coding standards. No runtime dependents. |

Enforced by Deptrac (`deptrac.php` / `deptrac.runtime.php`). Violations fail the build.

### Namespaces

| Path | Namespace |
|------|-----------|
| `src/` | `Fight\Common\` |
| `tests/` | `Fight\Test\Common\` |
| `release/` | `Fight\Release\` |

Test namespaces mirror source: `src/Domain/Specification/AndSpecification.php` → `tests/Domain/Specification/AndSpecificationTest.php`.

## Conventions

- **Value objects**: Immutable, validate on construction, throw `DomainException` on invalid input. Named factories (`fromString()`, `fromArray()`) are the public API.
- **Specifications**: Extend `CompositeSpecification`, implement `isSatisfiedBy(mixed $candidate): bool`, compose via `and()`/`or()`/`not()`.
- **Collections**: Typed. Use `ArrayList::of(string $type)` or `HashTable::of(string $keyType, string $valueType)`.
- **CQRS**: Commands → `CommandBus::execute()`, Queries → `QueryBus::fetch()`, Events → `EventDispatcher::trigger()`. Messages carry `MessageId`, `Meta`, timestamp.
- **Deprecation**: Deprecated public API stays supported for ≥1 released minor before removal in the next major.

## Tests

### Rules

- **100% statement coverage required** — enforced by the coverage gate (`requireCoverageMetadata` enabled)
- Every test class extends `Fight\Test\Common\TestCase\UnitTestCase`
- Every test class carries `#[CoversClass(Target::class)]`
- Method naming: `test_that_<subject>_<condition>()`
- Assertions: `self::assertTrue`, `self::assertSame`, `self::assertInstanceOf`, etc.

### Stubs vs Mocks

- **Anonymous classes** extending `CompositeSpecification` or implementing interfaces for simple stubs
- **`$this->mock(ClassName::class)`** (Mockery) for asserting call expectations or stubbing return values
- **Never mock value objects or domain primitives** — construct them directly

### Template

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
    }
}
```

## Pre-Submit Gate

**Always run before committing or creating a PR:**

```bash
./bin/build
```

This runs the complete submit gate: PHPCS, Deptrac architecture enforcement, PHPStan static analysis,
Rector (dry-run), and the full PHPUnit test suite with coverage.

For focused iteration:

```bash
docker run --rm -v $(pwd):/app:delegated -w /app fight-common \
    php vendor/bin/phpunit tests/Domain/Specification/AndSpecificationTest.php

docker run --rm -v $(pwd):/app:delegated -w /app fight-common \
    php vendor/bin/phpunit --filter test_method_name

docker run --rm -v $(pwd):/app:delegated -w /app fight-common \
    php vendor/bin/phpstan analyse

docker run --rm -v $(pwd):/app:delegated -w /app fight-common \
    php vendor/bin/rector process src/ --dry-run
```

The `./bin/*` wrappers use `-it` and rebuild the image — those are for interactive terminal use only.

### Learning: hooks are non-negotiable gates

If a commit hook runs this package gate, the hook itself must complete successfully before the commit is created.
Never use `git commit --no-verify`, disable the hook, or otherwise bypass it because a run is slow, interrupted, or
inconvenient. Diagnose and repair every failure, then rerun the hook to completion. An exception requires the user's
explicit authorization for that specific commit and must be reported as an unverified delivery state.

## Git Flow

- `main` — production-ready, merges from `develop` only
- `develop` — integration branch for completed features
- `feature/<name>` — branched from `develop`, merged back via `--no-ff`
- Never commit directly to `develop`

## Planning

See `planning/CONVENTIONS.md` for the canonical planning structure: ticket lifecycle, BOARD.md execution frontier,
Wayfinder maps, PRD and epic conventions, file naming, templates, and explicit-only archive operations. Never
archive planning records as a completion side effect; run `./bin/archive-planning` only on an explicit request,
review its dry run, and then apply it.

### Pre-PR Sync Checklist

Before final commit and PR for any feature or bug fix:

1. Mark the ticket `done` with verified acceptance criteria
2. Move the ticket to **Recently Done** in `planning/tickets/BOARD.md`
3. Recalculate the "What's Next?" contract if dependencies shifted
4. Update parent PRD and epic progress sections
5. Update `ROADMAP.md` if strategic progress changed
6. Verify no downstream ticket still lists the completed ticket as `blocked_by`
