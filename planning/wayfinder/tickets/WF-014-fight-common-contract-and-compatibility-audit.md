# Audit Fight Common contracts and the 1.2 compatibility envelope

**Labels:** `wayfinder:research`, `wayfinder:domain-modeling`
**Mode:** HITL
**Status:** Closed
**Map:** [Fight Framework Portability and Starter Projects](../fight-framework-portability-map.md)
**Specification:** [PRD-00014 — Fight Common Contract Repair and Compatibility Certification](../../specs/00014-PRD.md)
**Implementation tickets:** T-00047 through T-00056 in the canonical Fight Common ticket tracker
**Depends on:** [Establish the portability destination and release boundaries](WF-009-portability-destination-and-release-boundaries.md), [Define the package and repository ownership model](WF-010-package-and-repository-ownership.md), [Define the versioned HTTP, JSend, and presentation contracts](WF-011-versioned-http-jsend-and-presentation-contracts.md), [Define the portable AccessControl and persistence boundaries](WF-012-access-control-and-persistence-boundaries.md), [Define starter product, governance, and documentation standards](WF-013-starter-product-governance-and-documentation.md)

## Question

What exactly must Fight Common add, repair, deprecate, or defer so every public Application contract
can be composed in five frameworks without breaking the `1.2.0` compatibility envelope?

## Must decide

- authoritative inventory of every public Domain and Application contract and every existing Adapter;
- which contracts are current, experimental, obsolete, duplicated, or missing a viable composition;
- exact Scheduler constructor compatibility repair or explicit major-version deferral;
- final typed JSend payload API, `Arrayable` and `ResultSet` generic behavior, raw-array deprecation,
  encoding semantics, and native response adapter responsibilities;
- complete list of unqualified Symfony namespaces that need framework qualification, additive aliases
  or shims, deprecation notices, and `2.0.0` removal;
- whether the namespace standard is capability-first and framework-second throughout existing Adapter
  families, and which changes cannot be additive;
- Composer `require`, `require-dev`, and `suggest` consequences of supporting all five frameworks;
- public API, locked, lowest, latest, and combined dependency evidence needed to certify `1.2.0`; and
- a contract-to-capability worksheet that downstream framework research can complete without inventing
  redundant adapters.

## Decisions

### Contract inventory authority

Use the evidence-backed inventory in the linked WF-014 research note as the authoritative planning
baseline: 131 Domain declarations plus 13 production-autoloaded Domain functions, 166 Application
declarations, and 107 Adapter declarations. Domain and Application remain the current portable core;
portable adapters remain reusable; the 41 declarations added after `1.1.0` require deliberate
compatibility-manifest classification; and the unqualified Symfony adapters require additive
framework-qualified paths. Apparent duplication or obsolescence does not authorize narrowing or removal
in `1.2.0`.

This declaration inventory does not decide every operation-level promise. Callable, constructible,
extensible, and implementable status remains subject to the human classification required by the
compatibility manifest.

### Scheduler compatibility repair

Restore the exact published `1.1.0` Scheduler constructor and its command-execution behavior for `1.x`.
Add a named `Scheduler::withProcessRunner(...)` construction path for portable `ProcessRunner`
composition rather than inserting the runner into the legacy constructor. Keep the legacy
`processFactory` and conditional Symfony Process behavior functional and deprecated through `1.x`; remove
that compatibility path and require `ProcessRunner` composition only in `2.0.0`.

The legacy Symfony execution path is a narrow compatibility exception, not permission for new Application
capabilities to depend on framework implementations. If implementation evidence shows that the published
behavior cannot be reproduced, the required-runner form is deferred to `2.0.0`; it may not ship as the
current incompatible `1.2.0` constructor.

See [ADR 0017](../../adr/0017-scheduler-1x-construction-compatibility.md).

### Neutral JSend contract

