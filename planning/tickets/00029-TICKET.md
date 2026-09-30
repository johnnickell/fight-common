---
id: TICKET-00029
epic: EPIC-00007
title: Discover Structured Skills and Retrieve Revision Files Lazily
status: done
---

# Discover Structured Skills and Retrieve Revision Files Lazily

## Problem statement

A client needs a complete description of a Skill revision before choosing which files to retrieve. Flattened
Markdown, summary-only discovery, mutable file callbacks or mandatory archive downloads lose structure, undermine
integrity or force unnecessary content loading. Access filtering must protect an entire Skill consistently without
creating incomplete manifests or requiring duplicate variants for different callers.

## Solution and boundaries

Provide the static `io.modelcontextprotocol/skills` extension over MCP `2026-07-28`, using the shared Resources
capability from [TICKET-00028](00028-TICKET.md). Consumers supply immutable revisions and current availability;
Common supplies valid complete entries, representation, validation and consistent file-serving mechanics.

- `skills/list` returns paginated complete entries, including full frontmatter and the complete resource manifest.
  Never split an entry across pages or require `skills/get` to finish it. `skills/get` resolves an exact served
  `SKILL.md` URI independently of whether it appeared in a partial listing, subject to current authorization.
- Each revision retains root `SKILL.md` plus nested references, ordinary template files, scripts, binary assets and
  other supporting resources. Every file appears exactly once in the manifest and has an individually addressable
  Resource URI. Relative references remain within the Skill root and that revision's manifest.
- Whole bundle means the complete immutable revision and manifest, not flattened Markdown, an obligatory archive,
  bulk prefetch or loading every file into model context. Discovery requires no file fetches by the client; a
  client can retrieve just `SKILL.md`, then one selected nested member. Server-side snapshot validation may inspect
  bytes, but routine listing must not require re-reading/re-hashing all content on every request.
- Exact raw byte sizes and SHA-256 digests cover the bytes served, before JSON escaping or Base64 encoding. Preserve
  original UTF-8, line endings and frontmatter bytes. All authored JSON-representable frontmatter fields survive,
  including unknown fields, `license`, `metadata` and `allowed-tools`; preservation is not an execution grant.
- The manifest and file readers belong to one consistent immutable revision. New revisions receive new addresses;
  retained historical addresses cannot silently return newer bytes. Consumer storage owns atomic publication,
  retention and current-revision selection. Common validates the provider contract without creating a catalog store.
- Apply injected whole-Skill availability consistently to entries and every file Resource read, including direct
  historical URIs. Deny the whole Skill rather than redact manifest members. Separate complete content variants
  are created only when genuinely required, not as a substitute for ordinary authorization filtering.
- Advertise the Skills extension under `extensions` alongside Resources, with no directory or notification claim.
  Require list/get and readable Resources at composition. Supply the narrow registry extension-ID composition
  behavior needed here: distinct IDs coexist; identical same-ID settings may coalesce; contradictory same-ID
  settings and duplicate method ownership fail. Do not introduce a generic plugin framework.

## Use cases

These are read/description operations. No new business Command or domain Event is needed. Snapshot validation is a
composition/publication-input check, not a Common-owned publishing command, database mutation or Workflow transition.

| Use case | Commands | Queries | Events | Expected side effects |
|---|---|---|---|---|
| Consumer supplies a static revision | N/A; no Common publication command | Inspect supplied manifest inputs, frontmatter and bytes | N/A; no domain mutation | Accept a complete consistent revision or reject malformed input without executing it. |
| Client lists available Skills | N/A; read-only | `skills/list` with injected availability | N/A; no domain mutation | Return whole authorized entries, not file contents or partial manifests. |
| Client knows a Skill URI not present in its listing | N/A; read-only | `skills/get` for that exact URI | N/A; no domain mutation | Return the complete authorized entry or safe concealed failure. |
| Client loads instructions and later one nested file | N/A; read-only | Individual `resources/read` calls | N/A; no domain mutation | Deliver only requested revision files; template/script reads neither render nor execute. |
| Consumer denies a Skill after discovery | N/A; consumer policy changes outside Common | Fresh availability on list/get/every file read | N/A; no Common domain event | No new protected metadata or bytes are disclosed through saved/direct URIs. |
| Consumer supplies a later revision | N/A; consumer publication remains external | Lookup of each retained revision | N/A; no Common domain event | Old manifest/bytes stay old; the new revision is separately addressable. |

