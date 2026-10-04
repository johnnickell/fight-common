---
id: TICKET-00025
epic: EPIC-00006
title: Stream Progress and Cancel a Tool Request Across Supported Compositions
status: done
---

# Stream Progress and Cancel a Tool Request Across Supported Compositions

## Problem statement

MCP tools need timely progress and cooperative cancellation, but Common's HTTP adapters at the original planning
checkpoint constructed complete JSON bodies. A preassembled one-event stream would not prove the incremental,
request-scoped SSE behavior required by the EPIC, and inconsistent framework handling would make a shared tool
contract misleading.

## Solution and boundaries

Provide a framework-neutral, request-scoped `McpProgressReporter` and response selection behavior. A progress token
selects genuine incremental SSE, with progress notifications before the final response; without a token, the same
tool receives a no-op reporter and returns direct JSON. Progress reports monotonic numeric/status work only and
never carries partial result content. Closing the request stream marks the reporter cancelled; tools check at safe
points and stop cooperatively where practical.

Fight Common must prove the package behavior through a concrete framework-free runtime and package-owned adapter
conformance across the Symfony, Laravel, Yii, CodeIgniter, and Slim package compositions. Consumer adoption and
runtime qualification are independently owned by Fight Agent OS, not a five-starter confirmation gate in Common,
under the [approved amendment](../adr/0024-framework-adapter-support-and-delivery-boundaries.md#progressive-mcp-adoption-amendment--2026-09-28).
Common must not invent branded adapters where an existing native or PSR seam expresses the full behavior, nor
silently fall back to completed or buffered SSE.

## Use cases

| Use case | Commands | Queries | Events | Expected side effects |
| --- | --- | --- | --- | --- |
| A client requests progress for `tools/call` | N/A | N/A | N/A - MCP protocol notifications are not Fight Events | Emit timely `notifications/progress` protocol messages before the final JSON-RPC response through SSE. |
| A client does not request progress for `tools/call` | N/A | N/A | N/A | Supply a no-op reporter and return direct JSON without changing tool behavior. |
| A tool reports work | N/A | N/A | N/A - MCP protocol notifications are not Fight Events | Preserve monotonic notification ordering; keep partial result content exclusively in final `McpToolOutput`. |
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
progress content separation, safe final failure after progress, disconnect cancellation, and suppression after
closure. Fight Common adapter conformance and a concrete framework-free server/client journey prove package
translation and live emission across the package compositions. Each public addition is manifest-classified and
behavior evidence proves existing framework contracts remain additive. Consumer runtime qualification remains
necessary for a claim about that runtime, but is not required to close this package TICKET or unblock later work.
Any demonstrated package defect is reported upstream as a bug-fix TASK; no buffered fallback or untested
production-runtime guarantee is implied.

## TASKs

<!-- planning:children -->
| ID | Title | Status |
|---|---|---|
| [TASK-00108](../tasks/00108-TASK.md) | Orchestrate request-scoped MCP progress and cooperative cancellation | done |
| [TASK-00109](../tasks/00109-TASK.md) | Deliver Fight Common progressive MCP HTTP and SSE adapters | done |
| [TASK-00110](../tasks/00110-TASK.md) | Qualify progressive MCP in every supported framework starter | wontfix |
<!-- /planning:children -->

## Decisions and progress

### Approved closeout — 2026-09-28

John explicitly cancelled the five-starter qualification plan in [TASK-00110](../tasks/00110-TASK.md), now
`wontfix`. Fight Agent OS owns adoption and will report Common issues upstream as bug-fix TASKs. Neither its
implementation nor starter receipts block this TICKET, subsequent Common work, or require a replacement
confirmation TASK. ADR 0024 records this scoped amendment to the original
[grill handoff](../wayfinder/research/fight-common-mcp-http-support-grill-handoff.md); it is an explicit evidence
boundary decision, not a silent weakening of runtime behavior.

The retained package scope is complete and independently accepted:

| Requirement | Accepted evidence |
|---|---|
| Request-scoped reporter, ordered progress, no-op behavior, failure sanitization, cooperative cancellation and isolation | TASK-00108, accepted candidate `cca8a3bd5ddcec6bc4d4a54aa628e9923f0a3ed4`, PR #167; `McpToolExecutionTest` and regression coverage. |
| HTTP selection, SSE framing/headers, incremental emission, finalization, failures and observed-disconnect suppression | TASK-00109, accepted candidate `b1f643732c6da9da5edd65d6bd1e64b6e056ea88`, PR #168; `McpStreamingJourneyTest` across framework-free/PSR plus five package framework lanes. |
| Compatibility and complete local gate | TASK-00109 records 572 classified declarations, 4057 unit tests, exact 11448/11448 statements, 150 integration tests and 17 functional tests; full log/exit and runtime matrix remain linked from that TASK. |
| Five installed-package starter receipts | Cancelled by the owner decision; not executed or claimed passed. |

`done` records completion of the amended package scope using those prior accepted results. The package journey
uses PHP cli-server with buffering/compression disabled and no proxy; it does not establish starter, Agent OS,
PHP-FPM, worker or production-proxy behavior. No new release or deployment is claimed. EPIC-00006 remains open
for TICKET-00026 and TICKET-00027; their TASK dependencies remain unchanged.
