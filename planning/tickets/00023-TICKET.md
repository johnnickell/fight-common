---
id: TICKET-00023
epic: EPIC-00006
title: Serve a Safe Stateless MCP Endpoint
status: ready-for-agent
---

# Serve a Safe Stateless MCP Endpoint

## Problem statement

A consumer currently has no reusable Fight Common boundary for accepting an MCP request. Reimplementing
Streamable HTTP, JSON-RPC validation, discovery, header agreement, origin protection, and invocation limiting in
each application would create incompatible protocol behavior and could accidentally turn Common into the owner of
routes, authentication, or principal policy.

## Solution and boundaries

Provide the policy-free MCP `2026-07-28` POST-only protocol foundation. A consumer supplies its server identity,
route, authentication composition, and capability registration; Common validates and dispatches a request,
implements `server/discover`, and produces semantic direct-JSON protocol responses. A capability registry derives
the advertised capabilities from registered capability handlers so discovery remains truthful.

This requirement includes per-request MCP metadata, complete standard and declared custom `x-mcp-header` body
mirror validation, a required neutral `McpOriginPolicy`, and a required neutral invocation guard. It does not
introduce a generic principal, select a route, authenticate a caller, or define authorization policy.

## Use cases

| Use case | Commands | Queries | Events | Expected side effects |
| --- | --- | --- | --- | --- |
| An MCP client discovers a consumer server through `server/discover` | N/A | N/A | N/A | Return only the versions and capabilities actually supported by that configured consumer. |
| An MCP client sends a valid stateless MCP request | N/A | N/A | N/A | Produce one protocol-semantic direct JSON result without creating a framework response. |
| A request declares mirrored MCP information | N/A | N/A | N/A | Reject any missing, malformed, or body/header-mismatched value before capability dispatch. |
| A browser-originated request reaches the endpoint | N/A | N/A | N/A | An absent Origin is valid; reject a supplied disallowed Origin before dispatch with HTTP 403; map guard denial to HTTP 429 with a JSON-RPC error when an id exists. |

## Validation, permissions, and failures

Composition requires a consumer-configured origin policy and invocation guard; an implicit pass-through policy is
not valid. The guard owns its caller key, thresholds, exemptions, backing store, and policy. The origin policy
accepts an explicit native-only deny-all configuration or exact scheme-host-port allow-list entries. An Origin
that is present but not allowed fails with HTTP 403 before any capability or tool is selected.

Malformed JSON, invalid JSON-RPC, unsupported protocol versions, unknown methods, invalid outer parameters, and
header/body disagreement are protocol failures. They are represented by the central MCP responder, not by a
consumer Action or JSend adapter. The endpoint receives neither a principal nor credentials through Common's
protocol contracts. Authentication and authorization failures remain consumer-owned pre-dispatch behavior.

## Dependencies

- [EPIC-00006](../epics/00006-EPIC.md) supplies the accepted destination.
- [WF-037](../wayfinder/tickets/WF-037-target-the-stateless-mcp-http-profile.md) settles the MCP revision and
  stateless transport boundary.
- [WF-039](../wayfinder/tickets/WF-039-define-fight-common-and-consumer-ownership-boundary.md) settles the
  policy-free ownership boundary.
- [WF-041](../wayfinder/tickets/WF-041-define-mcp-presentation-errors-and-response-modes.md) settles protocol
  error and direct-response conventions.

## Compatibility and exclusions

Every public contract added by this work requires additive public-API-manifest classification and behavior-focused
compatibility evidence. Existing JSend, PSR HTTP, HMAC, framework, and consumer APIs remain unchanged.

Legacy MCP sessions, `initialize`, HTTP+SSE compatibility, GET/DELETE endpoint behavior, Resources, Prompts,
Tasks, Apps, Skills, subscriptions/listChanged, consumer routes, TLS/gateway configuration, authentication,
principal resolution, and authorization policy are excluded.

## Acceptance and evidence

Package-owned unit and contract evidence proves stateless POST handling, `server/discover`, truthful capability
advertisement, JSON-RPC/protocol failures, complete header mirrors, accepted absent/allowed Origin behavior,
pre-dispatch HTTP 403 for a disallowed Origin, invocation-guard denial, and direct JSON representation. Framework
wiring examples show consumer-owned routes delegating to the Common handler with explicit server identity,
capabilities, origin policy, guard, and authentication composition. Evidence proves that no route, principal,
credentials, or authorization policy enters Common's public protocol contracts. All public additions are classified
in the manifest and their observable behavior is covered before acceptance.

## TASKs

<!-- planning:children -->
| ID | Title | Status |
|---|---|---|
| [TASK-00104](../tasks/00104-TASK.md) | Establish MCP protocol semantics and truthful capability discovery | ready-for-agent |
| [TASK-00105](../tasks/00105-TASK.md) | Serve guarded stateless MCP requests through PSR HTTP | ready-for-agent |
<!-- /planning:children -->

## Decisions and progress

The [MCP-over-HTTP grill handoff](../wayfinder/research/fight-common-mcp-http-support-grill-handoff.md) establishes
the capability-registry direction, consumer-supplied server identity, full transport-mirror requirement, and
policy-free endpoint boundary. TASK-00104 owns the framework-neutral protocol semantics, truthful discovery,
capability dispatch, and centralized errors without exposing an unguarded HTTP endpoint. TASK-00105 then owns the
first PSR HTTP endpoint, direct JSON, and required Origin, invocation-guard, and complete header/body-mirror
safeguards, including integrated TICKET acceptance. The dependency keeps each TASK independently reviewable
without splitting Application and Adapter layers into separate durable work records.
