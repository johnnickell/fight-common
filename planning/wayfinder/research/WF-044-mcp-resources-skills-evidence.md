# MCP Resources and Skills: current evidence and ownership

## Question

What reusable MCP content-serving mechanisms are missing from Common, what does Agent OS actually need,
and which server/client responsibilities must remain separate?

**Inspection:** 2026-09-27 UTC. Research, not approved delivery scope or implementation verification.
Common planning checkout starts from `develop` at `6977533eed9c891cb9d7a0bf505e2e17c17f1cfe`.
The active HTTP implementation was inspected separately, read-only, at
`381539f6c0cae56a625ec4e7cad3e3d6983d6bdd` on `feature/task-00105-guarded-mcp-http`; it is not part of that base.
That checkpoint was local/unpublished at inspection; its paths below are local Git evidence, not public links.
Agent OS was inspected read-only at `a39bfc3b24ee1d7e8b782d0713b06c34ca544c6d`.
Local checkout observations are not claims about uninspected remote changes.

**EPIC-handoff update:** local `develop` now contains TASK-00105 at merge `52ff756` (PR #163). The table below
preserves the original inspection state; the isolated planning base was unchanged at that handoff. Publication
preparation later fast-forwarded the planning branch to `52ff756`, without changing the historical inspection table.
The accepted destination and completion evidence now live in [EPIC-00007](../../epics/00007-EPIC.md); no new
runtime/conformance run is claimed.

## Short answer

Retain Common's approved **MCP `2026-07-28`** target. The official stable
`io.modelcontextprotocol/skills` extension explicitly supports that revision; no protocol upgrade is needed.
Build a small Resources capability and a static Skills extension on the existing semantic registry and guarded
HTTP work, not a second endpoint or Tool dispatcher. Agent OS must separately supply authorized planning
projections, immutable Skill bundles, its trusted catalog, and an origin-aware Pi client. A server alone does
not make remote Skills usable in Pi.

The [delivery proposal](WF-045-mcp-resources-skills-delivery-proposal.md) led to John's acceptance of a separate
Common EPIC destination and structured multi-file/lazy-read boundary in
[WF-045](../tickets/WF-045-select-the-first-resources-and-skills-delivery.md) and the subsequently approved
[EPIC-00007](../../epics/00007-EPIC.md). Detailed interfaces, numeric values and implementation slicing remain later
design. One cross-project reconciliation remains: Agent OS's accepted WF-014 assigns
MCP wire mechanics to official SDKs rather than Common. The Common decision does not edit or silently supersede
that downstream record.

## 1. Implemented, ongoing, planned and released are different

| Boundary | Direct evidence | State at inspection |
|---|---|---|
| Common semantic MCP | [McpResponder](../../../src/Application/Mcp/McpResponder.php), [decoder](../../../src/Application/Mcp/McpRequestDecoder.php), [registry](../../../src/Application/Mcp/McpCapabilityRegistry.php), [capability contract](../../../src/Application/Mcp/McpCapability.php), [semantic tests](../../../tests/Application/Mcp/McpResponderTest.php), [TASK-00104](../../tasks/00104-TASK.md) | Implemented and merged. Supports one revision, `server/discover`, explicit method dispatch, semantic errors and metadata. Does not implement Resources just because standard method names are recognized. |
| Guarded HTTP | At the local HTTP checkpoint: `src/Adapter/Http/Mcp/McpRequestHandler.php`, `McpHeaderValidator.php` beside it, `tests/Functional/McpEndpointJourneyTest.php`, and `planning/tasks/00105-TASK.md` | Implemented on active branch, in progress pending independent review; not in this planning base. Required Origin policy, invocation guard, mirror validation, direct JSON and sanitized diagnostics. Recorded builder gate is historical evidence, not rerun here. |
| Tools | [TICKET-00024](../../tickets/00024-TICKET.md), [TASK-00106](../../tasks/00106-TASK.md), [TASK-00107](../../tasks/00107-TASK.md) | Approved/planned; no production Tool implementation in inspected source. Own registration, schemas, availability, pagination, validated CQRS invocation and safe output. |
| Progress and cancellation | [TICKET-00025](../../tickets/00025-TICKET.md), [TASK-00108](../../tasks/00108-TASK.md), [TASK-00109](../../tasks/00109-TASK.md), [TASK-00110](../../tasks/00110-TASK.md) | Planned. Genuine incremental SSE and disconnect qualification are not implemented. Five starter allocations remain `needs-info`; do not duplicate or waive this work. |
| Protected interactions | [TICKET-00026](../../tickets/00026-TICKET.md), [TASK-00111](../../tasks/00111-TASK.md), [TASK-00112](../../tasks/00112-TASK.md) | Planned Tool `input_required`, protected retries and atomic destructive confirmation. Not a general Workflow engine. |
| OAuth resource server | [TICKET-00027](../../tickets/00027-TICKET.md), [TASK-00113](../../tasks/00113-TASK.md) | Planned metadata, validation/handoff and HTTP challenges. Existing HMAC is distinct; invocation limiting is not authorization. |
| Resources / Skills | Production MCP inventory and [EPIC-00006](../../epics/00006-EPIC.md) | Missing. No Resource provider, content model, list/read capability, static manifest service, `skills/list` or `skills/get`. Explicitly later capabilities in the current Tools EPIC. |
| Released Common | [v1.2.0 source](https://github.com/johnnickell/fight-common/tree/v1.2.0/src), local tag tree and [changelog](../../../CHANGELOG.md) | Latest inspected local release tag is v1.2.0, with no `Application/Mcp` or `Adapter/Http/Mcp`. Merged MCP foundation is unreleased; this is not a fresh Packagist certification. |
| Agent OS installed dependencies | [locked dependencies][ao-lock] | Common v1.2.0 at `a2cd615d9b5064c9c30e994655536176249cd73b`; Access Control v0.3.0 at `22ffab6452b2278b82df9b147e8949630b97355f`. Consumer does not yet receive Common's new MCP foundation. |
| Agent OS runtime | [source tree][ao-src], [Harness extension][ao-extension], [foundation inventory][ao-foundation] | PHP security/persistence/API foundation and a presentation-only Pi package. No production Planning/Harness domain implementation or MCP route/client in inspected `src/` and `harness/`. Local `.pi/skills` are real instruction files, not remotely served Skills. |
| Agent OS product destination | [Harness EPIC-00006][ao-epic], [WF-014][ao-trust], [Workflow WF-017][ao-workflow] | Approved requirements, mostly undecomposed Harness work. Terminal-first amendment removes browser/database-cutover as blanket prerequisites, not immutable context or live authorization requirements. Markdown remains current Planning authority. |

Common's [closed MCP map](../fight-common-mcp-http-support-map.md), especially
[WF-039 ownership](../tickets/WF-039-define-fight-common-and-consumer-ownership-boundary.md) and
[WF-041 presentation](../tickets/WF-041-define-mcp-presentation-errors-and-response-modes.md), already settle
policy-free Common mechanics and consumer-owned authority. The relevant archived
[portability map](../archive/maps/fight-framework-portability-map.md) and
[WF-025 standards/adapters decision](../tickets/archive/WF-025-psr-interoperability-and-adapter-seams.md)
require honest, lossless standards seams, not speculative adapters or new framework route owners.
No old map needs reopening to explore a new non-Tool capability.

## 2. Concrete seams exposed by the implementation

- `McpCapability` owns declared methods, advertisement, outer validation and semantic handling. New Resources
  and Skills handlers can compose without putting a method switch into the generic responder.
- `McpResult::complete()` supplies a result envelope, **not** Resource/Skill schema or byte-integrity validation.
  The provider/capability must validate its own output. The dispatcher does not magically enforce extension contracts.
- The registry reserves `server/discover`, rejects duplicate methods, ties standard methods to matching families,
  and requires `resources/list` when `resources` is advertised. It rejects true subscription flags even if a
  generic listener name is registered. Preserve that truthful boundary.
- `registerCapabilities()` compares an entire top-level definition for equality. Distinct providers advertising
  `extensions: {vendor/a: ...}` and `extensions: {vendor/b: ...}` currently conflict. Blind recursive merging or
  last-wins would hide real conflicts. The bounded proposal is keyed extension composition with a conflict on
  unequal settings for the **same** extension ID, retaining exactly one owner per method.
- A Skills handler should advertise its extension; one Resources handler owns shared `resources/list`/`read`
  across planning and Skill providers. Do not register a second `resources/read` handler or make a Skills-only
  handler pretend it owns a standard Resource method. Validate Skills-to-Resources prerequisites at composition.
- The inspected HTTP validator already mirrors `resources/read.params.uri` into `Mcp-Name`. The pinned transport
  only assigns that standard header to `tools/call`, `resources/read` and `prompts/get`; it does **not** require
  `Mcp-Name` for `skills/get`. A client must not invent that mirror from method similarity. Required protocol and
  method headers and request `_meta` still apply to all extension calls. [Transport][transport]
- `McpRequestHandler` casts the full PSR body to a string before decoding; the decoder uses PHP's default JSON
  depth. There is no configurable bounded request-byte reader or Resource-content budget in this implementation.
  The invocation guard limits calls, not bytes. Record a small shared HTTP limit dependency rather than falsely
  claiming this is already protected or absorbing the HTTP TASK here.

These are source observations, not a claim that new regression tests or a full audit were performed.

## 3. Official Resources contract for the approved revision

Authority: [dated Resources][resources], [dated schema][schema], [pagination][pagination], [caching][caching].

- Advertise `resources: {}` for basic support. `resources/list` is required and may return an empty set; results
  may vary with request authorization, but not hidden connection state or previous requests. `resources/read`
  retrieves exact URI-addressed content. URIs obey RFC 3986 and do not have to be filesystem paths.
- Resource metadata includes required `uri` and `name`, with optional title, description, MIME type, byte size,
  icons and annotations. Reads use `contents` with `uri` and either UTF-8 `text` or Base64 `blob`, optionally MIME
  type. Multiple content items are permitted, not required for a minimal single-file provider.
- List pagination uses an opaque string cursor, server-selected page size and optional `nextCursor`. An empty
  string is a valid cursor value, not an end-of-list sentinel. Invalid cursors should yield `-32602`.
- Complete list/read/template results require `resultType: "complete"`, nonnegative integer `ttlMs`, and
  `cacheScope` (`private` or `public`). Pages have independent freshness but the same scope for a given listing.
  No cross-page snapshot consistency is guaranteed by MCP. Invalidated cursors recover by discarding pages and
  restarting; no human approval is required.
- Private caches cannot cross authorization contexts. TTL is a freshness hint, not a permission grant, immutable
  revision, polling interval or integrity proof. Protocol hints do not replace access control or HTTP cache policy.
- Unknown Resources return `-32602`, **not** the older `-32002` convention; internal failures use `-32603`.
  An empty `contents` array must not masquerade as not-found. Application concealment may use the same safe
  not-found result for a denied Resource, after transport authentication succeeds.
- Templates are parameterized RFC 6570 URI discovery, not required to address concrete known URIs. None of the
  named consumer journeys needs template expansion. An empty `resources/templates/list` response is a useful
  compatibility surface without introducing a template engine or promising arbitrary path substitution.
- Subscription delivery uses `subscriptions/listen` in this revision. Do not import older `resources/subscribe`
  or GET-session behavior. Neither subscriptions nor `listChanged` is needed to serve pinned content.
- [Tools][tools] explicitly allow `resource_link` content. Such a link need not appear in `resources/list` and
  conveys no read permission. A search Tool can return a short summary, structured metadata and links without
  embedding the complete planning corpus. Resource reads authorize again.

## 4. Verified official Skills contract

The [official extension README][skills-readme] names [stable `skills.mdx`][skills] as source of truth, not its
older archived design notes or overview. Inspected at `b0b3272f1d4c01a79c8171252c70b06dcada18bf`.
It targets base `2026-07-28` **or later**; Common retains only its approved revision.
The extension and Agent Skills format are independently versioned authorities; implementation must pin evidence
and check subsequent format changes rather than silently upgrading Common's base protocol.

| Contract | Required behavior and consequence |
|---|---|
| Advertisement | `server/discover.capabilities.extensions["io.modelcontextprotocol/skills"] = {}` plus `resources: {}`. No `skills` top-level capability. `directoryRead: true` adds a real optional method obligation. |
| `skills/list` | Paginated complete entries, never a summary requiring `skills/get` to finish it. Entries are atomic: never split one manifest across pages. Empty/partial listing is allowed and is not an absence oracle. |
| `skills/get` | Accept the exact `SKILL.md` URI, return `skill` with the same entry shape, no pagination. Must answer for served Skills even when omitted from listing; unknown URI is `-32602`. Current authorization still applies. |
| Entry | `uri`, complete `frontmatter` JSON object, and `resources`. Static `resources` enumerate `SKILL.md` and **every** supporting file exactly once as `{uri,digest,size}`. Alternative literal `"dynamic"` is permitted by the protocol but not required by our first provider. |
| Bytes | `sha256:` plus 64 lowercase hex characters over raw file bytes; `size` is the length of those same bytes. UTF-8 text is hashed before JSON escaping; binary is hashed before Base64 encoding. No line-ending or Unicode normalization during reads. |
| Frontmatter | Preserve **all** authored fields and values, not just name/description. Parsed YAML content equals the advertised JSON object field-for-field. Preserve original `SKILL.md` bytes, including frontmatter; JSON transport does not preserve YAML formatting, ordering or comments as separate semantics. |
| Addressing | Conventional `skill://<prefix>/<skill-name>/SKILL.md`; the final directory segment must equal frontmatter `name`. Put revision **before** the name, not between name and `SKILL.md`. Authority component is not a DNS destination. Other compliant schemes are permitted. |
| Supporting files | Standard `resources/read`, one URI per file. No packed archive retrieval. Relative references resolve from the Skill root and stay within its manifest and originating server. |
| Cache | Both Skills list/get require complete result and `ttlMs`/`cacheScope`. Freshness is not integrity. |
| Limits | Conforming hosts must support at least 512 files and 16 MiB aggregate raw bytes per static Skill; servers should not exceed these. No protocol cap on total catalog count. A smaller arbitrary host default would not meet that floor. |
| Directory option | If advertised, `resources/directory/read` returns paginated **direct-child metadata**, including subdirectories with `inode/directory`, for every directory in served Skill namespaces. Directory URIs have no trailing slash. Unknown/non-directory is `-32602`; empty directory is an empty list. No manifest extension through live directory results. |
| Errors | `-32602` for unknown Skill/file or invalid directory target; `-32603` for internal failure. A digest mismatch is a host verification failure, not a newly invented MCP wire error. |

A static manifest already answers which supporting files are present; the host can derive a directory view from it.
Therefore directory reading is genuinely optional for this first consumer, not a hidden delivery requirement.
Nested Skill files belong to the enclosing complete manifest, but reading nested Markdown does not activate another
Skill. Activation requires fresh explicit consent and its own verified entry.

### Server versus consuming Harness

**Server/Common mechanics:** valid schemas, exact URI dispatch, bounded results, safe errors, truthful composed
advertisement, complete consistent static entry representation and reads. Consumers supply atomic publication,
immutable retention and authorization decisions; Common enforces the provider contract and never promises that an
arbitrary database/filesystem implementation is intrinsically immutable.

**Agent OS server:** reviewed managed/custom catalog, exact immutable revision bytes, repository applicability,
current visibility, trusted publication process, provenance, authorized queries/commands, retention/tombstones and
transactional current-revision selection. A directory import is reviewed input, not an HTTP filesystem mount.

**Harness:** identity is `(host-assigned origin, URI)`, never `serverInfo.name`, hostname, alias or URI alone.
Registry construction and selection use entries without file prefetch. On activation/read, verify raw size and
hash; for `SKILL.md`, additionally compare all parsed frontmatter. Tag model context with origin, keep held
manifest/revision through the acting window, block unlisted/cross-origin reads, surface collisions and prevent
silent local substitution. Store any cache outside Pi filesystem Skill discovery, origin-separated, immutable
and host-only-writable or rehash on each access. Cache remains MCP-origin after restart/disconnect.

The extension permits per-server loading approval, so Agent OS's trusted first-party origin does not require a
new routine content prompt for every revision. However, **per-skill host execution approval**, explicit nested
activation consent, and approval for any `allowed-tools` grant are distinct protocol duties. This content delivery
adds no execution grants. Existing persisted per-skill approval, if used, must bind the entire URI/digest set and
be revoked when it changes. Integrity hashes establish consistency, not authorship or trust in the publisher.
[Skills security and verification][skills]

## 5. Pi reality and conformance: corrections to earlier leads

Agent OS's [earlier WF-013 assessment][ao-research] is useful orientation, not current authority.

- Installed Pi is `@earendil-works/pi-coding-agent` **0.87.1**. Read its complete `docs/skills.md`,
  `docs/extensions.md`, `docs/packages.md`, `docs/sdk.md`, relevant examples, compiled `core/skills.js`,
  `core/resource-loader.d.ts`, and searched installed JS/declarations for MCP/Skills methods. No built-in generic
  MCP client or Skills-extension implementation was found in that inspected surface. The separately inspected
  upstream [skill loader][pi-skills] remains filesystem-oriented; this is not an exhaustive ecosystem survey.
- Pi's local loader reads `SKILL.md` to obtain metadata, formats absolute filesystem locations, and retains the
  first same-name Skill with collision diagnostics. `ResourceLoader`/`resources_discover` deal in local paths;
  pointing them at a downloaded Skill cache loses origin semantics and can prefetch all files. Local Agent Skills
  support is not evidence of MCP Skills support.
- Pi extensions can register tools/commands, modify context, observe/block tool calls and own session lifecycle;
  the embedding SDK can replace resource loading. These are viable **integration seams**, not implemented trust
  behavior. Agent OS's actual `harness/pi/src/extension.ts` only customizes the terminal header.
- The recommended client is an Agent-OS-owned Pi extension/SDK adapter with dedicated origin-bound discovery,
  load and supporting-read operations. Reuse an official TypeScript SDK's compatible transport/custom-method
  seams where qualified. At inspection, [TypeScript Skills PR #2818][ts-pr] and [PHP Skills PR #372][php-pr]
  remain open/unmerged. Do not plan against proposed APIs as released support, or wait for them to establish
  Common's already-approved server ownership. Recheck exact client dependencies during downstream decomposition.
- Official [conformance source][conformance] at `7169291ec0b68eb370fddcd9947313ab0d5e4156` includes server
  Resources and Skills enumeration/manifest/directory scenarios **and client scenarios** for no-prefetch,
  digest, size and frontmatter rejection. The earlier assessment's implication of server-only scenarios is
  incomplete, even at its cited commit. [No-prefetch source][no-prefetch] and [verification source][verification]
  were inspected directly. Verification scenarios require a real `SKILL.md` read before treating absence of
  the supporting-file read as evidence; a client that only lists is not a successful verifier.
- The [traceability file][traceability] still excludes important host-internal duties such as acting-window
  semantics and model-visible origin/inspection. Wire scenarios do not prove catalog authorization, snapshot
  retention, safe cache access, no Workflow side effects, or Pi context handling. Do not repeat historical
  coverage counts as an acceptance guarantee. The latest observed [published conformance release][conformance-release]
  is v0.1.16 (2026-03-27); pin the later source when qualifying Skills and report skipped/unexercised checks.

No server, client, conformance suite, provider request or host execution was run in this research session.
These are verified source observations and proposed future evidence, not a conformance badge.

## 6. Capability / ownership map

| Consumer outcome | Common reusable mechanism | Agent OS owner | Access Control / integration owner |
|---|---|---|---|
| Read ROADMAP, EPIC, TICKET, TASK and authorized context | Resource list/read, metadata, URI validation, cursor/cache/error mechanics | Planning renders the authoritative revision; Workspace selects repository/context. No Common knowledge of record IDs or Markdown/database modes. | Current caller, repository/object visibility, delegated-user intersection; check lists and reads alike. |
| Search/filter planning | Existing planned Tools plus standard Resource-link output | Planning query criteria, summaries, safe links, ranking and bounded result projections | Tool availability **and** row-level query authorization; possession of a URI grants nothing. |
| Apply an accepted planning change | Existing planned validated Tool invocation and optional protected interaction | Existing application use cases own expected revisions, human apply authority, idempotency, atomic changes and events | Live permissions/grants before application dispatch, authoritative enforcement shared across transports. |
| Serve a revisioned Skill | Skills entries over the shared Resources provider, complete static manifests | Harness owns logical/revision identity, managed/custom lifecycle, immutable bytes and current pointer | Uniform bundle visibility and read checks; no permission names in Common. |
| Discover/load in Pi | Nothing server-side can replace a client | Harness package owns transport client, origin registry, aliases, held entry, verification, cache and snapshot evidence | Secure credentials and live permission-filtered operations; instructions cannot widen authority. |
| Status/progress/evidence | Read-only Resources/Tools; existing request progress/cancellation work | Workflow owns durable state; observational progress remains labeled; Artifact owns evidence bytes/metadata/redaction | Authorize status/evidence separately from conversation/private content. |

Transport authentication, byte integrity, trusted origin, user intent, object authorization, and workflow grant
are separate facts. None substitutes for another. The server can deny future reads after revocation; it cannot
make a recipient forget bytes already delivered. Harness cached-content/offline behavior must not claim otherwise.

## Open questions and evidence limits

- [WF-045](../tickets/WF-045-select-the-first-resources-and-skills-delivery.md) now records the human-accepted
  delivery boundary and multi-file clarification. The completed EPIC grill records scope and completion in
  [EPIC-00007](../../epics/00007-EPIC.md), not in this research. Downstream ownership reconciliation remains separate.
- Access Control's current checkout was not audited. Its future Agent-aware MCP availability attribute and
  Resource/Skill integration remain downstream requirements, not claims about an installed package.
- Exact PHP interface signatures, YAML parser selection, limit/error implementation, SDK release pin and network
  conformance qualification belong to later approved decomposition. No database migration, runtime enrollment,
  release, browser work or arbitrary execution is authorized.

## Sources

Local links above point to the planning base; HTTP-branch paths identify the separately pinned local checkpoint.
External links are primary authorities; website observations were retrieved on 2026-09-27. Official repository
source is pinned where available. Stable Skills source SHA-256:
`b8b4c2faf0ef38d72114b8ea2c4e29bbba1d30151b8dc3b476ca31f685de610c`.
Both Common and Agent OS were confirmed public at inspection. Downloaded sources and retrieval evidence remain
under the originating checkout's ignored `.runs/notes/mcp-resources-skills/`, not as vendored specifications.

[resources]: https://modelcontextprotocol.io/specification/2026-07-28/server/resources
[schema]: https://github.com/modelcontextprotocol/modelcontextprotocol/blob/ab3a39c13bd23be691c2760e1c6c5c15a64582e1/schema/2026-07-28/schema.ts
[transport]: https://github.com/modelcontextprotocol/modelcontextprotocol/blob/ab3a39c13bd23be691c2760e1c6c5c15a64582e1/docs/specification/2026-07-28/basic/transports/streamable-http.mdx
[pagination]: https://modelcontextprotocol.io/specification/2026-07-28/server/utilities/pagination
[caching]: https://modelcontextprotocol.io/specification/2026-07-28/server/utilities/caching
[tools]: https://modelcontextprotocol.io/specification/2026-07-28/server/tools#resource-links
[skills]: https://github.com/modelcontextprotocol/ext-skills/blob/b0b3272f1d4c01a79c8171252c70b06dcada18bf/specification/stable/skills.mdx
[skills-readme]: https://github.com/modelcontextprotocol/ext-skills/blob/b0b3272f1d4c01a79c8171252c70b06dcada18bf/README.md
[conformance]: https://github.com/modelcontextprotocol/conformance/tree/7169291ec0b68eb370fddcd9947313ab0d5e4156/src/scenarios
[traceability]: https://github.com/modelcontextprotocol/conformance/blob/7169291ec0b68eb370fddcd9947313ab0d5e4156/src/seps/sep-2640.yaml
[no-prefetch]: https://github.com/modelcontextprotocol/conformance/blob/7169291ec0b68eb370fddcd9947313ab0d5e4156/src/scenarios/client/skills/no-prefetch.ts
[verification]: https://github.com/modelcontextprotocol/conformance/blob/7169291ec0b68eb370fddcd9947313ab0d5e4156/src/scenarios/client/skills/verification.ts
[conformance-release]: https://github.com/modelcontextprotocol/conformance/releases/tag/v0.1.16
[ts-pr]: https://github.com/modelcontextprotocol/typescript-sdk/pull/2818
[php-pr]: https://github.com/modelcontextprotocol/php-sdk/pull/372
[pi-skills]: https://github.com/earendil-works/pi/blob/6f7551516b84278eb9da1c340c8e7bc66be1a6ba/packages/coding-agent/src/core/skills.ts
[ao-epic]: https://github.com/johnnickell/fight-agent-os/blob/a39bfc3b24ee1d7e8b782d0713b06c34ca544c6d/planning/epics/00006-EPIC.md
[ao-trust]: https://github.com/johnnickell/fight-agent-os/blob/a39bfc3b24ee1d7e8b782d0713b06c34ca544c6d/planning/wayfinder/tickets/WF-014-define-skill-trust-and-harness-distribution.md
[ao-workflow]: https://github.com/johnnickell/fight-agent-os/blob/a39bfc3b24ee1d7e8b782d0713b06c34ca544c6d/planning/wayfinder/tickets/WF-017-define-execution-history-and-event-authority.md
[ao-research]: https://github.com/johnnickell/fight-agent-os/blob/a39bfc3b24ee1d7e8b782d0713b06c34ca544c6d/planning/wayfinder/research/WF-013-mcp-skills-and-pi-support-research.md
[ao-lock]: https://github.com/johnnickell/fight-agent-os/blob/a39bfc3b24ee1d7e8b782d0713b06c34ca544c6d/composer.lock
[ao-src]: https://github.com/johnnickell/fight-agent-os/tree/a39bfc3b24ee1d7e8b782d0713b06c34ca544c6d/src
[ao-extension]: https://github.com/johnnickell/fight-agent-os/blob/a39bfc3b24ee1d7e8b782d0713b06c34ca544c6d/harness/pi/src/extension.ts
[ao-foundation]: https://github.com/johnnickell/fight-agent-os/blob/a39bfc3b24ee1d7e8b782d0713b06c34ca544c6d/planning/FOUNDATION.md
