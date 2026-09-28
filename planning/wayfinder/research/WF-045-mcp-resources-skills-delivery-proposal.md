# Proposed first Resources and Skills delivery

**Status:** Delivery boundary accepted by John in
[WF-045](../tickets/WF-045-select-the-first-resources-and-skills-delivery.md), including the structured multi-file
clarification below. The completed grill produced [EPIC-00007](../../epics/00007-EPIC.md), which now owns accepted
scope and completion evidence. Detailed APIs, numeric values and implementation slicing below remain illustrative
later design, not additional human approval gates.
**Evidence:** [Implementation checkpoints, official contracts and ownership map](WF-044-mcp-resources-skills-evidence.md).
The approved requirement split is now [TICKET-00028](../../tickets/00028-TICKET.md),
[TICKET-00029](../../tickets/00029-TICKET.md) and [TICKET-00030](../../tickets/00030-TICKET.md). TICKET-00028 now has
approved [TASK-00117](../../tasks/00117-TASK.md) and [TASK-00118](../../tasks/00118-TASK.md); TICKET-00029 has
[TASK-00119](../../tasks/00119-TASK.md) and [TASK-00120](../../tasks/00120-TASK.md). TICKET-00030 now has
[TASK-00121](../../tasks/00121-TASK.md) and [TASK-00122](../../tasks/00122-TASK.md), completing approved decomposition.
No implementation has begun, and no release target or public PHP API is approved.

## Recommendation and done condition

The accepted destination is the separate **[EPIC-00007 — Reusable MCP Resources and Structured Skills](../../epics/00007-EPIC.md)**.
Reuse [EPIC-00006](../../epics/00006-EPIC.md), rather than expanding its already-decomposed Tools/transport work.
The new destination is: an authenticated consumer can compose generic Resources and static MCP Skills over the
existing endpoint, with faithful discovery, authorized direct reads, consistent manifests and finite bounds.
Common package fixtures prove that contract **through the existing guarded endpoint, including coexistence with
Tools**, using injected consumer authorization decisions rather than Agent OS permissions or catalog policy.
Applicable pinned conformance checks with honest gap reporting, compatibility evidence, documentation and the
full gate establish Common completion. Agent OS separately proves its real Pi journey. Finite defaults and optional
validated overrides are accepted; exact interfaces, values and implementation slicing remain routine design.

Why separate: Resources introduce URI/content/provider behavior and Skills add a distinct extension contract.
Neither is necessary to accept existing Tools/SSE/OAuth work. A new EPIC keeps their completion criteria honest,
allows reuse of independently delivered foundations, and avoids turning EPIC-00006 into “all MCP”. Do not make
Agent OS adoption a Common release gate or claim an independently passing Common gate proves Harness safety.

The alternative is to add new TICKETs to EPIC-00006. It saves a planning container but changes that EPIC's explicit
non-Tool boundary and makes its completion depend on a new product destination. Not recommended.

## 1. Smallest coherent Common surface

### Resources

Propose one Resources capability exposing:

1. `resources/list`: safe descriptors, authorization filtering before deterministic URI ordering and opaque
   cursor pagination. Page the source rather than loading the whole planning corpus or every file into memory.
2. `resources/read`: exact authorized URI lookup returning one text or binary file in `contents` for the first
   provider composition. A valid empty file is an item with empty text/blob, not a not-found empty list.
3. `resources/templates/list`: empty, cacheable complete result initially. No parameterized templates are
   justified by the first journey; adding a general template resolver would create an unnecessary lookup surface.
   MCP URI templates are distinct from ordinary template files within a Skill; those files remain supported.

The public model should be understandable without knowing ROADMAP or Skill business semantics: a Resource
URI/descriptor, text-or-binary content, a page/cache result, and an explicitly registered provider. Avoid a
universal content graph, generic principal, filesystem mount, command catalog or policy engine.

**Provider seam (behavior, not frozen PHP signatures):** enumerate metadata with bounded continuation, resolve
metadata for a URI, and open its content only after the request's availability decision. Provider composition
selects one owner for a URI namespace; ambiguity is a composition failure, never provider-order precedence.
A request-scoped consumer availability collaborator receives neutral metadata and privately uses its own current
identity/authorization service. Apply the same service for list, direct lookup and content access. Providers may
push authorized filtering into existing query use cases for efficiency; that is not a second permission policy.
A caller cannot supply a principal, permission decision or provider name via `_meta` or the URI.