Add immutable `Application\Http\JSend\JSendEnvelope` and `JSendStatus` types. The envelope owns only the
semantic JSend status, typed data, required error message, optional integer error code, and semantic
serialization. Its factories are `success(?Arrayable $data = null)`, `fail(Arrayable $data)`, and
`error(string $message, ?Arrayable $data = null, ?int $code = null)`. `ResultSet<TData>` participates
through `Arrayable` when a paginated result is supplied.

The envelope contains no HTTP status, headers, framework response, service lookup, or I/O. Each native
`JSendResponse` adapter exposes `fromEnvelope(JSendEnvelope $envelope, int $statusCode, array $headers = [])`
and ergonomic `success(...)`, `fail(...)`, and `error(...)` factories. A controller action chooses the exact
HTTP status because a JSend `fail` may represent `400`, `401`, `403`, `404`, `409`, `422`, or another expected
HTTP outcome; the native adapter builds the framework response.

Raw arrays remain accepted only by the deprecated `Adapter\HttpFoundation\JSendResponse` compatibility path
through `1.x`. New envelope and native-adapter entry points require `Arrayable` presentation data. The term
**JSend envelope** is used instead of **payload** so it does not conflict with the existing Messaging meaning
of Payload.

### Arrayable and ResultSet typing

Correct the base `Arrayable::toArray()` PHPDoc from the inaccurate map-only
`array<string, mixed>` promise to `array<array-key, mixed>`. The base interface guarantees an arbitrary PHP
array shape; it is not generic and does not promise a map. Each implementation declares its narrower actual
shape. For example, `ArrayList<T>` returns `array<T>`, presentation objects may return
`array<string, mixed>`, and `ResultSet<TRecord>` returns its documented pagination shape with
`array<TRecord>` records.

Generalize `ResultSet<TRecord>` without imposing an `Arrayable` bound on `TRecord`; existing scalar and
object result sets remain valid, and `ResultSet::toArray()` retains its existing behavior.

The JSend boundary accepts either one ordinary `Arrayable` presentation object or a paginated
`ResultSet<TData>` whose individual records implement `Arrayable`. A single presentation object serializes
directly as JSend `data` with no pagination fields. A paginated result maps each record through `toArray()`
and preserves `page`, `per_page`, `total_pages`, `total_records`, and `records`. A result set containing a
non-`Arrayable` record is invalid only at this typed presentation boundary; it remains valid for other
existing `ResultSet` consumers.

### JSend encoding ownership

`JSendEnvelope` owns the final JSON body used by every native response adapter. Its `toArray()` exposes the
semantic JSend structure and its `toJson()` encodes that structure with the established option `79` plus
`JSON_THROW_ON_ERROR`. Presentation data must return JSON-encodable values. Invalid UTF-8 or another encoding
failure throws `JsonException` before a native response is constructed; partial or silently false output is
not permitted.

Every native adapter uses the encoded envelope body without re-encoding it. The adapter owns only the HTTP
status selected by the controller, response headers and content type, and construction of its framework's
native response. JSON whitespace and object-key ordering are not compatibility promises. The deprecated
Symfony `Adapter\HttpFoundation\JSendResponse` retains its existing option-79 default and caller-selected
encoding options throughout `1.x`.

See [ADR 0018](../../adr/0018-neutral-jsend-envelope-and-native-response-boundary.md).

### Adapter namespace standard

Use `Adapter\<Capability>\<Framework-or-Provider>\<Type>` as the canonical namespace structure for every
new adapter and every additive corrected path. A type belongs to the capability it serves; generic
top-level implementation buckets such as `DependencyInjection`, `HttpKernel`, and `EventSubscriber`, and
provider-first buckets such as top-level `Doctrine`, are not canonical destinations.

