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
| 4 | Messaging (event dispatchers, command/query buses) | 🔲 Pending | — |
| 5 | DI Compiler Passes (6 passes) | 🔲 Pending | — |
| 6 | Remaining (Filesystem, Mail, Templating, Routing) | 🔲 Pending | — |
| D | Domain gaps (Uri, RedBlackSearchTree, RulesParser) | 🔲 Pending | — |

---

## Batch 4 — Messaging

| File | Stmts | Covered |
|------|-------|---------|
| `Adapter/Messaging/Event/Sync/SimpleEventDispatcher.php` | 59 | 0 |
| `Adapter/Messaging/Event/Sync/ServiceAwareEventDispatcher.php` | 62 | 0 |
| `Adapter/Messaging/Command/Sync/CommandPipeline.php` | 9 | 0 |
| `Adapter/Messaging/Command/Sync/RoutingCommandBus.php` | 4 | 0 |
| `Adapter/Messaging/Command/Sync/Routing/InMemoryCommandRouter.php` | 13 | 0 |
| `Adapter/Messaging/Command/Sync/Routing/ServiceAwareCommandRouter.php` | 18 | 0 |
| `Adapter/Messaging/Query/QueryPipeline.php` | 13 | 0 |
| `Adapter/Messaging/Query/RoutingQueryBus.php` | 4 | 0 |
| `Adapter/Messaging/Query/Routing/InMemoryQueryRouter.php` | 13 | 0 |
| `Adapter/Messaging/Query/Routing/ServiceAwareQueryRouter.php` | 18 | 0 |
| `Adapter/Messaging/Serializer/SymfonyMessageSerializer.php` | 55 | 50 (gap: 5) |

## Batch 5 — DI Compiler Passes

| File | Stmts | Covered |
|------|-------|---------|
| `Adapter/DependencyInjection/CommandHandlerCompilerPass.php` | 16 | 0 |
| `Adapter/DependencyInjection/QueryHandlerCompilerPass.php` | 16 | 0 |
| `Adapter/DependencyInjection/EventSubscriberCompilerPass.php` | 15 | 0 |
| `Adapter/DependencyInjection/TemplateHelperCompilerPass.php` | 12 | 0 |
| `Adapter/DependencyInjection/QueryFilterCompilerPass.php` | 12 | 0 |
| `Adapter/DependencyInjection/CommandFilterCompilerPass.php` | 12 | 0 |

## Batch 6 — Remaining Adapters

| File | Stmts | Covered |
|------|-------|---------|
| `Adapter/Filesystem/SymfonyFilesystem.php` | 122 | 0 |
| `Adapter/Mail/Symfony/SymfonyMailTransport.php` | 103 | 0 |
| `Adapter/Templating/PhpEngine.php` | 84 | 0 |
| `Adapter/Routing/SymfonyUrlGenerator.php` | 36 | 0 |
| `Adapter/FileStorage/FlysystemStorage.php` | 58 | 36 (gap: 22) |
| `Adapter/Templating/DelegatingEngine.php` | 25 | 0 |
| `Adapter/Mail/Symfony/SymfonyAttachment.php` | 23 | 0 |
| `Adapter/EventSubscriber/SymfonyValidationSubscriber.php` | 16 | 0 |
| `Adapter/Mail/Logging/LoggingMailTransport.php` | 16 | 0 |
| `Adapter/Templating/TwigEngine.php` | 15 | 0 |
| `Adapter/Mail/Symfony/SymfonyMailFactory.php` | 14 | 0 |
| `Adapter/Repository/DoctrineUnitOfWork.php` | 4 | 0 |
| `Adapter/Mail/Null/NullMailTransport.php` | 1 | 0 |

## Domain Gaps

| File | Stmts | Covered | Gap |
|------|-------|---------|-----|
| `Domain/Value/Internet/Uri.php` | 387 | 372 | 15 |
| `Domain/Collection/Tree/RedBlackSearchTree.php` | 248 | 236 | 12 |
| `Application/Validation/RulesParser.php` | 142 | 132 | 10 |
| `Domain/Value/Basic/MbStringObject.php` | 379 | 377 | 2 |
| `Adapter/Auth/Security/JwtEncoder.php` | 42 | 40 | 2 |
| `Adapter/Auth/Hmac/HmacMethods.php` | 51 | 49 | 2 |
| `Domain/Value/Basic/StringObject.php` | 305 | 304 | 1 |
| `Domain/Collection/ArrayQueue.php` | 123 | 122 | 1 |