Do not freeze one class per noun before implementation design. A concrete consumer can provide one catalog
facade over several internal repositories. Common only needs enough contracts to keep enumeration, authorization
and byte retrieval distinct and testable; it does not need adapters for every possible backend.

**Planning URI proposal, owned by Agent OS:**
`planning://<repository-key>/epics/00006/revisions/<revision>` (and corresponding document families).
This is illustrative, not an approved URI grammar. Prefer concrete revision addresses in links and session
snapshots; a “current” lookup may resolve a latest revision but must label that fact. IDs, hierarchy, visibility,
revision lookup and Markdown rendering belong to the application. No Common dependency on the five-digit record
scheme, the current Markdown checkout, Twig or the eventual Planning database.

**Content:** Markdown as `text/markdown`, JSON as `application/json` when the consumer exposes a JSON projection,
and correct MIME types for supporting files; raw binary uses Base64 `blob`. Invalid UTF-8 is not silently repaired
into changed Skill bytes. Read output identifies exactly the requested immutable URI. No automatic HTTP redirects,
URL fetches, DNS lookup, directory recursion or filesystem access follow from a Resource URI.

### Static Skills

Propose a separate Skills capability exposing `skills/list` and `skills/get` and contributing files to the same
Resource provider composition. Advertise `io.modelcontextprotocol/skills: {}` under `extensions`, alongside
`resources: {}`. Do not advertise `directoryRead`, subscriptions or `listChanged`.

**Accepted structured-content boundary:** a Skill retains root `SKILL.md` and nested supporting references,
templates, scripts, assets and other resources. “Whole bundle” means a complete immutable revision and manifest,
not flattened Markdown, an obligatory archive download or loading all content at once. Each manifest member has
its own Resource URI and is retrieved lazily. Nested paths such as `references/review/checklist.md`,
`templates/php/handler.php.twig` and `scripts/checks/verify.sh` remain ordinary addressable files. Serving a script
is not executing it. Directory RPC and server-side template rendering are deferred, not the directory structure
or ordinary template-file content. A host can derive a nested file view from the complete held manifest.

A small static-bundle contract supplies a complete immutable set of relative file names and exact bytes (or
immutable bounded byte readers) plus the revision's root URI. Common's static-manifest support validates the
structure, derives/verifies byte sizes and SHA-256 digests, parses full JSON-representable frontmatter, and emits
the official entry. The consumer may persist validated snapshots so listing does not rehash every large file.
One snapshot must own the manifest and the bytes served by Resource reads; separate independently mutable
manifest and read callbacks are not a sufficient consistency guarantee.

Agent OS constructs and publishes the snapshot atomically from reviewed managed input or an authorized custom
revision. It owns retention and current-revision selection. Common has no database, background refresh worker,
origin trust store or revision-purge policy. Its reference package fixture can use an in-memory static bundle;
Agent OS uses its authoritative storage. Do not introduce a generic dynamic-content service just to serve this.

**Revision URI proposal:** `skill://agent-os/<logical-key>/<revision>/work/SKILL.md` with supporting
`skill://agent-os/<logical-key>/<revision>/work/references/checklist.md`.
The `work` segment matches frontmatter `name`; revision precedes that directory. The `agent-os` URI authority is
an organizational label, **not** the trusted origin. The Harness supplies the independent installation/origin ID.
A new revision gets new URIs; old URIs never silently return replacement bytes.

**Whole-Skill visibility is accepted for Agent OS v1.** Consumer authorization filters access to whole Skills,
not individual manifest members. A denied Skill is concealed; an allowed entry retains its complete manifest.
Apply that same decision to its entry and every supporting-file Resource read, including direct revision URIs;
a caller cannot bypass it by skipping `skills/get`. Do not create per-caller partial manifests or require
separate variants merely to implement access filtering. Publish separate complete content variants only when a
genuine audience/content requirement calls for them.
Common enforces completeness and consistent availability mechanics; Agent OS chooses its visibility rule and
Access Control supplies the decision. A partial `skills/list` is not denial: an omitted but authorized Skill
must still work through `skills/get`, followed by authorized `resources/read`.

