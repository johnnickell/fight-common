# Agent and MCP subsystem namespaces

All agent tracking and MCP-owned runtime capabilities have canonical homes under `Fight\Common\Domain\Mcp`,
`Fight\Common\Application\Mcp` and `Fight\Common\Adapter\Mcp`. This includes recording and inspecting execution,
retained notices, durable Task lifecycle, subscription tracking, protocol serving, Tools, Resources, Prompts,
Skills and the legacy AI-operation/webhook APIs. MCP is the subsystem boundary, not just the transport facade.
TASK-00177 established this map for the develop capabilities; durable Task-input additions follow the same
`Domain\Mcp\Task` and `Application\Mcp\Task` homes. See [durable Task input](task-input.md).
Complete [MCP Tasks serving](mcp-tasks.md) adds `McpTasks`, `McpTaskTool` and `McpTaskHandle` under
`Application\Mcp\Task`, preserving the released ordinary Tool and interactive resume signatures.
Ordinary stateless form elicitation and destructive confirmation remain unchanged.

Domain remains framework-free and organized by its actual concepts. Application coordinates through inward
contracts and the normal buses; adapters own persistence, cryptography, transport and native framework integration.
Grouping these capabilities does not require an MCP HTTP connection to use their shared Commands or Queries.
It does not turn a Task into a job executor, an observation into Workflow authority or a notice into authoritative state.

## Approved aggregate alignment target

