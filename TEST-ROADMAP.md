# Test Coverage Roadmap

Target: **100% code coverage** (minimum 99.10% acceptable)

Coverage is enforced via PHPUnit `requireCoverageMetadata`. Every test class must declare `#[CoversClass]`.

Non-interactive test run:
```bash
docker run --rm -v $(pwd):/app:delegated -w /app fight-common php vendor/bin/phpunit
```

---

## Progress

| Batch | Description | Status | Coverage Gained |
|-------|-------------|--------|-----------------|
| 1 | Auth/Cache adapters (HMAC, JWT, Password, PsrCache) | ✅ Done | — |
| 2 | All 11 Doctrine Data Types | ✅ Done | 85.21% |
| 3 | HTTP/Foundation/Guzzle adapters | ✅ Done | 87.77% |
| 4 | Messaging (event dispatchers, command/query buses) | ✅ Done | ~90% |
| 5 | DI Compiler Passes (6 passes) | ✅ Done | 92.21% |
| 6 | Remaining (Filesystem, Mail, Templating, Routing) | ✅ Done | ~99% |
| D | Domain gaps + coverage polish | ✅ Done | **100.00%** |

---

## Batch 4 — Messaging (✅ Done)

All messaging adapter files are fully tested. Major gap: `SymfonyMessageSerializer` has 1 uncovered statement.

## Batch 5 — DI Compiler Passes (✅ Done)

All 6 compiler passes at 100% coverage. 27 tests, 75 assertions.

| File | Stmts | Covered |
|------|-------|---------|
| `Adapter/DependencyInjection/CommandHandlerCompilerPass.php` | 16 | 16 |
| `Adapter/DependencyInjection/QueryHandlerCompilerPass.php` | 16 | 16 |
| `Adapter/DependencyInjection/EventSubscriberCompilerPass.php` | 15 | 15 |
| `Adapter/DependencyInjection/TemplateHelperCompilerPass.php` | 12 | 12 |
| `Adapter/DependencyInjection/QueryFilterCompilerPass.php` | 12 | 12 |
| `Adapter/DependencyInjection/CommandFilterCompilerPass.php` | 12 | 12 |

## Final State

**100% statement coverage achieved** across all 6761 statements.

Remaining warning: `SymfonyMessageSerializerTest` intentionally calls `trigger_error(E_USER_WARNING)` to exercise error-handling paths — this is expected and benign.