Diagnostics and optional cache effects are technical only. Common does not promise to revoke already disclosed
knowledge, control model context, execute scripts or activate nested `SKILL.md` files.

## Validation and permissions

Validate the static manifest's completeness, unique entries, required root `SKILL.md`, allowed relative structure,
URI/root/name agreement, exact byte sizes/digests and JSON-representable frontmatter. Reject missing or duplicate
members, out-of-root targets, mismatched content and mutable/inconsistent provider output safely. Preserve nested
paths without permitting traversal, repeated-decoding escapes, arbitrary URL fetching or filesystem mounting.

Frontmatter parsing must not execute YAML tags or follow includes. Reject ambiguous duplicate keys, unsupported
values and parser expansion beyond finite bounds rather than silently drop or alter authored fields. Parser
selection and dependency placement must respect inward architecture; exact library and interfaces remain design.

Use sensible finite limits for entry metadata, parsing, member count, total raw content and per-file/encoded output,
with validated optional overrides. Respect the pinned Skills interoperability requirements and ensure an accepted
revision fits the Resource serving limits. Atomic entries cannot be truncated or split merely to fit a page.
Exact operational values are not frozen by this record or the illustrative research defaults.

Availability uses the current injected consumer decision, not Agent OS permission strings, a principal supplied by
the caller, URI possession, a manifest digest or cache freshness. Listing omission is not itself denial: direct
get/read must independently resolve availability. Private cache reuse never substitutes for authorization. Use the
central safe protocol failures for invalid/unknown/unavailable inputs and unexpected provider failures; do not add
Tool `isError` envelopes or invent a wire error for a client's local integrity-verification failure.

## Dependencies and sequencing

- [EPIC-00007](../epics/00007-EPIC.md) owns the accepted structured multi-file boundary; the compatible pinned
  official contracts are recorded in [WF-044 evidence](../wayfinder/research/WF-044-mcp-resources-skills-evidence.md).
- [TICKET-00028](00028-TICKET.md) supplies shared Resource/provider behavior and file reads. Skill-specific
  completeness and whole-Skill availability wrap those mechanics rather than register competing Resource handlers.
- [TICKET-00023](00023-TICKET.md) supplies discovery/dispatch and the guarded endpoint. Registry extension merging
  and Skills prerequisites needed for this capability belong here, so this TICKET does not wait on final combined
  acceptance in [TICKET-00030](00030-TICKET.md). Focused Skills acceptance does not require completed Tools.
- Consumer storage, Agent OS catalog policy, compatible Pi client selection and live Access Control integration
  remain downstream. Their implementation is not a Common acceptance dependency.

## Acceptance and evidence

Package-owned fixtures demonstrate complete list/get entries, partial listings with successful authorized direct
get, atomic pagination, empty catalogs and lazy requested-file delivery. Use structured revisions containing nested
references, ordinary templates, scripts and binary assets; include Unicode/CRLF and unknown frontmatter fields.
Verify sizes and digests against original bytes, preserved old revisions and absence of rendering/execution.

Exercise invalid manifests/frontmatter, duplicate or escaped paths, wrong sizes/hashes, inconsistent snapshots,
limit boundaries and encoded expansion. Prove changed injected availability denies get and every supporting-file
URI without redaction or duplicate variants. Prove truthful extension advertisement, independent Resources use,
Skills-to-Resources prerequisite rejection and compatible extension-ID composition. A focused endpoint fixture
must demonstrate discovery/get and selected file reads through the existing guard/mirror contract, without
pretending its fixture client proves real Pi behavior.

Run applicable pinned server Skills conformance scenarios with nonempty/adversarial fixtures and record exact
source/version, results, skipped/unavailable cases and gaps. Unadvertised directory scenarios are explicitly not
applicable; skipped required behavior is not success. Owned tests cover obligations the official harness cannot
observe. Official client no-prefetch/integrity scenarios belong to later real Harness acceptance, not a claim made
from server fixtures.

