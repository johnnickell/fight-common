---
id: TICKET-00029
epic: EPIC-00007
title: Discover Structured Skills and Retrieve Revision Files Lazily
status: ready-for-agent
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
TICKET-00030 does not defer this TICKET's evidence or documentation. No runtime verification is claimed here.

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
| [TASK-00120](../tasks/00120-TASK.md) | Discover and Get Complete Authorized Skill Entries | ready-for-agent |
<!-- /planning:children -->

## Decisions and progress

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
