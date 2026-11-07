# MCP foundation namespace migration

## Released-operation inventory

TASK-00181 inspected every root declaration against local tags before selecting bridges. All 19 appear in
both `v1.3.0` and `v1.4.0`; none appears in an earlier tag. The compatibility manifest classifies each as public.
`C` below means public construction (including named arguments or named factories), `R` public calls, and
`I` consumer implementation. None promises consumer class inheritance. Paths omit `Fight\Common\Application\Mcp\`.

| Released root name | Canonical name | Promised operations and important shape |
| --- | --- | --- |
| `McpCapability` | `Capability\McpCapability` | R/I: methods, capability definitions, mirrors, validate(Request), handle(Request): Result |
| `McpMetadataAwareCapability` | `Capability\McpMetadataAwareCapability` | R/I: Capability subtype; handleWithMetadata(Request, metadata): Result |
| `McpCapabilityRegistry` | `Capability\McpCapabilityRegistry` | C/R: serverInfo, capabilities, optional subscriptions; identity, selection, advertisements, mirrors |
| `McpRequest` | `Protocol\McpRequest` | C/R: id, method, parameters, metadata; immutable typed reads |
| `McpRequestMetadata` | `Protocol\McpRequestMetadata` | C/R: fromObject(metadata); version, capabilities, client info, progress token, constants |
| `McpRequestDecoder` | `Protocol\McpRequestDecoder` | C/R: optional limits; limits(), decode(json): Request |
| `McpRequestLimits` | `Protocol\McpRequestLimits` | C/R: maxBytes, maxDepth; retained readonly fields and getters |
| `McpResult` | `Protocol\McpResult` | C/R: complete(data), toArray(); existing internal bounded/interaction/Task seams stay internal |
| `McpJsonResponse` | `Protocol\McpJsonResponse` | C/R: success(id, result), error(id, error); array, JSON, error code |
| `McpProtocolError` | `Protocol\McpProtocolError` | C/R: fixed safe error factories, supported/requested version arguments, constants, toArray() |
| `McpServerInfo` | `Protocol\McpServerInfo` | C/R: name, version; typed reads and toArray() |
| `McpProtocolException` | `Protocol\Exception\McpProtocolException` | C/R: protocolError, requestId; inherited Throwable behavior, catchability |
| `McpOriginPolicy` | `Http\McpOriginPolicy` | R/I: allows(origin): bool |
| `McpInvocationGuard` | `Http\McpInvocationGuard` | R/I: allows(method, name): bool |
| `McpMirrorDeclaration` | `Http\McpMirrorDeclaration` | C/R: method, parameterPath, headerName; immutable reads |
| `McpRequestMirrors` | `Http\McpRequestMirrors` | R/I: mirrorsFor(Request): array |
| `McpProgressReporter` | `Tool\McpProgressReporter` | R/I: report(progress, total, message), isCancelled() |
| `McpResponder` | `McpResponder` (unchanged) | C/R: registry, decoder, diagnostics; respond, dispatch, protocol constants |
| `McpDiagnostics` | `McpDiagnostics` (unchanged) | R/I: record(Throwable) |

Deprecation of the **17 relocated root spellings is planned to first ship in v1.5.0; it is not yet released**.
It is documentation-only, without runtime notices. The canonical spellings and the two unchanged root names are
not deprecated. Removal needs separate future-major authorization after at least one actually released minor of
deprecation; this plan claims neither publication nor the unrelated legacy AI 2.0 sunset. See the
[consolidated deprecation inventory](deprecations.md) for earlier released groups and migration risks.

## Bridge and runtime identity

Each canonical entry point is a PHP class/interface alias of its released declaration. This direction is
deliberate: retaining the released runtime declaration preserves exact concrete factory identity, finality,
reflection names, private serialized property scopes and backward reads without custom rehydration or duplicated
value implementations. Canonical names are the preferred imports; the root files are retained compatibility
implementations, not an additional consumer extension API. The unchanged root responder and existing legacy
HTTP handler retain their released reflected dependency spellings, so legacy registration IDs still wire them.
Deprecation belongs to the old **spelling**, not the shared runtime type, so no class-wide `@deprecated` annotation marks the canonical alias deprecated by reflection.

For the interfaces, aliases preserve exact parameter and return identity in both directions. A legacy capability
can accept a legacy Request and return a legacy Result while Common uses canonical names; canonical implementations
also satisfy legacy callers. The same applies to request mirrors, policies and Tool progress signatures. A child
interface or a subclass alone would only prove one direction. All released concrete classes remain final; no
compatibility parent is opened for consumer inheritance. Exception aliases preserve catches in either direction.

Composer eagerly registers the canonical aliases once. This is necessary because `instanceof` and exception
catch matching do not reliably autoload an unknown alias. Ordinary PSR-4 lookup also locates every canonical
entry point. Reflection of either spelling reports the retained root runtime name and the same constructor,
methods, constants and final/readonly flags. `::class` remains the literal spelling written by the caller.
Containers keyed by literal strings must register the spelling they request (or explicitly bind both); Common
cannot make unrelated consumer container IDs equivalent merely by adding PHP aliases. Reflection construction,
typed injection and existing framework references remain functional.

Native PHP serialization continues to write the released root class identity and property scopes even when
constructed through a canonical alias. `McpResult` explicitly restores a missing later optional response budget
to null when reading tagged frames; its native writes and public result representation are unchanged.
Tagged standalone value frames remain readable, including metadata, requests, results, responses, mirrors, limits, server identity and protocol errors. Existing public array/JSON
representations are unchanged. This is not permission to unserialize untrusted input, a new durable-storage
schema or a promise to serialize live capabilities/services and their external collaborators.

The untagged `Protocol\McpTraceContextGrammar`, `Protocol\McpImplementationInfoGrammar` and
`Composition\McpCompositionValidator` remain internal, with no root aliases or deprecations. Internal methods on
public types retain their existing classification; aliasing does not promote them to public package promises.

## Adoption

```php
use Fight\Common\Application\Mcp\Capability\McpCapabilityRegistry;
use Fight\Common\Application\Mcp\Http\McpInvocationGuard;
use Fight\Common\Application\Mcp\Protocol\McpRequestDecoder;
use Fight\Common\Application\Mcp\Protocol\McpServerInfo;
use Fight\Common\Application\Mcp\Tool\McpProgressReporter;
```

Change imports and explicit consumer registration IDs, not protocol methods, Tool identifiers, payloads or saved
values. Availability, independent authorization, safe errors, central budgets, transaction/post-commit ordering
and live delivery safeguards are unchanged. Application retains semantic protocol and inward HTTP ports;
concrete HTTP/native transport stays in Adapter. Historical TASK-00177 acceptance is not reopened.
