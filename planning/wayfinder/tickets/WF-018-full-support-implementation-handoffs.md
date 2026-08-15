# Synthesize full-support implementation handoffs

**Labels:** `wayfinder:grilling`, `wayfinder:domain-modeling`
**Mode:** HITL
**Status:** Open
**Map:** [Fight Framework Portability and Starter Projects](../fight-framework-portability-map.md)
**Depends on:** [Prove persistence, UnitOfWork, and walking-slice portability](WF-017-persistence-unit-of-work-and-walking-slice-prototypes.md)

## Question

How should the resolved decisions become Fight Common implementation tickets, a Fight AccessControl
repository plan, five starter repository plans, complete documentation work, and coordinated release
gates without creating one oversized cross-repository build?

## Must decide

- Fight Common PRDs and vertical tickets for compatibility repair, typed JSend, namespace shims,
  framework adapters, Composer metadata, documentation, and dependency matrices;
- bootstrap contract for creating Fight AccessControl and each initially private starter with canonical planning,
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
  policy, and the coordinated full-suite announcement gate; and
- one-home traceability so the umbrella map links repository-local plans without duplicating their
  acceptance criteria or status.

## Accepted handoff direction

- Create Fight AccessControl and the five starter repositories as the real implementation homes; do not
  build nested project copies in Fight Common.
- Keep every new repository private during its bounded compatibility incubation. Private status permits
  implementation and cross-repository testing; it does not authorize publication, Packagist registration,
  template visibility, or an announcement.
- Carry one user-valued vertical slice through all five starter repositories before beginning the next
  shared use case. A failure pauses that shared frontier while passing repositories remain green.
- Use the repository's own clean-clone build, framework-native functional tests, browser automation, fake
  credentials, exact local URL, expected result, cleanup, and human UAT as its implementation evidence.
- Make public visibility a separately approved transition after the repository's compatibility, security,
  documentation, and clean-clone gates pass. Repositories may become public independently; coordinated
  full-suite communication remains a later all-repository gate.

## Resolution boundary

Close the Wayfinder only after every repository has an approved, implementation-ready local handoff
and the cross-repository dependency order is explicit. Do not create production code, publish packages,
or announce full support while resolving this ticket.
