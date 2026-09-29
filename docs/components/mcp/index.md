---
template: atlas-article.html
atlas_article: true
title: MCP Protocol Semantics
atlas_article_heading_id: mcp-protocol-semantics
atlas_component_group: Coordinate Application Behavior
atlas_component_owner: Application
atlas_component_dependencies: PHP 8.5+ and this package
atlas_article_context: Application
atlas_article_lead: Decode one stateless MCP request into a framework-neutral semantic model, discover configured capabilities truthfully, and return a JSON-RPC result without selecting an HTTP route or security policy.
atlas_article_requires: PHP 8.5+
atlas_article_package: johnnickell/fight-common
atlas_relationship_source_label: Consumer composition
atlas_relationship_source: McpCapabilityRegistry
atlas_relationship_target_label: Semantic responder
atlas_relationship_target: McpResponder
atlas_relationship_description: The consumer supplies a server identity and explicitly registered capabilities; the responder derives discovery and dispatch behavior from that registry.
atlas_relationship_caption: Common owns protocol semantics and guarded PSR HTTP handling. Consumers own routes, authentication, authorization, and framework composition.
atlas_consequential_label: Security boundary
atlas_consequential_message: MCP metadata is never a principal or credential carrier. HTTP composition requires explicit Origin and invocation policies plus redaction-aware diagnostics; no default route or authorization policy exists.
atlas_local_contents:
  - label: Ownership
    href: "#ownership"
  - label: Composition
    href: "#composition"
  - label: Tool registration
    href: "#explicit-tool-registration"
  - label: Tool discovery
    href: "#available-tool-discovery"
  - label: Schema and output
    href: "#schema-declarations-and-semantic-output"
  - label: Tool invocation
    href: "#validated-tool-invocation"
  - label: Protected ordinary input
    href: "#protected-ordinary-input"
  - label: Atomic destructive confirmation
    href: "#atomic-destructive-confirmation"
  - label: Progress and cancellation
    href: "#request-scoped-progress-and-cancellation"
  - label: Tool metadata
    href: "#tool-envelope-metadata"
  - label: Protocol behavior
    href: "#protocol-behavior"
  - label: Guarded HTTP
    href: "#guarded-http"
  - label: Progressive HTTP
    href: "#progressive-http-delivery"
  - label: Consumer wiring
    href: "#consumer-wiring"
---

## Ownership

`Application\Mcp` implements framework-neutral MCP `2026-07-28` semantics. It decodes a JSON-RPC request,
retains bounded per-request MCP metadata, validates the protocol version, and produces a semantic JSON-RPC
result or protocol error. It does not create a PSR response, choose an endpoint, authenticate a caller, resolve a
principal, or make an authorization decision.

The consumer supplies `McpServerInfo` and one or more explicit `McpCapability` implementations through
`McpCapabilityRegistry`. Each capability owns its methods, advertised metadata, outer-parameter validation, and
handling. The registry rejects missing identity, capabilities without owned methods, duplicate method ownership,
contradictory metadata, a standard method whose advertised capability family does not match, an advertised standard
family without its mandatory anchor method, non-JSON capability definitions, and invalid transport-mirror
declarations during composition.

The `prompts.listChanged`, `resources.subscribe`, `resources.listChanged`, and `tools.listChanged` flags describe
subscription-driven delivery behavior. TASK-00104 supplies neither the subscription contract nor its long-lived
delivery lifecycle, so this foundation rejects those flags when `true`. A later capability must introduce and prove
that contract before it may advertise the flags.

## Composition

Construct `McpResponder` with a registry. `server/discover` is reserved to the responder. Every capability that
registers a dispatchable method must advertise at least one validated capability definition, so discovery cannot
hide a callable method. Its result advertises only the registry's configured capabilities, `supportedVersions`, and the consumer-supplied server identity under
`_meta.io.modelcontextprotocol/serverInfo`. Every successful semantic result likewise includes that configured
identity, preserving other `_meta` values while the responder owns and overrides its reserved server-identity key.
It uses the conservative cache defaults `ttlMs: 0` and
`cacheScope: private`; consumers do not supply a cache or authorization policy to this foundation.

For any other registered method, the responder validates the outer MCP request once and dispatches exactly that
capability. The result is `McpJsonResponse`, an `Arrayable` semantic JSON-RPC response. `toJson()` encodes it;
`errorCode()` permits transport status mapping. `dispatch(McpRequest)` handles an already-decoded request and
propagates failures to the caller's diagnostic boundary. Unlike `respond(string)`, it does not sanitize thrown
failures itself. `McpRequestHandler` uses this seam only after HTTP safeguards, and owns sanitization and diagnostics.

A capability rejects invalid outer parameters by throwing `McpProtocolException` with
`McpProtocolError::invalidParams()` and the request ID. The responder preserves that semantic rejection as the
JSON-RPC `invalidParams` error. Any other `Throwable` from a capability becomes the generic `internalError`, with
no exception details exposed. Direct semantic consumers must supply `McpResponder(..., diagnostics: $diagnostics)`
to record unexpected failures through their redaction-aware `McpDiagnostics`. The optional constructor argument
preserves existing compositions; omitting it supplies no logging. `dispatch()` never logs: its HTTP/caller boundary
owns the one diagnostic, preventing duplicate records.

## Explicit Tool registration

`Application\Mcp\Tool` provides explicit registration, discovery and validated invocation. A consumer Tool implements
`McpTool` and declares exactly one `#[McpToolInfo(name:, description:, inputSchema:, outputSchema:)]` on its
`handle(ApplicationData $input, McpProgressReporter $progress): McpToolOutput` method. `ApplicationData` is
`Application\Validation\Data\ApplicationData`; the reporter lives directly in `Application\Mcp`.

Construct `McpToolRegistry` with the complete array of opted-in Tool objects. Registration examines only those
objects' `handle()` metadata. It never scans a container, discovers HTTP Actions or CQRS handlers, hydrates a
payload, or invokes the Tool. Missing/duplicate method metadata, non-Tool registrations, malformed metadata,
unsupported schema declarations, invalid input-schema mirror annotations, and duplicate canonical names fail
composition with `DomainException`.
Names are case-sensitive, 1–128 ASCII letters, digits, underscores, dots or hyphens. Descriptions must be nonblank
UTF-8. Input and output schemas are required by the Common convention even though MCP makes output schemas optional.

`McpToolInfo` exposes only name, description and isolated schema snapshots. `McpToolRegistry::find()`, `definition()`
and `mirrorsFor()` are unprivileged composition lookups, **not** authorization or invocation APIs. Invocation applies
the same request-scoped availability decision as discovery before inspecting arguments or executing a Tool. Existing business authorization
remains the consumer use case's responsibility on every entry path.

```php
use Fight\Common\Application\Mcp\McpCapabilityRegistry;
use Fight\Common\Application\Mcp\McpResponder;
use Fight\Common\Application\Mcp\McpServerInfo;
use Fight\Common\Application\Mcp\Tool\McpToolDiscovery;
use Fight\Common\Application\Mcp\Tool\McpToolRegistry;

// Explicit consumer-owned Tool objects and request-scoped McpToolAvailability.
$tools = new McpToolRegistry([$findOrderTool, $listOrdersTool]);
$discovery = new McpToolDiscovery($tools, $availability, $cursorSigningKey);
$registry = new McpCapabilityRegistry(new McpServerInfo('Orders', '1.0.0'), [$discovery]);
$responder = new McpResponder($registry);
// The same registry can be supplied to the guarded PSR endpoint below.
```

The required cursor key is a consumer-generated secret of at least 32 bytes, stable across serving processes and
requests. Keep it in consumer secret configuration; do not use an access token, user identifier, or source-code
example key. Rotating it invalidates outstanding cursors. This composition needs no framework integration.

## Available Tool discovery

`McpToolDiscovery` registers only `tools/list` and advertises `tools: {listChanged: false}`. An empty registry
is valid and returns an empty list. Tool calls, subscriptions and list-change notifications are not implemented
by this capability; `tools/call` requires the separate `McpToolInvocation` capability below. Conflicting Tool metadata from another capability fails
in the existing `McpCapabilityRegistry`, rather than permitting contradictory discovery.

