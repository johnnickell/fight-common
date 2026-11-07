# Reusable execution introspection and MCP capability opportunities

## Question

Can Fight Common provide reusable MCP capabilities to record and inspect agent sessions/phases, with a dashboard
in a consuming project? What should be learned from the older SoftwareFactory, qualified against fight-agent-os's
current plans, and which additional MCP features are worth supporting?

**Inspection:** 2026-10-20. Research and recommendations, not accepted package scope or runtime qualification.
**Accepted constraint from John:** retain CQRS implementations. Paginate every large query where practical;
provide summary statistics first and allow bounded paginated drill-down into details. This constrains the design
without approving the proposed Tool catalog, persistence adapter or additional protocol features.
Owner: [Reusable execution introspection and MCP evolution](../mcp-execution-introspection-map.md).

**Subsequent decisions:** [WF-046](../tickets/WF-046-choose-execution-introspection-outcomes-and-ownership.md)
now approves Common-owned reusable CQRS implementation, persistence adapter and MCP presentation.
[WF-047](../tickets/WF-047-define-safe-observation-recording-and-query-guarantees.md) records John's desired
Factory-style browsing of captured messages, tool arguments/results and process output behind separate permissions.
The safe-ledger exclusions below are not a blanket ban on protected rich-detail browsing. Reasoning and complete
output capture are not requirements. John owns pending Agent OS planning alignment; citations below describe the
inspected consumer baseline, not a claim that it already approves this addition. These linked decisions supersede
candidate-only ownership recommendations in this initial research. WF-047 also selects stateless live offset/limit
Queries (`page`/`per_page` in the UI), accepting changing pages/totals between requests. This supersedes the cursor
and frozen-result recommendations below; those remain historical research proposals, not Common requirements.
Consumer UI navigation, retention/cleanup and archive workflows are excluded from the Common capability.

## Short answer

Yes: reuse a bounded observation/query contract and expose authorized execution views through existing MCP Tools
and Resources. Keep Commands and Queries as the implementation boundary: MCP and dashboard adapters dispatch the
same application use cases, not direct storage access or parallel business logic. MCP is an access surface, not
the database, collector, Workflow authority or dashboard. Capture
facts at trusted runtime boundaries; do not depend on the model remembering to call a recording Tool.

Keep Workflow/session/phase policy, permissions, storage operations, retention and UI in consumers. Common can own
policy-neutral mechanics where the first consumer demonstrates an actual reusable contract. A generic persistence
port and optional adapter are candidates, not a decision to add another event store or telemetry platform.

Do **not** add MCP Logging as the foundation. Common targets `2026-07-28`; that revision deprecates Logging,
Sampling and Roots. The official migration direction for structured observability is OpenTelemetry. Additional
features worth investigating are Prompts, Resource templates, subscriptions, and the Tasks extension; MCP Apps is
an optional later host UI integration. Supporting Tools/Resources/Skills does not establish support for all these
features or for a particular Pi client. [S1][S2][S3]

## Evidence identity and limits

- Fight Common inspected at `c4b52969686fd6581868d262e8ec8d4e3e0389a6`; clean working tree before this planning work.
- fight-agent-os inspected read-only at `c0a4b9dd7055efc2d1f882a10078689c3b319b77`; clean working tree. Its cited
  records are planning authority, not claims that the planned execution/history/log products are implemented.
- The requested `~/Projects/Fight/SoftwareFactory` does not exist. The nearby
  `~/Projects/Fight/fight-software-factory` at `5ba0fc002084607e267c98880902d497e6a858e3` contains a relevant Fight
  observatory design, Python tracer and Vue dashboard. It is the explicitly labelled historical example here,
  not a silently confirmed path substitution. `~/Projects/DirectMeds/SoftwareFactory` also exists; only its README
  and file inventory were used to distinguish the candidates, not to import another application's policy.
- No private trace databases, session transcripts, credentials or live dashboards were opened. No consumers,
  provider installations or runtime configuration were modified. No MCP/client conformance journey was executed.
- Protocol pages were retrieved from the official site on the inspection date, using the explicit `2026-07-28`
  core revision. Extension overview pages may evolve independently. Common's existing pinned upstream schema was
  also inspected. OpenTelemetry conventions were inspected at the commit in S11, not treated as stable contracts.

## 1. What the earlier Factory demonstrates