**Frontmatter handling:** never curate away unknown fields, `license`, `metadata`, or `allowed-tools`. Validate
required format constraints and JSON representability without executing YAML tags or following includes. Reject
ambiguous duplicate keys, unsupported YAML values and expansion beyond parser limits at publication, with a
repairable validation message. Do not normalize original file bytes on read. Any intentional authored
normalization happens before revision identity/digests are fixed. Parser/library selection remains decomposition.

### Capability composition, not a general plugin framework

One Resource handler owns each standard method across planning and Skills. The Skills extension owns its two
methods and references the shared Resource-serving contract. Derive discovery from composed handlers. Validate
that Skills has list/get and readable Resource support; reject a Skills-only declaration with no Resources.

Extend composition only as necessary to union **distinct extension IDs** under `extensions`. Identical settings
for the same ID may coalesce; unequal settings fail rather than recursively merging booleans or taking the last
registration. Preserve standard-family conflict checks and duplicate-method rejection. Never advertise
`directoryRead: true` merely because some other provider knows directories. This is a small extension-map rule,
not permission for arbitrary providers to rewrite each other's discovery.

### Safe errors and cache semantics

Use central protocol responses: invalid cursor/URI or unknown/unavailable resource/Skill is safe `-32602`,
unimplemented methods `-32601`, unexpected provider/serialization failure generic `-32603` with once-only
redacted diagnostics. Keep HTTP authentication/challenge behavior outside the capability and retain
TASK-00105's Origin, mirror and invocation rejection order. Resources do not return Tool `isError` envelopes.

Emit `ttlMs: 0`, `cacheScope: private` by default for list/read/get/template results. Validate optional cache
values; all pages of a list retain one scope. Only the consumer may designate genuinely caller-independent public
content. Immutable bytes alone do **not** make access public. Avoid HTTP shared-cache reuse of caller-sensitive
JSON-RPC POST responses. No ETag, conditional read, byte-range or cache-invalidation service is required in v1.

Provider cursors are opaque, bounded and scoped to the operation/catalog selection. Validate tampering and wrong
method/provider use; they carry no authority and never bypass fresh filtering. A simple signed stateless cursor
is one implementation option, not a new session store requirement. Do not expose concealed URIs or counts in a
cursor. A stale cursor returns a safe error and the client restarts automatically from page one. Unless the
consumer supplies snapshot pagination, catalog changes can produce gaps/duplicates as allowed by MCP; no false
cross-page snapshot promise. Skill **entries and revisions**, unlike the overall list, remain atomic/immutable.

## 2. Restrictions, defaults and recovery

**P = protocol requirement; I = correctness/security invariant; D = proposed operational default;
H = optional hardening.** Defaults are starting values for later implementation qualification, not ratified
API constants. Use one small immutable limits configuration with named validated overrides, not a policy DSL.
Normal configuration, cursor recovery, cache eviction and transient read retry require no human approval.

