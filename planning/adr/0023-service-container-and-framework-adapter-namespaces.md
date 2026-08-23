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

## Consequences

ADR 0019's capability-first rule remains the default for runtime adapters, but no longer governs framework
service-container extension points. The previously planned
`Adapter\Messaging\Symfony\DependencyInjection` and similar compiler-pass destinations must be reconciled
before their implementation tickets become executable.

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