The historical implementation has a practical split: Python producers record activity into a local SQLite WAL
store; the Vue dashboard queries sessions, phases and events, with separate evidence/usage views. Its tracer has
`sessions`, `phases`, `events`, `envelopes`, `gate_results`, `processes`, and `agent_sessions` tables. Phase parentage,
native session identity and requested/configured/reported model distinctions help explain delegated work. The UI
loads bounded event windows and selected-phase detail instead of requiring one complete in-memory transcript.
[S4][S5]

**Useful patterns to retain:**

- Stable execution, phase-attempt, conversation and operation identities; explicit parent/child relationships.
- Current summary first, phase/activity drill-down second, evidence and measured usage separately.
- Historical pagination and live refresh use the same query ownership, not a second truth store.
- Missing child completion, missing start times and unavailable usage remain unknown. Parent exit is not proof
  that all children succeeded; process success is not independent review acceptance.
- Tokens spent, current context occupancy, provider/account quota and estimated cost are different measurements.
  Preserve measurement source, timestamp, units and requested versus actually reported model.
- Evidence links identify the exact work subject. A dashboard rendering an old verdict cannot certify a new head.

**Do not transplant its promises or privacy model:** the old reference describes files as rebuildable truth, but
`Tracer.event()` writes JSONL and then SQLite separately. This inspection establishes neither atomic dual-write
nor complete reconstruction of every side table. Old capture/prompt/output visibility is also broader than Agent
OS permits. Raw JSONL mirroring, direct SQLite inspection by Agents, `.runs` runtime dependence, old approval rules
and its Vue UI are not automatically suitable Common features or Agent OS requirements. This is design sampling,
not a correctness audit of that project. [S4][S5]

## 2. The first consumer already has three distinct owners

| Kind of information | Agent OS owner | Consequence for a reusable MCP surface |
|---|---|---|
| Authoritative execution state and evidence | WF-017; TICKET-00032/00094 and TICKET-00096 | Only owning semantic commands advance state. Queries may expose a permission-filtered projection with provenance/currentness; telemetry cannot approve a phase or Review. |
| Sanitized activity and measured usage | TICKET-00096, with usage owner TICKET-00098 | A separate observation ledger tolerates duplicates, delay, missing/reordered facts and purge. Record safe metadata and references, not transcripts. |
| Raw operational diagnosis | EPIC-00019 and its linked log decisions | Private operational logs, proposed Loki search and independently verified archives have separate capture, retention and access policy. No direct Agent log access is approved. |

The sessions Dashboard already has an accepted compact list and separately loaded **Summary / Changes / Activity /
Evidence** sections. Phase and Agent timelines belong in Activity, not every list row. It uses stable server-side
search/pagination and honest stale/partial/unavailable states. Reconnecting clients reload durable state before
live notifications. MCP Tools should reuse those application queries rather than create a competing history API,
status model, permission scheme or dashboard. [S6][S7]

TICKET-00096 excludes raw prompts, assistant/model responses, compiled instructions, thinking, native JSONL and
transcript excerpts from execution history. EPIC-00019's raw log plan instead preserves emitted diagnostic content
without pipeline redaction, with producer secret avoidance and privileged human access. **These are different data
classes, not conflicting instructions to merge into one universal log endpoint.** General metrics dashboards and
distributed tracing are deferred there; recommending an OpenTelemetry-compatible seam does not amend that scope
or authorize installing tracing infrastructure. [S6][S8]

## 3. Proposed reusable contract, not an approved API

### Separate capture from access

```text
Trusted Harness / runner / application boundary
    -> observation Command -> safe admission -> consumer-owned observation storage

Owning Workflow Commands -> authoritative events -> existing state/evidence projections

MCP Tools / Resources and native dashboard adapters
    -> shared authorized application Queries
       -> summary statistics / bounded execution detail
       -> paginated execution / phase / activity / evidence drill-downs

Optional instrumentation -> OpenTelemetry exporter -> separately chosen operational backend
```

Application runtime should not call its own public MCP endpoint just to record a local observation. Trusted
instrumentation dispatches the owning Command through the established application pipeline. Query handlers own
read coordination; repositories/read-model adapters stay behind their contracts. Remote producers may later need an authenticated
bounded ingestion adapter, possibly a Tool, but this is separate from the model-facing query surface. Reads may
produce ordinary access diagnostics but must not start execution, replay work or repair projections as a side effect.

**Illustrative ordinary Tool names, not MCP-standard methods or committed public names:**