| Kind | Restriction/default | Failure prevented | Recovery / owner |
|---|---|---|---|
| P | Complete manifest; exact raw size/hash; all frontmatter; own `SKILL.md` entry exactly once | Host approves different or incomplete content from what it receives | Reject invalid publication; repair bundle and publish a new revision. Host rejects mismatched reads. |
| I | Exact provider ownership; URI validation; no URI-to-arbitrary-path/URL fallback | Traversal, SSRF, alternate-scheme and symlink escape | Unknown/denied safe result. Consumer explicitly registers content. If a later filesystem adapter is justified, validate containment and symlinks at access, not just startup. |
| I | Same current authority for enumeration/get/read; no metadata or bytes before allowance | Direct lookup bypasses filtered discovery, or a manifest leaks denied file names | Reauthenticate or obtain legitimate access; never change a cursor or URI to recover access. |
| I | Atomic static snapshot and immutable revision addresses | Manifest computed over old bytes while Resource reader serves new bytes | Retry exact read once for transient corruption; quarantine persistent mismatch and repair storage. Never silently move an active session to “latest”. |
| D | List pages at most 50 entries and 2 MiB serialized metadata; individual Skill entry stays atomic | Unbounded catalog responses and partial manifests | Return fewer entries plus cursor. Require a single entry to fit the metadata budget at publication; validate override relationships. |
| D | URI at most 2 KiB; cursor at most 4 KiB; frontmatter at most 64 KiB parsed/serialized and bounded nesting | Allocation/parser abuse, huge opaque tokens | Reject with actionable safe validation detail; publisher shortens metadata or operator explicitly tunes validated limits. These sizes are Common profile defaults, not MCP requirements. |
| D | Requests at most 1 MiB, JSON nesting at most 64, checked while reading before full decode | A guard on invocation count cannot prevent one oversized allocation | Consumer ingress and shared HTTP reader reject oversize (HTTP 413); reduce request or tune bound. Coordinate the additive shared safeguard with the HTTP owner, not a second endpoint. |
| P/D | Support 512 files / 16 MiB raw total per static Skill; use those as server publication defaults | Violating the host interoperability floor or publishing unexpectedly huge bundles | Reject larger bundle at publication or explicitly configure larger supported budgets; explain to publisher. No truncation. |
| D | Raw file read up to 16 MiB, with encoded-response allowance up to 100 MiB for JSON escaping/Base64 plus bounded metadata | Advertising a valid manifest whose file can never fit a response, or measuring only raw bytes | Validate raw/encoded limits together and terminate bounded encoding on excess; smaller application documents are encouraged. This worst-case ceiling is not a memory-allocation target or routine payload size. |
| I | Shared private cache is partitioned by authorization context; hash reuse is not authorization reuse | Another caller receives cached private metadata/bytes | Revalidate live entitlement before new protected use; evict context cache on credential/origin change. Server cannot revoke already disclosed knowledge. |
| D | TTL zero/private; bounded client retries with backoff; no background refetch merely because TTL expired | Stale authority assumptions and refresh storms | Re-fetch on demand; restart stale list automatically; show actual network failure after bounded retries. |
| I | No truncating verified Skill bytes to fit Pi output | Digest can pass on bytes different from content ultimately treated as the Skill | Verify exact content; if model context cannot hold it, fail activation clearly or use an explicit host-owned bounded presentation without claiming full activation. Publisher can split instructions. |
| H | Content-addressed storage, manifest signing, stricter deployment quotas or allowlisted MIME types | Additional supply-chain/operational risks beyond the basic profile | Optional consumer choices; signatures/federation infrastructure are not v1 prerequisites. |

A larger valid file in a static Skill can approach the whole 16 MiB total. Do not select an unrelated 1 MiB
read/response default that makes that advertised file impossible to load. With the URI/frontmatter defaults above,
a 512-member manifest fits the proposed atomic-entry budget; qualify boundary cases including JSON escaping.
Time budgets, concurrent requests and rate keys belong to existing consumer invocation/runtime controls. Bounded
stream reads and serialization must avoid eagerly reserving the entire maximum for every request.

For Agent OS's pinned sessions, a changed manifest at the **same immutable URI** is a server correctness failure,
not an ordinary content update. Automatic refetch can confirm a transient problem but cannot bless replacement
bytes. A newly selected revision follows Agent OS's already-required linked-continuation rule. Ordinary cache
misses and first-party loading do not need new approval dialogs; new execution authority is outside this scope.

## 3. Adjacent capabilities: assessed, not accumulated

Classifications: **1 existing work**, **2 required for first delivery**, **3 later enhancement**,
**4 unnecessary for current goals**. “Existing work” includes planned work, not only implemented capability.