Classify new public APIs and behavioral promises in the compatibility manifest; document structured revision
composition, validation, authorization injection, lazy retrieval, limits and failure/recovery. Preserve existing
Resources/Tools/HTTP consumers. Each implementation change runs the complete project gate with exact coverage;
TICKET-00030 does not defer this TICKET's evidence or documentation. Verified implementation checkpoints and
explicit parent acceptance are recorded below.

## Exclusions

Directory RPC, URI-template machinery, server-side template rendering, archives as the required retrieval form,
dynamic Skills, subscriptions/listChanged, arbitrary execution, implicit `allowed-tools` grants, catalog persistence,
managed/custom policy, production publication lifecycle, Pi client/cache/session management, permission policy,
federation, new framework adapters and release operations are excluded. Nested paths and ordinary template/script
files remain supported content despite those exclusions.

## TASKs

<!-- planning:children -->
| ID | Title | Status |
|---|---|---|
| [TASK-00119](../tasks/00119-TASK.md) | Serve Validated Immutable Skill Revisions as Resources | done |
| [TASK-00120](../tasks/00120-TASK.md) | Discover and Get Complete Authorized Skill Entries | done |
<!-- /planning:children -->

## Decisions and progress

The implementation checkpoints below retain their historical review and delivery states. The final parent
acceptance section records the current closeout disposition; earlier pending statements are not current blockers.

John approved this requirement area in the three-TICKET split. [WF-045](../wayfinder/tickets/WF-045-select-the-first-resources-and-skills-delivery.md)
records the complete-manifest/lazy-read and whole-Skill authorization decisions; the EPIC owns scope and acceptance.
[ADR 0005](../adr/0005-layer-dependency-matrix.md) governs parser/runtime dependency direction, and
[ADR 0010](../adr/0010-behavioral-contract-authority.md) governs compatibility promises. Exact types, values and
implementation details remain routine design without additional human gates.

John approved two vertical implementation slices: [TASK-00119](../tasks/00119-TASK.md) validates immutable Skill
revisions and serves their authorized files through Resources; [TASK-00120](../tasks/00120-TASK.md) adds complete
Skills list/get, extension composition and the integrated Skill journey. List/get ship together because advertising
the extension requires both; TASK-00119 does not advertise partial Skills support. Each TASK owns its applicable
verification, compatibility, documentation and full gate, rather than deferring them to final integration.

TASK-00119 depends on [TASK-00118](../tasks/00118-TASK.md); TASK-00120 depends on TASK-00119. Both stay unranked,
preserving existing global priorities. No Tools/Pi/TICKET-00030 blocker is introduced. This TICKET's decomposition
is complete, and John subsequently authorized TASK-00119 in the isolated worktree. At its 2026-09-30 pre-publication checkpoint,
validated immutable revision snapshots, bounded safe YAML parsing and whole-Skill-protected file Resources are
implemented and locally verified; independent review remains pending. Full gate: Unit 4,742/10,840, Integration
150/1,024, Functional 48/1,847 and exact 12,387/12,387 unit statements. Its TASK record and ignored handoff retain
scenario-specific Resource conformance results/gaps, compatibility classification and 38 actual HTTP observations.
No Skills extension conformance is claimed. Independent review subsequently returned R1 for lossy YAML merge and
duplicate-key handling. The authorized correction is implemented with failing-then-passing parser/revision
regressions and a fresh full gate (Unit 4,824/10,999; exact 12,467/12,467 statements; Integration 150/1,024;
Functional 48/1,847), repeated Resource checks and 38 HTTP observations. Renewed review still found R1's
comment-separated duplicate keys and multiword-key truncation. A second authorized correction adds explicit flow
key/value/separator validation, 18 failing-before-repair regressions and a fresh full gate (Unit 4,871/11,070;
exact 12,498/12,498 statements; Integration 150/1,024; Functional 48/1,847). The reviewer reproducer now rejects
all unsafe constructions; repeated Resource checks and 38 HTTP observations retain their documented outcomes.
Independent re-review remains pending; the TASK owns detailed evidence and limitations. TASK-00120 remains separately authorized future work
for complete list/get and advertisement; this TICKET is not accepted or closed by the first slice.

