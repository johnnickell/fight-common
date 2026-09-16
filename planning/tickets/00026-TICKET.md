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

Support MCP `input_required` results with two protected interaction modes. Ordinary multi-step input uses stateless,
versioned-AEAD opaque state; destructive confirmation uses a consumer-provided atomic one-time interaction store.
Both bind neutral caller identity, selected canonical Tool, originally validated arguments, the requested-input
contract, and expiry. An interactive Tool resumes with restored original arguments plus separately validated
`inputResponses`; it does not duplicate initial metadata or validation declarations. Ordinary state has no
server-side record and is not replay-preventing; destructive or otherwise replay-sensitive work must use confirmation
mode.

An interactive Tool statically declares form elicitation as its required client capability. After resolving current
availability but before initial validation or `handle()`, the selected-Tool invoker gates that declaration against
the current request. An incapable client never enters the Tool branch: Common emits no input request and calls
neither `handle()` nor `resume()` or an underlying bus.

Every retry privately authenticates and opens its opaque state only to recover its canonical Tool identity, then
reevaluates that Tool through the current request-scoped availability/concealment decision before revealing any
restored-state detail, validating responses, or invoking `resume()`. A Tool revoked between rounds is
indistinguishable from an unknown Tool and receives no state disclosure, `resume()`, command, query, or event dispatch.

Consumers decide which actions are destructive, supply the neutral caller identity and confirmation store, and own
their business confirmation policy. Common provides protected state mechanics and MCP representation only.

## Use cases

| Use case | Commands | Queries | Events | Expected side effects |
| --- | --- | --- | --- | --- |
| A capable client receives a request for ordinary additional input | N/A | N/A | N/A | Verify the declared client capability, call `handle()` only to yield `input_required`, then preserve caller, Tool, arguments, requested-input contract, and expiry without mapped command/query dispatch. |
| A capable client receives a destructive confirmation request | N/A | N/A | N/A | Verify the declared client capability and ensure one confirmation cannot be replayed or resumed twice. |
| A client without the interaction capability selects an interactive Tool | N/A | N/A | N/A | Reject before initial validation or `handle()`, input request, `resume()`, or bus dispatch. |
| A client resumes an interaction through `resume()` | Consumer-defined existing Command when the resumed tool mutates state | Consumer-defined existing Query when the resumed tool reads state | Consumer-defined events only | Continue the selected use case without repeating discovery metadata or initial validation declarations. |
| Invalid interaction state is presented | N/A | N/A | N/A | Return a safe interaction/protocol failure without dispatching the underlying use case. |

## Validation, permissions, and failures

`inputResponses` is a keyed map whose values are `ElicitResult` envelopes with an action of `accept`, `decline`, or
`cancel`. Common first validates the envelope shape, requested response keys, action values, and the absence of
accept-only content for `decline`/`cancel`; it validates restricted-schema content only for `accept`. Caller/Tool/
original-argument mismatch, expiry, AEAD authentication failure, malformed/oversized state, invalid response
envelope/key/action, unavailable-on-retry state, and already-consumed confirmation state fail closed. A confirmation
state is atomically consumed before affirmative resume and atomically retired on decline/cancel. Ordinary state
deliberately cannot offer replay prevention without a server-side record; valid repeated use follows the Tool's
ordinary retry semantics, while replay-sensitive operations require confirmation mode.

HMAC request nonce machinery is not reused. Common receives a neutral caller identity for state binding, never a
generic principal or permission model. Missing client interaction capability fails closed before initial Tool or
underlying use-case dispatch.

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
Common-owned interaction persistence, ordinary replay detection, reversible/plaintext client-visible bound values,
durable task lifecycle, forced cancellation, and automatic rollback are excluded.

## Acceptance and evidence

Package-owned interactive fixtures prove versioned-AEAD opaque ordinary state with no server-side record, active-key
rotation, retired-key rejection, strict pre-cryptography state-size limits, and no client-visible reversible caller/
Tool/argument/schema/expiry values. They prove caller/Tool/argument/expiry binding; keyed `ElicitResult` envelope/key/
action rules; restricted-schema validation only for `accept`; ordinary `decline`/`cancel` typed-resume behavior;
destructive `decline`/`cancel` terminal retirement without destructive dispatch; and confirmation atomic single use.

Retry fixtures prove current availability is reevaluated before restored-state disclosure or `resume()`, including a
Tool revoked between rounds that is concealed as unknown. Tests prove no HMAC nonce reuse and distinguish allowed
registry/availability resolution from prohibited handler/bus dispatch: an incapable request calls no `handle()`,
`resume()`, or bus; rejected retry calls no `resume()` or bus; an initial capable ordinary request may call `handle()`
solely to yield `input_required`. A transport-neutral cancellation simulation proves an acquired confirmation remains
consumed without automatic retry. Every public addition is manifest-classified and behavior evidence proves existing
authentication and command/query contracts unchanged.

## TASKs

<!-- planning:children -->
| ID | Title | Status |
|---|---|---|
| [TASK-00111](../tasks/00111-TASK.md) | Protect and resume ordinary MCP input_required interactions | ready-for-agent |
| [TASK-00112](../tasks/00112-TASK.md) | Atomically resume destructive MCP confirmations | ready-for-agent |
<!-- /planning:children -->

## Decisions and progress

The [grill handoff](../wayfinder/research/fight-common-mcp-http-support-grill-handoff.md) records the ordinary and
confirmation-state modes and reserves consumer business policy to the consumer. Decomposition preserves those as
two complete behavior slices rather than layer slices: TASK-00111 owns stateless versioned-AEAD ordinary additional-
input retry, and TASK-00112 extends its stable interaction envelope with consumer-provided atomic single-use
confirmation and owns integrated TICKET acceptance. Ordinary state is intentionally not a one-time token; consumers
must route destructive or otherwise replay-sensitive actions through confirmation mode.
