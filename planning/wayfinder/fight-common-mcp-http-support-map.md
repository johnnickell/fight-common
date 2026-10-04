# Fight Common MCP over HTTP Support

**Label:** `wayfinder:map`
**Status:** Closed

## Destination

Produce an evidence-backed, implementation-ready design for reusable Fight Common MCP-over-HTTP support that lets consumer applications expose existing CQRS use cases without coupling those use cases to MCP. Fight Access Control is the first intended consumer, using its Agent model and permission policy without moving either concern into Fight Common.

**Done** = achieved. The scoped decisions have evidenced resolutions, and EPIC-00006 owns the implementation
destination. No TICKET, TASK, implementation, or publication is created by this map.

## Notes

- Observed Fight Common capability: `HmacAuthenticator` verifies a signed PSR-7 request with credential, timestamp, nonce, optional body digest, and canonical method/authority/path/query. It is an application-to-application mechanism, not MCP OAuth interoperability.
- Observed Fight Access Control capability: its Agent security flow bridges the shared HMAC verifier, resolves an `AuthenticatedAgentPrincipal`, and owns Agent permissions and current-authority checks. Its safe `AgentView` query results are a candidate proof surface.
- The target protocol is MCP `2026-07-28` Streamable HTTP. The [official transport specification](https://modelcontextprotocol.io/specification/2026-07-28/basic/transports/streamable-http) and [authorization specification](https://modelcontextprotocol.io/specification/2026-07-28/basic/authorization) are authority; the announcement is orientation only.
- Consumer applications own paths, hosts, TLS, routing, and authentication selection. Separate OAuth/Bearer and HMAC routes are the easiest documented composition, not a Fight Common route convention.
- The desired outcome is uniform direct-JSON and request-scoped SSE support across Fight Common's supported framework adapters. Its feasibility is unverified and must not be silently narrowed.

## Grill handoff

[MCP-over-HTTP grill handoff](research/fight-common-mcp-http-support-grill-handoff.md) carries the accepted
boundaries, evidence, and resolved decisions into EPIC decomposition.

The resulting [EPIC-00006: Reusable MCP Streamable HTTP Tool Support](../epics/00006-EPIC.md) records the agreed
Tools-focused delivery destination.

## Decisions so far

- [Target the stateless MCP HTTP profile](tickets/WF-037-target-the-stateless-mcp-http-profile.md) is closed: support only MCP `2026-07-28` Streamable HTTP; legacy sessions and HTTP+SSE are excluded.
- [Set the authentication interoperability posture](tickets/WF-038-set-the-authentication-interoperability-posture.md) is closed: interoperable endpoints use OAuth/Bearer; HMAC remains available for consumer-selected machine-to-machine routes.
- [Define the Fight Common and consumer ownership boundary](tickets/WF-039-define-fight-common-and-consumer-ownership-boundary.md) is closed: Fight Common owns protocol primitives and policy-free integration support, while consumers and Access Control retain routes, principals, and authorization.
- [Define reusable OAuth resource-server support](tickets/WF-043-define-reusable-oauth-resource-server-support.md) is closed: Fight Common provides policy-free protected-resource and Bearer-token validation support, not an authorization server or principal model.
- [Define MCP tool and CQRS integration conventions](tickets/WF-040-define-mcp-tool-and-cqrs-integration-conventions.md) is closed: explicit tools validate and map MCP arguments to existing payloads, return semantic output, and may use policy-free envelope metadata filters without exposing transport or Agent concerns to handlers.
- [Define MCP presentation, errors, and response modes](tickets/WF-041-define-mcp-presentation-errors-and-response-modes.md) is closed: Common owns MCP presentation, exposes only explicitly mapped public-safe failures, conceals unavailable tools through one neutral availability seam, and uses direct JSON or progressive request-scoped SSE according to the accepted product convention.
- [Select the first Access Control MCP proof](tickets/WF-042-select-the-first-access-control-mcp-proof.md) is closed with a supported disposition: Common uses package-owned test fixtures, while any real Agent mutation waits for a suitable Access Control use case and is never a Common gate.

## Tickets

<!-- planning:decisions -->
| Decision ID | Title | Type | Mode | Status | Depends on |
|---|---|---|---|---|---|
| [WF-037](tickets/WF-037-target-the-stateless-mcp-http-profile.md) | Target the stateless MCP HTTP profile | wayfinder:research, wayfinder:grilling | HITL | Closed | — |
| [WF-038](tickets/WF-038-set-the-authentication-interoperability-posture.md) | Set the authentication interoperability posture | wayfinder:research, wayfinder:grilling | HITL | Closed | [WF-037](tickets/WF-037-target-the-stateless-mcp-http-profile.md) |
| [WF-039](tickets/WF-039-define-fight-common-and-consumer-ownership-boundary.md) | Define Fight Common and consumer ownership boundary | wayfinder:grilling, wayfinder:domain-modeling | HITL | Closed | [WF-037](tickets/WF-037-target-the-stateless-mcp-http-profile.md), [WF-038](tickets/WF-038-set-the-authentication-interoperability-posture.md) |
| [WF-040](tickets/WF-040-define-mcp-tool-and-cqrs-integration-conventions.md) | Define MCP tool and CQRS integration conventions | wayfinder:grilling, wayfinder:domain-modeling | HITL | Closed | [WF-039](tickets/WF-039-define-fight-common-and-consumer-ownership-boundary.md), [WF-043](tickets/WF-043-define-reusable-oauth-resource-server-support.md) |
| [WF-041](tickets/WF-041-define-mcp-presentation-errors-and-response-modes.md) | Define MCP presentation, errors, and response modes | wayfinder:research, wayfinder:grilling | HITL | Closed | [WF-039](tickets/WF-039-define-fight-common-and-consumer-ownership-boundary.md), [WF-040](tickets/WF-040-define-mcp-tool-and-cqrs-integration-conventions.md) |
| [WF-042](tickets/WF-042-select-the-first-access-control-mcp-proof.md) | Select the first Access Control MCP proof | wayfinder:grilling, wayfinder:domain-modeling | HITL | Closed | [WF-040](tickets/WF-040-define-mcp-tool-and-cqrs-integration-conventions.md), [WF-041](tickets/WF-041-define-mcp-presentation-errors-and-response-modes.md), [WF-043](tickets/WF-043-define-reusable-oauth-resource-server-support.md) |
| [WF-043](tickets/WF-043-define-reusable-oauth-resource-server-support.md) | Define reusable OAuth resource-server support | wayfinder:research, wayfinder:grilling | HITL | Closed | [WF-039](tickets/WF-039-define-fight-common-and-consumer-ownership-boundary.md) |
<!-- /planning:decisions -->

## Blocking relationships

```text
WF-037 ──→ WF-038 ──→ WF-039 ──→ /fight-grill handoff
                                      ├──→ WF-040 (closed)
                                      ├──→ WF-041 (closed)
                                      └──→ WF-043 (closed scope decision)
WF-040 (closed) ──→ WF-042 (closed) ←── WF-041 (closed)
```

## Implementation handoff

[EPIC-00006](../epics/00006-EPIC.md) owns the reusable Fight Common destination. The
[grill handoff](research/fight-common-mcp-http-support-grill-handoff.md) preserves the accepted named concepts,
protocol research, feasibility risks, and decomposition inputs. Decomposition may proceed EPIC → TICKET → TASK;
this map authorizes none of those later workflow stages by itself.

## Remaining implementation risks

- Genuine progressive SSE still requires framework-by-framework implementation evidence. An exception or scope
  reduction requires an explicit decision and cannot be inferred from this map's closure.
- Exact public API shapes beyond the accepted named concepts remain decomposition work.

## Out of scope

- Agent identity, Agent permission policy, permission strings, or authorization decisions.
- Consumer route names, framework routing, TLS, gateway, deployment, and OAuth authorization-server implementation.
- Legacy MCP transports, sessions, and HTTP+SSE compatibility. Tasks, Apps, Skills, authorization extensions, and
  other non-Tool capabilities are excluded from the first EPIC without becoming permanent exclusions.