Examples include `Adapter\Http\Symfony\JSendResponse`,
`Adapter\Filesystem\Symfony\SymfonyFilesystem`,
`Adapter\Messaging\Symfony\Command\MessengerCommandBus`,
`Adapter\EventSourcing\Symfony\DependencyInjection\EventMappingProviderCompilerPass`,
`Adapter\Templating\Symfony\DependencyInjection\TemplateHelperCompilerPass`, and
`Adapter\Persistence\Doctrine\Type\UuidDataType`.

Fight Common `1.2.0` introduces corrected paths additively. No existing public FQCN is deleted or renamed in
place; every superseded path remains functional and deprecated through `1.x` and is removed only in
`2.0.0`.

### Legacy namespace compatibility

Choose the compatibility mechanism per declaration rather than forcing one mechanism across every move.
Use `class_alias()` for a pure relocation only when consumer probes prove construction, static factories,
`instanceof`, serialization, and framework registration remain compatible. Use an explicit forwarding
class, compatibility implementation, or shared internal base when reflection identity, attributes, service
IDs, extension behavior, or framework registration would differ.

Keep the old `Adapter\HttpFoundation\JSendResponse` as an explicit compatibility implementation because its
raw-array and caller-selected encoding API is intentionally distinct from the new typed response. Treat
compiler passes, subscribers, Doctrine types, and other framework extension points as identity-sensitive
until their designated compatibility probes prove an alias sufficient.

Deprecate old paths in PHPDoc and documentation during `1.2.0` without runtime deprecation warnings. Test the
old and new FQCNs independently and remove old paths only in `2.0.0`.

### Exact namespace migration scope

The migration worksheet contains 32 declarations: 19 Symfony-semantic adapters and 13 top-level Doctrine
data types. The Symfony count includes 17 declarations that import Symfony directly plus
`SymfonyCommandMessageHandler` and `SymfonyEventMessageHandler`, whose documented role is Symfony Messenger
integration even though their source does not import Symfony.

| Existing declaration group | Canonical `1.2.0` destination |
| --- | --- |
| `Adapter\DependencyInjection\{CommandFilterCompilerPass, CommandHandlerCompilerPass, EventSubscriberCompilerPass, QueryFilterCompilerPass, QueryHandlerCompilerPass}` | `Adapter\Messaging\Symfony\DependencyInjection\*` |
| `Adapter\DependencyInjection\EventMappingProviderCompilerPass` | `Adapter\EventSourcing\Symfony\DependencyInjection\EventMappingProviderCompilerPass` |
| `Adapter\DependencyInjection\TemplateHelperCompilerPass` | `Adapter\Templating\Symfony\DependencyInjection\TemplateHelperCompilerPass` |
| `Adapter\EventSubscriber\{SymfonyExceptionSubscriber, SymfonyValidationSubscriber}` | `Adapter\Http\Symfony\EventSubscriber\*` |
| `Adapter\Filesystem\SymfonyFilesystem` | `Adapter\Filesystem\Symfony\SymfonyFilesystem` |
| `Adapter\HttpFoundation\JSendResponse` | `Adapter\Http\Symfony\JSendResponse` |
| `Adapter\HttpKernel\{ErrorController, JsonRequestMiddleware}` | `Adapter\Http\Symfony\*` |
| `Adapter\Messaging\Command\Async\MessengerCommandBus` | `Adapter\Messaging\Symfony\Command\MessengerCommandBus` |
| `Adapter\Messaging\Event\Async\MessengerEventDispatcher` | `Adapter\Messaging\Symfony\Event\MessengerEventDispatcher` |
| `Adapter\Messaging\Serializer\SymfonyMessageSerializer` | `Adapter\Messaging\Symfony\Serializer\SymfonyMessageSerializer` |
| `Adapter\Messaging\Handler\{SymfonyCommandMessageHandler, SymfonyEventMessageHandler}` | `Adapter\Messaging\Symfony\Handler\*` |
| `Adapter\Routing\SymfonyUrlGenerator` | `Adapter\Routing\Symfony\SymfonyUrlGenerator` |
| `Adapter\Doctrine\{AuditEntryIdDataType, EmailAddressDataType, JsonObjectDataType, MbStringObjectDataType, MbStringTextDataType, MessageDataType, MetaDataType, StringObjectDataType, StringTextDataType, TypeDataType, UriDataType, UrlDataType, UuidDataType}` | `Adapter\Persistence\Doctrine\Type\*` |