| Capability | Concrete consumer need / disposition | Class and owner |
|---|---|---|
| Read-only search/filter Tools | Find a relevant authorized planning document without enumerating every record | **1** mechanics: TICKET-00024; Agent OS implements query semantics separately. Not a new Common search API. |
| Authorized mutation Tools | Apply a human-accepted proposal through the same application use cases as other entrypoints | **1** invocation and protected interactions: TICKET-00024/00026; Agent OS owns application authority/idempotency/expected revisions. |
| Resource links in Tool results | Search summaries link to exact documents for lazy reading | **2** integration requirement, assigned to the existing TASK-00106/00107 output surface when reconciled. Do not create another Tool output implementation; current text/structured planning alone is not proof links will survive. |
| Prompts | A portable client could offer argument-driven server prompt selection | **3** only after a concrete client journey needs it. Harness prompt templates/personas alone do not require `prompts/list/get`. |
| Completions | An interactive picker could autocomplete prompt/template arguments | **4** now: no parameterized template or MCP prompt in the first journey. Search Tool filters are not the completion API. |
| Resource templates | A client might construct addresses from parameters | **4** now: concrete links and exact lookup suffice; return an empty URI-template list. This does not exclude ordinary Skill template files. |
| Skill template files / rendering | Agent reads a nested reusable template as supporting content | **2** ordinary file reads; server-side template rendering is deferred. No rendering engine is required to serve the original bytes. |
| Directory reading | Agent asks which static supporting files exist | **3** protocol method; held complete manifest already describes nested paths in v1. Do not advertise the RPC; preserve nested file reads. |
| Subscriptions / listChanged | Update a live catalog or document picker without manual refresh | **3**; refresh on demand now. Requires separate long-lived subscription authorization/revocation and delivery, not reuse of Tool request SSE by assertion. |
| MCP Tasks extension | Reconnect to a long-running MCP operation with a durable handle | **3**, only for a demonstrated async request/result lifecycle. Not needed for bounded reads; never a substitute for Agent OS TASK records or durable Workflow authority. |
| MCP Apps | Embed interactive HTML inside a capable chat host | **4** for this goal: Pi terminal and Agent OS's own browser UI do not require chat iframe applications. |
| Streaming / progress | Show useful status during slow Tool execution | **1** TICKET-00025. Ordinary content read is a bounded direct JSON result; no new partial Skill/file streaming protocol. |
| Cancellation | Stop request work when caller disconnects | **1** cooperative Tool mechanism; do not invent rollback or Workflow cancellation on dropped content reads. |
| Authentication / OAuth | Identify an Agent/client and protect the HTTP route | **1** existing HMAC composition and planned TICKET-00027; consumer selects route/authentication. No new authorization extension in this EPIC. |
| Resource/Skill authorization seam | Conceal denied entries and enforce direct reads after discovery/revocation | **2** neutral mechanics; consumer/Access Control own decisions and identity. |
| Diagnostics | Diagnose malformed provider output or integrity/storage fault without leaking files/secrets | **1** shared unexpected-failure sink; **2** new capability-safe classifications and bounds using that sink. MCP logging/subscription UI is **3**, not required. |
| Request/content limits | Prevent one request or bundle exhausting memory or the host context | **2** small validated bounds, coordinated with shared HTTP rather than a general policy system. |
| Status/progress/evidence Resources | Read authoritative status and bounded evidence through a standard content interface | **2** generic read mechanism; actual Workflow/Artifact projections stay downstream and do not block static Skill delivery. |

