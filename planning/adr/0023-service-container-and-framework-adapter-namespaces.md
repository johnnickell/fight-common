# ADR 0023: Service-Container and Framework Adapter Namespaces

- Status: accepted
- Date: 2026-08-22
- Supersedes: ADR 0019 namespace destinations where changed; ADR 0021 no-new-shared-adapter policy

## Decision

Fight Common may publish optional framework adapters when they provide reusable integration for a public
Domain or Application capability. Starter projects remain the executable consumer and composition evidence,
but a useful adapter is not forced into every starter merely to keep Fight Common small.

Framework service-container extension points live together under
`Adapter\ServiceContainer\<Framework>\<Type>`. The class name identifies the bounded capability it wires.
Examples include `Adapter\ServiceContainer\Symfony\CommandFilterCompilerPass` and
`Adapter\ServiceContainer\Laravel\CommandBusServiceProvider`. Capability-specific providers remain
independently installable and opt-in; Fight Common does not require one provider that wires every optional
integration.

Concrete runtime adapters continue to use
`Adapter\<Capability>\<Framework-or-Provider-or-Standard>\<Type>`. HTTP
controllers use `Adapter\Http\<Framework>\Controller`, framework middleware uses
`Adapter\Middleware\<Framework>`, and native JSend responses use `Adapter\Http\<Framework>`.
Repository, data-type, and UnitOfWork implementations use the `Adapter\Persistence` capability.

Fight Common is standards-first when an accepted PHP-FIG interface is the honest inward contract or a
lossless adapter can expose an existing Fight capability. It consumes PSR-3 and PSR-7 directly, implements
PSR-11 directly, and may publish standard-specific integrations such as `Adapter\Middleware\Psr15`,
`Adapter\Http\Psr17`, and `Adapter\Cache\Psr16`. Framework-native adapters remain appropriate when the
framework owns lifecycle, registration, queue, transaction, or request/response behavior that the standard
does not define.

Standards are not forced across a semantic mismatch. Fight messaging does not claim PSR-14 compatibility
because its envelope, registration, two-phase delivery, and aggregate-all-failures behavior conflict with
PSR-14 dispatch. A PSR-18 adapter may expose Fight's synchronous HTTP-client behavior, but a synchronous-only
PSR-18 client does not implement Fight's mandatory asynchronous contract. Similarity of names is not enough
to authorize an adapter or support claim.

Framework-neutral HTTP primitives receive additive canonical paths under `Application\Http`. Fight Common's
portable PSR-11 container receives the additive canonical path `Application\ServiceContainer\Container`.

Fight Common targets these additions for `1.2.0`. Existing public FQCNs remain independently functional and
deprecated without runtime warnings throughout `1.x`; their removal is reserved for `2.0.0`. A capability
whose correct adapter requires an inward contract break is deferred individually to `2.0.0` rather than
weakening the compatibility policy for the full release.

## MCP-specific runtime namespace amendment — TASK-00177

John explicitly requested that the nine released MCP HTTP adapters move to canonical `Adapter\Mcp` locations,
with their existing locations deprecated rather than removed. MCP-specific shared HTTP adapters therefore use
`Adapter\Mcp\Http`; native MCP response integration uses `Adapter\Mcp\Symfony` and `Adapter\Mcp\CodeIgniter`.
This is a scoped exception to the HTTP framework destinations above. Generic HTTP responses/controllers and
framework service-container extension points keep their existing canonical ownership.

Existing public names stay functional throughout 1.x without runtime deprecation warnings; future-major removal
needs separate authorization after at least one released minor of deprecation. Compatibility subclasses preserve
released concrete identities and constructor signatures. Their canonical parents are non-final only for this
compatibility mechanism, with no consumer extension promise. The immutable Origin retains a final compatibility
implementation to preserve its self-returning factory and old serialized value. Internal helpers relocate directly.

