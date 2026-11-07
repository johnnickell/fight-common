# Architecture and business ownership

Use DDD and ports and adapters with the direction `Adapter → Application → Domain`. Domain owns business concepts and invariants. Application coordinates use cases through Domain/Application interfaces. Adapters implement infrastructure and presentation. Keep framework dependencies within the project's explicitly permitted boundaries; use its dependency checker rather than inventing a second layer map.

Read and maintain CONTEXT.md for ubiquitous language, aggregate responsibility, important state transitions, and accepted decisions. A domain concept belongs with the object holding its knowledge even on its first use. Repeated logic appearing more than three times may suggest extraction; this is a design signal, not a numeric rule.

```php
if (!$user->canLogIn()) {
    throw new LoginRejectedException();
}
```

Avoid feature envy: handlers and adapters should not reconstruct domain policy from a collection of getters. Entities and value objects own state-based decisions. Rules needing repositories or other services commonly use an Application `CompositeSpecification`, normally registered in the container with injected dependencies. Handlers inject that specification; simple local construction is acceptable where it makes sense.

```php
if (!$this->uniqueEmailSpecification->isSatisfiedBy($emailAddress)) {
    throw DuplicateEmailException::fromEmail($emailAddress);
}
```

Prefer capability interfaces and dependency injection for testable business coordination. Apply SOLID to concrete responsibilities: cohesive reasons to change, substitutable contract behavior, interfaces limited to consumer needs, and dependencies pointing inward. Add abstractions for demonstrated variation or boundaries, not speculative extensibility. Fowler-style refactoring should reduce duplication and misplaced knowledge while preserving behavior; principle names alone are not review findings.

## Aggregate ownership and namespaces

For aggregate-oriented code, place Domain types under `Domain/{BoundedContext}/{Aggregate}`. The aggregate
root owns identity, coherent state transitions and invariants; its aggregate-specific identity and repository
interface live beside it. Nest subordinate entities and values under that aggregate's meaningful concept
branches rather than scattering them into context-wide technical folders. Use `Command` and `Query` branches
for verb-first payloads and `Event` for past-tense facts; [Naming](Naming.md) owns the naming rules.
An aggregate is a consistency/identity boundary, not any immutable object or database table.

Mirror that owner in `Application/{BoundedContext}/{Aggregate}`. Put use cases in `CommandHandler` and
`QueryHandler`, and reactions in `EventSubscriber` with names identifying the policy they apply, not merely
the event they forward. Meaningful capability branches may own cohesive admission, projection or coordination
knowledge. `Service` is a fallback for a demonstrated responsibility, not the aggregate root folder or a reason
to funnel every handler through one lifecycle service. A handler owns its use case and receives the specific
repository/read port, transaction boundary and policies it needs; reject broad lifecycle forwarding services
that merely duplicate handler operations. Do not move invariants out of the root into a handler.

A Domain service or DTO needs a demonstrated Domain responsibility. Technical encoding, cryptography,
protocol output, persistence receipts and query projections do not become Domain knowledge by being immutable.
Do not invent aggregates for technical registrations or heartbeats. Shared value branches such as Activity and
Usage can describe aggregate state without being independently persisted roots.

### Value objects

Types classified as value objects must extend `Fight\Common\Domain\Value\ValueObject`, directly or through a
meaningful existing value base such as `UniqueId`. Inspect the inherited `Value` contract and provide meaningful
immutable validation, equality, hashing, string reconstruction/representation and deliberate JSON behavior;
do not inherit a string-based default that changes an established structured representation. A value describes
a conceptual whole and is replaceable by an equal value. Immutable entities, aggregate roots, application DTOs,
native enums and technical ports are not automatically value objects and must not be forced into this hierarchy.

### Adapter destinations

Use capability-first `Adapter/{Capability}/{Provider-or-Framework-or-Standard}` ownership. Aggregate-oriented
persistence additionally identifies the aggregate it loads, saves or projects. MCP retains its approved
`Adapter/Mcp` umbrella: aggregate-specific persistence/read adapters belong in
`Adapter/Mcp/Persistence/{Aggregate}/Dbal`; shared execution schema/serialization knowledge may live in
`Adapter/Mcp/Persistence/Execution/Dbal` only when genuinely shared. Generic transaction mechanisms remain
`Adapter/Persistence/Doctrine`, independent of MCP.

Concrete MCP Tools that map input/schema and public-safe output to a shared use case are presentation adapters:
use `Adapter/Mcp/Tool/{Aggregate}` and dispatch through the normal buses, preserving independent use-case
authorization. Cohesive cross-aggregate Tool mapping support may use `Adapter/Mcp/Tool/Execution`; do not copy
schemas across aggregates or introduce a redundant forwarding adapter around an unchanged Application Tool.
The protocol engine and its inward ports remain Application-owned. Do not add speculative protocol-version
folders. Framework service-container extension points retain their separate
`Adapter/ServiceContainer/{Framework}` owner under [ADR 0023](../../../planning/adr/0023-service-container-and-framework-adapter-namespaces.md).

