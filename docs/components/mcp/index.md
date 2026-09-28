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
  - label: Protocol behavior
    href: "#protocol-behavior"
  - label: Guarded HTTP
    href: "#guarded-http"
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
no exception details exposed.

## Explicit Tool registration

`Application\Mcp\Tool` provides explicit registration and discovery, not invocation. A consumer Tool implements
`McpTool` and declares exactly one `#[McpToolInfo(name:, description:, inputSchema:, outputSchema:)]` on its
`handle(ApplicationData $input, McpProgressReporter $progress): McpToolOutput` method. `ApplicationData` is
`Application\Validation\Data\ApplicationData`; the reporter lives directly in `Application\Mcp`.

Construct `McpToolRegistry` with the complete array of opted-in Tool objects. Registration examines only those
objects' `handle()` metadata. It never scans a container, discovers HTTP Actions or CQRS handlers, hydrates a
payload, or invokes the Tool. Missing/duplicate method metadata, non-Tool registrations, malformed metadata,
unsupported schema declarations, and duplicate canonical names fail composition with `DomainException`.
Names are case-sensitive, 1–128 ASCII letters, digits, underscores, dots or hyphens. Descriptions must be nonblank
UTF-8. Input and output schemas are required by the Common convention even though MCP makes output schemas optional.

`McpToolInfo` exposes only name, description and isolated schema snapshots. `McpToolRegistry::find()` is an
unprivileged composition lookup, **not** an authorization or invocation API. Later invocation must apply the same
request-scoped availability decision as discovery before executing a Tool. Existing business authorization
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
by this capability; `tools/call` remains unregistered. Conflicting Tool metadata from another capability fails
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

This release validates a **bounded declaration profile**, not the full JSON Schema language or Tool arguments.
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
- `x-mcp-header`: a nonempty ASCII alphanumeric/hyphen suffix starting with an alphanumeric character. It is
  preserved as metadata only here, not extracted into invocation mirror declarations.

Other keywords and dialects fail closed. In particular, references, formats, patterns and conditional schemas are
not currently supported. No remote schema is fetched. This profile is deliberately narrower than MCP permits;
it is not a claim of full JSON Schema conformance. Schema/validation-rule consistency and runtime argument/output
conformance belong to TASK-00107, not discovery. `$defs` declarations are validated, but references are not enabled.
PHP arrays at `properties`/`$defs` map positions may use `[]` for `{}`; otherwise nested empty schemas use
`new stdClass()`. Lists, objects, numeric/Boolean/null values and annotations retain their JSON meaning.

Metadata and output accept only plain JSON values (including exact `stdClass` objects), bounded to depth 64:
the schema/output root is depth zero and each member or element adds one. Discovery's definition, catalog and
protocol wrappers do not consume a schema's depth budget. Malformed Unicode, nonfinite numbers, resources,
arbitrary objects/serializers and cycles reject. Object keys beginning with U+0000 also reject at construction
with `DomainException`, because PHP's object-mode JSON decoder cannot represent them; empty keys and keys with
non-leading U+0000 remain supported. This representation limit applies at every nesting level, including schema
annotation values. Construction snapshots mutable objects, and accessors return fresh copies preserving JSON
object/list distinctions. No consumer serializer executes.
This is data-shape safety, not automatic redaction: consumers must explicitly project public-safe schemas and data.

`McpToolOutput::structured($publicData)` carries one complete JSON value, exposed by `structuredContent()` and its
matching JSON `text()`. It creates no JSON-RPC response, error envelope, HTTP response, or partial result. The caller
owns safe projection; the later invoker owns output-schema conformance and expected-failure presentation.

`McpProgressReporter` is only the stable `report(float $progress, ?float $total, ?string $message)` and
`isCancelled(): bool` port required by the Tool signature. Its implementation contract calls for finite nonnegative,
monotonic progress, an optional total not below progress, public-safe status text, and no emission after cancellation.
It carries no Tool result content. No reporter implementation, live progress, SSE, disconnect handling, cooperative
execution orchestration, or rollback guarantee is supplied by discovery.

## Protocol behavior

The decoder requires a JSON-RPC `2.0` string-or-integer request ID, string method, object-shaped `params`, and object-shaped `_meta` with
the `io.modelcontextprotocol/protocolVersion` and
`io.modelcontextprotocol/clientCapabilities` fields. Client capabilities are an object; optional client information
is an implementation object with a name and version. A present progress token is likewise a string or integer;
numeric progress values are a later capability concern. The decoder validates every metadata key's MCP grammar,
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
the invocation guard; and only then selects, validates, and invokes a capability. Mirror declarations are
registration data, not capability execution. `server/discover` passes through the same safeguards.

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
wildcards alone are insufficient. This endpoint always selects direct JSON. Content type is `application/json`,
optionally with `charset=utf-8`. Consumer ingress owns body-size limits, TLS, trusted proxies and authentication.
Legacy session and Last-Event-ID headers are ignored and never echoed; no session, GET stream, or DELETE lifecycle
is created. Notification dispatch, SSE/progress, Tool invocation/runtime schema conformance, and OAuth remain later
work. Explicit Tool registration and discovery can use this endpoint through the composition above.

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

The HTTP foundation consumes explicit registration data; it does not extract annotations from Tool schemas.
Tool discovery preserves schema annotations but makes no invocation claim. The current mirror registration is
method-scoped. Future Tool invocation support must reconcile per-tool declarations before claiming
complete multi-tool schema support; it must not reuse one tool's declarations for every `tools/call` request.

The conformance authority is MCP `2026-07-28`, inspected at upstream commit
[`ab3a39c13bd23be691c2760e1c6c5c15a64582e1`](https://github.com/modelcontextprotocol/modelcontextprotocol/tree/ab3a39c13bd23be691c2760e1c6c5c15a64582e1):
[transport](https://github.com/modelcontextprotocol/modelcontextprotocol/blob/ab3a39c13bd23be691c2760e1c6c5c15a64582e1/docs/specification/2026-07-28/basic/transports/streamable-http.mdx),
[error policy](https://github.com/modelcontextprotocol/modelcontextprotocol/blob/ab3a39c13bd23be691c2760e1c6c5c15a64582e1/docs/specification/2026-07-28/basic/index.mdx), and
[schema](https://github.com/modelcontextprotocol/modelcontextprotocol/blob/ab3a39c13bd23be691c2760e1c6c5c15a64582e1/schema/2026-07-28/schema.ts).
`McpHeaderValidatorTest`, `McpRequestHandlerTest`, and `McpEndpointJourneyTest` retain executable success/rejection/
failure fixtures. The journey boots a consumer-owned Slim route with authentication middleware and verifies direct
JSON, discovery, denied Origin, complete mirrors, invocation denial, and sanitized diagnostics end to end.

## Consumer wiring

All examples leave path selection, authentication, authorization, request scope, and runtime emission to the consumer.
They are composition examples, not package-owned routes or proof of five installed starter journeys. Those progressive
runtime journeys belong to TASK-00110. Never reproduce only part of mirror validation in a framework Action.

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
// Consumer's PSR emitter sends $response. Common creates no router or listener.
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
that lost multiplicity was validated. These direct-JSON conversions make no SSE or disconnect-support claim.
