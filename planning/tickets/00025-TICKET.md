---
id: TICKET-00025
epic: EPIC-00006
title: Stream Progress and Cancel a Tool Request Across Supported Compositions
status: ready-for-agent
---

# Stream Progress and Cancel a Tool Request Across Supported Compositions

## Problem statement

MCP tools need timely progress and cooperative cancellation, but Common's current HTTP adapters construct complete
JSON bodies. A preassembled one-event stream would not prove the incremental, request-scoped SSE behavior required
by the EPIC, and inconsistent framework handling would make a shared tool contract misleading.

## Solution and boundaries

Provide a framework-neutral, request-scoped `McpProgressReporter` and response selection behavior. A progress token
selects genuine incremental SSE, with progress notifications before the final response; without a token, the same
tool receives a no-op reporter and returns direct JSON. Progress reports monotonic numeric/status work only and
never carries partial result content. Closing the request stream marks the reporter cancelled; tools check at safe
points and stop cooperatively where practical.

This behavior must be proven across Symfony, Laravel, Yii, CodeIgniter, Slim, and the framework-free/PSR path.
Frameworks retain their own routes and composition roots; Common must not invent branded adapters where an existing
native or PSR seam expresses the full behavior, nor silently fall back to completed or buffered SSE.

## Use cases

| Use case | Commands | Queries | Events | Expected side effects |
| --- | --- | --- | --- | --- |
| A client requests progress for `tools/call` | N/A | N/A | `notifications/progress` | Emit timely status notifications before the final JSON-RPC response through SSE. |
| A client does not request progress for `tools/call` | N/A | N/A | N/A | Supply a no-op reporter and return direct JSON without changing tool behavior. |
| A tool reports work | N/A | N/A | Progress notifications only | Preserve monotonic ordering; keep partial result content exclusively in final `McpToolOutput`. |
| A client closes the stream | N/A | N/A | N/A | Suppress further response messages and let the tool stop cooperatively without claiming rollback. |
| A framework composes MCP support | N/A | N/A | N/A | Preserve progressive delivery, buffering control, and disconnect semantics across every supported composition. |

## Validation, permissions, and failures

The protocol validates progress-token shape as part of the request. Progress is status rather than output and must
remain monotonic. Stream closure prevents subsequent emission, but Common neither forcefully interrupts application
work nor promises to undo a command already dispatched. A framework inability to provide timely incremental
delivery, buffering control, or closure propagation is an explicit evidence failure requiring a decision; it cannot
be represented as successful support with a one-event or buffered fallback.

Consumer authentication, authorization, routes, worker policy, and business cancellation semantics remain outside
this requirement.

## Dependencies

- [TICKET-00023](00023-TICKET.md) supplies protocol response selection and the endpoint foundation.
- [TICKET-00024](00024-TICKET.md) supplies selected tool invocation and semantic output.
- [WF-041](../wayfinder/tickets/WF-041-define-mcp-presentation-errors-and-response-modes.md) settles the accepted
  progress, SSE, and cancellation behavior.
- [ADR 0021](../adr/0021-framework-default-capability-compositions.md) and
  [ADR 0024](../adr/0024-framework-adapter-support-and-delivery-boundaries.md) define framework-owned composition
  and support-evidence boundaries.

## Compatibility and exclusions

Every public contract added by this work requires additive public-API-manifest classification and behavior-focused
compatibility evidence. Existing JSend response types, HTTP middleware, framework registrations, and optional
dependency boundaries remain supported.

Legacy HTTP+SSE/session behavior, long-lived subscriptions, `tools.listChanged`, durable Tasks, partial tool-result
streaming, forced interruption, transaction rollback guarantees, consumer route ownership, and a new branded
adapter for a framework that can use an existing complete seam are excluded.

## Acceptance and evidence

Package-owned contract fixtures prove direct JSON/no-op behavior, monotonic progress-before-final ordering,
progress content separation, disconnect cancellation, and suppression after closure. Framework-native and PSR
integration evidence proves actual timely multi-event SSE, buffering-control configuration where required, and
disconnect propagation for Symfony, Laravel, Yii, CodeIgniter, Slim, and the framework-free/PSR composition.
Each public addition is manifest-classified and behavior evidence proves existing framework contracts remain
additive. If any composition cannot meet the full contract, its exact limitation and the required explicit decision
are retained as acceptance evidence rather than hidden.

## TASKs

<!-- planning:children -->
| ID | Title | Status |
|---|---|---|
| None | — | — |
<!-- /planning:children -->

## Decisions and progress

The [grill handoff](../wayfinder/research/fight-common-mcp-http-support-grill-handoff.md) records the retained
streaming feasibility risk and forbids silent scope reduction. No TASKs have been decomposed yet.