These are approved placement requirements, not a claim that the current source already conforms. The
[TASK-00183 sequence](../../../planning/tasks/00183-TASK.md#coordination) owns the bounded follow-up refactors;
namespace moves must retain the released bridges described below.

## Package and consumer ownership

Fight Common owns reusable capabilities and their explicit public behavior/compatibility promises. Consumers own
application policy, permissions, routes, use-case composition and operational choices unless an explicit package
contract says otherwise. Use the owning package's public contracts directly; avoid aliases and forwarding wrappers
without demonstrated behavior. Consumer Application orchestration needs actual owned policy or coordination, not
just a second name for a package call. State-based policy stays with its Domain owner; handlers coordinate it and
adapters translate it. These preferences do not authorize removing Common's required compatibility aliases,
intentional extension points or reusable framework integrations.

### Finite package exceptions

Apply aggregate placement to real aggregate responsibilities, not indiscriminately to every library capability.
The accepted exceptions below have bounded owners; existing code alone does not create another exception.

| Exception | Scope and decision owner |
|---|---|
| Shared low-level primitives | Reusable values, collections, specifications, messaging contracts and utilities keep their established shared homes; they are not artificial business aggregates. The [project profile](../../../planning/agents/project-profile.md#identity-and-architecture) owns package boundaries. |
| Orthogonal Standards layer | `src/Standards` publishes development-time policy, outside the runtime chain; runtime layers cannot depend on it or vice versa. The profile and dependency configuration identify this boundary. |
| Application protocol semantics | MCP engine/ports and JSend envelopes remain Application-owned; concrete Tool mapping and native HTTP status/headers/responses remain Adapter concerns. [ADR 0023](../../../planning/adr/0023-service-container-and-framework-adapter-namespaces.md) and its scoped amendments own these destinations. |
| Portable container | The PSR-11 container remains `Application/ServiceContainer`; framework wiring stays `Adapter/ServiceContainer/{Framework}` under ADR 0023. |
| Scheduler expression utility | Application may use exactly `Cron\CronExpression`, not the whole Cron package or arbitrary third-party utilities; the profile and Deptrac allowlist own enforcement. |
| Scheduler released construction | [ADR 0017](../../../planning/adr/0017-scheduler-1x-construction-compatibility.md) retains the exact released constructor and conditional Symfony Process bridge through 1.x; new composition uses `ProcessRunner`. No new framework allowance follows. |
| Released namespace bridges | Required released names remain functional for their documented deprecation period; preserve factory identity, named parameters and serialization promises. ADR 0023, the [MCP namespace map](../../mcp-namespaces.md#released-names-and-legacy-ai-operations) and the profile identify the scoped bridges. Never-released paths move directly without snapshot aliases. |

A new exception requires an owned responsibility, reason, explicit decision and recorded scope/reference; do not
silently broaden this list to match nonconforming code. Do not transplant application-only namespaces or move
accepted protocol semantics merely to fit another project's diagram. Dependency enforcement and public
compatibility remain binding.

## CQRS and persistence

Commands express intent; queries return data without business mutation; events express facts. Follow the project's message identity, metadata, bus, and dispatch contracts. Keep transport information out of domain messages unless it is meaningful use-case context. Application handlers orchestrate; adapters do not become alternate business workflows.

Domain aggregate repositories own retrieval, persistence and removal of their aggregate, not transaction
lifecycle, connection access, disclosure policy or transport projection. Read-oriented Application ports may own
cohesive queries/projections that are not aggregate persistence; do not add reporting methods or caller grants
to a write repository merely to reuse its storage access.

Repositories may return null from `getById()`. The QueryHandler or CommandHandler decides whether absence is a failure and throws Fight Common's `LookupException` where appropriate. An adapter translates that failure. Do not rename a nullable repository method solely to imply failure behavior.

Map migrations to the persistence adapter. Coordinate domain/model changes before dependent migrations, messages, handlers, adapters, and UI. Check compatibility and existing data consequences for schema changes; do not assume a migration is reversible merely because a down method exists.

## Transactions and events

Identify which handler owns the transaction, what commits atomically, and what happens if later delivery fails.
Inject the existing `Application\Repository\TransactionalUnitOfWork` separately from the repository. A repository
must not extend that contract; a single concrete Store implementing both roles is not the replacement for this
separation. Use the existing contract rather than inventing an inward subtype or alias solely for shared mechanics.
Adapters may share a connection/serialization lock while persistence and transaction ownership remain distinct.

DBAL work requires a DBAL-backed transaction boundary, not substitution with an ORM EntityManager/flush boundary.
`Adapter/Persistence/Doctrine/DbalTransactionalUnitOfWork` implements the generic mechanism under
[TASK-00184](../../../planning/tasks/00184-TASK.md); capability-specific locks, schemas and failure translation
remain adapter-owned composition, not additions to the inward transaction contract.
Preserve actual commit/rollback, caller-transaction rejection, closure/uncertainty and recovery guarantees when
separating existing stores. Reconcile released consumer compatibility explicitly. Avoid a blanket rule that all
external calls must be outside all transactions.

Distinguish business persistence, durable delivery work, event publication, and subscriber execution. Post-commit dispatch can be lost; durable delivery state alone is not a transactional outbox. State the actual guarantees in the use case and evidence. Event sourcing and outboxes are deliberate choices, not mandatory CQRS infrastructure.

When implementing projections or publication workers, inspect current checkpoint/cursor semantics. A successful write followed by failed checkpoint persistence can repeat delivery. Determine idempotency, ordering, concurrency, retry, and recovery from the real use case. Recorded subscriber failure is not proof of automatic retry. Do not advertise reliable publication until the implementation proves it.