At the subsequent 2026-09-30 landing checkpoint, independent review accepted TASK-00119 candidate `9d631d8`
with R1 resolved, and independent QA passed 66 HTTP requests plus 478 tests / 1,017 assertions at that exact
head. The TASK records canonical review/QA evidence and limitations. Administrative publication and final-head
hosted delivery verification are pending at this checkpoint; human merge and this TICKET's acceptance remain
separate. TASK-00120 was still future work at that checkpoint.

### Complete Skills implementation checkpoint — 2026-09-30

John subsequently authorized TASK-00120 in the main checkout over merged TASK-00119. Complete list/get, same-provider
readable composition, extension-ID conflict handling, atomic bounded pages, fresh whole-Skill/general Resource
availability and the guarded entry/selected-file journey are implemented and locally verified. TASK-00120 owns the
current full gate: 4,939 Unit / 11,270 assertions, exact 12,640/12,640 statements, 150 Integration / 1,024 and
49 Functional / 1,999, exit 0. New API/behavior classifications, consumer docs and CONTEXT accompany the code.

| TICKET outcome | Integrated evidence |
|---|---|
| Complete immutable revisions, safe frontmatter/path/manifest input and original-byte integrity | Accepted TASK-00119 production contracts and parser/revision/resource tests remain unchanged and run in the current full gate. |
| Whole-Skill denial, retained historical bytes and lazy individual-file retrieval | Existing Resource-only journey plus TASK-00120's guarded Skills discovery/get/root/nested-file journey and all-member byte/digest/denial assertions. |
| Complete list/get, direct URI lookup independent of listing, atomic bounded pages and safe cache/cursor/error semantics | `McpSkillDiscoveryTest`, `McpSkillCompositionTest` and `McpSkillDiscoveryJourneyTest`; changing availability and general Resource-policy intersection conceal whole entries, never redact manifests. |
| Truthful extension/read prerequisites, compatible registry composition, preserved HTTP guards | Registry composition tests and real Slim/PSR handler journey; independent Resources remains supported; no invented Skills name mirror. |
| Pinned server Skills evidence | TASK-00120 records enumeration 31 SUCCESS/1 WARNING, manifest 3 SUCCESS/3 WARNING, unadvertised directory 6 SKIPPED N/A/1 schema SUCCESS, all process exits 0; 45 additional actual HTTP observations. |
| Compatibility, documentation and local verification | Two public additions, three behavior contracts, full structural surface check, consumer walkthrough and complete gate; exact logs/content mapping in the TASK handoff. |
| Independent acceptance and QA | Pending for TASK-00120; this builder mapping does not close the TICKET or supply either independent verdict. |

The same-name and inherited Resource descriptor SHOULD advisories are explicit in TASK-00120; all exercised
applicable MUST checks pass, without a blanket badge. Directory/subscriptions/execution, production publication
storage, Pi/host trust and cache behavior remain excluded/downstream. No evidence or documentation for this TICKET
is deferred to the combined Tools journey. Explicit parent acceptance/closeout remains separate.

Independent TASK-00120 review subsequently identified R1: float-valued frontmatter could make tight encoded pages
empty with an unusable cursor. The authorized repair aligns page sizing with result encoding, preserving existing
limits and metadata fingerprints. Two regressions failed before repair and now pass, including complete three-page
traversal. Fresh full gate: Unit **4,941 / 11,330**, exact **12,640/12,640 statements**, Integration **150 / 1,024**,
Functional **49 / 1,999**, exit 0; repeated pinned Skills conformance retains the same documented outcomes/advisories.
TASK-00120 owns detailed red/green and gate evidence. Independent re-review and applicable QA are pending; this
repair checkpoint neither accepts nor closes the TICKET.

Independent re-review then accepted `e60a66b`, but subsequent exact-head QA failed **QA-01**: reordered equivalent
extension-setting objects rejected composition. The authorized repair now compares extension values with unordered
object members while preserving ordered lists, types, member presence and other capability families. Five product
cases failed before repair; the final composition suite passes **46 / 132**, and the unchanged QA probe passes
all 12 controls. Fresh full gate: Unit **4,972 / 11,428**, exact **12,651/12,651 statements**, Integration
**150 / 1,024**, Functional **49 / 1,999**, exit 0. Repeated pinned Skills conformance retains the documented
outcomes/advisories. TASK-00120 owns detailed evidence and pending renewed independent review/affected QA;
prior reports remain unchanged, and this builder checkpoint neither accepts nor closes the TICKET.

