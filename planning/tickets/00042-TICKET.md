---
id: TICKET-00042
epic: EPIC-00009
title: Inspect and remove protected captured detail
status: ready-for-agent
---

# Inspect and remove protected captured detail

## Problem statement

Some execution outcomes require inspecting captured messages, tool arguments/results or process output. Timeline
permission is insufficient for those bodies, and captured content may disappear under consumer retention policy
while useful execution summaries remain. Common must support that separation without inventing capture, access
or maintenance policy.

## Solution and boundaries

Deliver the protected-content reading and ordinary removal use cases in
[EPIC-00009](../epics/00009-EPIC.md). [TICKET-00040](00040-TICKET.md) remains the owner of content admission,
recording and logical storage separation; this TICKET consumes that implementation rather than creating another
capture/store subsystem.

- Provide shared `QueryBus::fetch()` use cases for paginated captured messages, linked tool arguments/results and
  bounded process output. Use existing live `page`/`per_page` collection semantics; large individual bodies use
  bounded chunks rather than unbounded responses. Record/source precision and default observation ordering follow
  the same accepted contract as safe activity browsing.
- Recheck separate current detail permission for every page/chunk. Timeline permission, possession of a detail
  reference, a successful earlier read or permission granted to a human does not grant an Agent body access.
- Preserve content type and capture-completeness information. Partial capture is not a full transcript; neither
  missing output nor successful surrounding work establishes the content or result of an unobserved operation.
- Return truthful unavailable detail without fabricating content or presenting dangling references as readable.
  Preserve consumer-supplied expired-content markers. Absence alone does not distinguish expiration from
  never-captured or otherwise unavailable content; report only what retained evidence supports.
- Provide authorized ordinary removal through `CommandBus::execute()`, preserving related session/phase summaries
  and referential integrity. Apply per-Command atomicity to related database content/link changes, and acknowledge
  only after commit. No atomicity promise extends across external files or the consumer's maintenance job.
- Define and document how removing detail and any retained identity/comparison records affects later recording
  retries. Do not claim indefinite deduplication after required records are removed or silently report a missing
  body as still retained. Coordinate the behavior and its evidence with TICKET-00040's recording owner.
- Use the shared safe observation-fact event contract where removal emits a domain fact. Reads emit no domain
  events. Exact removal events belong to downstream design; any post-commit dispatch must preserve the accepted
  possible-loss boundary and cannot turn committed removal into a claimed database rollback.

## Use cases

Operation labels describe responsibilities rather than exact PHP class names.

| Use case | Commands | Queries | Events | Expected side effects |
|---|---|---|---|---|
| Separately authorized caller browses captured messages | N/A; read only | List protected messages | N/A; read only | Return a bounded live page with supported completeness metadata |
| Caller inspects tool arguments/results or process output | N/A; read only | Read protected detail/chunk | N/A; read only | Return only currently authorized bounded captured content |
| Caller follows a reference after content disappears or access changes | N/A; read only | Same detail Query with fresh access checks | N/A; read only | Return safe denial or truthful unavailable state, never fabricated content |
| Authorized consumer operation removes selected detail | Remove protected detail through the Command bus | Reads may subsequently verify retained summary and absent detail | Safe removal fact where specified; never raw content | Atomically remove related database detail/link state while retaining summaries |
| Consumer removes identity/comparison records under its policy, then retries recording | Recording retry remains TICKET-00040's Command | Detail/state inspection follows retained evidence | No assumed delivery replay | Apply documented retained-record guarantee rather than promising perpetual deduplication |

## Validation and permissions

Validate the requested scope, session/phase/detail relationships, supported content kind, collection pagination,
chunk bounds and finite response limits. Authorization applies to metadata as well as bodies; no error may expose
protected content or unauthorized relationships. Current consumer-supplied decisions independently govern reads
and removal. A trusted cleanup caller still supplies permitted scope; selecting a record is not permission to
remove it. Consumers own human/Agent permission differences, capture, redaction, retention duration/eligibility,
holds and operational cleanup composition. Common does not infer eligibility from completion time.

Concurrent removal, reads and recording retries must preserve integrity and truthful results; multi-page or
multi-chunk reads do not acquire a frozen retention lease. Do not preserve deleted content merely to finish an
old browsing sequence. Repeated removal and missing-record behavior must be explicit in the eventual Command
contract, without implying recovery of a multi-step consumer maintenance job.

## Dependencies and exclusions

Depends on TICKET-00040's protected recording, persistence, relationships and transaction contract. Coordinates
safe availability/reference presentation with [TICKET-00041](00041-TICKET.md) and supplies protected reads for
[TICKET-00043](00043-TICKET.md). Neither safe browsing nor this capability requires Subscriptions or MCP Tasks.

Automatic capture/redaction, required complete transcripts, hidden reasoning, retention eligibility/holds, cleanup
schedulers, job batching/recovery, archives and verification of external archival are excluded. No MCP removal
Tool, new authentication/permission policy or consumer records are added. Exact chunk representation, finite
limits, storage mechanics and removal event catalog remain downstream design within these requirements.