The [Architecture standard](https://github.com/johnnickell/fight-common/blob/develop/docs/engineering/standards/Architecture.md#aggregate-ownership-and-namespaces) owns the
placement rules and exceptions. John's [TASK-00183](https://github.com/johnnickell/fight-common/blob/develop/planning/tasks/00183-TASK.md) correction retains the MCP
umbrella while specializing the earlier [ADR 0023](https://github.com/johnnickell/fight-common/blob/develop/planning/adr/0023-service-container-and-framework-adapter-namespaces.md).
The following destinations remain **approved but pending their owning refactors**:

| Current source | Approved target / owning work |
| --- | --- |
| `Application\Mcp\Execution\{Session,Phase,Observation,Detail}` | Aggregate owners directly under `Application\Mcp\{Session,Phase,Observation,Detail}`; TASK-00191 |
| Concrete execution inspection Tools in `Application\Mcp\Execution` and `Application\Mcp\Notice\RecoverNoticesTool` | `Adapter\Mcp\Tool\{Session,Phase,Observation,Detail,Notice}`, with genuinely shared mapping support under `Tool\Execution`; TASK-00191 |
| MCP aggregate persistence/read adapters under `Adapter\Mcp\{Execution,Task,Notice,Subscription}` | `Adapter\Mcp\Persistence\{Aggregate}\Dbal`, with real shared schema/serialization support under `Persistence\Execution\Dbal`; TASK-00191 |
| Combined Application Notice store | Domain aggregate repository and separately injected existing `TransactionalUnitOfWork`; TASK-00186 |
| Domain Task disclosure encoding | Separate outward disclosure projection; TASK-00188 |

TASK-00184 implements execution's direct `TransactionalUnitOfWork` injection and reusable
`Adapter\Persistence\Doctrine\DbalTransactionalUnitOfWork`. The provisional execution transaction subtype is
removed; the DBAL execution seam retains real schema locking and failure translation, not another inward alias.
See [transaction composition](execution-observations.md#retry-and-transaction-guarantees). No aggregate namespace
moves are included in that correction.

Activity and Usage remain meaningful value branches, not new persisted aggregates. TASK-00189 and TASK-00190 own
identity/value classification and inheritance corrections; TASK-00192 owns descriptive variable names. MCP engine,
protocol semantics and ports remain Application-owned. Required released bridges remain functional; provisional
names move directly when the owning refactor executes. Do not import these target FQCNs as existing APIs yet.
This future aggregate alignment does not undo TASK-00181's implemented foundation canonical names or their
identity-preserving released aliases described below. Those canonical imports are available now; retained root
runtime declarations remain compatibility owners, not competing aggregate homes.

## Complete capability relocation map

This table records TASK-00177's implemented relocation and the current source at the TASK-00183 checkpoint;
it is not the final aggregate alignment target above.

Paths below omit the common `Fight\Common\` prefix. Each row covers its whole develop subtree, including values,
messages, events, repositories, policies, handlers, projections, exceptions and implementation helpers.

| Previous subtree | Canonical subtree |
| --- | --- |
| `Domain\Execution\Session` | `Domain\Mcp\Session` |
| `Domain\Execution\Phase` | `Domain\Mcp\Phase` |
| `Domain\Execution\Observation` | `Domain\Mcp\Observation` |
| `Domain\Execution\Activity` | `Domain\Mcp\Activity` |
| `Domain\Execution\Detail` | `Domain\Mcp\Detail` |
| `Domain\Execution\Usage` | `Domain\Mcp\Usage` |
| `Domain\Execution\Value` | `Domain\Mcp\Value` |
| `Domain\Notice` | `Domain\Mcp\Notice` |
| `Domain\Task` | `Domain\Mcp\Task` |
| `Domain\Subscription` | `Domain\Mcp\Subscription` |
| `Application\Execution` | `Application\Mcp\Execution` |
| `Application\Notice` | `Application\Mcp\Notice` |
| `Application\Task` | `Application\Mcp\Task` |
| `Adapter\Execution` | `Adapter\Mcp\Execution` |
| `Adapter\Notice` | `Adapter\Mcp\Notice` |
| `Adapter\Task` | `Adapter\Mcp\Task` |

Existing `Application\Mcp` branches remain in place. The moved execution/notices/Task Application classes merge
with their existing MCP Tool branches, rather than adding forwarding services around their use cases.
`Application\Mcp\Subscription` and `Adapter\Mcp\Subscription` were already grouped correctly. Their Domain
selection/Queries now join the same subsystem. Adapter tests and capability-specific test fixtures move with their
subjects; existing suite membership and independent-process worker behavior are retained.

For example, the caller changes its import, not the use case or bus pipeline:

```php
use Fight\Common\Domain\Mcp\Session\Query\GetExecution;

$view = $queryBus->fetch(new GetExecution($projectId, $sessionId));
```

These tracking subtrees have not appeared in a tagged release through `v1.4.0`. Their provisional names change
directly: no development-snapshot aliases, deprecations or migrations are added. Message payload type identities
follow their new PHP namespaces, including the dotted `Fight.Common.Domain.Mcp...` representation. External MCP
method/Tool names, safe output schemas, stored table names, retention, current admission, transaction ownership,
post-commit ordering and documented failure/recovery guarantees are unchanged. A namespace move does not grant
access or guarantee delivery/execution. Existing persisted development snapshots are not a supported migration format.

## Task persistence capabilities

TASK-00187 removes the provisional `TaskLifecycle`, `TaskInputLifecycle` and `TaskCancellationLifecycle` services.
The existing eight `Application\Mcp\Task\CommandHandler` classes and three `QueryHandler` classes directly own
use cases with operation-specific dependencies. No namespace relocation or replacement manager/facade is added;
ObservationRecorder and Notice orchestration stay intact. See [handler composition](durable-tasks.md#explicit-composition),
[input composition](task-input.md#composition-and-authority) and [cancellation composition](task-cancellation.md#composition-and-independent-authority).

TASK-00185 replaces the never-tagged Application `Mcp\Task\Service\TaskStore` with Domain
`Mcp\Task\TaskRepository` and separately injected existing Application `Repository\TransactionalUnitOfWork`.
Application `Mcp\Task\Service\CommittedTaskReader` supplies protected committed aggregate reads without disclosure
or reporting policy. `Adapter\Mcp\Task\Dbal\DbalTaskRepository` implements both persistence/read contracts;
`DbalTaskTransaction` owns Task serialization around the generic DBAL unit of work. These adapters remain in their
current namespace until TASK-00191. No old Store alias or transaction-bearing repository remains. See
[composition and compatibility](durable-tasks.md#explicit-composition).

## Internal metadata grammar homes

The untagged `McpTraceContextGrammar` and `McpImplementationInfoGrammar` implementation details live directly in
`Application\Mcp\Protocol`. Their short names and grammar behavior are unchanged; no old root aliases,
deprecations or public validator promises are added. Public metadata uses the canonical
`Application\Mcp\Protocol\McpRequestMetadata` name; its released root spelling remains functional.

## Foundation canonical names and released identity

The [complete foundation migration map](maintenance/mcp-foundation-namespaces.md) inventories all 19 released
root declarations and their public operations. Seventeen gain preferred names under `Application\Mcp\Capability`,
`Protocol`, `Protocol\Exception`, `Http` and the valid PHP `Tool` namespace, retaining their short names.
`McpResponder` and `McpDiagnostics` stay at root without new deprecation. All 17 relocated root spellings remain
functional with documentation-only deprecation; the first deprecating release is unassigned and future-major
removal requires separate authorization after the released-minor window.

Canonical class/interface aliases deliberately retain the released runtime declarations rather than introduce
one-way subtypes or duplicate immutable values. Old/new imports have identical parameter/return relationships,
final factory identities, catches, reflection and native saved-value scopes. Root implementation files are retained
compatibility owners; canonical names are the preferred consumer imports. Composer registers the aliases eagerly
so catches and type checks need no prior canonical construction. Canonical spellings are not deprecated, and
no consumer inheritance promise is introduced. Internal grammar and composition helpers retain their direct
internal homes; no development-snapshot bridge is added.

## Internal composition owner

`Application\Mcp\Composition\McpCompositionValidator` is a final `@internal` implementation detail classified
internal in the compatibility manifest. It owns cross-feature Skills/Resources, Task/Tool and subscription
consistency on behalf of `McpCapabilityRegistry`, which remains the sole trusted public construction boundary.
It is not a public validator, builder, registration contract or consumer setup step. Generic registry indexing,
advertisement merging and standard family/anchor constraints retain their current owner. No existing public
name or signature moves, and no compatibility alias or deprecation is introduced.

## Released names and legacy AI operations

The [nine released HTTP adapter names](components/mcp/index.md#canonical-adapter-namespaces-and-1x-compatibility)
remain functional deprecated compatibility entry points throughout 1.x. Internal HTTP helpers have no public
old-name compatibility promise.

Three older AI APIs also receive canonical MCP homes while their released names remain functional:

| Released deprecated name | Canonical legacy name |
| --- | --- |
| `Domain\Auth\AiOperation` | `Domain\Mcp\Operation\AiOperation` |
| `Application\Auth\WebhookDispatcher` | `Application\Mcp\Operation\WebhookDispatcher` |
| `Adapter\Auth\Hmac\HmacWebhookDispatcher` | `Adapter\Mcp\Operation\Hmac\HmacWebhookDispatcher` |

These APIs were already deprecated in 1.2; the relocation does **not** revive them, postpone their existing
2.0 removal, register an MCP Tool or authorize `deploy`, migrations or another action. Both old and
canonical legacy declarations remain deprecated. Existing old-interface implementations still satisfy the new
parent port. The final old HMAC class remains an implementation of its released old port and delegates behavior
to the canonical compatibility parent; that parent's non-finality is not a consumer extension promise. The old
AI payload factories keep their old concrete return, validation, array/JSON and private serialized state.
No runtime deprecation notices are emitted. Consumers own endpoint selection, signing configuration and authority.

## General-purpose dependencies

Messaging, validation, value primitives, generic authentication/signing, OAuth resource protection, HTTP clients,
Socket/Mercure publishing and other general infrastructure keep their established namespaces. They are dependencies
of the agent subsystem, not agent-owned implementations. Only the explicit released compatibility declarations
remain as agent/MCP entry points outside `Mcp`; all canonical agent/MCP implementations are grouped inside it.
The old names are supported bridges, not competing owners or copies of the tracking model.

This organization changes neither optional dependency floors nor the layer matrix. Namespace/promise inventory,
existing product suites, real database/concurrency journeys and live HTTP/native-response behavior verify the move.
Package release certification and deployed consumer qualification remain separate authorized work.