Authorities: [Resources](https://modelcontextprotocol.io/specification/2026-07-28/server/resources),
[Tools](https://modelcontextprotocol.io/specification/2026-07-28/server/tools),
[Prompts](https://modelcontextprotocol.io/specification/2026-07-28/server/prompts),
[Completion](https://modelcontextprotocol.io/specification/2026-07-28/server/utilities/completion),
[Tasks extension overview](https://modelcontextprotocol.io/extensions/tasks/overview),
[Apps overview](https://modelcontextprotocol.io/extensions/apps/overview), and the pinned Skills authority in
[the evidence note](WF-044-mcp-resources-skills-evidence.md). Optional adjacent extensions were assessed for fit,
not qualified as supported against Common's base revision.

## 4. Dependencies and downstream handoffs

### Common (supporting work areas; approved allocation lives in the planning records)

- **Semantic foundation:** reuse TASK-00104. Compose Resources and Skills and narrowly repair extension-map
  aggregation/prerequisite validation within the new capability boundary; preserve existing public contracts.
- **HTTP integration:** consume TASK-00105, now merged into local `develop` at `52ff756` (PR #163); this planning
  worktree originally predated that implementation and was fast-forwarded to `52ff756` for publication. TASK-00121
  owns additive bounded
  request reading/decoding, coordinated with the HTTP owner. Resource URI mirrors are already present;
  prove them through the new capability rather than rewriting transport rules.
- **Tool links:** reconcile the standard `resource_link` need with TASK-00106/00107 before integration freezes.
  Any necessary additive output support belongs to TASK-00122, not a silent expansion of those existing TASKs.
  Common's endpoint-coexistence/search-to-read acceptance depends on those Tools, while standalone Resources/Skills
  contract tests can proceed independently. Semantic-only fixtures do not replace endpoint acceptance.
- **Authentication:** existing consumer HMAC can support the first-party proof; OAuth qualification depends on
  TASK-00113 if that deployment chooses Bearer. Do not force OAuth or new auth extensions onto the local profile.
- **SSE/confirmation:** leave TASK-00108–00112 unchanged. They are not prerequisites for bounded static reads,
  nor should this new delivery become a prerequisite for their acceptance.
- **Verification/release:** classify all new public APIs, serialization and behavior in Common's compatibility
  manifest. Later implementation runs exact coverage/full gate plus relevant pinned protocol scenarios. No new
  release number, release mutation or hosted-check claim is made here.

### Agent OS (separate owned work, not Common implementation scope)

1. Reconcile WF-013/014's SDK-only server-ownership wording with the accepted Common delivery boundary in a
   separately authorized downstream update. Preserve its
   first-party trust, immutable Harness snapshots, no policy in Common and official-SDK client preference.
2. Deliberately consume a released/qualified Common version containing the mechanisms; current locked v1.2.0
   cannot serve them. The production consumer's locked dependency update is its own verified operation.
3. Bind a consumer route to existing authentication and request-scoped live authority. Planning owns read-only
   search, document rendering and mutation Tool adapters over its existing application operations. Where those
   operations are still planned, implement them in their own planning, not inside an MCP adapter.
4. Respect current Markdown authority until approved database cutover. A bounded interim provider may expose
   registered, revision-bound authorized Markdown records; it must not become a second writer or generic
   repository filesystem server. A future database provider changes storage, not public authority semantics.
5. Harness owns static bundle publication, logical/revision URI mapping, retention, managed/custom reconciliation,
   catalog visibility, immutable session bindings and complete provenance. No speculative third-party federation
   or dynamic Skills in v1.
6. Deliver the actual Pi client: compatible MCP request/HTTP headers, discovery and paging; permission-filtered
   Tools and Resource reads; origin-tagged Skill registry; direct `skills/get`; verified lazy load/read; held
   manifests; collisions; origin-separated cache; session/compaction/restart evidence. A header package is not this.
7. Status queries read Workflow truth and label progress observations/evidence provenance. Dropped SSE, a failed
   read or a cached snapshot cannot finish, cancel, approve or reopen Workflow state. Application commands remain
   its only mutation path.

### Access Control / consumer security integration (separate)

- Resolve actual current authenticated Agent/user and delegated-user limits; map authority to provider visibility
  and each application operation. Do not assume the planned `RequiresAgentPermission` integration exists today.
- Define the minimal Resource/Skill bundle visibility decision using existing permission/object-scope services;
  authorize direct `skills/get`, direct file reads and revision reads as well as discovery/search.
- Recheck live revocation at server requests and before authority-bearing application operations, irrespective of
  pinned Harness content. Token validity or successful HMAC is not object permission.
- Clarify protected cached-content use with the Harness: previously delivered bytes may be offline evidence, but
  offline cache does not grant new live actions or satisfy current permission checks. No promise to remotely erase
  model context after revocation.
- Consumer chooses credentials, route topology, audit retention and safe authorization diagnostics. Common neither
  defines new permission strings nor owns an identity/policy store.

## 5. End-to-end proposed journey

Actor: an authorized Agent acting within an explicit user/repository scope in the trusted Pi Harness.
All operations use MCP `2026-07-28` request `_meta`; HTTP uses POST, both required Accept media types and matching
protocol/method headers. `resources/read` also mirrors the URI in `Mcp-Name`. No `initialize` or protocol session.

1. Harness authenticates to its explicitly registered first-party origin and calls `server/discover`. It sees
   `tools`, `resources`, and `extensions["io.modelcontextprotocol/skills"]`; no unsupported notification flags.
2. The Agent invokes the consumer's read-only planning-search Tool with repository/type/status criteria.
   The Tool calls the existing authorized Planning query. Result includes a short safe summary and a
   `resource_link` to one exact EPIC revision. No write, event or Workflow transition occurs.
3. Harness requests that URI with `resources/read`. The provider checks current document authorization again
   and returns exact revision Markdown plus private/zero-TTL cache hints. A denied direct URI produces the same
   safe not-found outcome as an absent one. The Harness records source/revision, not a filesystem path.
4. Harness calls `skills/list`, following `nextCursor` until absent. It builds origin-qualified summaries from
   complete entries only; **zero** `resources/read` calls happen during listing. An explicit Skill URI absent
   from the listing can instead be confirmed by `skills/get` under the same current authority.
5. The session selects `skill://agent-os/<key>/<revision>/work/SKILL.md` and pins its full manifest and origin.
   On actual load, Harness reads only that `SKILL.md`, verifies byte length and SHA-256 and compares parsed
   frontmatter field-for-field. It injects origin-tagged content without granting `allowed-tools` or shell access.
6. Instructions refer to `references/review/checklist.md`. Harness resolves that nested path under the same
   Skill root and held manifest; the server rechecks whole-Skill access and returns only that revision's requested
   file. Harness verifies it before use. Nested templates and scripts remain unread until separately needed;
   reading a template returns original bytes without rendering, and reading a script does not execute it.
   Harness can derive the nested directory view from the manifest without a directory RPC or archive download.
7. A later catalog revision does not change these URIs or the active snapshot. Missing/corrupt bytes fail visibly;
   an unlisted file cannot be added by following a link. A new selected revision uses an explicit linked session
   continuation, not routine repeated content approval. Revoked authority denies further server reads/actions.

Applying a planning change is a distinct optional continuation: explicit authorized user Apply -> mutation Tool
-> existing application command with expected revision/idempotency -> truthful acknowledgement/resource link.
Loading the Skill itself is not Apply, authorization or a Workflow transition.

## 6. Behavior-focused acceptance scenarios for later decomposition

These are design/evidence inputs, not checked completion boxes. EPIC-00007 owns the accepted Common acceptance
boundary; rows owned by the Harness, Agent OS persistence or live Access Control remain downstream proof.

| Scenario | Observable result | Owner |
|---|---|---|
| Register planning + Skill providers + another test extension | One Resources family, both extension IDs, correct methods; conflicting same-ID settings and duplicate method/URI ownership fail composition. | Common |
| Skills without Resources or false directory claim | Composition fails before serving misleading discovery. Resources-only composition stays valid. | Common |
| Authorized listing and direct read | Filtering precedes pagination; safe descriptors and exact content; hidden documents/files disclose no names, counts, manifests or bytes. | Common fixture + consumer policy integration |
| Direct URI absent from listing | Authorized `skills/get` and read succeed despite partial list; denied and unknown behave identically. | Common + Agent OS |
| Revoke between list/get/read | Next protected request denies access even with a saved cursor, Skill entry or revision URI. No new bytes or application dispatch. | Consumer/Access Control |
| Traverse or reinterpret URI | `..`, encoded traversal/separators, foreign namespace, unsupported scheme and symlink escape cannot access unregistered storage or trigger HTTP/DNS. Valid percent-encoded identity is not repeatedly decoded into another resource. | Common provider contract + any storage adapter |
| Static bundle consistency | List/get and every read refer to the same retained revision, including Unicode/CRLF text and binary assets; size/hash cover original bytes. Unknown frontmatter fields survive. | Common + publisher |
| Structured multi-file revision | Manifest contains root `SKILL.md`, nested references, ordinary templates, scripts and assets. Load fetches only `SKILL.md`; later nested-file read fetches only that file. No flattening, mandatory archive, bulk prefetch, rendering or script execution occurs; directory RPC may remain absent. | Common + Harness |
| Whole-Skill authorization | Denied Skill is absent from discovery and unavailable by direct get/file URI. Authorized Skill retains every manifest member, regardless of which files have been read. No per-caller redaction or duplicate variant is needed for access filtering. | Consumer/Access Control + Common integration |
| Concurrent publication | A reader sees either old or new **complete** entry; old revision reads stay old. No mixed manifest or overwritten historical bytes. | Agent OS persistence |
| Invalid bundle | Missing/duplicate `SKILL.md`, duplicate/out-of-root URI, invalid name/path, incomplete set, wrong size/hash or unsupported frontmatter fails publication/serving safely. | Common + publisher |
| Pagination boundaries | Deterministic authorized continuation, no split Skill entries, empty catalog, final-page cursor omission and empty-string cursor handled correctly. Invalid/stale cursor safely restarts; no snapshot consistency claim on changing catalog. | Common + client |
| Cache separation | Private result cannot be served to another authorization context. TTL override validates and pages retain scope. Integrity verification still runs independently of freshness. | Common + Harness |
| Content limits | Exactly 512 entries and 16 MiB Skill total supported; one oversized request rejected before unbounded read; oversized publication rejected without truncation. Binary/escaped-text wire expansion and one large atomic manifest fit declared budgets. | Common + Harness |
| HTTP integration | Valid Resource URI mirror reaches provider once; missing/mismatched mirror, denied Origin/guard or failed authentication never reaches content retrieval. Extension methods use only their specified mirrors. | Common endpoint fixture |
| Search -> Resource link -> read | Link survives semantic Tool serialization, summary is safe, selected revision reads lazily, row-level denial is not bypassed by Tool availability. | Common link fixture + Agent OS journey |
| Pi discovery is lazy | Connecting, paging and selecting from entries perform no Skill-file reads; explicit direct URI works without a list entry. | Harness; official no-prefetch scenario |
| Pi integrity rejection | Digest, byte size, frontmatter mismatch and unlisted supporting file each prevent use; no automatic latest/local/cross-origin fallback. | Harness; official verification plus owned tests |
| Pi origin and collisions | Same name/URI from different sources cannot overwrite identity; local alias collision is visible and qualified choice remains available. Cache never enters filesystem Skill discovery after restart. | Harness |
| Pinned session and recovery | Cache miss refetches exact bytes without approval churn; corrupted immutable revision stops after bounded retry; later revision requires linked continuation. Rehydrated cache is verified and retains origin. | Harness + Agent OS |
| Host-authority separation | `allowed-tools`, scripts and nested `SKILL.md` do not grant execution/activation. Arbitrary host execution and sandbox management are not content-serving operations. | Harness |
| Status and uncertain mutation | Reads/progress neither transition Workflow nor imply command rollback; after an uncertain mutation outcome, query the existing application operation instead of blindly repeating it. | Agent OS |
| Safe failure | Provider exception produces one redacted diagnostic and generic error without file paths, credentials or content. Unknown file returns error, not successful empty `contents`. | Common |

Qualify server Resources/Skills using the pinned official scenarios in the evidence note, with package-owned
fixtures exercising nonempty, paginated and adversarial content. Optional directory scenarios should be explicitly
not applicable while unadvertised; a skipped required path is not success. Run the client scenarios against the
actual Harness and add owned tests for origin, live authorization, cache, context and Workflow boundaries the
wire harness cannot observe. No need to bring browser, sandbox or five new framework implementations into a
content-only proof that reuses the existing PSR endpoint.

## Accepted boundary and remaining fog

[WF-045](../tickets/WF-045-select-the-first-resources-and-skills-delivery.md) records John's accepted choices:

1. **Separate Common EPIC**, leaving the existing Tools EPIC's destination intact. Agent OS must deliberately
   adopt Common and still build its own client; downstream ownership wording remains to be reconciled there.
2. **Static, revision-addressed structured multi-file Skills**, with complete manifests and lazy individual-file
   retrieval. Whole-Skill authorization does not flatten content, require archives or prefetch files. Nested
   references, ordinary templates, scripts and other resources remain supported. Directory RPC and server-side
   rendering may wait; separate content variants are created only when genuinely needed.

Exact public type names, parser, cursor encoding and measured limit tuning are ordinary implementation design,
not new human gates. Remaining evidence work is the concrete compatible SDK/client pin, immutable-provider
storage proof and live cross-project authorization integration. Dynamic content, federation, general policy
frameworks, arbitrary execution and sandbox administration remain excluded rather than speculative tickets.
