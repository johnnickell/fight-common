# Fight Common project profile

## Identity and architecture

Library: `johnnickell/fight-common`. Read the root AGENTS.md and local engineering standards. Domain context lives in the applicable architecture/component documentation and accepted planning ADRs; inspect for a current CONTEXT.md before introducing or relocating that authority.

| Layer | Path | Boundary |
|---|---|---|
| Domain | `src/Domain/` | Pure business logic; no Application/framework dependencies |
| Application | `src/Application/` | Domain, PHP internals, PSR contracts and the allowlisted scheduler expression contract; no Adapter dependency |
| Adapter | `src/Adapter/` | Framework/infrastructure implementations, depending inward |
| Standards | `src/Standards/` | Orthogonal coding standard with no runtime dependents |

Common owns reusable capabilities and promised package behavior; consumers own application policy, permissions,
routes, composition and operations unless an explicit package contract assigns them to Common. Apply
[Architecture's ownership rule](../../docs/engineering/standards/Architecture.md#package-and-consumer-ownership)
without removing public compatibility aliases or framework integrations. Preserve Application-owned MCP/JSend
semantics, the portable container, capability-first adapters, the exact `Cron\CronExpression` allowance, and current
transaction contracts; no application-only namespace or blanket external-effect transaction rule is adopted.

`deptrac.php` and `deptrac.runtime.php` enforce these boundaries. Source namespace is `Fight\Common\`, tests `Fight\Test\Common\`, and release tooling `Fight\Release\`. Tests mirror the source paths. Inspect Composer's actual autoload configuration for release tooling locations.

Value objects are immutable, validate construction, and use named public factories such as `fromString()`/`fromArray()`; invalid values use DomainException as appropriate to their contract. Specifications extend CompositeSpecification and implement `isSatisfiedBy(mixed $candidate): bool`, composing with `and()`/`or()`/`not()`. Typed collections use `ArrayList::of()` and `HashTable::of()` according to their actual signatures.

Compatibility is against tagged releases, not earlier development commits. Never-released additions remain
provisional until first tagged: revise their APIs, payloads and schemas directly without backward-compatibility
code, aliases, deprecations or migrations for prior development snapshots. Keep current validation/integrity checks
and actually released API guarantees intact. John explicitly confirmed this rule in
[TASK-00148's correction](../tasks/00148-TASK.md#current-correction--user-amendment-after-publication).

CQRS uses `CommandBus::execute()`, `QueryBus::fetch()`, and `EventDispatcher::trigger()`. Follow the existing MessageId, Meta and timestamp contracts. Deprecated public APIs remain supported for at least one released minor before removal in the next major. Preserve intentional extensibility of public base classes; final-by-default does not make a reusable abstract/base contract final. Retain framework-required entity mutability/proxy compatibility.

## Commands and verification

### Local-first acceptance and publication

The current workflow is **work → local verification → independent Review → independent QA → authorized Land →
human merge**. [TASK-00172](../tasks/00172-TASK.md) adopts Agent OS QA and the later completion checkpoint;
the 2026-09-28 local-first decision remains intact.
Fight Common is public, but visibility does not add a pre-publication acceptance gate. A valid local build receipt
and sufficient behavior evidence permit independent acceptance without a hosted run or PR. Work and review do
not push or create PRs to obtain CI; publication belongs to an explicitly authorized land operation.

The 2026-09-28 decision removed the former public-repository hosted *review* prerequisite. John clarified on
2026-10-09 that Fight Common has **never required hosted CI to pass before human merge**: the later instruction
requiring successful final-head Tests / Complete pre-submit gate and Deploy Docs / build for land completion was
incorrect. Both workflows remain useful independent evidence, but their results are advisory by default. Land
inspects available results, records missing/skipped/failing runs and their cause, and may mark a locally accepted,
QA-satisfied PR ready for **human** review/merge without green hosted checks. Do not label an unrun or failed job
passed, conceal a demonstrated product defect or substitute hosted output for required local proof. A specifically
accepted TASK requirement or an actual host-enforced rule may still require a hosted check; verify its identity
and satisfy it rather than inventing or bypassing it. The main-only Docs deploy job is not a feature-PR check.
No agent approval, merge, release, certification or deployment authority follows from this discretion.

TASK-00172 supersedes TASK-00115's implementation-complete `done` convention prospectively: implementation stays
non-terminal until independent Review acceptance and required QA are satisfied. Historical records retain their
original meaning. Ordinary land requires independent acceptance and applicable QA before marking a PR ready;
truthful administrative completion/PR metadata may follow the accepted candidate without making the agent's
readiness conditional on optional hosted CI. Preserve the accepted candidate, later administrative closeout,
observed hosted results and human merge decision as distinct facts under
[Delivery](../../docs/engineering/standards/Delivery.md#acceptance-and-administrative-closeout).
This correction does not claim any missing or failed hosted run passed, change historical review/landing receipts,
or weaken the local gate. TASK-00109's old hosted-only review finding remains governed by its recorded
local-evidence equivalence assessment.

`./bin/build` is the complete pre-submit gate: Composer validation, syntax, PHPCS, Deptrac, PHPStan, Rector dry run, Unit with exact coverage, Integration, Functional, documentation and read-only planning checks. Run it for implementation/build-input changes before commit/PR. Documentation-only follow-ups may retain verified earlier full-gate evidence with current targeted checks under [Testing](../../docs/engineering/standards/Testing.md). Save the local build log, actual exit result and tested-content mapping in an ignored run receipt linked from the TASK handoff. Apply Testing's local-acceptance boundary regardless of repository visibility; optional hosted results are reported after publication, with any specifically required hosted check treated according to its accepted scope or actual host rule. Let commit hooks complete; never use `--no-verify` or disable them because they are slow or inconvenient.

The build validates record-derived Board/Roadmap routing both before expensive preparation and at completion;
these checks stay offline and never rewrite records. Land additionally runs the mandatory live
[delivery pre-submit and final-handoff checks](shared-workflows.md#delivery-pre-submit-and-final-handoff).
They verify current PR metadata, not hosted CI success, and are not technical-acceptance prerequisites for Work/Review.

This library ignores composer.lock. Ordinary builds use `composer update`. Release tooling resolves its candidate lanes first and uses `FIGHT_COMMON_DEPENDENCY_PROFILE=resolved ./bin/build` to preserve that resolution; use this mode only in the release-owned candidate procedure described in [the release guide](../../release/README.md).

Tooling runs in the project's `fight-common` PHP container. For focused, noninteractive checks from the selected checkout:

```bash
docker run --rm -v "$PWD:/app:delegated" -w /app fight-common \
  php vendor/bin/phpunit tests/Domain/Specification/AndSpecificationTest.php
```

For focused PHPStan use `php vendor/bin/phpstan analyse --memory-limit=512M` in that container pattern;
`bin/quality` and `bin/phpstan` supply the same per-process limit, including parallel workers. This is not a total
container allocation or a concurrency limit. Use the same container pattern for Rector when appropriate. Inspect `./bin/*` before selecting them: interactive helpers may allocate a TTY or rebuild the image. Long detached gates must produce a complete log and explicit exit status; do not interpret timeout output as completion.

The coding-standard authority is `src/Standards/Phpcs/ruleset.xml` with the project's PHPCS composition. Fight Common must demonstrate its own strict conventions: root `phpcs.xml` enables multiline docblocks, checks declaration docblock alignment and ignores PHPCS suppression annotations. Repair violations instead of adding exclusions, lowering diagnostic severity, suppressing warnings or narrowing the scanned paths to make a gate pass. Changes to the published consumer defaults remain subject to [ADR 0004](../adr/0004-coding-standard-compatibility.md). Unit tests use the established UnitTestCase and Mockery helpers. Test methods follow `test_that_<subject>_<condition>()`, with `self::assert...`. Direct unit tests have `#[CoversClass(Target::class)]`; qualifying integration/journey tests may use `#[CoversNothing]`, never to hide missing direct coverage. Every PHPUnit test class carries explicit coverage metadata. Follow each suite's actual base-class contract.

Require exact 100% statement coverage of owned production code. Choose mocks/stubs/reals for the behavior being proved; real value objects and simple anonymous stubs are normally useful, and `$this->mock()` supplies Mockery collaborators. Preserve existing release tests/probes without adding packaging or release-process tests in any suite or harness; see the retained inventory below. Runtime, shipped coding-standard and framework-integration contract tests remain product verification. Direct validation of docs/planning is part of the gate, not justification for adding tests of Markdown or shell/configuration text.

### Retained release verification

The current checkout has no tracked release-process PHPUnit suite; do not resurrect historical suites. Retain:

- `bin/release` and `release/scripts/{certify,functions}.php`: clean candidate/version checks, baseline/latest/lowest
  dependency lanes with the complete product gate, archive generation and installed-consumer qualification.
- `release/consumer/{probe,functions}.php`: the installed-package public behavior probe invoked by certification.
- `release/src/Adapter/{PackageSurfaceInspector,PhpParserStructuralInventory}.php` and
  `release/src/Application/CertificationRecord.php`: installed public-surface/autoload checks and bound evidence.
- `release/fixtures/ComposerConsumer/composer.json` and `release/fixtures/PublicApiConsumer/public-api-probe.php`:
  retained consumer fixtures; the latter is a legacy representative probe, not an active standalone PHPUnit suite
  or the current certifier's probe.
- `release/starter-receipts.json`: retained historical starter evidence cited by certification, not rerun implicitly.

The complete product gate still checks release PHP syntax/style/static architecture without executing certification.
Existing Unit/Integration/Functional product suites remain intact. [Release certification](../../release/README.md)
is separately authorized, uses its actual owning command, and is not required for planning/standards adoption.
This inventory is not authority to expand packaging/release tests under another name.

### Approved DBAL schema compatibility exception

[TASK-00116](../tasks/00116-TASK.md) records the maintainer's approval to retain DBAL 4.4 support before 2.0
and permit four exact PHPStan `method.internal` diagnostics for `Doctrine\DBAL\Schema\Schema::__construct()`.
That constructor remains runtime-compatible in 4.5, but its new public editor replacement does not exist in 4.4.
The exception is limited to one occurrence in each of the four existing Event Sourcing DBAL schema builders,
using exact message, identifier and file matches in `phpstan.neon.dist`. Missing matches are allowed for DBAL 4.4,
where the constructor is public; additional occurrences and other internal calls remain reportable.

This is an explicit compatibility exception, not permission to suppress other findings, narrow scanned paths,
change PHPCS policy, or raise the dependency floor. Preserve both lower/current runtime evidence and the complete
ordinary product gate. Reconsider the exception for 2.0 only with an approved support-policy change; remove it when
the remaining supported range permits the public replacement. No automatic support removal is authorized.

## Planning and Git

Read [CONVENTIONS.md](../CONVENTIONS.md). Shared skills and every delegated Build role also read the
[shared workflow binding](shared-workflows.md) for target substitutions, authority, handoffs and evidence.
EPIC → TICKET → TASK; normally one TASK per PR. The generated [Board](../tasks/BOARD.md) exposes the active task/human decision and executable ready frontier. The [Roadmap](../ROADMAP.md#planning-frontier) separately derives decomposition work from live and archived children. [Automatic parent completion](../CONVENTIONS.md#automatic-parent-completion) closes eligible TICKETs and EPICs in the same child-completion operation. TASK metadata owns state, priority, blockers and PR references. [MIGRATION.md](../MIGRATION.md) preserves legacy identities.

After record changes, run `./bin/planning-check --write`, then `./bin/planning-check`. These are offline local
consistency checks, not delivery freshness. Routing and Land use the separate read-only `--check-delivery` mode under
[Conventions](../CONVENTIONS.md#current-delivery-handoffs); Land marks the current published human handoff with
TASK `delivery: awaiting-merge` and its verified PR URL. Clear that marker only after the planning owner reconciles
verified merge/closure; preserve historical delivery evidence. Archive only on an explicit request, with an inspected `./bin/archive-planning` dry run before apply.

New TASK branches use `feature/task-NNNNN-<slug>` from develop to develop with merge commits; new TASK PR titles use `TASK-NNNNN — <TASK title>`. Preserve established branch/PR identities and release/patch conventions. No direct commits to develop or main. Release branches start from develop and merge to main; then main merges back into develop. Released library repairs use patch branches and the approved supported-line policy rather than application hotfix branches. Read actual release tooling and accepted ADRs before any release mutation.

TASK `done` requires implementation acceptance, required verification, independent Review acceptance and
satisfied required [QA](../../docs/engineering/standards/QA.md), including justified overall N/A. Keep pending
Review/QA explicit on a non-terminal TASK and record delivery separately. Use the single
[Review catalog and canonical handoff](../../docs/engineering/standards/Review.md).
One independent reviewer may conduct both passes; material contributors cannot accept their work. Preserve legacy
report history and use [Delivery's provenance bridge](../../docs/engineering/standards/Delivery.md#reconciling-reviewed-revisions)
for proven mechanical reconciliation, not semantic drift.

## Runtime, runs and delivery

Choose main checkout or isolated worktree for each TASK, retaining an existing user choice. Use ignored `.runs/worktrees/`, `.runs/notes/`, `.runs/handoffs/`, `.runs/reviews/` and `.runs/archive/`. Canonical Review and QA reports belong in the Git-metadata-resolved base worktree, not an arbitrary checkout. The [binding](shared-workflows.md#ignored-evidence) adds prospective Build/task-run locations without relocating legacy evidence. Keep environments through review; authorized landing cleans proven TASK-owned resources while preserving handoffs. Preserve unrelated services/worktrees.

No persistent HTTP runtime or LocalDevelopment enrollment is asserted by this adoption. Library-focused checks need no feature URL. Any later HTTP runtime/worktree integration must use the shared operator's documented enrollment procedure; record the actual portable operator contract when adopted.

[Release operations](../../release/README.md) own candidate resolution, certification, human signing and publication. Read the actual `bin/release` help and [ADR 0028](../adr/0028-clear-package-release-decisions.md) for the release flow and its scoped precedence over older process requirements. John approved this Fight Common policy in PR #192 on 2026-10-05: one readable summary and informed ship decision for an unchanged certified candidate, not fourteen mandatory assessment rows, typed/digest-bound approvals or fresh approval at every normal publication step. Changed inputs, failures, ambiguity or material new risks still stop for resolution; package promises, human signing, provider/registry checks and normal PR review remain. Tags use vX.Y.Z; maintenance branches use major.minor. A full product build does not certify a release. Support dates/status require the actual support authority, not branch existence. Reconciling the intended support authority is separate work. This library has no production-site deployment procedure.