| Candidate | Use case and boundary |
|---|---|
| `execution_context_get` | Return the caller's authorized execution/phase association and provenance when one exists; no fabricated association for an unmanaged session and no authority from a supplied ID. |
| `execution_summary_get` | Return authorized summary statistics for the selected scope/filter with an as-of point and known/partial/unavailable coverage, without requiring clients to enumerate detail pages. |
| `execution_list` | Search permitted executions by safe metadata, with bounded filters, opaque stable cursors and explicit freshness. |
| `execution_get` | Inspect one execution's source-authoritative summary, meaningful outcome, known active phase, evidence references and next human action. |
| `execution_phases_list` | Inspect phase attempts/parentage, interruptions and known boundaries without importing transcripts. |
| `execution_activity_list` | Page bounded sanitized observations for a selected execution/phase, distinguishing observations from authoritative transitions. |
| `execution_usage_get` | Read source-attributed measured usage/time; unknown is not zero and cumulative reports are not summed twice. |
| `execution_observations_append` — optional, separately gated | Admit bounded observations from a trusted producer with idempotency and provenance checks; never a generic status setter, self-approval or authority to write another execution. |

Tool outputs can include exact Resource links to authorized summaries or evidence. Link possession is not a read
grant. Start with existing exact Resource reads; dynamic Resource templates are useful only if a real discovery
journey requires them. Do not list every historical event as a Resource or invent URI-to-filesystem access.

### Accepted CQRS and summary-first pagination constraint

John confirmed this constraint during the research: **stick with CQRS implementations; paginate every big query
where possible; show summary statistics, then let callers drill into details in pages.**

- Existing `CommandBus::execute()` and `QueryBus::fetch()` are the composition path, not a second MCP-only bus.
  Command handlers own observation writes or other explicit mutations; Query handlers own read-only views.
- Start with a bounded, authorized summary Query for a repository/execution/filter. Counts, known usage and
  timing aggregates are independently computed/projected, not totals of only the current visible page. Expose
  their scope, provenance and freshness; an unavailable exact count is not zero or a fabricated estimate.
- Large execution, phase, activity and evidence collections use bounded pages. Drill-down keeps the selected
  scope/filter and rechecks current access. A single-execution result must not embed unbounded child collections.
- Prefer opaque stable cursors with deterministic ordering/tie-breaks for changing history. A new filter or explicit
  refresh starts a new result. Cursor/query identity binds the relevant scope; cursors convey no authority.
  Summary and detail must either share a snapshot/as-of basis or disclose differing freshness.
- Bounded singleton reads need no artificial pagination. Large artifact bytes use separately authorized bounded
  reads/downloads rather than disguising a whole blob as one unlimited detail row. Any necessary non-paginated
  large-query exception needs a named use case and explicit bounds.
- MCP Resources remain exact bounded representations; parameterized pagination is naturally exposed by Query-backed
  Tools. Do not claim the existing generic `resources/read` method adds pagination parameters it does not support.
- Summary projections, indexes and exact aggregate performance remain implementation decisions. The accepted
  constraint does not require a new analytics backend or promise inexpensive exact counts over arbitrary history.

### What belongs where

**Common candidates:** finite versioned observation envelopes; safe scalar context/provenance mechanics;
correlation/lifecycle instrumentation seams; bounded query/result/pagination mechanics; optional MCP presentation
and OpenTelemetry adapters. Prove concrete shared behavior before introducing interfaces or generic wrappers.
A reusable store contract/DBAL adapter is an option only if its guarantees stand independently of Agent OS policy.
Common's existing event-sourcing infrastructure is not automatically a suitable observation ledger. [S9]

**Consumer ownership:** the meaning and lifecycle of Workflow/phase/session; trustworthy producer enrollment and
caller resolution; allowed fields and sensitivity; permissions and cross-project scope; retention/holds/deletion;
persistence selection and migrations; queue/outbox/loss policy; usage accounting policy; browser UI and runtime
operations. Agent OS should compose its planned PostgreSQL ledger and projections, not synchronize a new Common
session database with a second Workflow truth. [S6][S8][S9]

**Candidate record information:** schema version; observation/source identity and available source sequence;
trusted producer identity; consumer-owned execution/phase/process/conversation references; kind; available producer
and server receipt timestamps; bounded safe summary/outcome; optional duration with clock basis; optional trace/span
correlation; separately authorized artifact/evidence references. Every optional association needs a real source.
MCP request ID, transport subscription ID, Workflow ID, Pi conversation ID and trace ID are not interchangeable.
The current stateless HTTP profile does not need an invented MCP transport session to represent an agent session.

