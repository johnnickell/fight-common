---
id: TICKET-00048
epic: EPIC-00011
title: Keep browser notice feeds live through optional Mercure
status: done
---

# Keep browser notice feeds live through optional Mercure

## Problem statement

A consumer-owned interface needs live notice updates alongside authorized history browsing, but Mercure must not
become the notice store, the MCP wire protocol or a prerequisite for polling. Hub failure must not discard durable
notice evidence, and a private transport flag alone cannot establish application authorization.

## Solution and boundaries

Deliver [EPIC-00011](../epics/00011-EPIC.md)'s optional Mercure output integration using existing Common public/private
publisher capabilities. This is reusable package behavior, not a dashboard or hub deployment.

- Translate successfully recorded safe notices into bounded consumer-facing updates with permitted references and
  metadata. Preserve the relationship distinctions supplied by TICKET-00045 without copying protected bodies or
  full Task state into a browser broadcast. Mercure messages are not MCP JSON-RPC subscription envelopes.
- Apply consumer topic/disclosure policy before publication, including private delivery where required. Consumer
  composition owns hub access rules and actual browser authorization. A claimed SUPER_ADMIN role, intended-recipient
  field or private-publication flag does not grant global notice access or prove that a recipient is authorized.
- Keep initial/history browsing and reconnect recovery on TICKET-00046's shared Queries. A browser can load an
  authorized page, receive live changes through Mercure and retrieve retained notices/current data after disconnect.
  Do not infer replay or complete catch-up from the hub, a live message or an empty history page.
- Isolate publication from recording. Failed or unavailable hub publication remains observable and distinguishable
  from storage failure; it cannot roll back or erase a saved notice. Preserve otherwise authorized polling recovery
  after failure. Do not claim receipt, processing or exactly-once delivery from publisher success.
- Make output explicitly optional. The shared feed, polling and MCP subscription composition remain usable without
  Mercure enabled. Reuse existing `Publisher`/`PrivatePublisher` and Mercure adapters rather than building another
  generic socket layer or forcing hub dependencies into unrelated capabilities.

## Use cases

Operation labels describe responsibilities, not exact PHP APIs or consumer topic names.

| Use case | Commands | Queries | Events | Expected side effects |
|---|---|---|---|---|
| Consumer enables Mercure output for permitted notice topics | N/A; explicit composition | N/A; no history read required to register output | No new business event | Configure optional publication without activating MCP or a dashboard |
| A saved safe notice is selected for browser publication | Recording belongs to TICKET-00045; no new business mutation | Supplied current topic/disclosure policy | External Mercure update; not a new authoritative domain fact | Attempt bounded public/private delivery under consumer policy |
| Consumer interface loads history or recovers after disconnect | N/A; read only | TICKET-00046's shared inspection/recovery Queries | N/A; reads create no receipt fact | Return authorized retained data independent of hub replay |
| Hub publication fails | No rollback of completed recording | Retained data remains available through TICKET-00046 | Observable delivery failure, not a storage rollback event | Preserve saved notice and report the distinct publication outcome |
| Consumer omits Mercure composition | N/A | Shared Queries and polling remain available | No Mercure updates | No required hub dependency for MCP or retained recovery |

## Validation and permissions

Validate mapped topics, payload shape and finite encoded output bounds under the consumer-supplied policy. Do not
broadcast an otherwise safe notice to a topic whose audience is broader than its permitted disclosure. Reject
unsupported/unauthorized publication combinations rather than falling back to public delivery. Safe topic,
identity and causation metadata is still access-controlled; a reference is not a permission grant.

Consumers own trusted identity, topic vocabulary, hub authentication/authorization and browser subscriptions.
Common enforces supplied publication decisions and preserves the safe-content boundary; it cannot infer every
browser recipient's current permissions from a publisher call alone. Exact policy seams and handling of permission
changes must be documented without claiming that private transport replaces application access enforcement.
Diagnostic output must not expose hub credentials, protected bodies or raw private exception details to clients.

## Dependencies and exclusions

Consumes [TICKET-00045](00045-TICKET.md)'s saved notices and [TICKET-00046](00046-TICKET.md)'s shared history/recovery
contract. It has no implementation dependency on [TICKET-00047](00047-TICKET.md)'s MCP live path, and that path does
not require this adapter. Existing `MercureHubPublisher` and `PrivateMercureHubPublisher` provide transport calls,
not the new notice mapping, consumer policy integration or recovery contract.

No new business Command or domain event is required merely to publish: publication is an external delivery effect,
separate from notice recording and application causation. Exact mapping APIs, limits and failure-reporting composition
remain downstream design. Exclude dashboard UI, SUPER_ADMIN policy, hub deployment/operations, arbitrary topic
invention, protected-content broadcasts, full Task-state delivery, archive workflows, guaranteed replay,
receipt/processing ledgers and exactly-once delivery. Consumer reload/presentation behavior remains outside Common.

## Acceptance and evidence

