# Define reusable OAuth resource-server support

**Labels:** `wayfinder:research`, `wayfinder:grilling`
**Mode:** HITL
**Status:** Closed
**Map:** [Fight Common MCP over HTTP Support](../fight-common-mcp-http-support-map.md)
**Depends on:** [Define Fight Common and consumer ownership boundary](WF-039-define-fight-common-and-consumer-ownership-boundary.md)

## Question

What policy-free OAuth resource-server primitives should Fight Common provide so a consumer can implement a compliant protected MCP endpoint without Fight Common becoming its authorization server, route owner, or principal model?

## Why this matters

MCP HTTP authorization requires protected-resource metadata, Bearer-token handling, exact token audience validation, and standards-compliant challenges. Fight Common’s existing JWT decoder verifies only a symmetric signature; its documentation deliberately leaves time, issuer, audience, token type, and key-rotation policy to consumers. Presenting it as OAuth support would be unsafe.

## Evidence required

- MCP 2026-07-28 authorization requirements and the underlying OAuth resource-server standards.
- Fight Common `Authenticator`, `TokenDecoder`, HMAC, PSR-7/15/17, and DI seams.
- Fight Access Control’s token issuance, authoritative principal-resolution, and authentication-context seams.

## Must decide

- the smallest reusable protected-resource metadata and Bearer challenge/response support;
- whether token extraction and cryptographic validation are reusable adapters, consumer ports, or both;
- the required validation inputs: issuer, audience/resource, expiration/not-before, token purpose, allowed algorithms, key ID/rotation, and scopes; and
- how successful validation hands off to a consumer without defining a Fight Common principal.

## Resolution boundary

This may specify resource-server building blocks and explicit consumer ports. It does not implement an OAuth authorization server, client registration, consent/login UI, token issuance policy, Agent/user resolution, or permission enforcement.

## Resolution

Fight Common may provide a policy-free OAuth resource-server kit: protected-resource metadata representation and response support, Bearer-token extraction, standards-compliant 401/403 challenges, a strict token-validation port returning validated claims, and optional PSR-15/PSR-17 adapters. The consumer configures and verifies issuer, audience/resource, expiration, not-before, token purpose/type, allowed algorithms, key identity/rotation, and required scopes.

Fight Common will not supply an OAuth authorization server, client registration, login or consent flow, token-issuance policy, routes, Agents, principals, or permission enforcement. A consumer maps accepted claims into its own authentication context and resolves its own authoritative principal. The existing JWT decoder is not presented as sufficient OAuth validation because it intentionally verifies only a symmetric signature.

**Decision owner:** John

**Exit condition:** Closed by this resolution. The tool convention will consume the consumer’s established authentication context without requiring a Fight Common principal.