The [MCP migration map](../../docs/components/mcp/index.md#canonical-adapter-namespaces-and-1x-compatibility)
identifies all nine names. The initial adapter-only checkpoint is superseded by John's expanded decision below.

### Complete agent subsystem amendment and develop-base correction

John expanded TASK-00177 to all agent/MCP-owned classes. Execution concepts move directly beneath `Domain\Mcp`;
Notice, Task and Subscription retain concept branches there. Whole Application and Adapter Execution/Notice/Task
capabilities move beneath `Mcp`, alongside existing protocol-facing branches. The
[complete map](../../docs/mcp-namespaces.md) records the destinations. Shared Commands/Queries do not require MCP
transport; generic values, messaging, validation, authentication, HTTP clients and Socket/Mercure remain independent.

Scope is every relevant class actually present on develop, not unaccepted classes from TASK-00169. The erroneous
mixed-branch prerequisite is superseded by John's explicit clean develop-based correction. Durable Task-input
feature behavior is excluded; existing stateless forms and confirmation remain unchanged. Provisional tracking
paths move directly without development aliases. Three legacy AI APIs gain equally deprecated `Mcp\Operation`
homes and released-name bridges, preserving their original 1.2 deprecation and 2.0 sunset without feature revival.
The move changes no inward layer boundaries, HTTP safeguards, streaming, storage/schema, admission, transaction,
delivery guarantees, optional dependencies or consumer policy ownership.

## Aggregate persistence and concrete Tool specialization — TASK-00183

John approved aggregate-first ownership on 2026-11-02. This scoped amendment specializes the general persistence
destination and the earlier whole-capability MCP grouping; it does not erase their historical acceptance.
[Architecture](../../docs/engineering/standards/Architecture.md#aggregate-ownership-and-namespaces) is the single
normative owner of the new rules and finite exceptions. The
[MCP namespace map](../../docs/mcp-namespaces.md#approved-aggregate-alignment-target) distinguishes current source
from the approved destinations and names the implementing tasks.

MCP aggregate persistence/read adapters target `Adapter/Mcp/Persistence/{Aggregate}/Dbal`; shared execution
schema/serialization support may target `Adapter/Mcp/Persistence/Execution/Dbal` for real cross-aggregate knowledge.
Generic DBAL transaction mechanics remain `Adapter/Persistence/Doctrine`, separately from repository persistence.
Concrete Query-to-Tool argument/schema/public-output mapping targets `Adapter/Mcp/Tool/{Aggregate}`, with cohesive
shared mapping support under `Tool/Execution` where needed. MCP engine/ports and protocol semantics remain in
Application. Matching Application aggregate homes no longer gain an extra technical `Execution` parent merely
because they share storage. No speculative protocol-version folders are introduced.

This is an approved target, not an assertion that TASK-00184 through TASK-00192 are implemented. Framework
service-container extension points, generic HTTP/JSend integration, the `Adapter/Mcp` umbrella and required released
namespace bridges retain their accepted ownership and compatibility. No public API removal, schema, dependency,
permission or delivery change follows from this documentation amendment. The foundation canonical names and
released identity-preserving aliases described below remain implemented and supported; aggregate alignment does
not relocate their runtime compatibility owners or reclassify protocol values as Domain value objects.

## Implemented aggregate and presentation specialization — TASK-00191

TASK-00191 implements the approved specialization above without reopening TASK-00177's historical acceptance.
Application Session, Phase, Observation and Detail homes now mirror Domain directly beneath `Application/Mcp`;
`ObservationAdmission` lives in `Observation/Service`. Handler, Data, Exception and meaningful Service ownership
remain inward. The removed execution transaction subtype does not return.

Concrete execution inspection Tools live in `Adapter/Mcp/Tool/{Session,Phase,Observation,Detail}` according to
the shared Query exposed; Notice recovery presentation uses `Tool/Notice`. Their actual attributes, argument
translation, schemas and safe result mapping move, not an extra adapter forwarding to an Application Tool.
Cross-aggregate execution presentation support remains cohesive under `Tool/Execution`.

DBAL repositories/readers use `Adapter/Mcp/Persistence/{Session,Phase,Observation,Detail,Task,Notice,Subscription}/Dbal`.
`Persistence/Execution/Dbal` retains only genuinely shared execution schema, serialization/transaction translation
and constrained read/decoding support. Generic DBAL mechanics stay in `Adapter/Persistence/Doctrine`.
`Adapter/Mcp/Http`, native MCP integrations, `Adapter/ServiceContainer`, generic Application protocol semantics
and TASK-00181's canonical foundations/runtime compatibility owners remain unchanged. No speculative version
folder, renamed transport or MCP identifier is introduced.

Inspection of all six actual local tags through v1.4.0, including prior namespaces by short name, finds none of
the 79 affected declarations released. They move directly without snapshot bridges or deprecations; existing
released foundation/HTTP/legacy-AI operation-specific compatibility remains intact. The
[current complete map](../../docs/mcp-namespaces.md#approved-aggregate-alignment-target), manifest and mirrored
runtime tests describe the canonical imports. Tool IDs/schemas, independent availability/Query authorization,
protected-body grants, transactions, storage bytes, response bounds and live delivery keep their prior contracts.

## MCP foundation namespace amendment — TASK-00181

John approved an additive foundation map on 2026-11-02: preserve short names while grouping capability contracts
and registry under `Application\Mcp\Capability`, protocol values/decoding under `Protocol`, the protocol exception
under `Protocol\Exception`, inward HTTP policies/guards/mirrors under `Http`, and the progress port under `Tool`.
`McpResponder` and `McpDiagnostics` stay at root without new deprecation. This refines, not reopens, TASK-00177's
historical accepted subsystem consolidation; it changes no Scheduler or public interface design.

The [per-declaration released-operation inventory](../../docs/maintenance/mcp-foundation-namespaces.md) records
all 19 names in v1.3.0/v1.4.0 and their operations. Canonical aliases deliberately retain released runtime
declarations and private saved-state scopes; one-way subtypes cannot preserve both consumer signature directions,
and duplicate values cannot preserve shared final type identity. Root source files remain compatibility
implementations. This preserves factory/reflection identity, finality, construction/named arguments, catches and
supported saved-value reads, without creating an inheritance promise. Composer eagerly registers the aliases
for catches and type checks; ordinary consumer containers still own their literal string registration IDs.

Deprecate only the 17 relocated released root **spellings**, in documentation rather than annotating the shared
runtime type as deprecated. Canonical aliases, unchanged roots and untagged internals are not deprecated.
TASK-00207 assigns first deprecation to planned v1.5.0; it is not yet released. Removal needs separately authorized
future-major work after at least one released minor of deprecation, not an inferred 2.0 deadline from unrelated
legacy AI APIs. Untagged internal grammar/composition helpers retain their direct homes without snapshot bridges.
Application retains semantic MCP and inward HTTP ownership; concrete transport stays in Adapter. No protocol,
wire/Tool identifier, authorization, schema, admission, budget, transaction/post-commit or delivery contract changes.

## Consequences

ADR 0019's capability-first rule remains the default for runtime adapters, but no longer governs framework
service-container extension points. The previously planned
`Adapter\Messaging\Symfony\DependencyInjection` and similar compiler-pass destinations must be reconciled
before their implementation tasks become executable.

ADR 0021's starter-owned composition evidence remains valid. Its blanket `no new shared adapter` policy and
statement that framework integration classes cannot live in Fight Common are superseded. Composer dependency
isolation, native behavior, and installed-package consumer probes remain required before an adapter is
supported.

Accepted PSRs are audited capability by capability. Coding and package standards remain conformance policy;
direct interface use needs no Fight-branded wrapper; standard adapters require contract tests proving their
direction and semantics; and standards with no current Fight capability remain deferred. The support matrix
records explicit non-support where translation would lose behavior.

## Rejected Alternatives

Scattering equivalent compiler passes, providers, and service factories beneath every runtime capability was
rejected because it hides their common service-container responsibility. A single all-capabilities provider
was rejected because installing one optional integration must not silently wire unrelated dependencies.

Keeping every framework adapter starter-owned was rejected because it duplicates reusable integration and
makes equivalent starter wiring drift. Removing old namespaces during `1.2.0` was rejected as a breaking
change.

Publishing a wrapper for every accepted PSR was rejected because several standards are already consumed
directly, while others either describe development-time conformance or do not match a Fight capability.
