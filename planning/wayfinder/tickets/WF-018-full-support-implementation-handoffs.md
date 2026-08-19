# Synthesize full-support implementation handoffs

**Labels:** `wayfinder:grilling`, `wayfinder:domain-modeling`
**Mode:** HITL
**Status:** Open
**Planning synthesis:** Complete
**Map:** [Fight Framework Portability and Starter Projects](../fight-framework-portability-map.md)
**Specifications:** [PRD-00016 — Fight Package and Starter Repository Ownership](../../specs/00016-PRD.md), [PRD-00017 — Fight AccessControl Identity and Authentication Lifecycle](../../specs/00017-PRD.md), [PRD-00018 — Framework Starter Product and Walking-Slice Acceptance](../../specs/00018-PRD.md)
**Depends on:** [Prove persistence, UnitOfWork, and walking-slice portability](WF-017-persistence-unit-of-work-and-walking-slice-prototypes.md)

## Question

How should the resolved decisions become Fight Common implementation tickets, a Fight AccessControl
repository plan, five starter repository plans, complete documentation work, and coordinated release
gates without creating one oversized cross-repository build?

## Must decide

- Fight Common PRDs and vertical tickets for compatibility repair, typed JSend, namespace shims,
  framework adapters, Composer metadata, documentation, and dependency matrices;
- bootstrap contract for creating Fight AccessControl and each public-source starter with canonical planning,
  `AGENTS.md`, architecture checks, `./bin/build`, CI, licensing, security policy, and contribution docs;
- first `project-symfony` extraction slice and independent Laravel, Yii, CodeIgniter, and Slim walking
  slices;
- capability-by-capability expansion order from walking slice to every audited Application contract;
- repository-owned migrations, Managed Role/Permission definitions, administrator commands, OpenAPI, and complete
  `/client` journeys;
- user Active Sessions and super-admin session-management UI/API journeys, including audit evidence and
  responsive revocation across application instances;
- project-owned security-audit persistence and enrichment, including atomic command failure or a durable
  audit handoff for every classified sensitive mutation;
- repository-owned realtime infrastructure and security handoffs, including the portable private-update
  contract, Mercure Hub container compositions, Laravel Reverb, topic/channel authorization, client
  reconnection, operational monitoring, and secret/key rotation;
- consumer-repository ownership of every AccessControl adapter, with no Adapter layer in the shared
  AccessControl package and one reusable behavioral conformance suite applied to each implementation;
- PostgreSQL and MySQL or MariaDB verification, exact coverage, static analysis, PHPCS, architecture,
  frontend, documentation, and security review gates;
- `0.x.y` release milestones, Composer create-project proof, GitHub template readiness, supported-line
  policy, and separate installable-release gates; and
- one-home traceability so the umbrella map links repository-local plans without duplicating their
  acceptance criteria or status.

## Accepted handoff direction

- Create Fight AccessControl and the five starter repositories as the real implementation homes; do not
  build nested project copies in Fight Common.
- Bootstrap Fight AccessControl first, then create all five public-source starter repositories as one
  parallel-ready foundation wave. Their planning, build, CI, licensing, security, and contribution
  foundations may proceed concurrently, but login implementation waits for its required Fight Common
  and Fight AccessControl contracts.
- Keep only shared prerequisite coordination tickets and one bootstrap/handoff ticket per new repository
  in Fight Common. After bootstrap, author and track detailed implementation tickets in the repository
  that owns the work; the umbrella map links those plans without copying their acceptance criteria or status.
- Fight AccessControl and every `project-*` starter are public source. Fight AccessControl completed its public-
  surface, licensing, security, documentation, clean-clone, and hosted-build gates during T-00061 under MIT.
  Public source does not authorize a version tag, Packagist registration, template enablement, or release.
- Permit starter login implementation once its required Fight Common and Fight AccessControl contracts,
  reusable behavioral conformance tests, and human UAT contract are merged and green at immutable public
  commit references. Do not wait for public tags or the coordinated `1.2.0` release to gather real framework
  integration evidence; tagged versions remain a later compatibility and public-readiness gate.
- When a starter exposes a shared-contract defect, pause the shared slice frontier, ticket and fix the defect
  in the repository that owns the contract, issue a new immutable commit reference, and rerun the
  relevant conformance evidence across all five starters. Keep framework-specific defects and fixes local;
  do not churn the other repositories or introduce starter-private shared-contract workarounds.
- Carry one user-valued vertical slice through all five starter repositories before beginning the next
  shared use case. A failure pauses that shared frontier while passing repositories remain green.
- Decompose each ordered AccessControl use case as one repository-owned Domain/Application ticket in Fight
  AccessControl when shared behavior must change, followed by one implementation ticket in each starter.
  Advance only after every required ticket in that six-repository slice packet is green. When the existing
  package already satisfies the use case, omit the shared ticket and record that no package change is needed.