`McpToolAvailability::isAvailable(McpToolInfo)` is mandatory. Its consumer implementation may privately retain
current authentication and policy collaborators but receives no principal, credential, headers, raw arguments,
or authorization reason from Common. For every request the registry asks availability about each registered
metadata definition, filters unavailable definitions, then sorts the visible sequence by bytewise canonical name.
Pagination operates only on that sequence. No availability result is cached in Common.

The default page size is 100; an explicit size must be 1–1000. A small/final page omits `nextCursor`. Continuations
are opaque, authenticated tokens bound to the current visible definitions and page size. They contain no Tool name,
concealed definition, or unfiltered count. Changing hidden registrations alone does not change the token. Changing
visible metadata or availability invalidates an affected continuation; the client restarts without a cursor.
Every continuation reevaluates current availability. Tampered, out-of-range, wrong-key, or malformed cursors return
the same `-32602` invalid-params error with no definitions or diagnostic detail. Tokens are not credentials and
provide no authorization. Unknown outer parameters, including client-supplied page sizes, reject.

Lists default to `ttlMs: 0` and `cacheScope: private`. Constructor overrides require a nonnegative JavaScript-safe
integer TTL and exactly `private` or `public`. Selecting public caching is an explicit consumer assertion that the
catalog is safe to share across authorization contexts; Common cannot prove that policy. Positive TTL also means
the consumer accepts cached discovery being stale. These overrides do not alter `server/discover` cache defaults.
Availability failures propagate to the existing guarded endpoint's diagnostics/sanitization boundary; they never
become a partially returned list or an exposed policy reason.

## Schema declarations and semantic output

Registration validates a **bounded declaration profile**; invocation evaluates that same profile against arguments
and successful structured output. Neither is a full JSON Schema implementation.
Both schemas are PHP arrays representing JSON objects. The input root must contain `type: object`; output schemas
may describe any JSON value, including scalar, array or null output. An empty output declaration means `{}`.
Without `$schema`, the dialect is JSON Schema 2020-12. If supplied, it must be exactly
`https://json-schema.org/draft/2020-12/schema`. This implementation currently supports:

- `type`: one JSON type or a nonempty unique type list; nested schemas may also be Booleans or objects.
- `properties`, `$defs`: maps of schemas; `items`, `additionalProperties`, `not`: one schema.
- `allOf`, `anyOf`, `oneOf`: nonempty arrays of schemas; `required`: unique string arrays.
- `enum`: nonempty arrays of JSON values; `const`, `default`: JSON values; `examples`: arrays.
- `title`, `description`: strings; `readOnly`, `writeOnly`, `deprecated`, `uniqueItems`: Booleans.
- `minLength`, `maxLength`, `minItems`, `maxItems`, `minProperties`, `maxProperties`: nonnegative PHP integers.
- `minimum`, `maximum`, `exclusiveMinimum`, `exclusiveMaximum`: finite numbers; `multipleOf`: positive numbers.
- `x-mcp-header`: a nonempty ASCII alphanumeric/hyphen suffix starting with an alphanumeric character. Input-schema
  annotations are compiled into per-Tool mirrors during registration, with the reachability/type rules below.

Other keywords and dialects fail closed. In particular, references, formats, patterns and conditional schemas are
not currently supported. No remote schema is fetched. This profile is deliberately narrower than MCP permits;
it is not a claim of full JSON Schema conformance. `$defs` declarations are validated, but references are not enabled.
At runtime, annotations (`default`, `examples`, descriptions, read/write flags and header names) do not transform or
validate data. Assertions apply only to their JSON type; strings count Unicode code points, objects compare without
property-order significance, lists retain order, and numeric equality does not coerce strings or Booleans.
Numeric bounds and `multipleOf` use exact decimal arithmetic over PHP's JSON-encoded numeric values, without epsilon
rounding or optional math extensions. This cannot recover precision already lost when PHP decoded a JSON number.
PHP arrays at `properties`/`$defs` map positions may use `[]` for `{}`. The Boolean schema `true` permits any value;
programmatic declarations can use `StrictJson::fromObject()` for an empty schema object. Lists, objects,
numeric/Boolean/null values and annotations retain their JSON meaning.

Schemas, request metadata and output use `Domain\Value\Basic\StrictJson`, accepting only plain JSON values
and existing immutable StrictJson nodes. Tool schemas, arguments and output are bounded to depth 64:
the schema/output root is depth zero and each member or element adds one. Discovery's definition, catalog and
protocol wrappers do not consume a schema's depth budget. Malformed Unicode, nonfinite numbers, resources,
arbitrary objects/serializers and cycles reject. Object keys beginning with U+0000 also reject at construction
with `DomainException`, because PHP's object-mode JSON decoder cannot represent them; empty keys and keys with
non-leading U+0000 remain supported. This representation limit applies at every nesting level, including schema
annotation values. Construction snapshots mutable input, and accessors retain immutable typed object nodes preserving JSON
object/list distinctions. No consumer serializer executes.
This is data-shape safety, not automatic redaction: consumers must explicitly project public-safe schemas and data.

`McpToolInfo::inputSchema()` and `outputSchema()` return `Fight\Common\Domain\Value\Basic\StrictJson` values.
Attribute declarations remain PHP arrays. Accessors and `toArray()` share immutable schema values, not mutable
decoder objects. Use `get('properties')->get('id')->get('type')` for object navigation, `properties()` for a property
map, and `toString()` or direct JSON serialization for wire output. Discovery JSON and cursor hashing are unchanged.

`McpToolOutput::structured($publicData)` carries one complete JSON value. `structuredContent()` returns immutable
`StrictJson`, whose `toString()` matches `text()`, including zero-fraction numbers such as `1.0`. It represents any
JSON root: object, list, string, number, Boolean or null. `toData()` returns an immutable StrictJson node for an
object, a PHP list with typed object children for an array, or the scalar itself. The factory validates plain data,
not arbitrary serializer objects. It creates no JSON-RPC response, error envelope, HTTP response, or partial result.
The caller owns safe projection; `McpToolInvoker` enforces output-schema conformance before emitting success.

