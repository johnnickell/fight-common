---
id: TICKET-00024
epic: EPIC-00006
title: Discover and Invoke Explicitly Opted-In CQRS Tools
status: ready-for-agent
---

# Discover and Invoke Explicitly Opted-In CQRS Tools

## Problem statement

Consumers need a safe way to expose selected existing commands and queries to MCP without discovering arbitrary
HTTP Actions, reflectively hydrating use cases, leaking authorization context into handlers, or changing the void
command-bus contract.

## Solution and boundaries

Provide explicit `McpTool`, `McpToolInfo`, and `McpToolOutput` contracts plus a focused tool registry. An opted-in
tool implements `McpTool::handle(ApplicationData $input, McpProgressReporter $progress): McpToolOutput` and declares
method-level `#[McpToolInfo(name: ..., description: ..., inputSchema: ..., outputSchema: ...)]`. It receives
validated `ApplicationData`, maps arguments explicitly to an existing command or query, and returns only safe,
semantic tool output. Structured output must conform to the declared output schema and provide compatible text
presentation; the protocol responder creates JSON-RPC/MCP wire results and centralizes failures.

`tools/list` filters before deterministic ordering, pagination, and cache metadata. One request-scoped neutral
availability boundary receives tool metadata only for both discovery and invocation. It conceals unavailable tools
from listing and makes direct invocation indistinguishable from an unknown tool.

## Use cases

| Use case | Commands | Queries | Events | Expected side effects |
| --- | --- | --- | --- | --- |
| A consumer explicitly exposes a tool | N/A | N/A | N/A | Reject duplicate, absent, or invalid metadata at composition rather than exposing an accidental tool. |
| A client lists available tools through `tools/list` | N/A | N/A | N/A | Return only available tools with conservative `ttlMs: 0` and `cacheScope: private` defaults unless safely overridden. |
| A client invokes a query-backed tool through `tools/call` | N/A | Consumer-defined existing Query, dispatched through `QueryBus::fetch()` | N/A; queries do not mutate business state | Return safe semantic output without coupling the query payload to MCP transport. |
| A client invokes a mutation-backed tool through `tools/call` | Consumer-defined existing Command, dispatched through void `CommandBus::execute()` | An existing consumer Query only when genuinely necessary for truthful output | Consumer-defined events only | Return only a truthful acknowledgement, such as a caller-generated identifier. |
| A consumer elects envelope observability | N/A | N/A | N/A | Add only namespaced canonical-tool/correlation baseline metadata and scalar namespaced consumer audit metadata. |

## Validation, permissions, and failures

The selected-tool invoker reflects `#[Validation]` beside method-level `#[McpToolInfo]`, calls `ValidationService`
over exactly `tools/call.params.arguments`, and passes the resulting `ApplicationData` to `handle()`. Explicit input
and output schemas require separate consistency and conformance evidence, including structured-output conformance
and compatible text presentation; an empty rule list still produces validated application data over the original
arguments. Exact reporter convenience methods and registration mechanics remain TASK design.

Availability receives neither a principal nor a generic principal context. Consumers retain their own
authentication and permission enforcement. Missing or invalid tool metadata, invalid canonical identity, and
duplicate registry names fail composition. Unknown or unavailable tools are protocol errors; after tool selection,
validation and expected typed business failures are complete `isError: true` tool results. Unclassified failures
are centrally logged through the consumer's redaction-aware diagnostics and become generic internal errors.

Fight Access Control owns its planned `RequiresAgentPermission` declaration and Agent-aware availability and
invocation enforcement; the consuming application wires that integration to current-Agent authentication. This is
future cross-project adoption, not a Fight Common implementation or acceptance dependency.

## Dependencies

- [TICKET-00023](00023-TICKET.md) provides protocol dispatch and capability registration.
- [WF-040](../wayfinder/tickets/WF-040-define-mcp-tool-and-cqrs-integration-conventions.md) establishes the
  explicit tool, schema, validation, CQRS, and metadata conventions.
- [WF-041](../wayfinder/tickets/WF-041-define-mcp-presentation-errors-and-response-modes.md) establishes the
  availability concealment and selected-tool error boundary.

## Compatibility and exclusions

Every public contract added by this work requires additive public-API-manifest classification and behavior-focused
compatibility evidence. `CommandBus::execute()` remains void, existing command/query payloads remain transport
neutral, and existing consumers retain normal bus behavior when filters are not composed.

Arbitrary Action discovery, reflective payload hydration, framework responses, raw JSON-RPC in tools, raw
arguments, headers, credentials, authorization decisions, Agent models, permission strings, and tool-created MCP
wire errors are excluded.

## Acceptance and evidence

Package-owned query-backed and mutation-backed fixtures prove explicit opt-in, metadata rejection, schema and
runtime-validation conformance, structured-output/output-schema conformance with compatible text presentation,
safe output projection, one-read/write discipline, deterministic availability-filtered pagination, conservative
cache defaults, optional filter metadata isolation, and all failure classifications. Public additions are
manifest-classified and behavior evidence proves existing bus and consumer contracts remain unchanged. No Fight
Access Control query, mutation, permission attribute, or consumer adoption is required: the Common-owned fixtures
are the acceptance authority.

## TASKs

<!-- planning:children -->
| ID | Title | Status |
|---|---|---|
| [TASK-00106](../tasks/00106-TASK.md) | Register and discover explicitly opted-in MCP Tools | ready-for-agent |
| [TASK-00107](../tasks/00107-TASK.md) | Invoke validated CQRS MCP Tools with safe semantic output | ready-for-agent |
<!-- /planning:children -->

## Decisions and progress

The [grill handoff](../wayfinder/research/fight-common-mcp-http-support-grill-handoff.md) fixes the named tool
contracts and behavioral constraints while leaving exact PHP registration mechanics to implementation decomposition.
[TASK-00106](../tasks/00106-TASK.md) owns explicit registration and truthful availability-filtered discovery;
[TASK-00107](../tasks/00107-TASK.md) owns validated query/mutation invocation, safe semantic output, optional CQRS
envelope metadata, and integrated TICKET acceptance. TASK-00106 follows the generic semantic capability foundation
in TASK-00104 and may proceed in parallel with TASK-00105's guarded HTTP transport; TASK-00107 follows TASK-00106.
