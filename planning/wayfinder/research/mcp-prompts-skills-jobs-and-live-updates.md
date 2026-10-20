# MCP roles, repeatable workflows, structured data and live jobs

## Question

For WF-048, can Prompts serve role/persona instructions alongside Skills? Do Resource templates shape data for
persistence? Can subscriptions reuse Mercure, Tasks support cron-polled jobs, and URL-mode input handle OAuth or
Agent web ingestion?

## Short answer

Prompts fit selectable role-guided task instructions; Skills fit reusable procedures and supporting files.
Neither assigns authoritative Agent identity, grants permissions or guarantees model behavior. Resource templates
are address patterns, not output schemas. Tool schemas and application validation supply structured data contracts.
Subscriptions can share application events with Mercure through separate protocol adapters. Tasks can represent
asynchronous jobs, but do not supply their scheduler/worker. URL-mode input is a human interaction, including
third-party OAuth, not a way to send an Agent to read a web page.

This is evidence and recommended composition, not approval of additional package features or consumer changes.
The initial blanket-deferral recommendation in WF-048 has not been accepted; John has identified candidate uses.

**Subsequent decision:** John accepted these distinctions and selected generic Prompts, Subscriptions and Tasks as
the leading implementation-planning candidates in [WF-048](../tickets/WF-048-select-additional-mcp-capabilities-from-consumer-journeys.md).
John subsequently approved deferring Apps, URL-mode input, Resource templates and Completion from the initial
scope. The research questions below are the investigation record; WF-048 owns the current selection and remaining
scope decisions.

## Findings

### 1. Prompts and Skills can complement each other

MCP Prompts expose discoverable parameterized messages through `prompts/list` and `prompts/get`. They are designed
for user-selected invocation, although the protocol does not mandate a particular UI. Prompt message roles in the
2026-07-28 schema are `user` and `assistant`, not a remotely assigned system/developer role. A prompt can contain
“Act as a reviewer” instructions, but does not assign an Agent role, install its permissions or become authoritative
runtime configuration. The host decides how returned messages enter model context. [S1]

Skills expose workflow instructions and supporting files with discovery and retrieval. The host owns skill loading;
reading a skill Resource or receiving a prompt that mentions it does not automatically activate it. [S2]

Recommended consumer composition:

- Runtime/persona configuration: persistent Agent identity, baseline role instructions and available permissions.
- Prompt: a parameterized entry point such as “Review task X as a standards reviewer.”
- Skill: the reusable review procedure, references and examples loaded through the host's skill-loading path.
- Tools: fetch evidence or submit explicitly authorized structured findings through application Queries/Commands.

Avoid two independent copies of the same role/skill instructions. A prompt can reference a chosen skill revision,
or both can use the consumer's canonical instruction source. Versioned instructions improve repeatability of the
procedure, not deterministic model output. Concrete composition and revision selection are not yet approved.
Common would supply reusable prompt-serving mechanics; consumer persona text and Workflow meaning remain outside it.

### 2. Three different meanings of “template”

- A **Prompt template** produces messages from arguments.
- An MCP **Resource template** describes a parameterized URI, for example `execution://sessions/{session_id}`.
  It helps clients locate/read a resource; it is not a database-row or response-format template. [S3]
- **Tool input/output schemas** describe accepted arguments and returned structured JSON. Completion supplies
  argument suggestions for Prompts/Resource templates, not LLM text generation or a data mapper. [S4][S5]

For Agent-produced structured findings, define Tool input fields, validate them, translate them into a consumer
Command and let its handler persist the result. For structured reads, return Query results matching the Tool output
schema. Common already validates declared Tool input/output schemas within its documented supported subset; this
use case does not require Resource-template support. No generic direct mapping from arbitrary model output to
trusted database writes is implied. [S6]

A prompt/skill can explain the expected fields and include examples. It cannot enforce them. Also distinguish MCP
`structuredContent` (server-produced Tool result data) from a model provider's schema-constrained text generation;
the former does not configure or guarantee the latter. [S5]