Existing `Adapter\Mail\Symfony` and `Adapter\Process\Symfony` declarations remain in place. Mercure remains
provider-qualified under `Adapter\Socket`; it is not moved merely because its package vendor is Symfony.
Every old FQCN in the table remains functional through the legacy compatibility policy above.

See [ADR 0019](../../adr/0019-capability-first-adapter-namespaces-and-1x-compatibility.md).

### Composer verification layout

Keep Symfony, Laravel, Yii, CodeIgniter, Slim, and their optional adapter packages out of production
`require`. The existing root project uses `require-dev` as the mutually resolvable combined framework and
adapter test set. Root `suggest` names each exact optional package that activates an adapter, while supported
version ranges remain normative in documentation because Composer suggestion text is not a constraint.

Add five minimal Composer fixture directories inside this repository, one per framework. These are dependency
manifests and compatibility probes, not starter applications or separate repositories. Each fixture requires
the Fight Common candidate plus exactly one framework and its selected native dependencies, then resolves
both the lowest and latest supported versions. The existing root supplies the combined resolution lane,
including lowest resolution where the final constraints make it meaningful.

Fixture locks are disposable lane inputs; each run records the exact resolved versions and lock digest as
evidence. Production verification installs the candidate with `--no-dev` and proves no optional framework
package is required or eagerly loaded. WF-015 owns the exact package names and supported ranges.

### Blocking 1.2.0 certification evidence

Fight Common `1.2.0` is not certifiable until every required lane passes:

1. the intentional public API manifest and operation-level diff against the authoritative published `1.1.0`
   baseline;
2. consumer probes for every published positional and named Scheduler construction style and command
   behavior;
3. old and new FQCN probes for all 32 namespace migrations, including relevant framework registration and
   identity behavior;
4. deprecated raw-array and new typed JSend semantic, encoding, HTTP, and native-response behavior;
5. the repository-locked full quality gate with exact complete production statement coverage and both
   supported databases;
6. lowest-permitted and latest-permitted root dependency resolutions;
7. lowest and latest resolutions plus compatibility probes for each of the five isolated framework fixtures;
8. the combined five-framework root resolution and adapter suite;
9. exported-package and `--no-dev` clean-install proof showing no optional framework dependency; and
10. exact resolved-version, lock-digest, public API, behavior, package, and archive receipts composed into the
    release certification manifest.

An unavailable, skipped, failed, or indeterminate lane does not certify `1.2.0` and cannot be replaced by a
single hosted check or raw log. It produces a certification stop handoff naming the missing evidence and one
resumable next action. WF-014 defines this evidence contract but does not implement the certification tooling.

### Downstream contract-to-capability handoff

The contract-to-capability worksheet in the WF-014 research note is the required handoff contract for WF-015
through WF-017. For every capability and framework, downstream work records the selected maintained
versions, exact Composer constraint, framework-native facility, existing Fight Common adapter or direct
binding, starter-owned composition, proposed new shared adapter with its evidence, lowest and latest lock
receipts, functional consumer journey, and remaining unknowns.

A new Fight Common adapter is authorized only when prototype evidence demonstrates reusable translation or
framework-extension behavior that cannot be expressed cleanly in the starter's composition root. Equal class
counts are not a goal. Different frameworks may bind an existing port directly, reuse a portable adapter, or
require a native adapter; complete documented consumer journeys remain the measure of support.

## Resolution boundary

Produce an evidence-backed contract and compatibility audit plus the decision inputs for the
framework-composition tickets. Do not implement shims, rename classes, alter Composer constraints, or
change Scheduler while resolving this ticket.
