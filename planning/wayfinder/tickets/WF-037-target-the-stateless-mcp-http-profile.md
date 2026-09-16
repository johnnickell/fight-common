# Target the stateless MCP HTTP profile

**Labels:** `wayfinder:research`, `wayfinder:grilling`
**Mode:** HITL
**Status:** Closed
**Map:** [Fight Common MCP over HTTP Support](../fight-common-mcp-http-support-map.md)
**Depends on:** —

## Question

Which MCP protocol revision and HTTP transport behavior should reusable Fight Common support target?

## Why this matters

The revision determines message lifecycle, header validation, scalability assumptions, compatibility obligations, and the surface Fight Common would need to preserve as public API.

## Evidence required

- The 2026-07-28 MCP announcement and the current official transport, base-protocol, and compatibility specifications.
- Existing Fight Common HTTP, JSend, HMAC, and CQRS boundaries.

## Must decide

- protocol revision and whether legacy clients are supported;
- whether sessions, `initialize`, legacy HTTP+SSE, or GET/DELETE endpoint behavior enter the support promise; and
- how direct JSON and request-scoped SSE relate to the initial proof.

## Resolution boundary

This settles the supported core protocol era and transport lifecycle. It does not decide a concrete route, framework integration, tool registry, or whether a reusable SSE response adapter is justified.

## Resolution

Fight Common will target only MCP `2026-07-28` Streamable HTTP. A consumer may mount the MCP handler at any path; each MCP message is an HTTP POST, carries per-request metadata, and has no protocol-level session or `initialize` exchange. The adapter must follow the protocol’s JSON-RPC and header/body consistency rules.

Legacy protocol sessions, the deprecated HTTP+SSE transport, and their compatibility paths are excluded. A response may be a direct JSON object or a request-scoped SSE stream under the specification; the initial consumer proof need not implement both. The reusable response surface is decided separately.

**Decision owner:** John

**Exit condition:** Closed by this resolution; downstream decisions may rely on the stateless-only lifecycle.