Before promising durable ingestion, decide exact duplicate/retry conflict handling, acknowledgment meaning, size
limits, transaction boundaries, storage exhaustion and diagnostic failure. For optional observations, visible gaps
may be acceptable; mandatory audit or authoritative event failure must keep its owner's stronger semantics. Do not
claim exactly-once delivery, automatic recovery, complete capture or durable storage from an HTTP 200 alone.

### Instrumentation is not an LLM task

Trusted hooks/bus/Tool boundaries can observe start, finish, duration and safe outcome without an extra model call.
Model-authored notes, if later desired, must be labelled assertions rather than measured runtime facts. Sanitization
happens before the safe ledger/export boundary, not merely when rendering the dashboard. Arbitrary arguments,
results, shell strings, paths, SQL, prompts and exception bodies are not safe by default. Render retrieved material
as untrusted data; it cannot supply new tool instructions or permission grants.

Existing Common `McpToolMetadataFilter` supplies a fresh `fight.mcp/correlation`, canonical Tool name and explicitly
allowlisted scalar consumer metadata inside command/query invocation. `McpRequestMetadata` validates trace context.
Neither records lifecycle observations, persists history, instruments a complete trace nor establishes producer
identity. Reuse these seams where they fit; do not advertise them as an observability implementation. [S9]

OpenTelemetry already offers MCP client/server operation and GenAI agent conventions. The inspected conventions
are **Development**, include content-bearing opt-ins, and still contain older MCP method/session examples. Use
versioned adapter mappings with content capture disabled, not a public Common schema frozen to unstable attribute
names or obsolete wire methods. A full OTel stack is not needed to ship bounded execution introspection. [S11]

## 4. MCP feature inventory and recommendation

The inspected Common source contains Tool registration/list/call, structured output and Resource links, exact
Resource list/read, immutable Skill revisions and `skills/list`/`skills/get`, guarded stateless HTTP, opt-in
request-scoped progress/SSE and cooperative disconnect cancellation, ordinary form input and atomic destructive
confirmation. Skills is an **official extension over Resources**, not a third core primitive replacing Prompts.
The primitive trio is Tools, Resources and Prompts. These are source/documentation findings, not a release or
installed-consumer qualification. [S1][S9]

The capability registry recognizes Prompt and Completion families, but that is not a built-in Prompt catalog or
Completion service. It explicitly rejects subscription/list-change flags set to true. General extension merging
is likewise not implementation of every extension. [S9]

| Feature | Fit / recommendation | Existing boundary or additional work |
|---|---|---|
| Prompts (`prompts/list`, `prompts/get`) | Good small candidate for deliberate user-selected diagnosis/inspection recipes. Distinct from full Skill directories. | Need an actual consumer-controlled template use case, availability, bounded arguments/results and client presentation proof; not an instruction-authority bypass. [S12] |
| Resource templates + argument Completion | Consider for navigable execution/phase queries and discoverable IDs. Exact Resource links may already suffice. | Templates are currently empty; Completion is suggestions for prompt/template arguments, not model text generation. Avoid leaking concealed IDs through suggestions. [S13] |
| Subscriptions / list and Resource change notifications | Strong candidate when clients need live introspection or refreshed catalogs. | `2026-07-28` uses `subscriptions/listen`, not the old `resources/subscribe`/GET recipe. Requires acknowledgment/filter semantics, revocation, bounded delivery, disconnect handling and durable-query reload; notifications are not storage or Workflow truth. Agent OS Mercure remains its browser owner. [S14] |
| Tasks extension | Useful for genuinely long-running Tool operations that must survive a client disconnect. | Current extension uses `io.modelcontextprotocol/tasks`, durable handles and `tasks/get`, `tasks/update`, `tasks/cancel`; do not copy the older experimental core API. Need durable ownership, TTL, result access, idempotency, recovery and actual client support. An MCP task is not a planning TASK or an entire Workflow. [S15] |
| MCP Apps extension | Optional later embedded visualization within a supporting MCP host. | Tool-linked UI Resources, sandbox/CSP, host bridge and permissions need their own contract. Resources support alone does not implement Apps; do not replace the consumer dashboard or assume Pi rendering. [S16] |
| Elicitation | Build on existing ordinary form and destructive-confirmation support. | URL-mode/out-of-band interaction remains a separate need/authorization/security decision, not required for read-only introspection. [S9] |
| HTTP authorization / protected-resource discovery | Important for private or remote introspection, independent of feature breadth. | Reuse Common WF-043's approved policy-free kit boundary and Agent OS's actual principal/Permission owners. Existing guarded MCP HTTP is not a full OAuth server/resource-server implementation. Client credentials/enterprise extensions only for demonstrated client requirements. DCR is deprecated in this revision. [S10][S17][S3] |
| Logging, Roots, Sampling | **Do not add for a new feature just for completeness.** | Deprecated as of `2026-07-28`; retained compatibility is different from recommending new support. Logging notifications are request-scoped, not a durable session-history feed. [S2][S3] |
| stdio / other content forms / optional Skill directory read | Demand-driven, not prerequisites for this dashboard. | Qualify a concrete client journey before expanding transport, rendering, bundle or execution promises. Current Skills list/get and exact file reads already cover the approved baseline. [S1][S9] |

