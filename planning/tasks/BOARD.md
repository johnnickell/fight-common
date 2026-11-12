# TASK Board

## Routing index

Read only current human decisions and active work below; otherwise the first ready TASK.
Use [lightweight routing](../CONVENTIONS.md#lightweight-work-routing) for validation and evidence limits.
[Wayfinder status and frontiers](../wayfinder/README.md) answer the decision-routing question.
Use the [Roadmap](../ROADMAP.md) and current handoffs for planning phases; do not infer full decomposition from closed maps.
Read the referenced current section, not the full historical record. Missing handoff/phase needs owner input.

Execution view for Fight Common. TASK records own status, dependencies, priority (`order`), and PR links.
Refresh generated rows with `./bin/planning-check --write`. A PR link does not assert merge or deployment.
[Shared handoffs](../agents/shared-workflows.md#handoffs) select the next stage from actual evidence. Implemented
TASKs awaiting independent Review/QA remain active, not done; historical terminal records retain their old meaning.

## What's next?

For `/ask-matt` or an unqualified "What's next?", report the current Human Action or Needs Info decision and the
active TASK. Otherwise return the first executable TASK in Ready Frontier. Do not select by ID alone.

<!-- planning:next -->
| Route | TASK | Title | Status | Blocked by |
|---|---|---|---|---|
| Active Work | [TASK-00212](00212-TASK.md) | Reduce Functional coverage to 106 critical executions for 1.5.1 | in-progress | — |
<!-- /planning:next -->

This summary and the [Roadmap execution handoff](../ROADMAP.md#current-task-handoff) derive from the same TASK
records as the sections below. Inspect each selected TASK's current handoff and independent evidence to choose
its workflow stage; metadata alone does not establish Review, QA or delivery. Do not add competing current-status
prose here. Historical completion and delivery evidence remain in the owning TASK records.

<!-- planning:board -->
## Active Work

| Order | TASK ID | Title | Parent TICKET | Status | Blocked by | PR |
|---|---|---|---|---|---|---|
| 1 | [TASK-00212](00212-TASK.md) | Reduce Functional coverage to 106 critical executions for 1.5.1 | — (standalone chore) | in-progress | — | — |

## Ready Frontier

| Order | TASK ID | Title | Parent TICKET | Status | Blocked by | PR |
|---|---|---|---|---|---|---|
| None | — | — | — | — | — | — |

## Waiting

| Order | TASK ID | Title | Parent TICKET | Status | Blocked by | PR |
|---|---|---|---|---|---|---|
| None | — | — | — | — | — | — |

## Needs Info

| Order | TASK ID | Title | Parent TICKET | Status | Blocked by | PR |
|---|---|---|---|---|---|---|
| None | — | — | — | — | — | — |

## Human Action

| Order | TASK ID | Title | Parent TICKET | Status | Blocked by | PR |
|---|---|---|---|---|---|---|
| None | — | — | — | — | — | — |

## Needs Triage

| Order | TASK ID | Title | Parent TICKET | Status | Blocked by | PR |
|---|---|---|---|---|---|---|
| None | — | — | — | — | — | — |

## Recently Closed

| Order | TASK ID | Title | Parent TICKET | Status | Blocked by | PR |
|---|---|---|---|---|---|---|
| 1 | [TASK-00103](00103-TASK.md) | Adopt the EPIC, TICKET, and TASK planning surface | — (standalone chore) | done | — | [PR #155](https://github.com/johnnickell/fight-common/pull/155) |
| 1 | [TASK-00104](00104-TASK.md) | Establish MCP protocol semantics and truthful capability discovery | [TICKET-00023 — Serve a Safe Stateless MCP Endpoint](../tickets/00023-TICKET.md) | done | — | [PR #157](https://github.com/johnnickell/fight-common/pull/157) |
| 1 | [TASK-00174](00174-TASK.md) | Refresh Common's PHP runtime and documentation trust stores | — (standalone bug) | done | — | [PR #223](https://github.com/johnnickell/fight-common/pull/223) |
| 1 | [TASK-00178](00178-TASK.md) | Separate MCP metadata grammar machinery | — (standalone chore) | done | — | [PR #258](https://github.com/johnnickell/fight-common/pull/258) |
| 1 | [TASK-00207](00207-TASK.md) | Resolve v1.5.0 pre-tag audit findings and complete release notes | — (standalone chore) | done | — | [PR #295](https://github.com/johnnickell/fight-common/pull/295) |
| 1 | [TASK-00208](00208-TASK.md) | Reconcile certifier image-preparation outcome validation | — (standalone bug) | done | — | [PR #297](https://github.com/johnnickell/fight-common/pull/297) |
| 1 | [TASK-00209](00209-TASK.md) | Normalize the release certification workspace to its physical path | — (standalone bug) | done | — | [PR #301](https://github.com/johnnickell/fight-common/pull/301) |
| 1 | [TASK-00210](00210-TASK.md) | Contain terminal Symfony safe-error rendering faults on 1.4 | — (standalone bug) | done | — | [PR #298](https://github.com/johnnickell/fight-common/pull/298) |
| 2 | [TASK-00105](00105-TASK.md) | Serve guarded stateless MCP requests through PSR HTTP | [TICKET-00023 — Serve a Safe Stateless MCP Endpoint](../tickets/00023-TICKET.md) | done | — | [PR #163](https://github.com/johnnickell/fight-common/pull/163) |
| 2 | [TASK-00179](00179-TASK.md) | Clarify MCP capability composition validation | — (standalone chore) | done | — | [PR #261](https://github.com/johnnickell/fight-common/pull/261) |
| 3 | [TASK-00106](00106-TASK.md) | Register and discover explicitly opted-in MCP Tools | [TICKET-00024 — Discover and Invoke Explicitly Opted-In CQRS Tools](../tickets/00024-TICKET.md) | done | — | [PR #165](https://github.com/johnnickell/fight-common/pull/165) |
| 3 | [TASK-00180](00180-TASK.md) | Organize MCP tests around observable contracts | — (standalone chore) | done | — | [PR #262](https://github.com/johnnickell/fight-common/pull/262) |
| 4 | [TASK-00107](00107-TASK.md) | Invoke validated CQRS MCP Tools with safe semantic output | [TICKET-00024 — Discover and Invoke Explicitly Opted-In CQRS Tools](../tickets/00024-TICKET.md) | done | — | [PR #166](https://github.com/johnnickell/fight-common/pull/166) |
| 4 | [TASK-00181](00181-TASK.md) | Classify MCP foundation namespaces with released-name compatibility | — (standalone chore) | done | — | [PR #264](https://github.com/johnnickell/fight-common/pull/264) |
| 5 | [TASK-00108](00108-TASK.md) | Orchestrate request-scoped MCP progress and cooperative cancellation | [TICKET-00025 — Stream Progress and Cancel a Tool Request Across Supported Compositions](../tickets/00025-TICKET.md) | done | — | [PR #167](https://github.com/johnnickell/fight-common/pull/167) |
| 5 | [TASK-00182](00182-TASK.md) | Separate validation construction from execution | — (standalone chore) | done | — | [PR #265](https://github.com/johnnickell/fight-common/pull/265) |
| 6 | [TASK-00109](00109-TASK.md) | Deliver Fight Common progressive MCP HTTP and SSE adapters | [TICKET-00025 — Stream Progress and Cancel a Tool Request Across Supported Compositions](../tickets/00025-TICKET.md) | done | — | [PR #168](https://github.com/johnnickell/fight-common/pull/168) |
| 6 | [TASK-00183](00183-TASK.md) | Codify aggregate-first architecture and descriptive naming rules | — (standalone chore) | done | — | [PR #267](https://github.com/johnnickell/fight-common/pull/267) |
| 7 | [TASK-00110](00110-TASK.md) | Qualify progressive MCP in every supported framework starter | [TICKET-00025 — Stream Progress and Cancel a Tool Request Across Supported Compositions](../tickets/00025-TICKET.md) | wontfix | — | [PR #169](https://github.com/johnnickell/fight-common/pull/169) |
| 7 | [TASK-00184](00184-TASK.md) | Introduce DBAL unit of work and separate execution transaction mechanics | — (standalone chore) | done | — | [PR #268](https://github.com/johnnickell/fight-common/pull/268) |
| 8 | [TASK-00111](00111-TASK.md) | Protect and resume ordinary MCP input_required interactions | [TICKET-00026 — Resume Protected input_required Interactions](../tickets/00026-TICKET.md) | done | — | [PR #171](https://github.com/johnnickell/fight-common/pull/171) |
| 8 | [TASK-00185](00185-TASK.md) | Separate Task repository persistence from transaction ownership | — (standalone chore) | done | — | [PR #269](https://github.com/johnnickell/fight-common/pull/269) |
| 9 | [TASK-00112](00112-TASK.md) | Atomically resume destructive MCP confirmations | [TICKET-00026 — Resume Protected input_required Interactions](../tickets/00026-TICKET.md) | done | — | [PR #172](https://github.com/johnnickell/fight-common/pull/172) |
| 9 | [TASK-00186](00186-TASK.md) | Separate Notice repository transactions and recovery reads | — (standalone chore) | done | — | [PR #270](https://github.com/johnnickell/fight-common/pull/270) |
| 10 | [TASK-00113](00113-TASK.md) | Compose policy-free OAuth resource-server protection for MCP | [TICKET-00027 — Protect MCP Endpoints with Reusable OAuth Resource-Server Support](../tickets/00027-TICKET.md) | done | — | [PR #174](https://github.com/johnnickell/fight-common/pull/174) |
| 10 | [TASK-00187](00187-TASK.md) | Restore Task use-case ownership to CQRS handlers | — (standalone chore) | done | — | [PR #271](https://github.com/johnnickell/fight-common/pull/271) |
| 11 | [TASK-00121](00121-TASK.md) | Bound MCP Request Reading and Decoding | [TICKET-00030 — Compose Resources, Skills and Tools Through One Guarded Endpoint](../tickets/00030-TICKET.md) | done | — | [PR #181](https://github.com/johnnickell/fight-common/pull/181) |
| 11 | [TASK-00188](00188-TASK.md) | Separate Task disclosure projection from aggregate lifecycle rules | — (standalone chore) | done | — | [PR #273](https://github.com/johnnickell/fight-common/pull/273) |
| 12 | [TASK-00122](00122-TASK.md) | Deliver the Combined Tools, Resources and Skills Journey | [TICKET-00030 — Compose Resources, Skills and Tools Through One Guarded Endpoint](../tickets/00030-TICKET.md) | done | — | [PR #182](https://github.com/johnnickell/fight-common/pull/182) |
| 12 | [TASK-00189](00189-TASK.md) | Model Task identity and values with the existing ValueObject contract | — (standalone chore) | done | — | [PR #275](https://github.com/johnnickell/fight-common/pull/275) |
| 13 | [TASK-00123](00123-TASK.md) | Introduce an Optional Metadata-Aware MCP Capability Contract | — (standalone chore) | done | — | [PR #186](https://github.com/johnnickell/fight-common/pull/186) |
| 13 | [TASK-00190](00190-TASK.md) | Clarify execution and Notice domain building blocks and identities | — (standalone chore) | done | — | [PR #274](https://github.com/johnnickell/fight-common/pull/274) |
| 14 | [TASK-00124](00124-TASK.md) | Defer Dynamic Resource-Provider Validation Until Guarded Dispatch | — (standalone chore) | done | — | [PR #187](https://github.com/johnnickell/fight-common/pull/187) |
| 14 | [TASK-00191](00191-TASK.md) | Align MCP aggregate namespaces and move concrete Tools to adapters | — (standalone chore) | done | — | [PR #278](https://github.com/johnnickell/fight-common/pull/278) |
| 15 | [TASK-00125](00125-TASK.md) | Reconcile v1.3 MCP documentation and prepare release qualification | — (standalone chore) | done | — | [PR #188](https://github.com/johnnickell/fight-common/pull/188) |
| 15 | [TASK-00192](00192-TASK.md) | Check and correct descriptive variable names across the codebase | — (standalone chore) | done | — | [PR #288](https://github.com/johnnickell/fight-common/pull/288) |
| — | [TASK-00114](00114-TASK.md) | Reconcile PR #159 strict PHPCS delivery evidence | — (standalone chore) | done | — | [PR #159](https://github.com/johnnickell/fight-common/pull/159) |
| — | [TASK-00115](00115-TASK.md) | Adopt approved planning, ownership, and independent review conventions | — (standalone chore) | done | — | [PR #173](https://github.com/johnnickell/fight-common/pull/173) |
| — | [TASK-00116](00116-TASK.md) | Restore DBAL schema-builder compatibility with supported dependencies | — (standalone bug) | done | — | [PR #162](https://github.com/johnnickell/fight-common/pull/162) |
| — | [TASK-00117](00117-TASK.md) | Discover Authorized Resources Through the Guarded Endpoint | [TICKET-00028 — Discover and Read Authorized Resources](../tickets/00028-TICKET.md) | done | — | [PR #175](https://github.com/johnnickell/fight-common/pull/175) |
| — | [TASK-00118](00118-TASK.md) | Read Exact Authorized Resource Content | [TICKET-00028 — Discover and Read Authorized Resources](../tickets/00028-TICKET.md) | done | — | [PR #176](https://github.com/johnnickell/fight-common/pull/176) |
| — | [TASK-00119](00119-TASK.md) | Serve Validated Immutable Skill Revisions as Resources | [TICKET-00029 — Discover Structured Skills and Retrieve Revision Files Lazily](../tickets/00029-TICKET.md) | done | — | [PR #177](https://github.com/johnnickell/fight-common/pull/177) |
| — | [TASK-00120](00120-TASK.md) | Discover and Get Complete Authorized Skill Entries | [TICKET-00029 — Discover Structured Skills and Retrieve Revision Files Lazily](../tickets/00029-TICKET.md) | done | — | [PR #178](https://github.com/johnnickell/fight-common/pull/178) |
| — | [TASK-00126](00126-TASK.md) | Create certification archives from pristine release content | — (standalone chore) | done | — | [PR #193](https://github.com/johnnickell/fight-common/pull/193) |
| — | [TASK-00127](00127-TASK.md) | Make Validation Reuse Exception-Safe | [TICKET-00031 — Safely Reuse Validation Services](../tickets/00031-TICKET.md) | done | — | [PR #195](https://github.com/johnnickell/fight-common/pull/195) |
| — | [TASK-00128](00128-TASK.md) | Add Strict Pagination Construction | [TICKET-00032 — Construct Safe Pagination Requests](../tickets/00032-TICKET.md) | done | — | [PR #196](https://github.com/johnnickell/fight-common/pull/196) |
| — | [TASK-00129](00129-TASK.md) | Add Immutable JSON Snapshots | [TICKET-00033 — Snapshot JSON Without Mutable Aliasing](../tickets/00033-TICKET.md) | done | — | [PR #197](https://github.com/johnnickell/fight-common/pull/197) |
| — | [TASK-00130](00130-TASK.md) | Give StreamId Tuple Value Semantics | [TICKET-00034 — Treat Stream Identifiers as Values](../tickets/00034-TICKET.md) | done | — | [PR #198](https://github.com/johnnickell/fight-common/pull/198) |
| — | [TASK-00131](00131-TASK.md) | Correct Quoted Email Part Extraction | [TICKET-00035 — Validate Network and Contact Values](../tickets/00035-TICKET.md) | done | — | [PR #199](https://github.com/johnnickell/fight-common/pull/199) |
| — | [TASK-00132](00132-TASK.md) | Add Canonical IP Address Values | [TICKET-00035 — Validate Network and Contact Values](../tickets/00035-TICKET.md) | done | — | [PR #200](https://github.com/johnnickell/fight-common/pull/200) |
| — | [TASK-00133](00133-TASK.md) | Add Lexical E.164 Phone Numbers | [TICKET-00035 — Validate Network and Contact Values](../tickets/00035-TICKET.md) | done | — | [PR #201](https://github.com/johnnickell/fight-common/pull/201) |
| — | [TASK-00134](00134-TASK.md) | Add Calendar and Local Time Values | [TICKET-00036 — Model Calendar Time and Instant Boundaries](../tickets/00036-TICKET.md) | done | — | [PR #202](https://github.com/johnnickell/fight-common/pull/202) |
| — | [TASK-00135](00135-TASK.md) | Add Exact Elapsed Durations | [TICKET-00036 — Model Calendar Time and Instant Boundaries](../tickets/00036-TICKET.md) | done | — | [PR #203](https://github.com/johnnickell/fight-common/pull/203) |
| — | [TASK-00136](00136-TASK.md) | Construct Strict Zoned DateTimes | [TICKET-00036 — Model Calendar Time and Instant Boundaries](../tickets/00036-TICKET.md) | done | — | [PR #204](https://github.com/johnnickell/fight-common/pull/204) |
| — | [TASK-00137](00137-TASK.md) | Add Inclusive Calendar Date Ranges | [TICKET-00036 — Model Calendar Time and Instant Boundaries](../tickets/00036-TICKET.md) | done | — | [PR #205](https://github.com/johnnickell/fight-common/pull/205) |
| — | [TASK-00138](00138-TASK.md) | Add Half-Open Instant Ranges | [TICKET-00036 — Model Calendar Time and Instant Boundaries](../tickets/00036-TICKET.md) | done | — | [PR #207](https://github.com/johnnickell/fight-common/pull/207) |
| — | [TASK-00139](00139-TASK.md) | Add Exact Decimal Arithmetic | [TICKET-00037 — Calculate Exact Monetary Values](../tickets/00037-TICKET.md) | done | — | [PR #208](https://github.com/johnnickell/fight-common/pull/208) |
| — | [TASK-00140](00140-TASK.md) | Define 49 Common Currency Values | [TICKET-00037 — Calculate Exact Monetary Values](../tickets/00037-TICKET.md) | done | — | [PR #206](https://github.com/johnnickell/fight-common/pull/206) |
| — | [TASK-00141](00141-TASK.md) | Calculate and Allocate Exact Money | [TICKET-00037 — Calculate Exact Monetary Values](../tickets/00037-TICKET.md) | done | — | [PR #210](https://github.com/johnnickell/fight-common/pull/210) |
| — | [TASK-00142](00142-TASK.md) | Express Transport-Neutral Application Failures | [TICKET-00038 — Express Transport-Neutral Application Failures](../tickets/00038-TICKET.md) | done | — | [PR #211](https://github.com/johnnickell/fight-common/pull/211) |
| — | [TASK-00143](00143-TASK.md) | Present Safe Errors Through PSR-15 | [TICKET-00039 — Present Safe Errors Across HTTP Adapters](../tickets/00039-TICKET.md) | done | — | [PR #212](https://github.com/johnnickell/fight-common/pull/212) |
| — | [TASK-00144](00144-TASK.md) | Integrate Safe Symfony Error Handling | [TICKET-00039 — Present Safe Errors Across HTTP Adapters](../tickets/00039-TICKET.md) | done | — | [PR #213](https://github.com/johnnickell/fight-common/pull/213) |
| — | [TASK-00145](00145-TASK.md) | Integrate Safe Laravel Error Handling | [TICKET-00039 — Present Safe Errors Across HTTP Adapters](../tickets/00039-TICKET.md) | done | — | [PR #214](https://github.com/johnnickell/fight-common/pull/214) |
| — | [TASK-00146](00146-TASK.md) | Make Hosted CI Advisory for Fight Common PR Delivery | — (standalone chore) | done | — | [PR #209](https://github.com/johnnickell/fight-common/pull/209) |
| — | [TASK-00147](00147-TASK.md) | Record durable execution sessions and phases | [TICKET-00040 — Record trusted execution observations](../tickets/00040-TICKET.md) | done | — | [PR #221](https://github.com/johnnickell/fight-common/pull/221) |
| — | [TASK-00148](00148-TASK.md) | Record phase activity and measured usage | [TICKET-00040 — Record trusted execution observations](../tickets/00040-TICKET.md) | done | — | [PR #222](https://github.com/johnnickell/fight-common/pull/222) |
| — | [TASK-00149](00149-TASK.md) | Record separately protected execution detail | [TICKET-00040 — Record trusted execution observations](../tickets/00040-TICKET.md) | done | — | [PR #224](https://github.com/johnnickell/fight-common/pull/224) |
| — | [TASK-00150](00150-TASK.md) | Summarize and browse authorized executions | [TICKET-00041 — Inspect execution summaries and activity](../tickets/00041-TICKET.md) | done | — | [PR #227](https://github.com/johnnickell/fight-common/pull/227) |
| — | [TASK-00151](00151-TASK.md) | Inspect an execution summary and its phases | [TICKET-00041 — Inspect execution summaries and activity](../tickets/00041-TICKET.md) | done | — | [PR #228](https://github.com/johnnickell/fight-common/pull/228) |
| — | [TASK-00152](00152-TASK.md) | Inspect phase activity and safe detail references | [TICKET-00041 — Inspect execution summaries and activity](../tickets/00041-TICKET.md) | done | — | [PR #229](https://github.com/johnnickell/fight-common/pull/229) |
| — | [TASK-00153](00153-TASK.md) | Inspect separately authorized captured detail | [TICKET-00042 — Inspect and remove protected captured detail](../tickets/00042-TICKET.md) | done | — | [PR #230](https://github.com/johnnickell/fight-common/pull/230) |
| — | [TASK-00154](00154-TASK.md) | Remove protected detail while preserving execution evidence | [TICKET-00042 — Inspect and remove protected captured detail](../tickets/00042-TICKET.md) | done | — | [PR #231](https://github.com/johnnickell/fight-common/pull/231) |
| — | [TASK-00155](00155-TASK.md) | Inspect execution summaries and activity through MCP | [TICKET-00043 — Inspect execution evidence through MCP](../tickets/00043-TICKET.md) | done | — | [PR #232](https://github.com/johnnickell/fight-common/pull/232) |
| — | [TASK-00156](00156-TASK.md) | Inspect separately protected execution detail through MCP | [TICKET-00043 — Inspect execution evidence through MCP](../tickets/00043-TICKET.md) | done | — | [PR #235](https://github.com/johnnickell/fight-common/pull/235) |
| — | [TASK-00157](00157-TASK.md) | Discover and retrieve authorized MCP Prompts | [TICKET-00044 — Discover and retrieve authorized MCP Prompts](../tickets/00044-TICKET.md) | done | — | [PR #236](https://github.com/johnnickell/fight-common/pull/236) |
| — | [TASK-00158](00158-TASK.md) | Record durable safe change notices | [TICKET-00045 — Record and retain safe change notices](../tickets/00045-TICKET.md) | done | — | [PR #237](https://github.com/johnnickell/fight-common/pull/237) |
| — | [TASK-00159](00159-TASK.md) | Remove retained notices without concealing history loss | [TICKET-00045 — Record and retain safe change notices](../tickets/00045-TICKET.md) | done | — | [PR #238](https://github.com/johnnickell/fight-common/pull/238) |
| — | [TASK-00160](00160-TASK.md) | Summarize and inspect authorized retained notices | [TICKET-00046 — Poll and inspect retained notices](../tickets/00046-TICKET.md) | done | — | [PR #239](https://github.com/johnnickell/fight-common/pull/239) |
| — | [TASK-00161](00161-TASK.md) | Recover retained notices through shared Queries and MCP | [TICKET-00046 — Poll and inspect retained notices](../tickets/00046-TICKET.md) | done | — | [PR #240](https://github.com/johnnickell/fight-common/pull/240) |
| — | [TASK-00162](00162-TASK.md) | Follow Tool and Resource changes through live MCP | [TICKET-00047 — Follow MCP changes and inspect current subscribers](../tickets/00047-TICKET.md) | done | — | [PR #242](https://github.com/johnnickell/fight-common/pull/242) |
| — | [TASK-00163](00163-TASK.md) | Summarize and inspect current MCP subscribers | [TICKET-00047 — Follow MCP changes and inspect current subscribers](../tickets/00047-TICKET.md) | done | — | [PR #243](https://github.com/johnnickell/fight-common/pull/243) |
| — | [TASK-00164](00164-TASK.md) | Follow authorized Prompt catalog changes through MCP | [TICKET-00047 — Follow MCP changes and inspect current subscribers](../tickets/00047-TICKET.md) | done | — | [PR #245](https://github.com/johnnickell/fight-common/pull/245) |
| — | [TASK-00165](00165-TASK.md) | Publish retained safe notices through optional Mercure | [TICKET-00048 — Keep browser notice feeds live through optional Mercure](../tickets/00048-TICKET.md) | done | — | [PR #246](https://github.com/johnnickell/fight-common/pull/246) |
| — | [TASK-00166](00166-TASK.md) | Record and retrieve durable asynchronous Task state | [TICKET-00049 — Start durable asynchronous operations and retrieve their outcomes](../tickets/00049-TICKET.md) | done | — | [PR #248](https://github.com/johnnickell/fight-common/pull/248) |
| — | [TASK-00167](00167-TASK.md) | Remove retained asynchronous Task data safely | [TICKET-00049 — Start durable asynchronous operations and retrieve their outcomes](../tickets/00049-TICKET.md) | done | — | [PR #250](https://github.com/johnnickell/fight-common/pull/250) |
| — | [TASK-00168](00168-TASK.md) | Start and recover asynchronous operations through MCP | [TICKET-00049 — Start durable asynchronous operations and retrieve their outcomes](../tickets/00049-TICKET.md) | done | — | [PR #254](https://github.com/johnnickell/fight-common/pull/254) |
| — | [TASK-00169](00169-TASK.md) | Supply and admit durable Task input | [TICKET-00050 — Supply requested input to asynchronous operations](../tickets/00050-TICKET.md) | done | — | [PR #252](https://github.com/johnnickell/fight-common/pull/252) |
| — | [TASK-00170](00170-TASK.md) | Request and observe cooperative Task cancellation | [TICKET-00051 — Request cooperative cancellation of asynchronous operations](../tickets/00051-TICKET.md) | done | — | [PR #253](https://github.com/johnnickell/fight-common/pull/253) |
| — | [TASK-00171](00171-TASK.md) | Follow authorized Task state and recover after reconnecting | [TICKET-00052 — Follow live Task state and recover after reconnecting](../tickets/00052-TICKET.md) | done | — | [PR #255](https://github.com/johnnickell/fight-common/pull/255) |
| — | [TASK-00172](00172-TASK.md) | Bind Fight Common to shared workflows and independent QA | — (standalone chore) | done | — | [PR #220](https://github.com/johnnickell/fight-common/pull/220) |
| — | [TASK-00173](00173-TASK.md) | Deprecate released public properties in favor of getters | — (standalone chore) | done | — | [PR #247](https://github.com/johnnickell/fight-common/pull/247) |
| — | [TASK-00175](00175-TASK.md) | Make PHPStan's memory budget explicit | — (standalone bug) | done | — | [PR #226](https://github.com/johnnickell/fight-common/pull/226) |
| — | [TASK-00176](00176-TASK.md) | Keep planning routing views and delivery handoffs consistent | — (standalone bug) | done | — | [PR #234](https://github.com/johnnickell/fight-common/pull/234) |
| — | [TASK-00177](00177-TASK.md) | Consolidate all agent and MCP classes under Mcp | — (standalone chore) | done | — | [PR #251](https://github.com/johnnickell/fight-common/pull/251) |
| — | [TASK-00193](00193-TASK.md) | Add validated ResultSet construction | — (standalone chore) | done | — | [PR #266](https://github.com/johnnickell/fight-common/pull/266) |
| — | [TASK-00194](00194-TASK.md) | Configure Event Store and sequence table names | [TICKET-00053 — Customize Event Sourcing persistence tables](../tickets/00053-TICKET.md) | done | — | [PR #281](https://github.com/johnnickell/fight-common/pull/281) |
| — | [TASK-00195](00195-TASK.md) | Configure projection checkpoint storage | [TICKET-00053 — Customize Event Sourcing persistence tables](../tickets/00053-TICKET.md) | done | — | [PR #282](https://github.com/johnnickell/fight-common/pull/282) |
| — | [TASK-00196](00196-TASK.md) | Configure publication storage and complete the customized journey | [TICKET-00053 — Customize Event Sourcing persistence tables](../tickets/00053-TICKET.md) | done | — | [PR #284](https://github.com/johnnickell/fight-common/pull/284) |
| — | [TASK-00197](00197-TASK.md) | Record executions in configurable tables | [TICKET-00054 — Customize execution persistence tables](../tickets/00054-TICKET.md) | done | — | [PR #283](https://github.com/johnnickell/fight-common/pull/283) |
| — | [TASK-00198](00198-TASK.md) | Inspect and remove captured detail from configurable execution storage | [TICKET-00054 — Customize execution persistence tables](../tickets/00054-TICKET.md) | done | — | [PR #286](https://github.com/johnnickell/fight-common/pull/286) |
| — | [TASK-00199](00199-TASK.md) | Configure durable Task storage | [TICKET-00055 — Customize durable MCP lifecycle tables](../tickets/00055-TICKET.md) | done | — | [PR #287](https://github.com/johnnickell/fight-common/pull/287) |
| — | [TASK-00200](00200-TASK.md) | Configure Notice persistence and recovery storage | [TICKET-00055 — Customize durable MCP lifecycle tables](../tickets/00055-TICKET.md) | done | — | [PR #289](https://github.com/johnnickell/fight-common/pull/289) |
| — | [TASK-00201](00201-TASK.md) | Configure subscription storage and complete live integration | [TICKET-00055 — Customize durable MCP lifecycle tables](../tickets/00055-TICKET.md) | done | — | [PR #291](https://github.com/johnnickell/fight-common/pull/291) |
| — | [TASK-00202](00202-TASK.md) | Qualify ORM table overrides and complete the inventory | [TICKET-00055 — Customize durable MCP lifecycle tables](../tickets/00055-TICKET.md) | done | — | [PR #293](https://github.com/johnnickell/fight-common/pull/293) |
| — | [TASK-00203](00203-TASK.md) | Contain terminal Laravel safe-error rendering faults | — (standalone bug) | done | — | [PR #276](https://github.com/johnnickell/fight-common/pull/276) |
| — | [TASK-00204](00204-TASK.md) | Make subscriber inspection journey respect live clock semantics | — (standalone bug) | done | — | [PR #280](https://github.com/johnnickell/fight-common/pull/280) |
| — | [TASK-00205](00205-TASK.md) | Record and inspect typed activity execution facts | [TICKET-00056 — Record and inspect typed activity execution facts](../tickets/00056-TICKET.md) | done | — | [PR #294](https://github.com/johnnickell/fight-common/pull/294) |
| — | [TASK-00206](00206-TASK.md) | Deprecate public table-name constants for 2.0 removal | — (standalone chore) | done | — | [PR #292](https://github.com/johnnickell/fight-common/pull/292) |
| — | [TASK-00211](00211-TASK.md) | Apply activity visibility consistently to execution summaries | — (standalone bug) | done | — | [PR #306](https://github.com/johnnickell/fight-common/pull/306) |
<!-- /planning:board -->

## Wayfinder

Consult the [map index](../wayfinder/README.md) for current charting state. A map's authored Frontier identifies
the next decision; a closed map does not create a new implementation commitment.

## History

Completed planning remains in the [TASK archive](archive/README.md). The [migration map](../MIGRATION.md) resolves
old IDs and paths. Archive records only on an explicit request.