At the subsequent TASK-00120 landing checkpoint, independent technical review accepted `f9079be` with R1 and
QA-01 resolved; exact-head independent QA passed nine behavioral scenarios, with visual evidence N/A. The TASK
owns canonical reports, 124 fresh HTTP observations, semantic/composition probes and inherited pinned conformance
provenance. John authorized publication; final-head hosted delivery checks and independent continuation remain
pending at this checkpoint. Both TASKs now have independent implementation acceptance and applicable QA; this
progress update does not perform the TICKET's separate acceptance/closeout or authorize merge.

## Parent acceptance and closeout

John authorized this tracked closeout after the independent parent assessment of `develop` at
`5d8ca58341695f9e4498aefc6fca205569475854`. That assessment explicitly accepted this bounded Skills parent,
including the complete immutable-revision and list/get journey. TICKET-00029 is **done**: both children have
independent technical acceptance and applicable behavioral QA PASS, the parent outcomes above are satisfied,
and required local verification is complete. Historical checkpoints and original reports remain unchanged.

- [TASK-00119](../tasks/00119-TASK.md) supplies complete immutable snapshots, safe bounded frontmatter parsing,
  original-byte manifests and whole-Skill-protected Resource files. Its parser R1 corrections received independent
  acceptance and QA PASS. [TASK-00120](../tasks/00120-TASK.md) supplies complete atomic list/get, direct lookup,
  current whole-Skill/general Resource availability, lazy selected-file reads and truthful extension composition.
  Independent technical review accepted `f9079be` with R1 and QA-01 resolved; subsequent exact-head QA PASS
  superseded the earlier failure and the technical report's then-pending QA checkpoint. Canonical evidence is
  `.runs/reviews/TASK-00119/review.md`, `.runs/reviews/TASK-00120/review.md`,
  `.runs/qa/TASK-00119/qa.md` and `.runs/qa/TASK-00120/qa.md`.
- The assessment verified current-content applicability, all four Resource/Skill QA artifact manifests and the
  complete outcome mapping, including every-member denial, historical-byte retention and inert supporting files.
  Fresh assessment checks passed 1,507 focused tests / 4,363 assertions plus six journeys totaling 32 tests /
  898 assertions. The accepted `f9079be` product inputs are unchanged at the assessed head; only planning differs.
  These checks do not relabel inherited QA or conformance evidence as newly executed.
- The retained TASK-00120 complete gate has exit 0: Unit 4,972 / 11,428, exact 12,651/12,651 owned statements,
  Integration 150 / 1,024 and Functional 49 / 1,999. Its tested snapshot matches `d9cce23`; documentation and
  planning checks passed at the assessed head. This administrative closeout changes no product inputs; its own
  targeted verification and content mapping belong in the local handoff.
- Inherited official evidence remains pinned to `7169291ec0b68eb370fddcd9947313ab0d5e4156`, version
  `0.2.0-alpha.11`, protocol `2026-07-28`: enumeration 31 SUCCESS / one same-name WARNING; manifest three
  SUCCESS / three SHOULD warnings for root MIME, relative name and omitted description; unadvertised directory
  six SKIPPED N/A / one schema SUCCESS. Applicable exercised MUST checks pass, not a blanket Skills/client badge.
  Resource subset qualifications and existing documentation advisories remain as recorded in TICKET-00028.

The complete assessment is retained locally at `.runs/reviews/parent-closeout-5d8ca583/report.md`; administrative
verification belongs in `.runs/handoffs/ticket-00028-00029-closeout/receipt.md`. TICKET-00030 still owns ingress
bounds and the combined Tools journey; EPIC-00007 remains open. TASK-00123/00124 are separate approved follow-ups,
not unfinished children: current Skills construction still enumerates shared dynamic Resource metadata, and this
acceptance does not claim TASK-00124's future no-constructor-I/O behavior. Consumer publication/retention, real
identity/cache policy, client integrity/no-prefetch and Pi/host activation remain downstream. This closeout grants
no archive, publication, merge, release or deployment authority and leaves TICKET-00026/00027 and their QA unchanged.