**Recommended order, pending human scope choice:**

1. Prove authorized execution summary and selected-phase activity reads with existing Tools/Resources and trusted
   producer capture. Separate what Common supplies from the already planned Agent OS ledger and Dashboard.
2. Choose the smallest additional feature with a named user journey: Prompts for explicit diagnostic entry, or
   subscriptions if live updates are actually required. Neither is a prerequisite for basic durable inspection.
3. Evaluate Tasks for a real long-running operation; defer MCP Apps until a supported host requires an embedded UI.
4. Keep auth/disclosure and real consumer qualification as part of the selected outcome. Do not fund features
   solely to fill a protocol checklist, import a deprecated capability, or claim all clients share extension support.

## 5. Proposed acceptance evidence for a later scoped slice

- One real producer emits safe lifecycle observations correlated to a real authorized execution/phase; a consumer
  reads the same persisted facts through its application query and MCP surface, without a second state authority.
- Retry/duplicate/reordered/missing observations and interrupted capture preserve truthful unknown/partial states;
  server-reported identity cannot be forged by model input. Scope changes/revocation apply to every read and link.
- Verify one CQRS read/write implementation through both entry adapters. Summary statistics cover the authorized
  selected result, not just a displayed page; large child collections remain paginated. Prove stable traversal,
  tie handling, changed filters, concurrent additions, revocation and summary/detail freshness without omitted or
  duplicated records within the promised snapshot semantics.
- Bound pages, payloads, slow consumers, storage failures and returned evidence; distinguish recorded, persisted,
  projected and visible facts. Test retention/purge effects through both native and MCP query paths.
- Show a consumer-native dashboard query integration, not an embedded Common UI or a fake telemetry demo. Preserve
  approved Agent OS Summary/Changes/Activity/Evidence ownership and no raw Agent log access.
- Qualify the actual client/protocol/extension combination. Existing package tests and another host's extension
  matrix do not qualify Pi, a deployed proxy, storage recovery or the complete consuming application.

## Sources

Local Common paths below are checkout-owned. External project citations use inspected commits to preserve the
boundary between historical example, current consumer planning and proposed Common scope.