## Acceptance and evidence

Use real persisted sessions/phases/details to prove separately authorized reading, permission denial/revocation
between pages/chunks, distinct human/Agent access, project isolation and bounded message/tool/output retrieval.
Demonstrate capture gaps, supplied expiry markers and unknown absence reasons without inventing content. Prove
that removal retains summaries, preserves referential integrity, rolls back related database writes together on
failure and produces truthful subsequent reads. Cover concurrent access/removal and the retry-guarantee boundary
with both retained and deliberately removed identity/comparison evidence. Any removal event tests must distinguish
committed deletion from later publication failure and verify safe payloads.

Document limits, missing/expired meanings, removal/retry behavior and consumer responsibilities; align CONTEXT.md
when implemented. Each implementation TASK owns the relevant behavior coverage, exact owned-code coverage and
complete `./bin/build` gate under the [project profile](../agents/project-profile.md), with existing compatibility
promises preserved. These are future acceptance obligations, not checks already run or consumer qualification.

## TASKs

<!-- planning:children -->
| ID | Title | Status |
|---|---|---|
| [TASK-00153](../tasks/00153-TASK.md) | Inspect separately authorized captured detail | ready-for-agent |
| [TASK-00154](../tasks/00154-TASK.md) | Remove protected detail while preserving execution evidence | ready-for-agent |
<!-- /planning:children -->

## Decisions and progress

John approved this as the third of four EPIC-00009 requirement TICKETs, including ordinary authorized removal
without Common-owned retention orchestration. The boundary is the accepted
[WF-047](../wayfinder/tickets/WF-047-define-safe-observation-recording-and-query-guarantees.md), not its withdrawn
cleanup checklist or earlier retention-engine proposals. `ready-for-agent` means accepted requirements, not
implementation acceptance. John subsequently approved the complete two-TASK split below; no implementation,
consumer changes or publication have occurred.

All map TICKET decompositions are accepted and written. TICKET-00040's complete three-TASK split is now accepted
as TASK-00147–00149. TASK-00149 supplies separately database-backed detail and storage-level deletion/retry evidence;
this TICKET retains the public authorized read/removal and integrated deletion/retry journey without a reverse blocker.
TICKET-00041's complete TASK decomposition is also accepted/written as TASK-00150–00152. TASK-00152 owns safe
activity/reference presentation using TASK-00149's capture metadata, with storage-level absent-detail evidence;
this TICKET retains separate current body permissions and its public read/removal journey. Do not introduce a
reverse dependency from safe browsing onto these APIs or duplicate the availability semantics.

| Accepted TASK | Delivered outcome | Blockers |
| --- | --- | --- |
| [TASK-00153 — Inspect separately authorized captured detail](../tasks/00153-TASK.md) | Bounded message, tool-data and output-chunk Queries with separate current permission and truthful capture/absence states | TASK-00149, TASK-00150 |
| [TASK-00154 — Remove protected detail while preserving execution evidence](../tasks/00154-TASK.md) | Authorized ordinary removal, atomic database changes and integrated protected/safe-read and recording-retry evidence | TASK-00153, TASK-00152 |

Both TASKs are accepted and written. Continue with database-backed detail and actual SQLite, MySQL and PostgreSQL
qualification. TASK-00153 reuses storage/read infrastructure without inheriting timeline permission. TASK-00154
consumes its protected Queries and TASK-00152's safe activity/reference Queries to prove the complete removal
outcome; neither earlier TASK depends on removal. Read and removal permission remain independent.

Each vertical TASK owns handlers, actual database implementation, validation/permissions, compatibility,
documentation/CONTEXT.md, exact coverage and the full local build. Exact names, chunk representation, finite limits,
repeat/missing-record removal behavior and any removal-event catalog remain implementation details to document
within the accepted contract. No filesystem adapter, MCP removal Tool, retention eligibility/holds, scheduler,
archival workflow or consumer-policy change is added. No global execution priority or worktree choice is assigned;
choose main checkout or isolated worktree explicitly before implementation. Release target remains unassigned.

All map TICKET decompositions and TICKET-00040–00043's complete TASK decompositions are accepted/written,
completing this EPIC's planning decomposition. TASK-00156 consumes TASK-00153's protected Queries for MCP;
removed-content protocol fixtures do not require TASK-00154 or expose its removal Command as a Tool.
The [map handoff](../wayfinder/mcp-execution-introspection-map.md#planning-handoff) now records complete map-wide TASK decomposition through TASK-00171 and recommends Board-based selection, not automatic execution. TICKET-00044's
complete single-TASK decomposition is also accepted/written as TASK-00157, independently of introspection.

TICKET-00045's complete recording/removal split is also accepted/written as TASK-00158–00159.

TICKET-00046's complete inspection/recovery split is also accepted/written as TASK-00160–00161.

Next: `/skill:next`