Prove safe notice translation, preserved relationship/reference semantics, consumer-policy enforcement and selection
of public/private publishers. Exercise invalid/oversized payloads and denied publication without unsafe public
fallback. Demonstrate publication errors and unavailable-hub behavior remain observable while the real stored
notice can still be retrieved under current authorization through TICKET-00046. Verify the live-output composition
uses shared Queries for initial/history recovery, not a second database read implementation or hub replay assumption.

Prove operation of retained Queries/polling and existing MCP HTTP support with Mercure disabled, retaining optional dependency
boundaries. Adapter tests must distinguish publisher invocation/return from actual browser delivery or processing.
No deployed hub/browser receipt is required to establish this package contract, and package evidence must not claim
that deployment was qualified. Document composition, topic/private-delivery policy seams, finite limits and failure/
recovery semantics; align CONTEXT.md when implemented. Each implementation TASK owns relevant behavior tests, exact
owned-code coverage, compatibility classification and the full local `./bin/build` gate under the
[project profile](../agents/project-profile.md). These are future acceptance requirements, not checks already run.

## TASKs

<!-- planning:children -->
| ID | Title | Status |
|---|---|---|
| [TASK-00165](../tasks/00165-TASK.md) | Publish retained safe notices through optional Mercure | done |
<!-- /planning:children -->

## Decisions and progress

John approved this fourth requirement area in EPIC-00011's complete split. The accepted optional output scope
refines [WF-048](../wayfinder/tickets/WF-048-select-additional-mcp-capabilities-from-consumer-journeys.md) and preserves
the [research distinction](../wayfinder/research/mcp-prompts-skills-jobs-and-live-updates.md#3-subscriptions-and-mercure-can-share-changes-not-wire-protocols)
between shared changes and different wire protocols. John subsequently approved the complete single-TASK decomposition,
now written as [TASK-00165 — Publish retained safe notices through optional Mercure](../tasks/00165-TASK.md), blocked
only by TASK-00161. Recording/removal and shared inspection/recovery are covered transitively; MCP live support,
subscriber inspection, Prompts and introspection are not blockers.

Approved direction: reuse existing Publisher/PrivatePublisher and Mercure adapters through an explicit bounded delivery
operation and working post-commit composition. Only committed notices may publish; pre-commit failure sends nothing,
and post-commit failure remains distinct from recording failure without deleting data or rolling back caller work.
No automatic retry worker/outbox/delivery ledger; repeated attempts may duplicate publication and never prove receipt.
Current consumer policy selects permitted topics, public/private mode and disclosed fields. Read access is not broadcast
authority; denied/missing private support must not fall back to public. Bound topics/references and actual encoded output,
including escaping/multibyte content, while preserving permitted notice identity and relationship distinctions.

Use shared Queries for initial history and qualified recovery, not adapter SQL or hub replay. Keep Mercure explicitly
optional. Consumers own hub/browser authorization; private transport cannot prove every subscriber's current permission
or retract sent bytes. Existing wrapped hub exception messages are private diagnostics, not public failure text. Exact
APIs, numeric limits, mapping and safe failure reporting remain documented implementation details. Evidence includes
actual SQLite/MySQL/PostgreSQL recording/recovery and Common adapters with a controlled HubInterface; that is not a
deployed browser delivery/processing receipt, and no deployed receipt is required by this package contract.

The TASK owns compatibility, docs/CONTEXT.md, meaningful behavior tests, exact coverage and full local build. EPIC-00011's
complete TASK decomposition is accepted/written, not implemented. No global priority or checkout/worktree choice is
assigned; choose main checkout or isolated worktree explicitly before implementation. Release target remains unassigned.
At that planning checkpoint, `ready-for-agent` meant accepted planning, not execution authority; no implementation,
consumer amendment, commit or publication had occurred. Current implementation/verification and the next independent
stage are owned by [TASK-00165's completion notes](../tasks/00165-TASK.md#completion-notes), not this historical
planning narrative.

All map TICKET decompositions are accepted and written. TICKET-00040's complete three-TASK split is now accepted
and written as TASK-00147–00149. The
[map handoff](../wayfinder/mcp-execution-introspection-map.md#planning-handoff) now records complete map-wide TASK decomposition through TASK-00171 and recommends Board-based selection, not automatic execution. TICKET-00041's
complete split is accepted/written as TASK-00150–00152; TICKET-00042's complete split is accepted/written as
TASK-00153–00154. TICKET-00043's complete MCP split is also accepted/written as TASK-00155–00156.
TICKET-00044's complete single-TASK decomposition is accepted/written as TASK-00157.

TICKET-00045's complete recording/removal split is also accepted/written as TASK-00158–00159; actual optional
Mercure mapping/failure qualification remains here, not implied by storage-level recovery evidence.

TICKET-00046's complete inspection/recovery split is now accepted/written as TASK-00160–00161. Optional Mercure
consumes their shared history/recovery Queries, not a separate database read policy or assumed hub replay;
ordinary inspection/polling retains no reverse dependency on this output adapter.

TICKET-00047's complete live/inspection/optional-Prompt split is now accepted/written as TASK-00162–00164.
This Mercure adapter remains independent of that MCP live path; both consume shared retained history/recovery.

Next: `/skill:next`