This pre-release revision replaces the earlier MCP JsonObject/raw-object accessors with StrictJson. Request decoding,
client capability/info accessors, nested validated Tool arguments, discovery definitions, mirror traversal and semantic
structured output now use the same typed object representation; HTTP JSON remains compatible. Missing Tool arguments
become `StrictJson::fromObject()`. `McpRequestMetadata::fromObject()` takes StrictJson, not a generic PHP object.
The Domain type owns bounded JSON safety; MCP retains protocol/schema policy, including the original 512-level
protocol codec limit separately from the 64-level Tool-data bound. Legacy `JsonObject`, JSend and cache
sentinels are unchanged. See [StrictJson](../values/index.md#strictjson) for construction, navigation and equality. The
final output class derives compatible text from the identical snapshot, so a Tool cannot supply a separate unsafe
or contradictory text representation. Shape checks cannot determine whether an otherwise valid scalar is secret.

`McpProgressReporter` is only the stable `report(float $progress, ?float $total, ?string $message)` and
`isCancelled(): bool` port required by the Tool signature. Its implementation contract calls for finite nonnegative,
monotonic progress, an optional total not below progress, public-safe status text, and no emission after cancellation.
It carries no Tool result content. `NullMcpProgressReporter` validates monotonic finite progress but emits nothing and
never reports cancellation. Invocation supplies a fresh instance per Tool call by default. The request-scoped
semantic execution below supplies live progress without broadening this interface. The opt-in HTTP composition
below supplies SSE and observed-disconnect cancellation; rollback is never implied.

## Validated Tool invocation

Compose `McpToolInvocation(new McpToolInvoker($tools, $availability, ...))` alongside `McpToolDiscovery` in the same
capability registry. Both advertise `tools: {listChanged: false}`; discovery remains the required anchor method.
Use the same request-scoped availability implementation for both. The invoker is a semantic boundary, not a bus,
authorizer or transaction owner.

```php
use Fight\Common\Application\Mcp\Tool\McpToolFailureMap;
use Fight\Common\Application\Mcp\Tool\McpToolInvocation;
use Fight\Common\Application\Mcp\Tool\McpToolInvoker;

$invocation = new McpToolInvocation(new McpToolInvoker(
    $tools,
    $availability,
    failures: new McpToolFailureMap([
        OrderAlreadyClosed::class => 'The order is already closed.'
    ])
));
$registry = new McpCapabilityRegistry($serverInfo, [$discovery, $invocation]);
$responder = new McpResponder($registry, diagnostics: $diagnostics);
```

Initial `tools/call` accepts `name` and optional `arguments`; protected interactive retries additionally carry
`requestState` and `inputResponses` as described below. Omitted arguments mean `{}`; present non-object arguments
are a selected-Tool validation failure. Unknown outer parameters reject as invalid params. The default composition
also rejects a progress token: it must not silently accept progress without a bound execution and delivery path.

Invocation selects an explicitly registered Tool, asks availability about its immutable metadata, then checks the
arguments against the input schema. Unknown and unavailable Tools produce identical `-32602`, `Unknown or
unavailable tool.` errors without argument/rule validation, invocation, metadata enrichment or diagnostics.
For an available Tool, reflection reads method-level `#[Validation]`; absent attributes or empty rules mean no
additional rules. `ValidationService` receives exactly the arguments' top-level field array and produces the
`ApplicationData` passed to `handle()`. Nested objects/lists retain their types, and input is snapshotted without
executing serializers. Numeric top-level property names reject because PHP arrays cannot retain them as the string
keys required by `ValidationService`. Plain-JSON depth/Unicode/key restrictions above apply to arguments too.

Declare rules beside `McpToolInfo`, for example:

```php
#[McpToolInfo(
    name: 'orders.find',
    description: 'Find one public order',
    inputSchema: [
        'type' => 'object',
        'properties' => ['id' => ['type' => 'string', 'minLength' => 1]],
        'required' => ['id'],
        'additionalProperties' => false
    ],
    outputSchema: [
        'type' => 'object',
        'properties' => ['id' => ['type' => 'string']],
        'required' => ['id'],
        'additionalProperties' => false
    ]
)]
#[Validation(rules: [['field' => 'id', 'label' => 'Order', 'rules' => 'required|not_blank']])]
public function handle(ApplicationData $input, McpProgressReporter $progress): McpToolOutput
{
    $order = $this->queries->fetch(new GetOrder($input->get('id')));

    return McpToolOutput::structured((object) ['id' => $order->id]);
}
```

Import the attributes/types from `Application\Mcp\Tool`, `Application\Attribute\Validation`,
`Application\Mcp\McpProgressReporter`, and `Application\Validation\Data\ApplicationData`. The consumer owns the
existing `GetOrder` Query and safe projection. For a mutation, explicitly construct the existing Command, call void
`CommandBus::execute()` once, then acknowledge only known data such as its caller-generated identifier. Do not
invent a command result, reflectively hydrate messages, or perform a read solely to manufacture a result.
Consumer handlers retain authorization, transaction, retry and idempotency policy; this layer adds none.

Schema checks and runtime rules are separate. The package fixtures prove shared required/string/length boundaries,
an additional runtime `not_blank` rejection, empty-rule pass-through, one query/write, and public projection. The
consumer must keep its own schema and rules consistent; registration does not infer one from the other.

| Outcome | Semantic result |
| --- | --- |
| Input schema, plain-JSON or runtime validation rejection after selection | Complete `isError: true`, fixed safe text, no input/labels reflected |
| Exact failure class registered in `McpToolFailureMap` during Tool execution | Complete `isError: true`, consumer-authored constant text |
| Unregistered failure, availability/metadata/validator failure, invalid successful output | Generic `-32603`, recorded once at the central boundary |
| Successful conformant output | Complete text plus identical `structuredContent` |

Failure mappings use exact classes, not inheritance or automatic Domain/Application classification. Messages must
be nonblank valid UTF-8, at most 4096 bytes, without control characters. They are deliberate public projections,
never exception messages. A mapped error omits structured success content and need not satisfy the success schema.
A Tool-thrown `McpProtocolException` cannot bypass this boundary: it is unexpected unless explicitly mapped to safe
Tool-error text. Output validation and metadata failures cannot be mistaken for mapped business failures.

## Protected ordinary input

An ordinary additional-input interaction is an opt-in Tool result, not a confirmation, authentication mechanism,
transaction or durable workflow. `Application\Mcp\Tool\Interaction\McpInteractiveTool` extends `McpTool` with:

```php
public function resume(
    ApplicationData $input,
    McpInputResponses $responses,
    McpProgressReporter $progress
): McpToolOutput|McpInputRequired;
```

**Approved pre-release API change:** `McpTool::handle()` now returns `McpToolOutput|McpInputRequired`. Existing
non-interactive implementations retain their narrower `McpToolOutput` return and unchanged behavior. Callers of the
base interface must handle both variants; this is not signature-compatible with an assumption of complete output.
`McpToolOutput` itself remains final, immutable and complete-only. All new interaction types below belong to
`Application\Mcp\Tool\Interaction` unless otherwise qualified.

Declare `requiresFormElicitation: true` in `#[McpToolInfo(...)]` on an interactive Tool's `handle()`. Registration
rejects a mismatch between that flag and the interactive interface. Keep initial `#[Validation]` and all Tool
metadata on `handle()` only; do not duplicate them on `resume()`. Current availability is checked first, then the
current request must declare `_meta.io.modelcontextprotocol/clientCapabilities.elicitation.form: {}` before initial
schema/rule validation or `handle()`. Missing form support returns `-32021` with safe
`data.requiredCapabilities: {elicitation: {form: {}}}` and invokes no Tool or bus. An empty `elicitation: {}`, a URL-only
capability, a prior request, server advertisement, or endpoint configuration does not substitute for form support.
Direct `McpToolInvoker::invoke()` has no request capability evidence and rejects interactive Tools. The registered
`McpToolInvocation` uses `invokeRequest()` with the current semantic request instead.

A Tool may complete normally, or return ordinary additional input without dispatching its mapped use case:

```php
return McpInputRequired::fromRequests([
    'details' => McpInputRequest::form(
        message: 'Provide a public label',
        schema: [
            'type' => 'object',
            'properties' => ['label' => ['type' => 'string', 'minLength' => 1]],
            'required' => ['label']
        ],
        rules: [['field' => 'label', 'label' => 'Label', 'rules' => 'not_blank']]
    )
]);
```

Each response key is a nonempty, non-numeric UTF-8 string. A request contains at least one form. Messages must be
nonblank UTF-8. Forms must never solicit passwords, API keys, payment authorizations or other secrets. Consumers
own that content policy; shape validation is not secret detection. The selected invoker turns this value into
`resultType: input_required`, keyed `inputRequests` containing `elicitation/create` form requests, and opaque
`requestState`. The responder preserves this result kind and adds its normal server identity. Runtime rule labels
and declarations are inside encrypted state only, never inside the public elicitation schema.

### State protection and composition

Compose the existing invoker with an explicit request-scoped interaction service:

```php
use Fight\Common\Adapter\Mcp\Sodium\SodiumMcpStateProtector;
use Fight\Common\Application\Mcp\Tool\Interaction\McpToolInteraction;

$protector = new SodiumMcpStateProtector(
    activeVersion: 2,
    keys: [1 => $previousRandom32ByteKey, 2 => $activeRandom32ByteKey]
);
$invoker = new McpToolInvoker(
    $tools,
    $availability,
    interaction: new McpToolInteraction($protector, $neutralCallerBinding)
);
```

The optional Sodium adapter requires `ext-sodium` and uses XChaCha20-Poly1305 AEAD with a fresh random 24-byte nonce,
a 16-byte authentication tag and domain-separated associated data including the version prefix. Tokens have the
form `1.<key-version>.<unpadded-base64url nonce-and-ciphertext>`. Only format/key versions are readable; caller,
Tool, arguments, requested contracts/runtime rules, expiry and prior request ID remain encrypted. Base64 alone is
not state protection. Encryption and validation use a dedicated key ring, never Bearer/HMAC credentials or
`NonceRepository`. At most four keys are accepted, each exactly 32 random bytes and indexed by an integer version
from 1 through 999999999. Issue only with the active version; retain older keys only for the desired grace period.
Removing a key retires its tokens immediately. Unknown/retired versions, modified headers/nonces/ciphertexts/tags,
and noncanonical token encodings fail closed. Keep all keys outside source control, requests, logs and fixtures
except explicitly nonproduction test keys.

`McpStateProtector::MAX_STATE_BYTES` is **65536 bytes**, enforced before token parsing/decryption in both the
Application boundary and Sodium adapter. The adapter also rejects an oversized projected token before encryption.
A custom `McpStateProtector` must preserve these confidentiality, size, key-rotation and failure guarantees; it is
not permission to substitute plaintext or integrity-only state. Oversized server-authored interaction output is an
unexpected internal error, not a truncated or partially protected token. There is no interaction table or in-memory
session: another instance with the same keys and caller binding can resume.

The consumer supplies a stable neutral caller-binding string (1–1024 UTF-8 bytes), not a principal, credential,
permission or raw request. Use separate key rings per endpoint audience, or explicitly qualify the binding with the
audience. Default TTL is 300 seconds; composition accepts 1–900 seconds. Workers must have synchronized clocks.
The state binds ordinary mode, `tools/call`, canonical Tool name, original request arguments, the original validated
`ApplicationData`, requested forms/rules/keys, caller, expiry, and issuing JSON-RPC ID. Objects are canonicalized by
bytewise sorted keys; lists retain order, object/list distinctions and scalar types (including `1` versus `1.0`).
No serializer is run. State wrapper depth permits the existing 64-level Tool argument bound.

### Retry ordering and outcomes

Retry `tools/call` with a **fresh JSON-RPC ID**, the same `name` and original `arguments`, echoed `requestState`, and
`inputResponses: {details: {action: "accept", content: {label: "Approved label"}}}`. Object key ordering may differ;
replacement values or types reject. An originally omitted argument object may remain omitted or be explicit `{}`.

1. Bound and authenticate/open the token privately only far enough to locate its candidate Tool.
2. Reevaluate that Tool's **current** availability. Unknown or revoked Tools return the identical unknown/unavailable
   error before restored-state disclosure, binding details, response validation, `resume()` or bus dispatch.
3. Require current form capability; check mode/method, caller, Tool/name, arguments, expiry and fresh request ID.
4. Validate the complete keyed response map and every envelope/action before any accept-only content. Missing or
   extra keys, unknown envelope fields/actions and malformed envelopes reject. This deliberately uses the TASK's
   stricter exact-key/fail-closed policy rather than MCP's advisory ignore-extra/reprompt behavior.
5. For `accept`, require an object and validate it against the retained form schema and runtime rules with a fresh
   `ValidationService` per response. `decline` and `cancel` prohibit `content`, including explicit null, and skip
   accept-only validation. Restore original validated arguments without rerunning initial schema or `#[Validation]`.
6. Call `resume()` once with restored `ApplicationData`, typed `McpInputResponses`, and the existing reporter.
   `$responses->get('details')->action()` returns `accept`, `decline` or `cancel`; `content()` returns validated
   `ApplicationData` only for accept, otherwise null. `toArray()` returns the keyed typed map.

Ordinary decline/cancel reaches the Tool for its safe terminal decision; it is not a destructive confirmation.
`resume()` owns mapping an accepted response to the existing query/command and projecting public-safe output.
Complete output still passes the declared output schema and existing failure/metadata boundaries. Further
additional-input results are supported without reentering initial `handle()`; each new result binds its issuing ID.
Invalid state and invalid response content share generic `-32602 Invalid params.` with no private diagnostic text.
Unexpected Tool/output/protection failures are sanitized and diagnosed at the existing central boundary; exact
mapped consumer failures retain their authored public text.

**Ordinary tokens are replayable until expiry or key retirement.** A fresh ID does not make them single-use.
Destructive or otherwise replay-sensitive operations require the distinct [atomic confirmation mode](#atomic-destructive-confirmation).
Do not use ordinary input as an authorization approval token. Consumers
retain current business authorization, idempotency and dispatch policy on every `resume()`. Availability is not a
substitute for authorization inside the consumer use case.

## Atomic destructive confirmation

A consumer-designated replay-sensitive Tool uses the same `McpInteractiveTool`, static form-capability gate,
form schemas, validation, protected state, current-availability checks and `resume()` contract. It opts in by
returning `McpInputRequired::confirmation($forms)` instead of `fromRequests($forms)`. Common never infers
which actions are destructive or what an affirmative form value means. The Tool must not dispatch its mutation
while requesting confirmation; it owns current business authorization and content policy when resumed.

```php
// Inside the consumer's interactive Tool; the form content and destructive policy belong to that consumer.
return McpInputRequired::confirmation([
    'approval' => McpInputRequest::form(
        'Confirm removal of this fixture value',
        [
            'type' => 'object',
            'properties' => ['confirm' => ['type' => 'boolean']],
            'required' => ['confirm']
        ]
    )
]);

// At the composition root; $confirmationStore implements McpConfirmationStore.
$interaction = new McpToolInteraction(
    $protector,
    $neutralCallerBinding,
    confirmations: $confirmationStore
);
```

Confirmation state adds a dedicated cryptographically random 256-bit identity and `confirmation` mode to the
same encrypted bindings. It contains no authentication credential or HMAC request nonce. Before exposing the
`input_required` result, Common seals the bounded state and calls `McpConfirmationStore::issue($id, $binding,
$expires)`. The store receives only a 64-character lowercase hexadecimal identity, the SHA-256 digest of the
**complete canonical protected state**, and its exclusive Unix-second expiry. No raw caller, original arguments,
forms or approval language cross this storage port. An unavailable store, collision or issue failure emits no
usable confirmation result and reaches the existing sanitized internal-error boundary. Each issue creates a new
identity; it never renews a consumed confirmation.

### Atomic store contract

Consumers implement `Application\Mcp\Tool\Interaction\McpConfirmationStore` against shared durable storage:

- `issue()` persists one unused identity before returning and must never overwrite an existing or consumed entry.
- `consume()` compares the identity, full-state binding and exact expiry, checks expiry against the current time,
  and durably transitions unused to consumed **atomically**. Only one contender can return `CONSUMED`. An exclusive
  transaction/conditional update or equivalent atomic mechanism is required; separate get/delete operations are not.
- `McpConfirmationOutcome` distinguishes `CONSUMED`, `ABSENT`, `EXPIRED`, `MISMATCHED`, `ALREADY_CONSUMED` and
  `CONTENDED` internally. A consumer may reject lock contention instead of waiting. Preserve consumed identity
  until expiry; absent/expired records cannot be acquired. Consumers own retention, backend access controls,
  synchronized clocks and operational recovery, not Common.
- Throw on infrastructure failure. An ambiguous acknowledgement must never restore potentially consumed state.
  Common does not retry, release, renew, roll back or reinstate confirmations. `NonceRepository`, `MutableCache`,
  a process-local flag, or a read-then-delete pair cannot substitute for this contract.

No production store adapter is supplied. The package's `LockedConfirmationStore` **test fixture** demonstrates
cross-process mutual exclusion over one file; it is not qualified for production, distributed filesystems, database
failover or crash recovery. Consumers must prove the same contract for their chosen storage technology.

### Confirmation retry and terminal outcomes

Retry uses the same fresh-ID, original-arguments and keyed `ElicitResult` rules as ordinary input. Common first
checks current Tool availability before restored-state disclosure or response validation, then current form
capability and every protected binding. It validates all envelopes/keys/actions and accept-only schema/rule content
**before** store acquisition. An unavailable Tool remains indistinguishable from an unknown Tool and never touches
the store. Invalid responses do not consume the confirmation or a different identity.

After successful validation, exactly one atomic consume occurs:

- If every action is `accept`, Common checks the current reporter for cancellation and then calls `resume()` once.
  An `accept` action means the client supplied form content, not that Common approves the business action. For
  example, a schema-valid `confirm: false` still reaches the consumer, which must interpret it before dispatch.
- If **any** keyed action is `decline` or `cancel`, Common retires the whole confirmation and returns a complete
  text-only result, `Confirmation not accepted.` It calls neither `resume()` nor any underlying bus. Refusals
  prohibit even null `content`; they do not validate accept-only content. Mixed maps still validate the content
  of their accepted responses before retirement.
- A reporter cancelled after acquisition but before `resume()` prevents Tool/bus dispatch and returns a safe
  cancelled Tool result; an outer cancelled stream suppresses final delivery. During `resume()`, cancellation
  remains cooperative: the Tool must observe its reporter. Common never interrupts work or rolls back a command.
- Consumption is permanent after success, mapped rejection, unexpected `Throwable`, invalid final output,
  metadata/response failure, cancellation or disconnect. Any later attempt fails closed before `resume()` or
  command/query/event dispatch. This is at-most-once admission, **not** a transaction with the business mutation:
  a crash after consumption may leave the action unperformed, and a failed response may follow a committed action.
  Recovery and any newly issued confirmation are explicit consumer decisions, never automatic replay.

Store rejections (including contention) and consumption failures all expose only `-32602 Invalid params.`;
no store, caller, Tool, arguments or internal rejection reason is reflected. Existing exact Tool failure mappings,
central unexpected-failure diagnostics, command/query envelopes, HMAC nonces and Bearer composition are unchanged.
Ordinary input remains stateless and replayable even when a confirmation store is configured.

`tests/Application/Mcp/Tool/Interaction/McpConfirmationTest.php` proves protected state, ordering, rejection,
refusal, consumption after each outcome, cancellation before/during resume, and simulated stream closure through
the semantic responder. `tests/Functional/McpConfirmationJourneyTest.php` checks a concrete store contract and
releases two independent processes from one start barrier: exactly one complete retry, one safe loser, and one
command plus event side effect through the real routing bus/event dispatcher. This is package evidence, not a
consumer persistence certification, live HTTP cancellation receipt or business-authorization guarantee.

### Elicitation schema profile and evidence

Forms require a root `type: object`, `properties`, optional declared `required` names, and optional exact JSON Schema
2020-12 `$schema`. Properties are non-numeric string names. Supported fields are strings with length bounds,
number/integer bounds, Booleans, string enums, titled single-select `oneOf` const/title choices, and string-array
multi-selects (`items` type/string-enum or `items.anyOf` const/title choices) with item-count bounds. Fields may have
string title/description and a conformant typed default. No defaults are inserted into responses. Only declared
content properties are accepted. Runtime rules use the existing `field`/`label`/`rules` string declarations and must
name declared form fields.

This is a bounded subset of MCP's restricted form vocabulary: `format`, legacy `enumNames`, nested objects,
arbitrary arrays, references, patterns and other keywords reject at declaration rather than being silently ignored.
Use explicit runtime rules for additional supported server-side checks. Client UI constraints never replace server
validation. This does not claim full JSON Schema or every optional MCP elicitation feature; URL mode, sampling,
roots and durable Tasks remain outside this form slice; concrete confirmation persistence remains consumer-owned.

Pinned authority: MCP commit
[`ab3a39c13bd23be691c2760e1c6c5c15a64582e1`](https://github.com/modelcontextprotocol/modelcontextprotocol/tree/ab3a39c13bd23be691c2760e1c6c5c15a64582e1),
[Tools](https://github.com/modelcontextprotocol/modelcontextprotocol/blob/ab3a39c13bd23be691c2760e1c6c5c15a64582e1/docs/specification/2026-07-28/server/tools.mdx),
[MRTR](https://github.com/modelcontextprotocol/modelcontextprotocol/blob/ab3a39c13bd23be691c2760e1c6c5c15a64582e1/docs/specification/2026-07-28/basic/patterns/mrtr.mdx), and
[elicitation](https://github.com/modelcontextprotocol/modelcontextprotocol/blob/ab3a39c13bd23be691c2760e1c6c5c15a64582e1/docs/specification/2026-07-28/client/elicitation.mdx).
`McpToolInteractionTest` exercises real semantic responder round trips with query/command spies and a fresh retry
instance; `McpInputRequestTest` proves schema, envelope, typed response and runtime validation behavior;
`SodiumMcpStateProtectorTest` proves AEAD round trips, tampering, limits and rotation. These are package fixtures,
not external SDK certification, a consumer authorization receipt or framework-starter qualification.

## Request-scoped progress and cancellation

`McpToolInvoker::invoke($name, $arguments, $progress)` accepts an optional `McpProgressReporter`. Omission preserves
its fresh `NullMcpProgressReporter` default and existing validation, availability, metadata and safe-output behavior.
The reporter interface is unchanged; progress never enters Tool arguments or CQRS payloads. Non-interactive Tools
return complete `McpToolOutput`; opted-in interactive Tools may instead request ordinary input as described above.
Neither result is a generator, callback, partial result or response object.

Internal `Tool\McpToolExecution` supplies ordered execution. Its invocation binding, including
`McpResponder::dispatch()`'s optional execution parameter and `McpToolInvocation::withExecution()`, remains a
package implementation seam, not a consumer transport API. The HTTP adapter binds an isolated invocation.
The default guarded endpoint remains direct JSON; enable `progressive: true` only with the live emitter below.
This preserves existing buffered consumers instead of silently accepting progress-token requests.

One execution binds the exact decoded `tools/call` request and is shared only with that request's invocation
capability. Its `run()` dispatches through the existing semantic responder; an absent token selects exactly the
no-op reporter, while a string or integer token selects the live reporter. Empty strings, zero and integer signs
are preserved without coercion. Present null, Boolean, array, object or floating-point tokens reject during decode;
a token misplaced in outer Tool parameters rejects before invocation. Tokens are not globally indexed: clients
own uniqueness, and even equal tokens on independent request objects share neither reporter nor cancellation state.

The live reporter accepts finite nonnegative work, strictly increasing after the first report (which may be zero).
Optional total must be finite and at least progress; optional message is UTF-8 public-safe human-readable status.
Each report delivers exactly `{jsonrpc: "2.0", method: "notifications/progress", params: {progressToken, progress,
...optional total/message}}` before the final semantic response. Notifications have no response ID or content field.
Structured result content cannot enter the typed status API. Text can still contain sensitive or result data:
Tools own safe status wording; Common cannot detect secrets or reinterpret arbitrary text as a partial result.
The no-op reporter retains its compatible nondecreasing validation and emits nothing.

Input schema/rule validation completes before Tool execution and progress; rejection is one complete `isError`
Tool result. Expected mapped business failure after progress likewise produces one complete safe Tool error.
Unclassified failures, output-schema violations, reporter violations and response-preparation failures produce
one generic JSON-RPC internal error while delivery is open. The execution is the central diagnostic boundary
around `McpResponder::dispatch()`: unexpected failures are recorded at most once, and diagnostic sink failures
cannot escape to the client. A Tool cannot turn a reporter violation into success by catching it or by mapping its
exception class, including malformed argument types. The internal live implementation checks those types inside
its retained-failure boundary; this does not broaden the stable reporter interface or the no-op reporter's contract.
Earlier progress never means partial success.

The outer adapter calls `cancel()` when it observes client closure. The live reporter then returns true from
`isCancelled()` and suppresses all later progress and final messages, including errors; diagnostics remain
server-side. Tools must check cancellation at business-safe points. Cancellation neither interrupts execution
nor reverses a dispatched command, and it emits no `notifications/cancelled`. No-token reporters remain no-op
and never report cancellation. Reuse of an execution or invocation rejects. Completion closes reporting before
attempting the final delivery, so late/reentrant reports and duplicate final attempts cannot escape.

These guarantees assume serialized, synchronous delivery for each request, including cooperative PHP Fiber
interleaving. Each concurrent request needs a separate execution and any request-scoped metadata filters. Delivery
must not run ahead of emission; the HTTP adapter pauses at each frame until the emitter flushes it, and owns
byte writes, closure detection and buffering. Once final
delivery begins it is never retried, since a failing sink may have already written it. A terminal sink failure is
diagnosed at most once and propagates as infrastructure failure rather than attempting another response.

`McpToolExecutionTest` proves controlled ordering, equivalent query/mutation output, validation and late failure,
once-only completion, cancellation before/after command dispatch, Fiber interleaving and cross-request isolation.
It does **not** prove HTTP/SSE framing, real disconnect observation, threads, native framework emission or starter
support. `McpStreamingJourneyTest` separately proves package transport. Consumer adoption is independently
owned; TASK-00110's five-starter confirmation plan was explicitly closed without implementation.

## Tool envelope metadata

`Tool\Messaging\McpToolMetadataFilter` optionally implements both existing `CommandFilter` and `QueryFilter`.
Share one request-scoped instance between the invoker's `metadata:` argument and the normal command/query pipelines.
It enriches envelopes only while a validated available Tool executes, preserving payload, message identity,
timestamp and existing unrelated metadata. Outside that scope it forwards the original message untouched.
`QueryPipeline` forwards the filtered envelope through its inner bus's `dispatch()` rather than recreating it.

The reserved baseline is `fight.mcp/tool` (canonical name) and `fight.mcp/correlation` (fresh random 128-bit identity,
shared across that invocation's envelopes). Neither JSON-RPC IDs nor raw protocol metadata enter the bus. An
optional `McpToolAuditMetadata::fieldsFor(McpToolInfo)` implementation may close over consumer-owned context and
project explicitly allowlisted namespaced scalar fields:

```php
$metadata = new McpToolMetadataFilter($auditProjection, ['orders/actorId', 'orders/traceId']);
$commands->addFilter($metadata);
$queries->addFilter($metadata);
$invoker = new McpToolInvoker($tools, $availability, metadata: $metadata);
```

Field declarations reject unnamespaced names, the reserved `fight.mcp/` namespace, and credential/password/secret/
token/authorization/permission/argument/header/payload names. Returned undeclared keys, arrays, objects, null,
nonfinite floats, malformed Unicode, control-bearing strings and strings over 256 bytes reject before Tool/bus
execution. The consumer must project genuinely non-secret values: no syntactic check can detect a credential
mislabelled as an actor ID. The hook receives no arguments, headers or principal from Common and conveys no policy.
Scopes clear on success and every failure; nested/concurrent reuse rejects. Use separate instances for concurrent
requests/fibers. No global state, payload changes, durable audit guarantee, or cross-request correlation is added.

## Protocol behavior

The decoder requires a JSON-RPC `2.0` string-or-integer request ID, string method, object-shaped `params`, and object-shaped `_meta` with
the `io.modelcontextprotocol/protocolVersion` and
`io.modelcontextprotocol/clientCapabilities` fields. Client capabilities are an object; optional client information
is an implementation object with a name and version. A present progress token is likewise a string or integer;
numeric progress values are validated by the selected reporter. The decoder validates every metadata key's MCP grammar,
defined field shape, present trace-context format, and schema-permitted HTTP/HTTPS or image-data implementation
icon source before dispatch, while
preserving schema-permitted empty strings, an empty opaque metadata key, and open objects. Image data URI sources accept
RFC 2045 token-valued or correctly percent-escaped parameter values when their raw representation uses RFC 2396 URL
characters or valid percent escapes. The decoder validates decoded media subtype and parameter attribute tokens, plus
escaped RFC 2045 tspecial and quoted-string parameter-value forms, before their terminal Base64 marker; malformed raw
characters, token syntax, quoting, or percent escapes reject before dispatch. Discovery configuration and server identity must be JSON-safe
Unicode values during composition. It retains only protocol metadata needed by this shared layer: protocol version,
client capabilities and information, and progress token. It intentionally drops opaque extension metadata rather than
treating it as credentials or authority.

Malformed JSON, malformed JSON-RPC, invalid outer parameters, unsupported protocol version, and unknown methods
produce centralized JSON-RPC protocol errors without selecting a capability. If a usable request ID is available,
the error retains it; otherwise the semantic response has no ID. An unexpected capability failure produces the
generic internal protocol error and exposes no exception detail.

## Guarded HTTP

`Adapter\Http\Mcp\McpRequestHandler` implements PSR-15's `RequestHandlerInterface`. A consumer-owned route
passes a PSR-7 server request to it and receives a PSR-7 response created through PSR-17 factories. No HTTP framework
is required. Its constructor requires a registry, `McpOriginPolicy`, `McpInvocationGuard`, and `McpResponseFactory`;
the response factory requires `McpDiagnostics`. Missing policies fail composition. There is no default allow-all
Origin policy or invocation guard.

### Enforcement and errors

The handler checks a supplied Origin before reading the body, on every method. Next it checks POST, `Accept`,
and JSON content type; decodes the body; validates the complete mirror set; checks the supported version; asks
the invocation guard; and only then invokes a capability. Tool mirror selection performs only metadata lookup and
availability before custom-header comparison; it does not validate arguments or execute the Tool. Invocation
reevaluates current availability after the guard rather than caching an earlier authorization decision.
`server/discover` passes through the same safeguards.

| Outcome | HTTP status | Representation |
| --- | --- | --- |
| Disallowed, malformed, null, or multiple Origin values | 403 | Empty body, no capability selection |
| Non-POST, including GET/DELETE | 405 | Empty body and `Allow: POST` |
| Missing/incomplete/invalid `Accept` | 406 | Empty body |
| Missing/non-JSON/unsupported-charset content type | 415 | Empty body |
| Malformed JSON / request / outer parameters | 400 | JSON-RPC `-32700` / `-32600` / `-32602` |
| Missing, ambiguous, malformed, or mismatched mirror | 400 | JSON-RPC `-32020`, `Header mismatch.` |
| Unsupported version with agreeing mirrors | 400 | JSON-RPC `-32022` with supported/requested versions |
| Invocation denied | 429 | Common-defined JSON-RPC `429`, `Invocation limit exceeded.`, no diagnostic data |
| Unknown method, including unregistered legacy `initialize` | 404 | JSON-RPC `-32601` |
| Unexpected throwable, including result encoding failure | 500 | JSON-RPC `-32603`, `Internal error.` |
| Successful semantic result | 200 | Direct `application/json`, never JSend |

JSON-RPC errors retain a usable decoded request ID, including zero or an empty string. Errors encountered before
an ID can be decoded omit it. Transport-only rejections have no JSON-RPC body. The `429` JSON-RPC code is an
explicit Common convention approved in TASK-00105, not a code assigned by MCP. It is outside JSON-RPC's reserved
range. No threshold, caller-key format, retry time, quota store, exemption, or authorization policy is implied.

POST must explicitly list both `application/json` and `text/event-stream` in `Accept`, with a nonzero weight;
wildcards alone are insufficient. The default endpoint selects direct JSON; opt-in progressive delivery selects
SSE for a guarded `tools/call` with a progress token. Request content type is `application/json`,
optionally with `charset=utf-8`. Consumer ingress owns body-size limits, TLS, trusted proxies and authentication.
Legacy session and Last-Event-ID headers are ignored and never echoed; no session, GET stream, or DELETE lifecycle
is created. Incoming notification dispatch remains later work. Optional
[OAuth resource-server protection](../auth/index.md#oauth-resource-server) now composes outside this endpoint:
consumer validation and claims handoff precede capability selection, with distinct empty 400/401/403 responses.
Consumer-selected HMAC remains a separate compatible composition. Tool registration, discovery and
invocation use this endpoint; outgoing progress uses the additive SSE composition below.

### Origins, guards, and diagnostics

`ExactMcpOriginPolicy` canonicalizes configured HTTP/HTTPS origins with `McpOrigin::fromString()`: scheme/host case,
default port, and equivalent IPv6 spellings normalize to one exact scheme-host-port value. Paths (even `/`), queries,
fragments, credentials, wildcards, malformed hosts, and invalid ports fail configuration. A trailing DNS dot remains
a distinct host. Runtime malformed values, `Origin: null`, and multiple values reject before policy evaluation.
There is no suffix matching, DNS resolution, Host-header inference, or forwarded-header inference.

`DenyAllMcpOriginPolicy` explicitly selects native-only composition: every supplied Origin is denied, while an
absent Origin remains valid. Custom `McpOriginPolicy::allows(string)` implementations receive only a validated
canonical Origin. `McpInvocationGuard::allows(string $method, ?string $name)` receives only the mirrored method and
applicable standard name/URI, never a principal, credential, raw request, metadata, or arguments. A request-scoped
consumer implementation may privately retain its authenticated caller and policy services.

`McpDiagnostics::record(Throwable)` is called once for an unexpected endpoint failure. Consumers must supply a
redaction-aware implementation that does not throw; exception details and traces are not sent to the client.
Protocol/Origin/guard rejections are not unexpected diagnostic events. A broken diagnostic sink is contained so its
own exception cannot leak, but successful log delivery cannot be guaranteed when that consumer contract fails.
Infrastructure that cannot create a response at all remains a consumer runtime failure.

### Complete transport mirrors

`McpHeaderValidator` owns the rules, independent of PSR header extraction. It matches field names case-insensitively,
including dynamic suffixes, and rejects multiple values or case-variant duplicate entries for recognized fields.
Adapters and ingress must preserve the separate field values; a proxy that irreversibly coalesces duplicate fields
cannot be repaired by the package. Values and JSON property names remain case-sensitive.

- `MCP-Protocol-Version` and `Mcp-Method` are required and match their decoded body fields.
- `Mcp-Name` is required for `tools/call`, `prompts/get`, and `resources/read`, matching `params.name` or `params.uri`.
  An extraneous name on another method rejects rather than becoming routing authority.
- Each registered `McpMirrorDeclaration` supplies a method, exact parameter property path, and `x-mcp-header` suffix.
  For example, `['arguments', 'account', 'region']` and `Region` mirror into `Mcp-Param-Region`. Only object properties
  are traversed, never array items. A null or absent path requires omission; a present non-null value requires a
  matching header. Case-insensitive declaration collisions are composition errors.
- Strings, booleans (`true`/`false`), and JavaScript-safe integers are supported. Integer comparison is exact and
  numerical, including equivalent decimal/exponent forms, without floating-point rounding of header values.
  Non-integral numbers, unsafe integers, arrays, and objects cannot be mirrored.
- Non-ASCII, control-bearing, or edge-whitespace strings require the case-sensitive `=?base64?...?=` UTF-8 sentinel.
  Literal sentinel-shaped strings must themselves be encoded. Decoding requires valid canonical Base64 and UTF-8;
  malformed encodings fail closed. Plain values permit only visible ASCII, internal spaces and horizontal tabs.
- Unannotated body properties do not create mirrors. Unrecognized custom headers have no authority and are ignored;
  capability parameter/schema validation remains responsible for unknown or invalid body properties.

### Request-selected Tool mirrors

`McpToolRegistry` compiles input-schema annotations at composition. Only chains of `properties` from the root may
contain mirrored properties, with explicit string/integer/Boolean types (optionally unioned with null). Root,
array-item, `$defs`, additional-property and combinator annotations reject. Case-insensitive header collisions
within one Tool reject; other Tools have independent declarations. Defaults/examples are data, not schema nodes.

`McpToolInvocation` implements `McpRequestMirrors`: the HTTP validator asks for the currently available named Tool's
declarations after standard mirrors agree, without inspecting arguments or invoking it. Unknown and unavailable
Tools reject identically before custom mirrors, preventing header requirements from exposing hidden registration.
The normal invoker rechecks current availability before argument validation; consumers must use a stable
request-scoped policy, and no stale selection is cached. The header validator merges these declarations with any
method-wide declarations and rejects cross-set collisions or wrong-method declarations. It never applies another
Tool's schema to the selected name. Standalone semantic callers do not handle HTTP headers; use the guarded endpoint
for HTTP rather than exposing `respond()` directly on a route.

The conformance authority is MCP `2026-07-28`, inspected at upstream commit
[`ab3a39c13bd23be691c2760e1c6c5c15a64582e1`](https://github.com/modelcontextprotocol/modelcontextprotocol/tree/ab3a39c13bd23be691c2760e1c6c5c15a64582e1):
[transport](https://github.com/modelcontextprotocol/modelcontextprotocol/blob/ab3a39c13bd23be691c2760e1c6c5c15a64582e1/docs/specification/2026-07-28/basic/transports/streamable-http.mdx),
[error policy](https://github.com/modelcontextprotocol/modelcontextprotocol/blob/ab3a39c13bd23be691c2760e1c6c5c15a64582e1/docs/specification/2026-07-28/basic/index.mdx), and
[schema](https://github.com/modelcontextprotocol/modelcontextprotocol/blob/ab3a39c13bd23be691c2760e1c6c5c15a64582e1/schema/2026-07-28/schema.ts).
`McpHeaderValidatorTest`, `McpRequestHandlerTest`, and `McpEndpointJourneyTest` retain executable success/rejection/
failure fixtures. The journey boots a consumer-owned Slim route with authentication middleware and verifies direct
JSON, discovery, denied Origin, complete mirrors, invocation denial, and sanitized diagnostics end to end.

## Progressive HTTP delivery

Enable `McpRequestHandler(..., progressive: true)` only with `Adapter\Http\Mcp\McpResponseEmitter` or the
native translations below. A string/integer token on `tools/call` selects one lazy SSE response **after** Origin,
negotiation, decode, mirrors, version and invocation-guard validation. Zero and empty-string tokens are preserved.
No-token calls still execute immediately with a fresh no-op reporter and unchanged complete JSON; other methods
remain direct JSON. Consumers retain authentication, authorization and request-scope ownership.

The progressive response is HTTP 200 with `Content-Type: text/event-stream`,
`Cache-Control: no-store, no-cache, must-revalidate`, `X-Accel-Buffering: no`, and no Content-Length.
Each frame is `data: <one JSON-RPC object>\n\n`. Progress notifications precede exactly one final JSON-RPC
response. No event names, event IDs, retry hints, session, resumability or terminal sentinel is added.
A late selected-Tool failure remains a complete `isError` result. An unexpected late failure produces one
sanitized `-32603` error and one diagnostic. HTTP status cannot change after headers. Pre-stream transport and
guard rejections retain their direct-JSON/empty-body status mappings.

The internal `McpEventStream` PSR body is single-use and non-seekable. A PHP Fiber pauses the synchronous Tool at
each frame until the emitter requests the next fragment. Reads never assemble a complete transcript ahead of
emission. Closing the body cancels only that request and resumes suspended Tool work, discarding later output;
it does not forcibly kill execution. Availability is reevaluated when invocation starts. Keep the response and
its collaborators inside the consumer-owned request/security scope; use separate collaborators for concurrent work.

`McpResponseEmitter::emit()` sends PSR headers and body through PHP's SAPI. `prepare()` checks streaming conditions
before native headers; `emitBody()` supplies the native send callback. Every read is written and flushed before
the next read. During streaming, the emitter enables `ignore_user_abort(true)`, checks `connection_aborted()` before
each read, closes the body on exit and restores the prior abort setting. PHP typically observes disconnect on a
write; this is not immediate detection of remote/proxy closure. After observation no later progress/error/final
bytes are attempted. Tools check `isCancelled()` at safe points; already-dispatched commands are not reversed.
A Tool ignoring cancellation can still run to completion.

### Runtime conditions and package evidence

`McpStreamingJourneyTest` uses PHP's built-in `cli-server`, output buffering **0**, zlib compression **0**, direct
loopback HTTP without proxies, and a raw socket client. Its six lanes execute framework-free/PSR, Symfony native
StreamedResponse, Laravel `ResponseFactory::stream`, Yii Router plus the shared emitter, CodeIgniter native send,
and Slim App plus the shared emitter. Each proves two separately read progress events before Tool completion,
success, expected/unexpected failure after progress, direct JSON, early disconnect/cooperative stop, buffered
emission rejection and legacy opt-out. Native APIs are executed, not merely inspected.

The emitter rejects an active output buffer, zlib compression, Content-Length or Content-Encoding on a progressive
response before Tool execution. It does not discard consumer buffers or change compression configuration. Disable
buffering, compression, response-body collectors and debug middleware for this route. Never cache, replay, pre-read
or log its body. String conversion and `getContents()` explicitly collect bytes and are **not** streaming support;
an ordinary PSR emitter or complete-body bridge does not establish live delivery.

No PHP-FPM, Apache, worker-server or reverse-proxy guarantee follows from this harness. `X-Accel-Buffering: no`
is not proof that every intermediary honors it. Consumers must qualify their actual SAPI, buffer stack, proxy,
timeouts, compression and disconnect propagation. Package conformance is **not** a booted installed-package
starter receipt. Under the approved TASK-00110 closeout, the accepted package evidence completes TICKET-00025
without waiting for five starter receipts or consumer adoption. Fight Agent OS owns its implementation and
runtime qualification and reports demonstrated Common defects upstream as bug-fix TASKs. This scoped evidence
amendment does not claim any consumer journey passed. No persistent package server, route, deployment
configuration or new framework dependency is introduced.

## Consumer wiring

All examples leave path selection, authentication, authorization, request scope, and runtime emission to the consumer.
They are composition examples, not package-owned routes or proof of five installed starter journeys. Actual runtime
qualification belongs to the consuming application and does not require a Common confirmation TASK. Never
reproduce only part of mirror validation in a framework Action.

### Shared composition and framework-free PSR

```php
use Fight\Common\Adapter\Http\Mcp\ExactMcpOriginPolicy;
use Fight\Common\Adapter\Http\Mcp\McpRequestHandler;
use Fight\Common\Adapter\Http\Mcp\McpResponseFactory;
use Fight\Common\Application\Mcp\McpCapabilityRegistry;
use Fight\Common\Application\Mcp\McpServerInfo;
use GuzzleHttp\Psr7\HttpFactory;

// Consumer supplies explicit capabilities, a request-scoped McpInvocationGuard,
// and a redaction-aware McpDiagnostics. Guzzle is an optional PSR-17 choice.
$factory = new HttpFactory();
$endpoint = new McpRequestHandler(
    new McpCapabilityRegistry(new McpServerInfo('Consumer', '1.0.0'), $capabilities),
    new ExactMcpOriginPolicy(['https://client.example']),
    $guard,
    new McpResponseFactory($factory, $factory, $diagnostics)
);

// Only after consumer authentication/authorization succeeds:
$response = $endpoint->handle($serverRequest);
// Default direct JSON can use the consumer's ordinary PSR emitter.
// For progressive: true, use the concrete emitter below.
```

For native-only access, explicitly replace `ExactMcpOriginPolicy` with `DenyAllMcpOriginPolicy`.
`$guard` is not an optional placeholder: its consumer implementation must decide whether this caller's invocation
is permitted by configured limits. Authentication remains outside that interface.

### Slim and Yii

Both use PSR requests/responses directly. Use the shared composition above. Slim's consumer route can delegate all
methods so the handler supplies its 405 contract; attach consumer authentication middleware outside it:

```php
$app->any('/chosen/mcp', fn ($request) => $endpoint->handle($request));
```

In Yii, bind the endpoint in consumer DI and use a route action that delegates the received PSR request:

```php
Route::post('/chosen/mcp')->action(fn (ServerRequestInterface $request) => $endpoint->handle($request));
```

Import `Yiisoft\Router\Route` and `Psr\Http\Message\ServerRequestInterface` in the Yii composition. A POST-only
router must itself return 405/Allow for other methods, or explicitly route them to the handler. Consumer middleware
owns authentication and authorization in either framework.

For `progressive: true`, use the shared emitter at the consumer-owned send boundary:

```php
use Fight\Common\Adapter\Http\Mcp\McpResponseEmitter;

// Framework-free, or Yii's consumer-owned emitter integration:
(new McpResponseEmitter())->emit($endpoint->handle($serverRequest));
// Slim: use App::handle(), not App::run() with its ordinary non-flushing emitter.
(new McpResponseEmitter())->emit($app->handle($serverRequest));
```

Yii's application runner needs a consumer-owned integration invoking this same `emit()` method. The package
harness proves Router plus emitter, not an unmodified runner. Neither Yii nor Slim needs a branded adapter copy.

### Symfony and Laravel

Use the optional `symfony/psr-http-message-bridge` compatible with the consumer's framework and a PSR-17 factory.
Neither is silently installed by Common. Symfony's native Request and Laravel's Request subclass can use the same
bridge, preserving the full header-value arrays. The consumer Action/controller performs conversion only:

```php
use Symfony\Bridge\PsrHttpMessage\Factory\HttpFoundationFactory;
use Symfony\Bridge\PsrHttpMessage\Factory\PsrHttpFactory;

$psr = new PsrHttpFactory($factory, $factory, $factory, $factory);
$response = $endpoint->handle($psr->createRequest($nativeRequest));
return (new HttpFoundationFactory())->createResponse($response);
```

Construct `$endpoint` with explicit server identity/capabilities/policies/diagnostics as above in the consumer's
service configuration. Register a consumer-owned route and run its firewall/authentication/authorization before
this delegation. No framework-specific mirror checks are needed or permitted as a substitute.

The generic response bridge is for **direct JSON only**. With `progressive: true`, replace response conversion
with the Common native factory, which also handles ordinary JSON:

```php
use Fight\Common\Adapter\Http\Symfony\McpResponseFactory as NativeMcpResponses;

return (new NativeMcpResponses())->fromResponse($endpoint->handle($psrRequest));
```

Laravel accepts the same native Symfony Response/StreamedResponse; no branded wrapper is needed. Alternatively,
after `$emitter->prepare($response)`, Laravel's
`response()->stream(fn () => $emitter->emitBody($response), $response->getStatusCode(), $response->getHeaders())`
is the callback boundary exercised by the package fixture. Do not use `eventStream()` with its default event
naming and `</stream>` sentinel. Native middleware must not collect content, add compression, replace streaming
headers or outlive the request-scoped collaborators.

### CodeIgniter

The consumer controller builds a PSR request without flattening repeated header values, delegates all validation,
and maps only the returned status/headers/body back to its native response. With the same `$factory` and `$endpoint`:

```php
$psrRequest = $factory->createServerRequest($this->request->getMethod(), (string) $this->request->getUri());
foreach ($this->request->headers() as $field) {
    foreach (is_array($field) ? $field : [$field] as $header) {
        $psrRequest = $psrRequest->withAddedHeader($header->getName(), $header->getValue());
    }
}
$psrRequest = $psrRequest->withBody($factory->createStream((string) $this->request->getBody()));
$psrResponse = $endpoint->handle($psrRequest);
$this->response->setStatusCode($psrResponse->getStatusCode());
foreach ($psrResponse->getHeaders() as $name => $values) {
    $this->response->setHeader($name, $values);
}
return $this->response->setBody((string) $psrResponse->getBody());
```

Place the route, filters, identity resolution, and request-scoped policies in the consuming application. If its SAPI
or bridge has already collapsed repeated request fields, reject ambiguous input at ingress instead of claiming
that lost multiplicity was validated. The string-body conversion above is **direct JSON only**.

For progressive delivery replace that native conversion with:

```php
use Fight\Common\Adapter\Http\CodeIgniter\McpResponse;

return new McpResponse($endpoint->handle($psrRequest));
```

This narrow native Response subclass retains the guarded PSR body until CodeIgniter calls `sendHeaders()` and
`sendBody()`. It emits once, using the shared emitter instead of CodeIgniter's stored-string body. Normal
CodeIgniter configuration/services must already be booted. Disable response caching, compression and toolbar
filters; finish the framework's output buffer before sending. Framework-generated body appendages are not part
of MCP output. Consumers retain routes, filters and kernel/runtime wiring. The native send-phase fixture does
not establish a booted installed-package CodeIgniter starter journey.
