---
id: TICKET-00027
epic: EPIC-00006
title: Protect MCP Endpoints with Reusable OAuth Resource-Server Support
status: done
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
| [TASK-00113](../tasks/00113-TASK.md) | Compose policy-free OAuth resource-server protection for MCP | done |
<!-- /planning:children -->

## Decisions and progress

The implementation and landing checkpoints below retain their historical states. The parent acceptance section
records current closeout; earlier pending-review and pending-closeout statements are not current blockers.

The [grill handoff](../wayfinder/research/fight-common-mcp-http-support-grill-handoff.md) and
[WF-043](../wayfinder/tickets/WF-043-define-reusable-oauth-resource-server-support.md) establish resource-server,
not authorization-server, ownership. TASK-00113 owns the complete resource-server slice around TASK-00105's
guarded endpoint: metadata, strict consumer token-validation input, neutral validated claims, compliant HTTP
authorization outcomes, lossless PSR adaptation, and unchanged HMAC/JWT behavior. Consumer routes, validator/key
infrastructure, claim-to-principal mapping, and authorization remain outside Common.

At TASK-00113's 2026-09-29 pre-publication checkpoint, the complete package slice is implemented and locally
verified, including PSR adapters, metadata and failure/handoff journeys, unchanged HMAC behavior and additive
manifest classification. Independent TASK review is pending; this TICKET's explicit parent acceptance/closeout
has not been performed. Actual consumer cryptographic validation and deployment remain separate qualification.

The first independent TASK review requested R1, omission of empty `scopes_supported` metadata under RFC 9728.
TASK-00113's same-day regression-first repair and fresh complete local gate are recorded in its completion notes;
independent re-review remains pending. This does not close the parent or qualify a deployed consumer.

At the subsequent 2026-09-29 landing checkpoint, independent TASK review accepted candidate `c5b6542` with R1
resolved and all Spec/Standards criteria passing. Authorized publication and final-head delivery verification
remain separate from that technical acceptance. This TICKET's explicit parent closeout is still pending;
consumer cryptographic validation and deployment qualification are not implied.

## Parent acceptance and closeout

John authorized this tracked closeout after the parent reassessment at
`3d92010c22f0f81ab1d84d7932cd67b5055d4e73` explicitly accepted the complete bounded OAuth resource-server slice.
TICKET-00027 is **done**: TASK-00113 has applicable independent technical acceptance and behavioral QA PASS,
all parent outcomes above are satisfied, and required local verification is complete. The earlier PC-02 hold was
missing independent behavioral QA, not a product defect. Its resolution preserves the original review, R1 repair
and historical delivery checkpoints.

- [TASK-00113](../tasks/00113-TASK.md) supplies exact protected-resource metadata, header-only Bearer extraction,
  complete validator requirements, independent normalized claim checks, effective-scope comparison and immutable
  claims-only handoff before guarded MCP dispatch. Distinct malformed 400, missing/invalid 401 and insufficient-
  scope 403 outcomes retain safe metadata challenges and no forbidden dispatch. Consumer identity/policy ownership
  and unchanged HMAC/JWT behavior remain explicit.
- Independent QA PASS at the exact reassessed head exercised 161 bounded calls / 3,086 assertions, including
  90 actual in-process PSR calls, not 161 live HTTP requests. It challenged metadata/R1 empty-scope omission,
  transport ambiguity, intrinsic claim/time/key boundaries, scope sets, handoff/diagnostic failures, guarded
  endpoint ordering and real HMAC signature/nonce isolation. Supporting checks passed 408 tests / 1,866 assertions.
  No confirmed product defect remains; visual evidence is N/A, not behavioral QA. Canonical reports are
  `.runs/reviews/TASK-00113/review.md` and `.runs/qa/TASK-00113/qa.md`.
- Across this and the interaction parent reassessment, all 123 indexed child QA artifacts verified without
  mismatch; each QA snapshot matched all 1,573 tracked paths and current dependencies/runtime. OAuth/Auth and
  guarded HTTP source match original accepted content; later shared MCP integration is separately reconciled.
  Evidence from overlapping suites or earlier attempts is not counted as new unique coverage.
- The retained complete gate has exit 0: Unit 4,972 / 11,428, exact 12,651/12,651 statements, Integration
  150 / 1,024 and Functional 49 / 1,999. Its tested snapshot matches `d9cce23`; subsequent changes through
  reassessment are planning-only. Existing documentation advisories remain. This administrative closeout changes
  no product inputs; targeted checks and content mapping belong in its local handoff.

Complete outcome mapping and qualifications are retained in
`.runs/reviews/parent-closeout-00026-00027-3d92010c/report.md`; closeout verification belongs in
`.runs/handoffs/ticket-00026-00027-closeout/receipt.md`. The validator fixture qualifies Common's contract and
normalized checks, not production cryptographic honesty, issuer/key infrastructure or a deployed authorization
server. Consumers retain complete validation, principal mapping, context isolation, scope hierarchy, redacting
sinks, routes and TLS/ingress. Signature-only JWT is not OAuth validation; sink invocation does not guarantee
durable logging from a broken sink. No EPIC closeout, archive, child PR reopening, external evidence publication,
merge, release or deployment follows from this parent acceptance.
