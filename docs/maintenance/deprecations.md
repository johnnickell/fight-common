# Deprecation and migration inventory

This is the current consolidated inventory of **already-deprecated contracts**, not a removal plan or release
approval. Paths below omit `Fight\Common\`. Earlier first-deprecating minors are preserved; the four newer
groups first shipped in [released v1.5.0](https://github.com/johnnickell/fight-common/releases/tag/v1.5.0)
on 2026-11-09T20:13:04Z. Source annotations saying “Planned v1.5.0” retain their preparation-time wording;
they are not independent publication proof.

[ADR 0011](https://github.com/johnnickell/fight-common/blob/develop/planning/adr/0011-non-structural-compatibility-policy.md) requires at least **one actually
released minor** with the deprecation before separately authorized major removal. Development snapshots do not
count. Released v1.5.0 supplies that minor before separately authorized v2.0.0 removal; no mandatory v1.6.0 is invented. Existing v2.0.0
targets remain targets, not permission to remove anything in this cleanup. No runtime warning is added.

<a id="planned-v150-groups"></a>

## First released in v1.5.0

| Contract | Complete inventory and replacement | First deprecating minor |
| --- | --- | --- |
| 17 relocated MCP foundation **root spellings** | [Complete old/new operation map](mcp-foundation-namespaces.md): prefer `Application\Mcp\Capability`, `Protocol`, `Protocol\Exception`, `Http` and `Tool` homes | Released v1.5.0 |
| 9 released MCP HTTP adapter spellings | [Complete adapter map](../components/mcp/index.md#canonical-adapter-namespaces-and-1x-compatibility): `Adapter\Http\{Mcp,Symfony,CodeIgniter}` names → corresponding `Adapter\Mcp` homes | Released v1.5.0 |
| 50 public readonly property reads in 14 HTTP/OAuth/MCP classes | [Complete property-to-method map](public-property-getters.md): `$value->name` → `$value->name()` with unchanged type/nullability | Released v1.5.0 |
| 4 default-only schema `TABLES` arrays (13 table names) | [Complete constant-to-selection map](table-name-constants.md): Execution/Task/Notice/Subscription schema constants → the actual explicitly composed selection accessors | Released v1.5.0 |

Foundation deprecation belongs only to the old spelling, **not** the shared runtime declaration or canonical
alias. `McpResponder` and `McpDiagnostics` stay at root without deprecation. A class-wide deprecated annotation
would incorrectly deprecate both names, so the foundation map/manifest carry spelling-only metadata. The other
three groups have existing member/class annotations identifying the planned minor that has now shipped. Standards is wholly excluded
from the property migration; native ORM mapping and Doctrine type-name constants are not table constants.

## Already released in v1.2.0

The [v1.2.0 changelog](https://github.com/johnnickell/fight-common/blob/develop/CHANGELOG.md),
[ADR 0019 migration worksheet](https://github.com/johnnickell/fight-common/blob/develop/planning/adr/0019-capability-first-adapter-namespaces-and-1x-compatibility.md)
and linked source own this history. These are **not** new v1.5.0 deprecations.

| Deprecated contract | Replacement / migration | Source and contract authority |
| --- | --- | --- |
| `Adapter\Cache\PsrCache` | `Adapter\Cache\Psr6\Psr6Cache` | [Source](https://github.com/johnnickell/fight-common/blob/develop/src/Adapter/Cache/PsrCache.php), [cache guide](../components/cache/index.md) |
| Raw `Adapter\HttpFoundation\JSendResponse` | Typed `Adapter\Http\Symfony\JSendResponse`; translate raw input/encoding intentionally, not just an import rename | [Source](https://github.com/johnnickell/fight-common/blob/develop/src/Adapter/HttpFoundation/JSendResponse.php), [HTTP migration](../safe-http-errors.md) |
| `Application\Repository\UnitOfWork`, standalone `commit()`, `Adapter\Repository\DoctrineUnitOfWork` | `TransactionalUnitOfWork::commitTransactional()` and `Adapter\Persistence\Doctrine\DoctrineTransactionalUnitOfWork` | [Port](https://github.com/johnnickell/fight-common/blob/develop/src/Application/Repository/UnitOfWork.php), [adapter](https://github.com/johnnickell/fight-common/blob/develop/src/Adapter/Repository/DoctrineUnitOfWork.php), [repositories](../components/repositories/index.md) |
| 5 Messenger paths | Complete map below; update bus/serializer registration and worker handlers together | [Messaging compatibility map](../components/messaging/index.md#1x-compatibility-names) |
| 7 `Adapter\DependencyInjection` compiler passes | Same short names under `Adapter\ServiceContainer\Symfony`; update explicit service IDs and compiler-pass registration | [Old sources](https://github.com/johnnickell/fight-common/tree/develop/src/Adapter/DependencyInjection), [messaging](../components/messaging/index.md), [Event Sourcing](../components/event-sourcing/index.md) |
| 6 Symfony adapter paths | Complete map below; update framework registration as well as imports | [ADR 0019](https://github.com/johnnickell/fight-common/blob/develop/planning/adr/0019-capability-first-adapter-namespaces-and-1x-compatibility.md) and annotated old declarations |
| 13 `Adapter\Doctrine` data types | Same short names under `Adapter\Persistence\Doctrine\Type`; retain registered database type names and existing stored encodings | [Old sources](https://github.com/johnnickell/fight-common/tree/develop/src/Adapter/Doctrine), [value migration map](../components/values/index.md) |
| Concrete `Domain\Serialization\JsonSerializer` and `PhpSerializer` | Application counterparts; retain old concrete identities while native saved objects/queues still need them | [JSON source](https://github.com/johnnickell/fight-common/blob/develop/src/Domain/Serialization/JsonSerializer.php), [PHP source](https://github.com/johnnickell/fight-common/blob/develop/src/Domain/Serialization/PhpSerializer.php), [serialization](../components/serialization/index.md) |
| `Domain\Auth\AiOperation`, `Application\Auth\WebhookDispatcher`, `Adapter\Auth\Hmac\HmacWebhookDispatcher` | No revived feature: their equally deprecated `Mcp\Operation` homes preserve the original v1.2.0 deprecation/v2.0.0 target | [Exact old/new map and caveats](../mcp-namespaces.md#released-names-and-legacy-ai-operations) |
| Legacy `Scheduler` constructor, optional `processFactory` seam and conditional Symfony Process command bridge | `Scheduler::withProcessRunner()` with an Application `ProcessRunner`; preserve exact old positional/named construction during 1.x | [Scheduler guide](../components/scheduler/index.md#legacy-command-compatibility-bridge), [ADR 0017](https://github.com/johnnickell/fight-common/blob/develop/planning/adr/0017-scheduler-1x-construction-compatibility.md) |

The five Messenger identities, relative to `Adapter\Messaging`, are:

| Old name | Canonical replacement |
| --- | --- |
| `Command\Async\MessengerCommandBus` | `Symfony\MessengerCommandBus` |
| `Event\Async\MessengerEventDispatcher` | `Symfony\MessengerEventDispatcher` |
| `Serializer\SymfonyMessageSerializer` | `Symfony\Serializer\SymfonyMessageSerializer` |
| `Handler\SymfonyCommandMessageHandler` | `Handler\CommandMessageHandler` |
| `Handler\SymfonyEventMessageHandler` | `Handler\EventMessageHandler` |

The seven compiler-pass short names are `CommandHandlerCompilerPass`, `CommandFilterCompilerPass`,
`QueryHandlerCompilerPass`, `QueryFilterCompilerPass`, `EventSubscriberCompilerPass`, `TemplateHelperCompilerPass`
and `EventMappingProviderCompilerPass`. All seven old declarations are retained, including the Event Sourcing pass.

The six remaining Symfony relocations, relative to `Adapter`, are:

| Old name | Canonical replacement |
| --- | --- |
| `EventSubscriber\SymfonyExceptionSubscriber` | `Http\Symfony\EventSubscriber\SymfonyExceptionSubscriber` |
| `EventSubscriber\SymfonyValidationSubscriber` | `Http\Symfony\EventSubscriber\SymfonyValidationSubscriber` |
| `HttpKernel\JsonRequestMiddleware` | `Middleware\Symfony\JsonRequestMiddleware` |
| `HttpKernel\ErrorController` | `Http\Symfony\Controller\ErrorController` |
| `Filesystem\SymfonyFilesystem` | `Filesystem\Symfony\SymfonyFilesystem` |
| `Routing\SymfonyUrlGenerator` | `Routing\Symfony\SymfonyUrlGenerator` |

The thirteen Doctrine short names are `AuditEntryIdDataType`, `EmailAddressDataType`, `JsonObjectDataType`,
`MbStringObjectDataType`, `MbStringTextDataType`, `MessageDataType`, `MetaDataType`, `StringObjectDataType`,
`StringTextDataType`, `TypeDataType`, `UriDataType`, `UrlDataType` and `UuidDataType`. These plus the 19 Symfony
identities above are ADR 0019's 32-name worksheet; they are not 32 newly deprecated contracts in v1.5.0.

## Already released in v1.4.0

| Deprecated contract | Replacement / migration | Authority |
| --- | --- | --- |
| Public `Domain\Repository\Pagination::__construct()` | Explicit `Pagination::strict()` after validating caller bounds/order fields; legacy permissive behavior remains functional without notices | [Source](https://github.com/johnnickell/fight-common/blob/develop/src/Domain/Repository/Pagination.php), [migration](../components/repositories/index.md#legacy-pagination-and-migration), [v1.4.0 notes](https://github.com/johnnickell/fight-common/blob/develop/CHANGELOG.md) |
| Unsafe legacy HTTP arbitrary-exception-message behavior/defaults | Explicit safe PSR-15 middleware, Symfony listener/controller or Laravel exception handler with consumer projection, challenge and redaction-aware diagnostics | [Safe HTTP migration guide](../safe-http-errors.md), [legacy PSR-15 annotation](https://github.com/johnnickell/fight-common/blob/develop/src/Adapter/Middleware/Psr15/JSendErrorMiddleware.php), [v1.4.0 notes](https://github.com/johnnickell/fight-common/blob/develop/CHANGELOG.md) |

The HTTP behavior group includes old `JSendErrorMiddleware`, Symfony exception listener/controller paths and
Laravel's legacy ErrorController/HttpServiceProvider path. It is distinct from the **v1.2.0 namespace**
deprecations above; canonical legacy HTTP names do not become safe merely by changing imports. Preserve deliberate
validation status differences and native HTML delegation; do not silently replace defaults in a minor release.

## Migration and removal decisions still owned separately

- **ResultSet legacy construction is not deprecated.** `ResultSet::strict()` is opt-in validation, not removal
  metadata. Domain serializer **interfaces** are likewise not deprecated by the concrete serializer migration.
- Namespace aspirations alone do not deprecate `Application\Service\Container`,
  `Application\HttpFoundation\HttpMethod` or `HttpStatus`. Any additional desired removal, platform floor,
  coding-standard tightening or replacement policy needs John's decision before release, not an inferred list.
- Do not mechanically delete root runtime owners, canonical alias targets or serializer predecessors. Foundation
  aliases write released identities/private scopes; literal container keys are not automatically equivalent.
  Update explicit service registrations, worker/queue payload dependencies and framework wiring deliberately.
  Native unserialization remains trusted-only; live collaborator/service graphs gain no new storage guarantee.
- Preserve semantic backward reads and storage meanings. Table configuration performs no data migration; changing
  nonce tables does not transfer replay memory, and new cursors/feeds do not establish continuity. Operators own
  coordinated data movement, backups and recovery, not this deprecation inventory.
- The [actual support policy](https://github.com/johnnickell/fight-common/blob/develop/SUPPORTED_VERSIONS.md) still governs every supported line, including the
  limited six-month final-1.x obligation after a new major. Do not infer dates from a planned version, expire a
  line early, or treat a maintenance branch as proof of support. This inventory records the observed v1.5.0
  publication; it does not authorize removal of APIs in v2.0.0.