- Let `project-symfony` satisfy its login ticket by extracting and adapting the existing Symfony behavior.
  Laravel, Yii, CodeIgniter, and Slim implement independently from the shared behavior and conformance
  contracts using framework-native adapters and composition; they do not port Symfony infrastructure.
  Keep all five implementations on the same login frontier despite that different starting point.
- Keep persistence invariants and reusable behavioral tests in Fight AccessControl while each starter owns
  its migrations, ORM or database records, and framework-native schema details. Require equivalent behavior
  and constraints on PostgreSQL and MySQL or MariaDB; do not require byte-for-byte identical DDL.
- Keep the versioned OpenAPI and public realtime JSON Schemas in Fight AccessControl as non-runtime contract
  assets. Each starter owns its HTTP actions, complete `/client` application, committed generated TypeScript,
  and a build-time drift check against those shared schemas; AccessControl gains no framework adapter layer.
- Include required security-audit behavior in the same slice packet as every classified sensitive mutation.
  Each starter proves either an atomic same-database mutation and audit commit or an atomic durable handoff
  that retries delivery to a separate audit store. The slice cannot pass if required audit evidence may be
  silently lost after the business mutation commits.
- Introduce repository-owned realtime infrastructure with the authorized user-listing slice, the first
  accepted private cross-browser invalidation journey, rather than during bootstrap. Laravel uses Reverb;
  Symfony, Yii, CodeIgniter, and Slim use the accepted Mercure line. Prove authorization and client behavior
  against that user outcome instead of building a generic realtime platform first.
- Keep the first realtime slice to the minimum complete path: private authorization, live transport,
  two-browser invalidation, reconnect, and refetch. Track monitoring, failure recovery, server-side
  revocation or disconnect behavior, and secret or key rotation as separate repository-local hardening
  tickets that must pass before public readiness.
- Limit each bootstrap ticket to durable foundation documentation. Every capability ticket updates and
  verifies its own API, configuration, operations, UAT, and troubleshooting guidance, followed by ongoing
  repository-local documentation and security audits. Fight Common does not duplicate those
  repository-owned instructions.
- Make `0.1.0` the first release milestone only after invitation and activation, delivery recovery,
  login, cold-load restoration, logout, user session management, refresh continuity, password reset, and
  authenticated password change are green across all five starters. Make `0.2.0` complete the remaining
  account lifecycle through email change, disable and enable, deletion and restoration, and pending-invitation
  correction. Group later capabilities into intentional `0.x` milestones; authorize `1.0.0` only through a
  separate stability decision.
- Use the repository's own clean-clone build, framework-native functional tests, browser automation, fake
  credentials, exact local URL, expected result, cleanup, and human UAT as its implementation evidence.
- Every `project-*` repository is public source from bootstrap. Public source does not authorize a release tag,
  Packagist publication, template enablement, or create-project distribution; those installable-release effects
  require separate approval and verification.

## Implementation graph

- [T-00061 — Bootstrap the Fight AccessControl Repository and Transfer Authority](../../tickets/00061-TICKET.md)
  is complete and established the shared package's local PRD-00001 authority, preserving Fight Common
  PRD-00017 only as immutable source provenance, plus its immutable bootstrap receipt.
- [T-00062 — Bootstrap the Public Symfony Starter and Transfer Authority](../../tickets/00062-TICKET.md),
  [T-00063 — Bootstrap the Public Laravel Starter and Transfer Authority](../../tickets/00063-TICKET.md),
  [T-00064 — Bootstrap the Public Yii Starter and Transfer Authority](../../tickets/00064-TICKET.md), and
  [T-00065 — Bootstrap the Public CodeIgniter Starter and Transfer Authority](../../tickets/00065-TICKET.md)
  are complete and each transferred its public-source foundation and repository-local planning authority.
  [T-00066 — Bootstrap the Public Slim Starter and Transfer Authority](../../tickets/00066-TICKET.md) is the
  remaining handoff.
- [T-00067 — Verify All Six Repository Handoffs and Close WF-018](../../tickets/00067-TICKET.md) is blocked by all
  six bootstraps and owns the final umbrella link, receipt, dependency, and authority-transfer audit.
- Repository creation, tags, publication, and distribution remain execution-time actions requiring their own
  authorization; approval of this planning graph performs none of them.

## Planning synthesis state

The Fight Common planning layer is complete as of 2026-08-17. PRD-00014 through PRD-00016 produced the
Fight Common implementation and handoff graph through T-00067. Running `/to-tickets` for PRD-00017 or
PRD-00018 in Fight Common is intentionally a no-op: detailed capability tickets belong to Fight AccessControl
and the five starter repositories after their respective authority transfers. WF-018 remains open until
T-00066 and T-00067 complete the remaining repository handoff and final umbrella verification.

## Resolution boundary

Close the Wayfinder only after every repository has an approved, implementation-ready local handoff
and the cross-repository dependency order is explicit. Do not create production code, publish packages,
or tag releases while resolving this ticket.
