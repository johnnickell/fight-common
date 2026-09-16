# Define Fight Common and consumer ownership boundary

**Labels:** `wayfinder:grilling`, `wayfinder:domain-modeling`
**Mode:** HITL
**Status:** Closed
**Map:** [Fight Common MCP over HTTP Support](../fight-common-mcp-http-support-map.md)
**Depends on:** [Target the stateless MCP HTTP profile](WF-037-target-the-stateless-mcp-http-profile.md), [Set the authentication interoperability posture](WF-038-set-the-authentication-interoperability-posture.md)

## Question

What reusable MCP responsibilities belong in Fight Common, and what must remain in the consuming application and Fight Access Control?

## Why this matters

An overly broad shared layer would invent a generic principal or authorization model and violate Fight Common’s package boundary. An overly thin layer would duplicate protocol correctness and use-case invocation mechanics in every consumer.

## Evidence required

- Existing Fight Common Action–Domain–Responder, JSend, request validation, CQRS, DI, and HMAC seams.
- Fight Access Control Agent principal, permission, command/query, and View seams.
- MCP transport and authorization responsibilities.

## Must decide

- whether Fight Common remains a protocol-only dispatcher called after consumer authentication and authorization;
- the minimal consumer-supplied tool and execution interfaces, if justified;
- where request context can enter without creating a shared principal model; and
- which framework-facing pieces remain optional adapters.

## Resolution boundary

This ticket may settle component ownership and neutral interfaces. It must not decide Agent permissions, endpoint paths, OAuth-server behavior, or individual tool names.

## Resolution

Fight Common owns MCP protocol primitives, dispatch/registration conventions where demonstrated reusable, and policy-free authentication integration support. Its MCP surface must not encode a route, Agent, user, permission, or application authorization decision. A protocol response envelope may follow the existing JSend separation—semantic representation first, framework/PSR response adaptation second—but MCP’s JSON-RPC wire format remains independent of JSend.

Fight Access Control owns authenticated Agent and user principals, current-authority resolution, and permission policy. Consumer applications own endpoint topology, framework routing/middleware, TLS and gateways, authorization-server deployment, and the composition that selects HMAC or Bearer authentication before or around MCP dispatch. A generic shared principal or opaque principal-context contract is not justified at this stage; consumer tool implementations may use their request-scoped Access Control services directly.

**Decision owner:** John

**Exit condition:** Closed by this resolution. WF-041 determines the MCP semantic-presentation and native
response-adapter boundary without requiring a single envelope type; WF-043 determines reusable OAuth
resource-server support.
