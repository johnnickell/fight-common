# Set the authentication interoperability posture

**Labels:** `wayfinder:research`, `wayfinder:grilling`
**Mode:** HITL
**Status:** Closed
**Map:** [Fight Common MCP over HTTP Support](../fight-common-mcp-http-support-map.md)
**Depends on:** [Target the stateless MCP HTTP profile](WF-037-target-the-stateless-mcp-http-profile.md)

## Question

How should interoperable MCP authorization and existing HMAC authentication coexist without putting route or permission policy into Fight Common?

## Why this matters

MCP HTTP clients use OAuth Bearer-token conventions when authorization is offered, while Fight Common’s HMAC implementation signs requests using `Authorization: HMAC-SHA256`. Treating them as interchangeable would either break standard-client interoperability or weaken the existing machine-to-machine contract.

## Evidence required

- MCP 2026-07-28 authorization requirements for protected-resource metadata, Bearer tokens, audiences, and authorization failures.
- Fight Common HMAC canonical-request and nonce behavior.
- Fight Access Control Agent authentication, principal resolution, and permission ownership.

## Must decide

- which mechanism serves interoperable MCP clients;
- whether local provisioned agents can retain HMAC-protected MCP access; and
- whether Fight Common owns routes, Agent identity, OAuth policy, or permissions.

## Resolution boundary

This settles the supported authentication posture. It does not decide whether a reusable OAuth resource-server adapter exists, how a consumer issues tokens, or a consumer’s endpoint topology.

## Resolution

An MCP endpoint intended for standard remote-client interoperability uses the MCP OAuth/Bearer profile. Existing HMAC remains available for consumer-selected, provisioned machine-to-machine MCP interactions, whose clients can sign the MCP POST with Fight Common’s existing HMAC request contract.

Fight Common designates neither route. Consumers choose whether to expose either mode, both, or neither. Separate OAuth/Bearer and HMAC routes are the recommended documented composition because the schemes use incompatible `Authorization` values and make gateway policy straightforward; they are not mandated. Fight Access Control owns resolving an HMAC-authenticated Agent and applying Agent permissions.

**Decision owner:** John

**Exit condition:** Closed by this resolution; the ownership decision must now define the shared boundary without inventing a principal model.
