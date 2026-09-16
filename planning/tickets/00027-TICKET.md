---
id: TICKET-00027
epic: EPIC-00006
title: Protect MCP Endpoints with Reusable OAuth Resource-Server Support
status: ready-for-agent
---

# Protect MCP Endpoints with Reusable OAuth Resource-Server Support

## Problem statement

Standard remote MCP clients require interoperable OAuth/Bearer resource-server behavior, while local provisioned
machine-to-machine clients may use Fight Common's established HMAC request signing. The existing JWT decoder only
checks a symmetric signature and must not be represented as complete OAuth validation. A shared implementation must
avoid becoming an authorization server or inventing a principal model.

## Solution and boundaries

Provide reusable, policy-free protected-resource metadata representation, Bearer extraction, standards-compliant
401/403 challenge support, and a strict token-validation port that returns validated claims to consumer-owned
authentication composition. Consumers configure issuer, audience/resource, expiration, not-before, token
purpose/type, allowed algorithms, key identity/rotation, and required scopes; they map accepted claims into their
own authentication context and authoritative principal.

Existing consumer-selected HMAC MCP routes remain compatible. Separate HMAC and Bearer routes are helpful because
the schemes use incompatible `Authorization` values, but they are not mandatory. Common owns neither route nor
which authentication mode a consumer exposes.

## Use cases

| Use case | Commands | Queries | Events | Expected side effects |
| --- | --- | --- | --- | --- |
| A remote MCP client discovers a protected resource | N/A | N/A | N/A | Describe the configured resource-server boundary without issuing credentials. |
| A Bearer request reaches a consumer MCP route | N/A | N/A | N/A | Hand validated claims to consumer authentication composition before MCP dispatch. |
| Authentication fails | N/A | N/A | N/A | Return HTTP 401 without selecting or invoking a tool. |
| Scope is insufficient | N/A | N/A | N/A | Return HTTP 403 without converting the failure to a selected-tool result. |
| A provisioned client uses HMAC | N/A | N/A | N/A | Retain the established HMAC signature and nonce contract without treating it as OAuth. |

## Validation, permissions, and failures

The validation port is strict: a consumer must configure and validate issuer, audience/resource, expiry,
not-before, purpose/type, allowed algorithms, key identity/rotation, and scopes. Invalid/missing Bearer material,
claims, or required scope stops before MCP dispatch and preserves HTTP OAuth semantics. Validated claims do not
become a Fight Common Agent, user, principal, or permission decision; consumer composition owns that mapping.

## Dependencies

- [TICKET-00023](00023-TICKET.md) provides the policy-free MCP endpoint that consumer authentication surrounds.
- [WF-038](../wayfinder/tickets/WF-038-set-the-authentication-interoperability-posture.md) establishes the
  interoperable Bearer and optional HMAC posture.
- [WF-039](../wayfinder/tickets/WF-039-define-fight-common-and-consumer-ownership-boundary.md) establishes
  consumer ownership of routes, principals, and authorization.
- [WF-043](../wayfinder/tickets/WF-043-define-reusable-oauth-resource-server-support.md) establishes the
  resource-server boundary.

## Compatibility and exclusions

Every public contract added by this work requires additive public-API-manifest classification and behavior-focused
compatibility evidence. Existing HMAC authenticator, request signer, nonce semantics, JWT decoder contract, and
consumer authentication composition remain supported; the JWT decoder is not upgraded by implication into OAuth
validation.

OAuth authorization-server operation, token issuance, client registration, login, consent, authorization policy,
permission checks, Agents, principal types, route selection, and gateway/TLS deployment are excluded. Optional
PSR-15/PSR-17 adapters may be supplied only where they preserve the complete observable resource-server behavior.

## Acceptance and evidence

Focused contract evidence proves protected-resource metadata, Bearer extraction, validation-port inputs and
outputs, compliant 401/403 challenges, pre-dispatch failure behavior, claim handoff without a Common principal,
and coexistence with consumer-selected HMAC routes. Optional PSR adapter conformance proves no required behavior is
lost. Every public addition is manifest-classified and behavior evidence proves legacy authentication contracts
remain additive.

## TASKs

<!-- planning:children -->
| ID | Title | Status |
|---|---|---|
| [TASK-00113](../tasks/00113-TASK.md) | Compose policy-free OAuth resource-server protection for MCP | ready-for-agent |
<!-- /planning:children -->

## Decisions and progress

The [grill handoff](../wayfinder/research/fight-common-mcp-http-support-grill-handoff.md) and
[WF-043](../wayfinder/tickets/WF-043-define-reusable-oauth-resource-server-support.md) establish resource-server,
not authorization-server, ownership. TASK-00113 owns the complete resource-server slice around TASK-00105's
guarded endpoint: metadata, strict consumer token-validation input, neutral validated claims, compliant HTTP
authorization outcomes, lossless PSR adaptation, and unchanged HMAC/JWT behavior. Consumer routes, validator/key
infrastructure, claim-to-principal mapping, and authorization remain outside Common.