### 3. Subscriptions and Mercure can share changes, not wire protocols

For the target revision, `subscriptions/listen` establishes an MCP notification stream with explicit filters and
an acknowledgment. Resource updates and catalog changes use MCP messages; a Mercure stream is not automatically
an MCP subscription merely because both can use SSE. [S7]

A reasonable integration is one committed application change feeding two outputs:

```text
Committed application change
  -> private Mercure publication -> browser
  -> MCP notification adapter    -> MCP client -> authorized Query/read
```

This diagram is a proposal, not an implemented bridge. Mercure could also be an internal event-distribution backend
for an MCP adapter, but translation, current authorization, disconnect handling and protocol qualification would
still be needed. A Mercure requirement should not be imposed on every Common consumer.

Common already contains public and private Mercure publisher adapters. Agent OS TASK-00389 plans protected,
best-effort post-commit Mercure publication with authorized durable reloads; it is planning evidence, not proof of
a live installation. Notifications can say that data changed; neither channel has to broadcast protected bodies.
Lost notifications do not change stored truth or confer Workflow authority. [S8][S9]

### 4. Tasks fit asynchronous jobs; execution remains separately owned

The Tasks extension lets a request return a durable handle. `tasks/get` reports status and, on termination, the
result/error; polling is the default. `tasks/update` supplies requested mid-flight input, not an arbitrary public
setter for job status. Cancellation is cooperative. Polling can survive client disconnects within the handle's
retention/TTL; support must be negotiated by both sides. [S10]

Two cron patterns are possible:

- A consumer's cron/worker runs or checks its job and records current state; MCP Tasks presents that state.
- An authorized scheduled MCP client polls a returned handle and reacts to completion.

Tasks itself is neither a cron scheduler nor a job executor. External job state and its MCP handle need an explicit
mapping; an MCP Task is not automatically a whole Workflow, a planning TASK or independent-review approval.
Common can supply reusable protocol/lifecycle mechanics if selected; consumer scheduling and execution policy
remain consumer-owned. A long-running operation must be named before choosing the initial implementation scope.

#### Released-contract check during TICKET-00049 decomposition

