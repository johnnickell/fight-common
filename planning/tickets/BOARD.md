# Ticket Board

Operational execution view for Fight Common. Ticket files are canonical for status and blocking edges; this
board is canonical for recommended order. IDs identify artifacts only. Update this file whenever ticket
status, dependencies, or roadmap priority changes.

Last updated: 2026-09-11

## “What’s Next?” Contract

When `/ask-matt` or a plain “What’s next?” is invoked:

1. **Human decision:** return the item under **Now** when it still requires judgment.
2. **Implementation:** return the first ticket under **Ready Frontier**.
3. If the question is unqualified, return both targets. Never choose by ticket number alone.

## Now

No human decision is currently required. T-00099 verified the first hosted documentation publication. The next
implementation target is T-00102, which applies ADR 0026, simplifies the package gate, and certifies a fresh exact
candidate before T-00017 final release acceptance. Package publication remains a later, separately authorized
effect.

## Wayfinder Review

No active Wayfinder map currently has an unblocked review candidate. The closed
[Fight Common Documentation Presentation](../wayfinder/fight-common-documentation-presentation-map.md) map hands
off to [EPIC-00005](../epics/00005-EPIC.md), PRD-00020 through PRD-00022, and T-00088 through T-00099. Wayfinding
and implementation planning are complete; new uncertainty should begin in a new map rather than reopening this
one.

## Ready Frontier

| Rank | Ticket | Parent PRD | Current slice |
|------|--------|------------|---------------|
| 1 | [T-00102 — Simplify the Fight Common Pre-Submit Gate Before 1.2](00102-TICKET.md) | [PRD-00006](../specs/00006-PRD.md) | Apply ADR 0026, prove the lean package-owned gate, and certify a fresh exact release candidate. |

## Waiting

Waiting tickets retain their executable or human-review status; their position here is derived from unfinished
blocking edges.

| Suggested Order | Ticket | Parent PRD | Waiting On |
|-----------------|--------|------------|------------|
| 3 | [T-00035 — Publish the Certified Tag and GitHub Release](00035-TICKET.md) | [PRD-00011](../specs/00011-PRD.md) | T-00017 |
| 4 | [T-00041 — Verify Packagist Projection and Published Installation](00041-TICKET.md) | [PRD-00011](../specs/00011-PRD.md) | T-00035 |
| 5 | [T-00087 — Transfer the Canonical Pre-Submit Quality Gate to Every Starter](00087-TICKET.md) | [PRD-00018](../specs/00018-PRD.md) | T-00041 |

## Needs Info

No tickets currently require a decision authority.

## Final Priority

This ticket is the final `1.2` acceptance boundary and remains last until every higher-priority release item is
complete.

| Rank | Ticket | Parent PRD | Why Last |
|------|--------|------------|----------|
| 2 | [T-00017 — Complete 1.2 Compatibility and Release Acceptance](00017-TICKET.md) | [PRD-00006](../specs/00006-PRD.md) | Accept the documentation-complete, simplified, freshly certified T-00102 candidate before publication. |

The completed projection path now combines ordered at-least-once handling and fail-stop retry with durable,
named monotonic DBAL checkpoints proven on SQLite, MySQL, and PostgreSQL. Synchronous dispatcher failure isolation,
in-memory event publication, durable named publication cursors, transactional failure evidence, and composable PSR-3
logging are complete across the same database matrix. T-00016 now documents the complete integration and
operations surface through single-source executable SQLite DBAL examples, including the delivered Symfony provider
autoconfiguration path that composes private, dependency-injected mapping providers through the portable Event
Mapper contract. T-00017 remains the board's final pre-publication priority after T-00102 replaces the earlier
candidate with documentation-complete, simplified quality-gate and release-certification evidence. The
quality-gate path is complete: the canonical coding standard, its mechanical, member-layout, and semantic
documentation migrations, and its reusable fixer repairs are complete without baselines or suppressed legacy
violations. Architecture enforcement is complete with exact layer allowances, mandatory unassigned-token
failure, and Scheduler command execution through the required `ProcessRunner`. Core iterator, timezone, and
validation-service coverage is exact without inline exclusions or test-order dependence. Filesystem,
template-buffering, StatsD, process, FTP, and Scheduler failure boundaries are now deterministic without
public-contract or production filesystem-semantic changes. The permanent coverage gate now rejects every
production exclusion directive and fails closed unless the provided Clover project metrics prove exact statement
equality. T-00028 composes those contracts into the shared host-neutral gate, T-00029 wraps it in the canonical
non-interactive disposable local build with locked and latest-compatible dependency modes, and T-00030 runs the
shared gate directly in hosted CI after ephemeral latest-compatible dependency resolution. T-00031 completes the
path with opt-in tracked pre-commit enforcement that delegates to the default local build without duplication.

