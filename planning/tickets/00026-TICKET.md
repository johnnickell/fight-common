---
id: TICKET-00026
epic: EPIC-00006
title: Resume Protected input_required Interactions
status: ready-for-agent
---

# Resume Protected input_required Interactions

## Problem statement

Some consumer tools need additional ordinary input or one destructive-action confirmation after initial argument
validation. Without protected retry state, a client could replay, substitute, or resume another caller's interaction;
using the HMAC authentication nonce would incorrectly couple the mechanism to one authentication scheme and fail
for Bearer-authenticated callers.

## Solution and boundaries

Support MCP `input_required` results with two protected interaction modes. Ordinary multi-step input uses
integrity-bound state; destructive confirmation uses a consumer-provided atomic one-time interaction store. Both
bind neutral caller identity, selected canonical tool, originally validated arguments, and expiry. An interactive
tool resumes with restored original arguments plus separately validated `inputResponses`; it does not duplicate
initial metadata or validation declarations.

Before emitting an input request, Common verifies that the current request declares the required client interaction
capability. A tool cannot enter the interaction flow for a client that did not advertise support.

Consumers decide which actions are destructive, supply the neutral caller identity and confirmation store, and own
their business confirmation policy. Common provides protected state mechanics and MCP representation only.

## Use cases

| Use case | Commands | Queries | Events | Expected side effects |
| --- | --- | --- | --- | --- |
| A capable client receives a request for ordinary additional input | N/A | N/A | N/A | Verify the declared client capability, then preserve caller, tool, arguments, and expiry for a later retry. |
| A capable client receives a destructive confirmation request | N/A | N/A | N/A | Verify the declared client capability and ensure one confirmation cannot be replayed or resumed twice. |
| A client without the interaction capability reaches an interactive branch | N/A | N/A | N/A | Reject the interaction before emitting an input request or dispatching the resumed use case. |
| A client resumes an interaction through `resume()` | Consumer-defined existing Command when the resumed tool mutates state | Consumer-defined existing Query when the resumed tool reads state | Consumer-defined events only | Continue the selected use case without repeating discovery metadata or initial validation declarations. |
| Invalid interaction state is presented | N/A | N/A | N/A | Return a safe interaction/protocol failure without dispatching the underlying use case. |

## Validation, permissions, and failures

Common separately validates resumed `inputResponses` against the schema retained with the interaction request.
Caller mismatch, tool mismatch, original-argument mismatch, expiry, integrity failure, replay, and already-consumed
confirmation state fail closed. A confirmation state is atomically consumed before resume. HMAC request nonce
machinery is not reused. Common receives a neutral caller identity for state binding, never a generic principal or
permission model. Missing client interaction capability also fails closed before any input request or underlying
use-case dispatch.

## Dependencies

- [TICKET-00023](00023-TICKET.md) supplies protocol errors and request validation.
- [TICKET-00024](00024-TICKET.md) supplies explicit tools, metadata, validation, and selected invocation.
- [WF-040](../wayfinder/tickets/WF-040-define-mcp-tool-and-cqrs-integration-conventions.md) settles the
  interactive-tool convention.
- [WF-041](../wayfinder/tickets/WF-041-define-mcp-presentation-errors-and-response-modes.md) settles safe result
  representation and cancellation boundaries.

## Compatibility and exclusions

Every public contract added by this work requires additive public-API-manifest classification and behavior-focused
compatibility evidence. Existing HMAC authentication and nonce behavior remain unchanged.

Consumer destructive-action policy, approval language, principal resolution, authorization, persistence selection,
durable task lifecycle, forced cancellation, and automatic rollback are excluded.

## Acceptance and evidence

Package-owned interactive fixtures prove ordinary integrity-bound state, confirmation atomic single use, caller/tool/
argument/expiry binding, separate `inputResponses` validation, safe retry behavior, and rejection of tampered,
expired, mismatched, replayed, or already-consumed state. Tests prove no HMAC nonce reuse and no dispatch occurs for
rejected state. Capability fixtures prove input requests are emitted only when the current request advertises the
required client capability and that unsupported clients receive no input request or use-case dispatch. Every public
addition is manifest-classified and behavior evidence proves existing authentication and command/query contracts
are unchanged.

## TASKs

<!-- planning:children -->
| ID | Title | Status |
|---|---|---|
| None | — | — |
<!-- /planning:children -->

## Decisions and progress

The [grill handoff](../wayfinder/research/fight-common-mcp-http-support-grill-handoff.md) records the ordinary and
confirmation-state modes and reserves consumer business policy to the consumer. No TASKs have been decomposed yet.