The [2026-07-28 specification](https://github.com/modelcontextprotocol/ext-tasks/blob/main/specification/2026-07-28/tasks.md)
and [schema](https://github.com/modelcontextprotocol/ext-tasks/blob/main/schema/2026-07-28/schema.ts) were read directly
while proposing that TASK split. They define one empty extension capability object, not separate polling/input/cancel
flags. Cancellation is cooperative but requires acknowledgment. Creation is a flat resultType:task result; get is
resultType:complete plus detailed state. Missing per-request support uses -32021; get/update/cancel HTTP routing uses
Mcp-Name matching taskId. completed includes Tool isError:true, whereas failed is reserved for JSON-RPC execution errors.
TTL is duration from creation in integer milliseconds or null, not a sliding inactivity timeout. The schema, rather than
isolated prose referring to an embedded task, establishes the flat creation shape.

John approved lifecycle-first implementation with input/cancel suppliers and then complete protocol activation; see
[TICKET-00049](../../tickets/00049-TICKET.md#decisions-and-progress). That sequencing is a project decision drawn from
these protocol obligations, not a claim that Common or deployed clients already implement them.

### 5. Apps require a host that can display them

MCP Apps can embed an interactive view in a compatible host, with UI Resources, sandboxing and a permissioned
bridge. They do not replace the native dashboard and are not supported merely because Resource reads exist.
A selected host and actual embedded-view journey are still missing. [S11]

### 6. URL-mode input is for humans, including third-party authorization

URL-mode elicitation can direct a user to a separate page to connect an external account, provide sensitive
information or complete a payment. It can initiate third-party OAuth for a service the MCP server calls. It is
explicitly **not** the OAuth mechanism authenticating the MCP client to the MCP server. Third-party credentials
must stay out of model/client messages; the consumer owns the external account/token lifecycle. [S12]

The client must not automatically prefetch the URL or open it without user consent, and the user interaction must
not expose its content/inputs to the client or LLM. An `accept` response signals consent, not proof that the
external flow completed; the server must verify completion. Consequently URL elicitation is the wrong mechanism
for asking an Agent to ingest web data. [S12]

Use ordinary search and separately authorized page-retrieval Tools for web research. Agent OS TICKET-00095 already
plans that split, including bounded responses, SSRF protection, protected provider credentials and its required
content-screening policy. These are consumer requirements, not new obligations silently assigned to Common.
Retrieved pages remain untrusted data, not instructions or permission grants. [S13]

## Open questions for WF-048

- Select role-guided Prompt entry points as a package destination, versus merely serving persistent persona files.
- Choose the first concrete Prompt and its Skill relationship; confirm the target host can expose/load both.
- Separate the structured-Tool-data use case from actual need for Resource templates or argument suggestions.
- Choose which MCP client needs live changes and whether a shared event source is sufficient without a Mercure bridge.
- Name the long-running operation and who runs/polls it before selecting a Tasks implementation.
- Identify an embedded-view host and a third-party account-connect use case before selecting Apps or URL mode.

## Sources

Protocol findings target MCP **2026-07-28** and the cited official extensions. Official Elicitation, Resources and
Tools documents were fetched for this follow-up; Prompts, Skills, Tasks, subscriptions and Completion were checked
against the retained primary-source copies from the initial investigation. No target MCP client was qualified.

- **S1:** [MCP Prompts](https://modelcontextprotocol.io/specification/2026-07-28/server/prompts),
  [PromptMessage schema](https://modelcontextprotocol.io/specification/2026-07-28/schema#promptmessage).
- **S2:** [Skills extension](https://modelcontextprotocol.io/extensions/skills/overview).
- **S3:** [Resource templates](https://modelcontextprotocol.io/specification/2026-07-28/server/resources#resource-templates).
- **S4:** [Completion](https://modelcontextprotocol.io/specification/2026-07-28/server/utilities/completion).
- **S5:** [Tool structured content and output schemas](https://modelcontextprotocol.io/specification/2026-07-28/server/tools#structured-content).
- **S6:** [Common MCP documentation](../../../docs/components/mcp/index.md#schema-declarations-and-semantic-output),
  [Tool invoker](../../../src/Application/Mcp/Tool/McpToolInvoker.php).
- **S7:** [MCP subscriptions](https://modelcontextprotocol.io/specification/2026-07-28/basic/patterns/subscriptions).
- **S8:** [Mercure publisher](../../../src/Adapter/Socket/MercureHubPublisher.php),
  [private Mercure publisher](../../../src/Adapter/Socket/PrivateMercureHubPublisher.php).
- **S9:** [Agent OS TASK-00389](https://github.com/johnnickell/fight-agent-os/blob/15aada06ee7726f29dcdcd7bb8ac9b2a71ba6d41/planning/tasks/00389-TASK.md),
  inspected read-only with no working-tree change to that file.
- **S10:** [Tasks overview](https://modelcontextprotocol.io/extensions/tasks/overview),
  [Tasks specification repository](https://github.com/modelcontextprotocol/ext-tasks).
- **S11:** [MCP Apps](https://modelcontextprotocol.io/extensions/apps/overview).
- **S12:** [URL-mode elicitation](https://modelcontextprotocol.io/specification/2026-07-28/client/elicitation#url-mode-elicitation-requests),
  [OAuth distinction](https://modelcontextprotocol.io/specification/2026-07-28/client/elicitation#url-mode-elicitation-for-oauth-flows),
  [safe URL handling](https://modelcontextprotocol.io/specification/2026-07-28/client/elicitation#safe-url-handling).
- **S13:** [Agent OS TICKET-00095](https://github.com/johnnickell/fight-agent-os/blob/15aada06ee7726f29dcdcd7bb8ac9b2a71ba6d41/planning/tickets/00095-TICKET.md),
  inspected read-only with no working-tree change to that file.