The Unreleased changelog now presents Event Sourcing as an additive `1.2.0` capability, records the metadata
isolation behavior change, and reconciles the typed JSend and canonical Symfony Messenger compatibility surfaces.
This satisfies T-00017's release-notes criterion only; its certification and complete-acceptance blockers remain
unchanged.

The release foundation and maintainer-only isolation remain historical outcomes through T-00032, T-00040, and
T-00068. ADR 0025 supersedes their simulated release machinery. T-00056 proved the real thin-certification seam;
T-00102 will apply ADR 0026 and certify a fresh exact candidate before T-00017 acceptance. T-00035 and T-00041
remain separately authorized publication and Packagist qualification outcomes. T-00036 through T-00039 and T-00042 through
T-00043 are closed `wontfix`.

The Fight Common compatibility authority and installed-package consumer harness are complete through T-00047.
Scheduler compatibility is complete through T-00048. JSend, Symfony and Doctrine namespace, transactional
UnitOfWork, private Mercure, shared PSR/container composition, and the first Laravel and CodeIgniter walking
slices are complete through T-00049 to T-00053, T-00059, T-00060, T-00069, T-00070, T-00073, and T-00077.
The Yii native and fallback lane is complete through T-00072. Native framework lanes continue through T-00054,
T-00071, and T-00074. T-00075 binds all five repository-owned starter receipts after independent review and
merge; T-00056 now cites their immutable identities in the Fight Common `1.2.0` certification evidence required
before T-00017 can close release acceptance.

The repository handoff path has completed T-00061 through T-00067. Fight Common's specification and umbrella-ticket
layer is complete: PRD-00017 produces no detailed Fight Common implementation tickets, while PRD-00018 uses
T-00087 to transfer ADR 0026's lean pre-submit gate into five repository-local tickets after public 1.2
qualification. Fight
AccessControl and all five starters create their capability tickets locally; T-00067 verified the six authority
transfers and closed WF-018 without centralizing their builds, local acceptance criteria, visibility decisions, or
release state in Fight Common.

## Recently Closed

| Ticket | Parent PRD | Outcome |
|--------|------------|---------|
| [T-00036 — Implement Maintenance-Line Lifecycle Decisions](00036-TICKET.md) | [PRD-00012](../specs/00012-PRD.md) | Closed `wontfix`; ADR 0025 does not retain maintenance-line simulation or support-clock machinery. |
| [T-00037 — Release the Oldest Affected Supported-Line Patch](00037-TICKET.md) | [PRD-00012](../specs/00012-PRD.md) | Closed `wontfix`; concrete patches require a fresh scoped workflow rather than simulated release routing. |
| [T-00042 — Forward-Port Patches Through Newer Affected Lines](00042-TICKET.md) | [PRD-00012](../specs/00012-PRD.md) | Closed `wontfix`; each future forward port remains a separately reviewed and certified change. |
| [T-00038 — Add Release Skills and Catalog Routing](00038-TICKET.md) | [PRD-00013](../specs/00013-PRD.md) | Closed `wontfix`; one certification command does not justify six release-phase skills. |
| [T-00043 — Add the State-First Dispatcher and Journey-Card Runbook](00043-TICKET.md) | [PRD-00013](../specs/00013-PRD.md) | Closed `wontfix`; the retired state machine no longer needs an operator dispatcher. |
| [T-00039 — Integrate CI and Validate the Final Epic Handoff](00039-TICKET.md) | [PRD-00013](../specs/00013-PRD.md) | Closed `wontfix`; expensive certification remains release-time and is not duplicated in everyday CI. |
| [T-00055 — Retire the In-Repository Framework Fixture Plan](00055-TICKET.md) | [PRD-00015](../specs/00015-PRD.md) | Closed `wontfix`; the five real starter repositories own framework dependency and compatibility evidence, so Fight Common will not regain nested Composer projects or a combined framework application. |

## Recently Done