- **S1:** [Common MCP documentation](../../../docs/components/mcp/index.md),
  [Skills contract](../../../docs/components/mcp/skill-revisions.md), and
  [official Skills overview](https://modelcontextprotocol.io/extensions/skills/overview).
- **S2:** [MCP 2026-07-28 Logging](https://modelcontextprotocol.io/specification/2026-07-28/server/utilities/logging).
- **S3:** [MCP 2026-07-28 deprecated features](https://modelcontextprotocol.io/specification/2026-07-28/deprecated).
- **S4:** [Historical Factory context](https://github.com/johnnickell/fight-software-factory/blob/5ba0fc002084607e267c98880902d497e6a858e3/CONTEXT.md),
  [observability reference](https://github.com/johnnickell/fight-software-factory/blob/5ba0fc002084607e267c98880902d497e6a858e3/.claude/skills/sssf/references/observability.md).
- **S5:** [Factory tracer](https://github.com/johnnickell/fight-software-factory/blob/5ba0fc002084607e267c98880902d497e6a858e3/.claude/skills/sssf/templates/adws/adw_modules/tracer.py),
  [dashboard query adapter](https://github.com/johnnickell/fight-software-factory/blob/5ba0fc002084607e267c98880902d497e6a858e3/.claude/skills/sssf/apps/visualizer/server/db.ts),
  [session/phase view](https://github.com/johnnickell/fight-software-factory/blob/5ba0fc002084607e267c98880902d497e6a858e3/.claude/skills/sssf/apps/visualizer/src/components/SessionTrace.vue).
- **S6:** [Agent OS WF-017](https://github.com/johnnickell/fight-agent-os/blob/c0a4b9dd7055efc2d1f882a10078689c3b319b77/planning/wayfinder/tickets/WF-017-define-execution-history-and-event-authority.md),
  [TICKET-00096 history/activity](https://github.com/johnnickell/fight-agent-os/blob/c0a4b9dd7055efc2d1f882a10078689c3b319b77/planning/tickets/00096-TICKET.md).
- **S7:** [Agent OS TICKET-00097 Dashboard](https://github.com/johnnickell/fight-agent-os/blob/c0a4b9dd7055efc2d1f882a10078689c3b319b77/planning/tickets/00097-TICKET.md).
- **S8:** [Agent OS EPIC-00019 operational diagnosis](https://github.com/johnnickell/fight-agent-os/blob/c0a4b9dd7055efc2d1f882a10078689c3b319b77/planning/epics/00019-EPIC.md).
- **S9:** [Common capability registry](../../../src/Application/Mcp/McpCapabilityRegistry.php),
  [metadata filter](../../../src/Application/Mcp/Tool/Messaging/McpToolMetadataFilter.php),
  [request metadata](../../../src/Application/Mcp/McpRequestMetadata.php),
  [ownership standard](../../../docs/engineering/standards/Architecture.md#package-and-consumer-ownership).
- **S10:** [Common WF-043 resource-server boundary](../tickets/WF-043-define-reusable-oauth-resource-server-support.md),
  [Agent OS TICKET-00061 Agent authority](https://github.com/johnnickell/fight-agent-os/blob/c0a4b9dd7055efc2d1f882a10078689c3b319b77/planning/tickets/00061-TICKET.md).
- **S11:** [OpenTelemetry MCP conventions](https://github.com/open-telemetry/semantic-conventions-genai/blob/547934afa90a34298441caf80c43b27255c3a292/docs/gen-ai/mcp.md),
  [Agent spans](https://github.com/open-telemetry/semantic-conventions-genai/blob/547934afa90a34298441caf80c43b27255c3a292/docs/gen-ai/gen-ai-agent-spans.md).
- **S12:** [MCP Prompts](https://modelcontextprotocol.io/specification/2026-07-28/server/prompts).
- **S13:** [MCP Completion](https://modelcontextprotocol.io/specification/2026-07-28/server/utilities/completion),
  [Resources](https://modelcontextprotocol.io/specification/2026-07-28/server/resources).
- **S14:** [MCP subscriptions](https://modelcontextprotocol.io/specification/2026-07-28/basic/patterns/subscriptions).
- **S15:** [Tasks overview](https://modelcontextprotocol.io/extensions/tasks/overview),
  [official Tasks repository](https://github.com/modelcontextprotocol/ext-tasks) (README lists `2026-07-28` stable schema).
- **S16:** [MCP Apps](https://modelcontextprotocol.io/extensions/apps/overview),
  [official client support matrix](https://modelcontextprotocol.io/extensions/client-matrix).
- **S17:** [MCP HTTP authorization](https://modelcontextprotocol.io/specification/2026-07-28/basic/authorization),
  [official extension inventory](https://modelcontextprotocol.io/extensions/overview).

## Open questions and planning handoff

1. [WF-046 — Choose reusable execution introspection outcomes and ownership](../tickets/WF-046-choose-execution-introspection-outcomes-and-ownership.md):
   should Common ship reusable observation mechanics plus MCP query presentation, or only protocol primitives
   while the first consumer proves the domain shape? Which caller and first end-to-end question matter most?
2. [WF-047 — Define safe observation recording and query guarantees](../tickets/WF-047-define-safe-observation-recording-and-query-guarantees.md):
   establish the actual minimum durable contract, producer trust, safe fields, failure semantics and store boundary.
3. [WF-048 — Select additional MCP capabilities from consumer journeys](../tickets/WF-048-select-additional-mcp-capabilities-from-consumer-journeys.md):
   select/defer features against accepted journeys and actual clients, not checklist completeness.

No EPIC/TICKET/TASK, source implementation, consumer amendment, runtime enrollment, commit or publication is
created or authorized by this research. The old Factory path identity remains an explicit confirmation question;
its design is supporting evidence, not current Agent OS authority.
