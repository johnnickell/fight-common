# Define MCP presentation, errors, and response modes

**Labels:** `wayfinder:research`, `wayfinder:grilling`
**Mode:** HITL
**Status:** Closed
**Map:** [Fight Common MCP over HTTP Support](../fight-common-mcp-http-support-map.md)
**Depends on:** [Define Fight Common and consumer ownership boundary](WF-039-define-fight-common-and-consumer-ownership-boundary.md), [Define MCP tool and CQRS integration conventions](WF-040-define-mcp-tool-and-cqrs-integration-conventions.md)

## Question

How should MCP request validation, safe Views, protocol errors, application failures, and direct JSON versus request-scoped SSE responses be represented?

## Why this matters

MCP requires its own JSON-RPC/MCP representation. Reusing JSend’s wire format would be non-compliant, but Fight Common’s existing JSend approach may still supply useful architectural separation between semantic presentation and native HTTP response creation.

## Evidence required

- MCP base-protocol, tools, and Streamable HTTP response/error requirements.
- Existing JSend envelope and framework-response adapter boundaries.
- Existing validation exceptions and public-safe error handling.

## Must decide

- protocol versus application error taxonomy and public-safe diagnostics;
- safe View/result serialization and structured tool output;
- when a direct JSON response is sufficient and when SSE is justified; and
- cancellation, logging, and correlation/context behavior.

## Resolution boundary

This settles presentation conventions, not an implementation of every streaming or MCP extension capability.

## Resolution

Fight Common presents MCP independently of JSend. Its central responder owns JSON-RPC/MCP representation and
separates failures by when they occur:

- malformed JSON, invalid JSON-RPC, unknown methods, invalid outer parameters, header/body mismatches, unknown
  tools, and unavailable tools are protocol errors;
- after a tool is selected, validation and expected business failures are complete tool results with
  `isError: true`;
- only explicitly mapped public-safe failures may expose sanitized actionable detail; an unclassified
  `Throwable` becomes a generic `-32603` internal error and is logged once through the consumer's
  redaction-aware diagnostic path; and
- OAuth authentication and scope failures remain HTTP `401`/`403` Bearer behavior before MCP dispatch. The
  required invocation guard retains the accepted HTTP `429` response with a JSON-RPC error when an id exists.

Authorization-sensitive availability uses one decision for discovery and invocation. Fight Common asks a
request-scoped neutral availability service about tool metadata; it does not receive a principal or define a
generic principal context. Fight Access Control will supply the standard Agent-aware integration, including the
planned `RequiresAgentPermission` declaration and enforcement that resolves its own current Agent authority.
The consuming application wires that integration to its request authentication and runtime. The attribute is an
accepted cross-project design responsibility, not an assertion that it is already implemented. An unavailable
tool is omitted from `tools/list`, and direct invocation is indistinguishable from an unknown tool.

Representative wire shapes follow. Exact field values and framework response objects remain decomposition and
conformance-test concerns.

**Direct JSON success** (`Content-Type: application/json`, no progress token):

```json
{"jsonrpc":"2.0","id":7,"result":{"resultType":"complete","content":[{"type":"text","text":"{\"agentId\":\"agent-123\"}"}],"structuredContent":{"agentId":"agent-123"}}}
```

**Selected-tool validation or explicitly mapped application failure:**

```json
{"jsonrpc":"2.0","id":8,"result":{"resultType":"complete","content":[{"type":"text","text":"The permission name is invalid."}],"isError":true}}
```

**Unknown or authorization-concealed tool:**

```json
{"jsonrpc":"2.0","id":9,"error":{"code":-32602,"message":"Unknown or unavailable tool."}}
```

**Unexpected failure:**

```json
{"jsonrpc":"2.0","id":10,"error":{"code":-32603,"message":"Internal error."}}
```

**Progressive request-scoped SSE** (`Content-Type: text/event-stream`, progress token present):

```text
event: message
data: {"jsonrpc":"2.0","method":"notifications/progress","params":{"progressToken":"p-1","progress":1,"total":2,"message":"Resolving agent"}}

event: message
data: {"jsonrpc":"2.0","id":11,"result":{"resultType":"complete","content":[{"type":"text","text":"{\"agentId\":\"agent-123\"}"}],"structuredContent":{"agentId":"agent-123"}}}
```

Progress is monotonic status, never partial result content. Stream closure marks the request-scoped reporter
cancelled and suppresses further messages; tools stop cooperatively at safe checkpoints, without Common claiming
rollback of already-dispatched work. Correlation uses the JSON-RPC id, canonical tool name, and a private
consumer correlation/trace identity. Logs and traces must redact credentials, raw arguments, personal data,
paths, stack traces, and internal class or storage details from client-visible output.

**Decision owner:** John

**Exit condition:** Met by the failure classification, availability boundary, and representative wire shapes
above. Framework and consumer execution proves feasibility later; it is not required to close this planning
decision or to begin dependent development.