| Ticket | Parent PRD | Outcome |
|--------|------------|---------|
| [T-00099 — Verify the First Hosted Pages Publication](00099-TICKET.md) | [PRD-00022](../specs/00022-PRD.md) | Verified the exact protected Pages deployment for merge commit `40f347f` and the public site over HTTPS, including all canonical routes, interactions, assets, metadata, sitemap, responsive behavior, and branded 404; recorded the published route and anchor compatibility commitments. |
| [T-00098 — Accept the Initial Documentation Release Candidate](00098-TICKET.md) | [PRD-00022](../specs/00022-PRD.md) | Accepted the repaired predeployment candidate pinned at `098d18f` through strict artifact and editorial review, exact asset baselines, responsive and accessibility evidence, John's separate Brave and VoiceOver passes, and the complete canonical build; T-00099 later verified the hosted publication separately. |
| [T-00097 — Complete Operations and Framework Guidance](00097-TICKET.md) | [PRD-00021](../specs/00021-PRD.md) | Completed the Operate Workloads guides, direct framework-free and supported-framework adoption paths, accurate provider and unavailable-capability boundaries, and current maintainer guidance with responsive Atlas presentation. |
| [T-00096 — Complete Connect Systems Component Guidance](00096-TICKET.md) | [PRD-00021](../specs/00021-PRD.md) | Completed all nine remaining Connect Systems guides with accurate portable contracts, adapters, security and operational boundaries, syntax-highlighted examples, direct discovery, and an accepted responsive Atlas presentation. |
| [T-00095 — Complete Domain and Application Component Guidance](00095-TICKET.md) | [PRD-00021](../specs/00021-PRD.md) | Completed all ten Domain and Application guides with accurate ownership, portable adoption paths, current symbols, behavior and failure guidance, reciprocal discovery, contrast-safe syntax highlighting, consistently aligned examples, and responsive Atlas presentation. |
| [T-00094 — Establish the Component Guide Contract Through Mail](00094-TICKET.md) | [PRD-00021](../specs/00021-PRD.md) | Established Mail as the reference component guide with a portable application-first journey, accurate Symfony and Laravel delivery paths, supported framework fallbacks, operational boundaries, reciprocal discovery, and behaviorally proven recipient replacement. |
| [T-00093 — Explain Hexagonal Architecture and CQRS Visually](00093-TICKET.md) | [PRD-00021](../specs/00021-PRD.md) | Delivered John's approved diagram-led Hexagonal Architecture and CQRS guide, responsive color-independent message flows, roomier shared guide navigation, and corrected site-wide copy controls and feedback. |
| [T-00101 — Rewrite Root Authorship and Re-certify Framework Consumers](00101-TICKET.md) | [PRD-00015](../specs/00015-PRD.md) | Rewrote the root email with byte-equivalent trees and topology, migrated all affected refs, and merged five exact-candidate consumer re-certifications after hosted CI. |
| [T-00056 — Replace the Release Framework with Thin Certification](00056-TICKET.md) | [PRD-00014](../specs/00014-PRD.md) | Removed the simulated release framework and 418 release tests; retained one fail-fast exact-commit certification seam with three dependency lanes, archive and clean consumer proof, package-surface comparison, and cited starter receipts. |
| [T-00075 — Compose the Five Booted Starter Support Receipts](00075-TICKET.md) | [PRD-00015](../specs/00015-PRD.md) | Bound the five repository-owned, lowest/latest booted starter receipts to immutable merged commits and content identities after independent review; T-00056 now cites them in exact-candidate certification. |
| [T-00092 — Deliver the Framework-Neutral Quick Start](00092-TICKET.md) | [PRD-00021](../specs/00021-PRD.md) | Delivered the approved framework-picker entry, honest pre-1.0 starter guidance, and an executable framework-neutral order-processing journey through individually explained, syntax-highlighted, copyable classes in event-before-handler order. |
| [T-00091 — Rebuild the Repository README as the Fight Common Entry Surface](00091-TICKET.md) | [PRD-00020](../specs/00020-PRD.md) | Rebuilt the concise repository entry surface with canonical themed identity, factual trust signals, inward architecture proof, representative capability routes, deterministic validation, and browser-qualified fallback behavior. |
| [T-00090 — Deliver the Atlas Deck Homepage and Documentation Shell](00090-TICKET.md) | [PRD-00022](../specs/00022-PRD.md) | Reimplemented the generated documentation against the human-approved Atlas Deck with dedicated homepage, guide, and article templates; preserved Material search, navigation, palette, and copy behavior; and passed desktop, compact, light, and dark visual QA. |
| [T-00089 — Create the Canonical Fight Identity Asset Family](00089-TICKET.md) | [PRD-00020](../specs/00020-PRD.md) | Added the approved Inward Port family mark, outlined Fight Common lockups, deterministic theme/favicon/README/social exports, validated usage guidance, and a human-approved small-size specimen. |
| [T-00088 — Build One Reproducible Documentation Artifact](00088-TICKET.md) | [PRD-00022](../specs/00022-PRD.md) | Added exact, separated PHP and Python Docker contexts; repository-owned preview, strict build, and artifact validation; pull-request artifact proof; and protected `main` Pages deployment without generated-branch writes. |
| [T-00086 — Complete Laravel Async Messaging and Private Publication Ownership](00086-TICKET.md) | [PRD-00015](../specs/00015-PRD.md) | Published reusable Laravel async command, async event, and private publication adapters through the bounded Fight Common providers. |
| [T-00085 — Emit PSR-7-Compatible HMAC Request Headers](00085-TICKET.md) | [PRD-00014](../specs/00014-PRD.md) | Corrected HMAC timestamp emission to the PSR-7 string contract and proved the unchanged canonical signature through a real signer-to-authenticator journey. |
| [T-00084 — Publish the Complete Laravel Filesystem Adapter](00084-TICKET.md) | [PRD-00015](../specs/00015-PRD.md) | Published the complete Laravel-native filesystem adapter, composed Laravel's filesystem service where available, filled the remaining Fight operations with PHP primitives, and proved the public contract with exact coverage. |
| [T-00058 — Publish the Framework Support Matrix and Activation Guide](00058-TICKET.md) | [PRD-00015](../specs/00015-PRD.md) | Published the normative framework support matrix and capability-scoped activation guide, with Composer-suggestion and navigation contract coverage; confirmed the complete canonical build and exact coverage. |
| [T-00054 — Prove Optional Adapter Dependency Modes and Production Isolation](00054-TICKET.md) | [PRD-00014](../specs/00014-PRD.md) | Proved dependency modes and selected-framework isolation; ADR 0025/T-00056 later moved continuing proof from `bin/release compatibility` to thin certification. |
| [T-00074 — Deliver CodeIgniter Native Adapters and Prove Fallbacks](00074-TICKET.md) | [PRD-00015](../specs/00015-PRD.md) | Shipped native CodeIgniter cache, JSend/error response, and named-route URL adapters with independently selectable service delegates; retained conformance-proven Symfony Mailer, Twig/PHP, and Symfony Filesystem fallbacks where native facilities are incomplete; and proved booted capability isolation, optional-package absence, public authority, and exact coverage. |
| [T-00071 — Deliver Laravel Native Adapters and Prove Fallbacks](00071-TICKET.md) | [PRD-00015](../specs/00015-PRD.md) | Shipped Laravel password, cache, JSend/error, URL, Blade, mail, and private-broadcast adapters with independently selectable providers; wired PSR-3 directly; and proved exact native FileStorage and accepted Symfony, Guzzle, Process, and null-metrics fallbacks where Laravel is incomplete. |
| [T-00072 — Deliver Yii Adapters, Providers, and Proven Fallbacks](00072-TICKET.md) | [PRD-00015](../specs/00015-PRD.md) | Shipped native Yii DB transaction, routing, and View adapters; proved capability-scoped strict-container composition; retained tested Symfony Mailer and Filesystem fallbacks after exact native prototype gaps; and classified stable Yii Queue as unavailable. |
| [T-00073 — Deliver CodeIgniter Queued Messaging, Transactions, and Service Delegates](00073-TICKET.md) | [PRD-00015](../specs/00015-PRD.md) | Added official Queue command/event envelope transport with visible push and retry failures, the native transactional UnitOfWork, independently selectable service delegates, real CodeIgniter lifecycle proof, development-only dependencies, and exact public-authority coverage. |
| [T-00070 — Deliver Laravel Queued Messaging, Transactions, and Service Providers](00070-TICKET.md) | [PRD-00015](../specs/00015-PRD.md) | Added Laravel queued command/event envelopes with post-commit at-least-once semantics, exact serialized-payload reconstitution and byte-identical retry replay proof, the narrow transactional UnitOfWork, independently selectable providers, and the shared Doctrine/Laravel transaction conformance suite. |
| [T-00069 — Deliver Shared PSR Interoperability and Portable Container Composition](00069-TICKET.md) | [PRD-00014](../specs/00014-PRD.md) | Added PSR-15/17/18 and PSR-6/16 adapters, explicit Fight-container capabilities, Slim route generation, package/manifest authority, offline-consumer proof, and exact coverage. |
| [T-00060 — Publish Private Realtime Updates Through Mercure](00060-TICKET.md) | [PRD-00014](../specs/00014-PRD.md) | Added the separate private-publication port and Mercure adapter; preserved public publication; proved causal transport failure, copied-package public/private behavior, optional dependency isolation, documentation, and public API authority. Hub compatibility-mode configuration remains starter-owned deployment evidence. |
| [T-00077 — Publish the Canonical Doctrine Transactional UnitOfWork Adapter](00077-TICKET.md) | [PRD-00014](../specs/00014-PRD.md) | Published the transaction-only canonical Doctrine adapter, retained the silent deprecated 1.x UnitOfWork and Repository-path journey, and proved both surfaces through manifest, installed-consumer, documentation, architecture, and exact-coverage authority. |
| [T-00059 — Publish the Additive Transactional UnitOfWork Boundary](00059-TICKET.md) | [PRD-00014](../specs/00014-PRD.md) | Published the additive transactional contract; retained the silent deprecated `UnitOfWork::commit()` surface; proved Doctrine nested-transaction rejection, installed-package legacy and narrow consumers, manifest authority, and exact coverage; and repaired linked-worktree quality-gate Git visibility. |
| [T-00053 — Publish Canonical Doctrine Data Type Paths](00053-TICKET.md) | [PRD-00014](../specs/00014-PRD.md) | Published thirteen canonical Doctrine type paths; preserved the deprecated 1.x identities with their complete public surface; and proved all twenty-six paths through registration, schema, conversion, consumer, and compatibility-authority checks. |
| [T-00052 — Publish Capability-Scoped Symfony Service Container Paths](00052-TICKET.md) | [PRD-00014](../specs/00014-PRD.md) | Published seven capability-scoped Symfony compiler passes; preserved deprecated independently registerable `1.x` identities; reconciled public-API authority; and proved real-container compatibility with exact coverage. |
| [T-00051 — Publish Neutral Message Handlers and Canonical Symfony Messenger Paths](00051-TICKET.md) | [PRD-00014](../specs/00014-PRD.md) | Published neutral complete-message handlers and canonical Symfony Messenger paths; preserved legacy `1.x` FQCNs without runtime notices; and proved real Messenger registration, exact envelope fidelity, installed-package compatibility, and exact coverage. |
| [T-00050 — Publish Canonical Symfony HTTP, Filesystem, and Routing Paths](00050-TICKET.md) | [PRD-00014](../specs/00014-PRD.md) | Published canonical Symfony HTTP, middleware, filesystem, and routing identities; retained legacy `1.x` paths without runtime notices; and added registration and interoperability evidence. |
| [T-00049 — Deliver Typed JSend Through the Symfony Response Boundary](00049-TICKET.md) | [PRD-00014](../specs/00014-PRD.md) | Added framework-neutral typed JSend success, fail, error, and pagination semantics; published the canonical Symfony response under `Adapter/Http/Symfony`; preserved the deprecated raw-array `1.x` path; and proved both entry points through authenticated copied-package evidence. |
| [T-00076 — Correct Architecture Drift and Canonicalize Repository Guidance](00076-TICKET.md) | [PRD-00014](../specs/00014-PRD.md) | Moved canonical serializers to Application while preserving standalone Domain compatibility, retained webhook validation through deprecation, classified the new public API, and established accurate repository and planning guidance. |
| [T-00048 — Restore Scheduler 1.x Construction Compatibility](00048-TICKET.md) | [PRD-00014](../specs/00014-PRD.md) | Restored the exact published `1.1.0` construction and command journey, added explicit portable `ProcessRunner` composition, and proved copied-package compatibility with authenticated fail-closed evidence. |
| [T-00034 — Compose Certification Evidence and Compatibility Lanes](00034-TICKET.md) | [PRD-00011](../specs/00011-PRD.md) | Added content-addressed package handoffs and governed evidence, complete attributed dependency and compatibility lanes, immutable certification manifests, durable failed/indeterminate stops, and append-only certification run-state receipts. |
| [T-00033 — Prove the Normal Feature Package Journey](00033-TICKET.md) | [PRD-00011](../specs/00011-PRD.md) | Added the `package` command as the phase after `prepare`: revalidates the phase handoff, derives and approves the exact bounded packaging effect set, binds the candidate OID and deterministic rootless archive digest, and proves approval, refusal, drift, and already-satisfied postconditions offline. |
| [T-00057 — Pin Fight Common's Symfony Components to the Current Supported Line](00057-TICKET.md) | [PRD-00015](../specs/00015-PRD.md) | Pinned Fight Common's own Symfony floor to the current-only support window, aligning with the accepted framework support policy. |
| [T-00047 — Establish the Public API Authority and Consumer Harness](00047-TICKET.md) | [PRD-00014](../specs/00014-PRD.md) | Established the exact `1.1.0` baseline, scanner-authenticated public policy, stable fail-closed structural findings, and a distinct installed-package consumer copy with exact machine-readable receipts. |
| [T-00068 — Move Release Coordination into a Maintainer-Only Module](00068-TICKET.md) | [PRD-00019](../specs/00019-PRD.md) | Established development-only `Fight\Release` and consumer isolation; ADR 0025/T-00056 later reduced the module to the thin certifier. |
| [T-00040 — Prove Resumable Release Runs and Phase Handoffs](00040-TICKET.md) | [PRD-00010](../specs/00010-PRD.md) | Delivered unique plan-bound runs, append-only crash-safe transitions, atomic projections, live input and postcondition revalidation, precise stop recovery, and canonical content-addressed preparation evidence and handoffs. |
| [T-00032 — Establish Release Inspection, Plans, and Boundary Fakes](00032-TICKET.md) | [PRD-00010](../specs/00010-PRD.md) | Delivered complete category-derived inspection, typed immutable release approval and plan identity, credential-free deterministic boundary fakes, capability firewalls, and confined content-addressed planning artifacts without external release effects. |
| [T-00067 — Verify All Six Repository Handoffs and Close WF-018](00067-TICKET.md) | [PRD-00016](../specs/00016-PRD.md) | Verified the six canonical repository plans and immutable bootstrap receipts, retained only portfolio links and dependency order, and closed WF-018 without authorizing implementation or release effects. |
| [T-00066 — Bootstrap the Public Slim Starter and Transfer Authority](00066-TICKET.md) | [PRD-00016](../specs/00016-PRD.md) | Established the public-source Slim foundation, repository-local PRD-00018 authority, canonical local and hosted builds, immutable clean-checkout receipt, and accepted handoff without authorizing a release. |
| [T-00065 — Bootstrap the Public CodeIgniter Starter and Transfer Authority](00065-TICKET.md) | [PRD-00016](../specs/00016-PRD.md) | Established the public-source CodeIgniter foundation, repository-local PRD-00018 authority, canonical local and hosted builds, an immutable clean-clone receipt, and accepted cross-repository handoff without authorizing a release. |
| [T-00062 — Bootstrap the Public Symfony Starter and Transfer Authority](00062-TICKET.md) | [PRD-00016](../specs/00016-PRD.md) | Established the public-source Symfony foundation, repository-local PRD-00018 authority, canonical local and hosted builds, immutable clean-clone receipt, and accepted cross-repository handoff without authorizing a release. |
| [T-00063 — Bootstrap the Public Laravel Starter and Transfer Authority](00063-TICKET.md) | [PRD-00016](../specs/00016-PRD.md) | Established the public-source Laravel foundation, repository-local PRD-00001 authority for PRD-00018, canonical local and hosted builds, immutable clean-clone receipt, and accepted cross-repository handoff without authorizing a release. |
| [T-00064 — Bootstrap the Public Yii Starter and Transfer Authority](00064-TICKET.md) | [PRD-00016](../specs/00016-PRD.md) | Established the public-source Yii foundation, repository-local PRD-00001 authority for PRD-00018, canonical local and hosted builds, immutable clean-clone receipt, and accepted cross-repository handoff without authorizing a release. |
| [T-00061 — Bootstrap the Fight AccessControl Repository and Transfer Authority](00061-TICKET.md) | [PRD-00016](../specs/00016-PRD.md) | Established the public MIT Fight AccessControl package, repository-local PRD-00001 authority, canonical local and hosted builds, immutable clean-clone receipt, and accepted cross-repository handoff without authorizing a release. |
| [T-00031 — Add the Tracked Pre-Commit Build Gate](00031-TICKET.md) | [PRD-00009](../specs/00009-PRD.md) | Added opt-in tracked pre-commit enforcement with repository-root resolution, disconnected stdin, exact default-build delegation, unchanged status propagation, documented activation and bypass, and no pre-push duplicate. |
| [T-00030 — Run Latest-Compatible Verification in CI](00030-TICKET.md) | [PRD-00009](../specs/00009-PRD.md) | Replaced duplicated hosted quality commands with ephemeral latest-compatible resolution followed by direct shared-gate execution, preserving supported PR targets, database services, and explicit failure propagation. |
| [T-00029 — Deliver the Disposable Local Build and Dependency Modes](00029-TICKET.md) | [PRD-00009](../specs/00009-PRD.md) | Added one non-interactive local build that composes tracked or latest-compatible dependency resolution, invoking-user ownership, disposable databases, linked-worktree support, and the shared quality gate in one PHP container. |
| [T-00028 — Establish the Shared Executable Quality Gate](00028-TICKET.md) | [PRD-00009](../specs/00009-PRD.md) | Added one visibly ordered, fail-fast host-neutral gate with deterministic process coverage and current-invocation-only exact Clover enforcement for agent and CI-prepared environments. |
| [T-00016 — Document Event Sourcing Integration and Operations](00016-TICKET.md) | [PRD-00006](../specs/00006-PRD.md) | Published the complete navigable lifecycle and operations guide with framework-free composition first, shipped Symfony mapping integration, migration and recovery contracts, and single-source executable aggregate, projection, and publication examples. |
| [T-00015 — Add Symfony Event-Mapping Autoconfiguration](00015-TICKET.md) | [PRD-00006](../specs/00006-PRD.md) | Added container-tested provider auto-tagging and reference composition through the portable Event Mapper registration and validation path while preserving framework-free construction and exact coverage. |
| [T-00027 — Enforce Zero-Exclusion Exact Coverage](00027-TICKET.md) | [PRD-00009](../specs/00009-PRD.md) | Added a fail-closed production-directive and Clover-metric gate with deterministic process fixtures, exact 9,033/9,033 statement evidence, and no stale-report orchestration leakage from T-00028. |
| [T-00046 — Flatten the PHPCS Standard Implementation Layout](00046-TICKET.md) | [PRD-00007](../specs/00007-PRD.md) | Flattened the unreleased ruleset, sniff filesystem, and PHP namespace; established `Phpcs.*` custom identifiers while retaining the `FightCommon` standard name and behavior. |
| [T-00024 — Make Scheduler Coverage Exact](00024-TICKET.md) | [PRD-00009](../specs/00009-PRD.md) | Removed all ten Scheduler exclusions through deterministic runtime and lock controls while preserving public behavior, exact `ProcessRunner` outcomes, and complete coverage. |
| [T-00026 — Cover Process and FTP Integration Boundaries](00026-TICKET.md) | [PRD-00009](../specs/00009-PRD.md) | Removed the process and FTP exclusions through real Symfony process execution and an isolated native-delegating FTP test boundary while preserving public contracts and exact complete coverage. |
| [T-00025 — Cover Adapter Failure Boundaries](00025-TICKET.md) | [PRD-00009](../specs/00009-PRD.md) | Removed seven filesystem, template-buffering, and StatsD exclusions through deterministic test controls and an internal UDP sender while preserving public contracts and exact complete coverage. |
| [T-00023 — Eliminate Core Coverage Exclusions](00023-TICKET.md) | [PRD-00009](../specs/00009-PRD.md) | Removed core iterator, timezone, and validation-service exclusions, covered public failure behavior, eliminated hidden test-order dependence, and retained exact complete statement coverage. |
| [T-00022 — Introduce Mandatory Architecture Enforcement](00022-TICKET.md) | [PRD-00008](../specs/00008-PRD.md) | Enforced exact runtime and Standards dependency allowances with mandatory violation and unassigned-token checks, repaired Scheduler through `ProcessBuilder` and required `ProcessRunner`, and retained exact complete coverage without baselines or skips. |
| [T-00014 — Persist and Log Publication Operational State](00014-TICKET.md) | [PRD-00005](../specs/00005-PRD.md) | Added monotonic DBAL publication cursors, transactional first-evidence failure recording, shared SQLite/MySQL/PostgreSQL conformance, and required-delegate PSR-3 logging while preserving exact coverage. |
| [T-00021 — Migrate and Enable Documentation Rules](00021-TICKET.md) | [PRD-00007](../specs/00007-PRD.md) | Resolved 288 documentation findings across 124 production files, retained semantic and static-analysis contracts, enabled all documentation rules without suppressions, and preserved exact complete coverage. |
| [T-00020 — Migrate and Enable Member Layout Rules](00020-TICKET.md) | [PRD-00007](../specs/00007-PRD.md) | Enabled four canonical member-layout rules, corrected 273 order and spacing violations without changing nonblank production content, aligned Rector ownership, and preserved exact complete coverage. |
| [T-00045 — Repair Mechanical PHPCBF Fixer Output](00045-TICKET.md) | [PRD-00007](../specs/00007-PRD.md) | Corrected canonical strict-types whitespace and return indentation, proved second-pass PHPCBF idempotence, and preserved exact complete coverage. |
| [T-00019 — Migrate and Enable Mechanical Coding Rules](00019-TICKET.md) | [PRD-00007](../specs/00007-PRD.md) | Enabled five canonical mechanical rules, corrected and semantically reviewed 105 production violations, preserved exact complete coverage, and captured two PHPCBF fixer regressions in T-00045. |
| [T-00018 — Package the Reusable FightCommon Coding Standard](00018-TICKET.md) | [PRD-00007](../specs/00007-PRD.md) | Published the optional path-free standard, accepted Omphalos parity, stable compatibility contracts, dist-like consumer discovery, complete custom-sniff coverage, and copy-ready integration guidance. |
| [T-00044 — Eliminate Environment-Skipped Database Tests](00044-TICKET.md) | [PRD-00009](../specs/00009-PRD.md) | Added skip-free disposable MySQL/PostgreSQL verification, complete and fast PHPUnit workflows, scoped cleanup, CI fail-on-skipped enforcement, and exact Clover evidence. |
| [T-00013 — Publish Committed Events with In-Memory Operational State](00013-TICKET.md) | [PRD-00005](../specs/00005-PRD.md) | Added committed-event publication with monotonic named cursors, idempotent bounded failure records, explicit infrastructure-failure propagation, and crash-retry duplicate coverage. |
| [T-00012 — Isolate Synchronous Dispatcher Handler Failures](00012-TICKET.md) | [PRD-00005](../specs/00005-PRD.md) | Added complete two-phase handler fan-out with ordered transient failure aggregation and raw infrastructure-failure propagation across simple and service-aware dispatchers. |
| [T-00011 — Persist Projection Checkpoints with DBAL](00011-TICKET.md) | [PRD-00005](../specs/00005-PRD.md) | Added independently installable DBAL checkpoints with atomic monotonic saves, named reset, and shared lifecycle/concurrency conformance across SQLite, MySQL, and PostgreSQL. |
| [T-00010 — Run Projections with In-Memory Checkpoints](00010-TICKET.md) | [PRD-00005](../specs/00005-PRD.md) | Added stable projector contracts, ordered bounded projection with per-event checkpoints, fail-stop retry, crash-duplicate coverage, and isolated reset-to-zero rebuild behavior. |
| [T-00009 — Implement EventSourcedRepository](00009-TICKET.md) | [PRD-00004](../specs/00004-PRD.md) | Added stable aggregate definitions and a generic save/find repository with ordered reconstitution, expected-version appends, empty-save no-op, and fail-stop release behavior. |
| [T-00008 — Implement the Doctrine DBAL Event Store](00008-TICKET.md) | [PRD-00004](../specs/00004-PRD.md) | Added SQLite/MySQL/PostgreSQL DBAL storage with transactional sequence allocation, exact retry recovery, and CI-backed conformance verification. |
| [T-00007 — Implement the In-Memory Event Store](00007-TICKET.md) | [PRD-00003](../specs/00003-PRD.md) | Added atomic mapped append, exact retry classification, historical hydration, ordered polling, and the reusable Event Store conformance suite. |
| [T-00006 — Implement Event Mapping and Upcasting](00006-TICKET.md) | [PRD-00003](../specs/00003-PRD.md) | Added explicit direct/provider mapping registration, stable bidirectional event identity, validated sequential upcasting, and fail-closed current-event hydration. |
| [T-00005 — Define Stored-Event and Event Store Contracts](00005-TICKET.md) | [PRD-00003](../specs/00003-PRD.md) | Defined stable stream identity, immutable stored-event envelopes, ordered Event Store operations, exact retries, optimistic concurrency, and prefix-stable global visibility. |
| [T-00004 — Implement Aggregate Lifecycle](00004-TICKET.md) | [PRD-00003](../specs/00003-PRD.md) | Added explicit record, replay, version, one-time release, fail-closed routing, and private-constructor reconstitution behavior through a framework-free aggregate contract. |
| [T-00003 — Isolate Metadata Across Message Envelopes](00003-TICKET.md) | [PRD-00003](../specs/00003-PRD.md) | Made every command, query, and event envelope copy mutable metadata on construction and access while preserving same-ID derivation, serialization, and equality. |
| [T-00001 — Evaluate a Consumer Migration Pilot](00001-TICKET.md) | [PRD-00001](../specs/00001-PRD.md) | Selected Omphalos Metis `Operation` as the one bounded, non-release-blocking migration pilot after an equal-criteria comparison with Fight CMS and the Fight project template. |
| [T-00002 — Establish Event Sourcing Context and Decisions](00002-TICKET.md) | [PRD-00002](../specs/00002-PRD.md) | Established the ubiquitous language and durable architectural decisions that unblock both Event Sourcing implementation branches. |
