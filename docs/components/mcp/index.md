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
atlas_relationship_caption: Common owns protocol semantics. Consumers own HTTP, authentication, authorization, and framework composition.
atlas_consequential_label: Security boundary
atlas_consequential_message: MCP metadata is bounded protocol context, never a principal or credential carrier. No default route, origin policy, or invocation policy exists in this foundation.
atlas_local_contents:
  - label: Ownership
    href: "#ownership"
  - label: Composition
    href: "#composition"
  - label: Protocol behavior
    href: "#protocol-behavior"
  - label: Deferred transport
    href: "#deferred-transport"
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
family without its mandatory anchor method, non-JSON capability definitions, and invalid future transport-mirror
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
capability. The result is `McpJsonResponse`, an `Arrayable` semantic JSON-RPC response. A future transport adapter
may encode it with `toJson()` and select its HTTP status/headers without changing protocol ownership.

A capability rejects invalid outer parameters by throwing `McpProtocolException` with
`McpProtocolError::invalidParams()` and the request ID. The responder preserves that semantic rejection as the
JSON-RPC `invalidParams` error. Any other `Throwable` from a capability becomes the generic `internalError`, with
no exception details exposed.

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

## Deferred transport

This foundation intentionally exposes no constructible HTTP endpoint. PSR HTTP extraction and direct response
adaptation, Origin policy, invocation limiting, and complete header/body mirror enforcement belong to TASK-00105.
`McpMirrorDeclaration` only carries a capability-owned method, parameter path, and HTTP-header-name declaration so
that later transport work can validate the same registration without making the foundation an HTTP policy owner.

Tools, `tools/list`, `tools/call`, schemas, availability filtering, progress/SSE, cancellation, protected
interactions, OAuth resource-server support, consumer routes, and framework service-provider wiring remain later
capabilities.
