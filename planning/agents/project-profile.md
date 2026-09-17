# Fight Common project profile

## Identity and architecture

Library: `johnnickell/fight-common`. Read the root AGENTS.md and local engineering standards. Domain context lives in the applicable architecture/component documentation and accepted planning ADRs; inspect for a current CONTEXT.md before introducing or relocating that authority.

| Layer | Path | Boundary |
|---|---|---|
| Domain | `src/Domain/` | Pure business logic; no Application/framework dependencies |
| Application | `src/Application/` | Domain, PHP internals, PSR contracts and the allowlisted scheduler expression contract; no Adapter dependency |
| Adapter | `src/Adapter/` | Framework/infrastructure implementations, depending inward |
| Standards | `src/Standards/` | Orthogonal coding standard with no runtime dependents |

`deptrac.php` and `deptrac.runtime.php` enforce these boundaries. Source namespace is `Fight\Common\`, tests `Fight\Test\Common\`, and release tooling `Fight\Release\`. Tests mirror the source paths. Inspect Composer's actual autoload configuration for release tooling locations.

Value objects are immutable, validate construction, and use named public factories such as `fromString()`/`fromArray()`; invalid values use DomainException as appropriate to their contract. Specifications extend CompositeSpecification and implement `isSatisfiedBy(mixed $candidate): bool`, composing with `and()`/`or()`/`not()`. Typed collections use `ArrayList::of()` and `HashTable::of()` according to their actual signatures.

CQRS uses `CommandBus::execute()`, `QueryBus::fetch()`, and `EventDispatcher::trigger()`. Follow the existing MessageId, Meta and timestamp contracts. Deprecated public APIs remain supported for at least one released minor before removal in the next major. Preserve intentional extensibility of public base classes; final-by-default does not make a reusable abstract/base contract final. Retain framework-required entity mutability/proxy compatibility.

## Commands and verification

Fight Common is a public repository (verified 2026-09-15). John explicitly retained its public-repository hosted-check policy: declared required hosted checks still apply to review/landing; the private-repository exemption does not apply. Builders supply local receipts and do not monitor hosted CI. Recheck visibility if repository policy changes.

`./bin/build` is the complete pre-submit gate: Composer validation, syntax, PHPCS, Deptrac, PHPStan, Rector dry run, Unit with exact coverage, Integration, Functional, documentation and read-only planning checks. Run it for implementation/build-input changes before commit/PR. Documentation-only follow-ups may retain verified earlier full-gate evidence with current targeted checks under [Testing](../../docs/engineering/standards/Testing.md). Save the local build log, actual exit result and tested-content mapping in an ignored run receipt linked from the TASK handoff. Apply Testing's repository-visibility policy: private repositories require local proof, not a successful hosted CI run. Let commit hooks complete; never use `--no-verify` or disable them because they are slow or inconvenient.

This library ignores composer.lock. Ordinary builds use `composer update`. Release tooling resolves its candidate lanes first and uses `FIGHT_COMMON_DEPENDENCY_PROFILE=resolved ./bin/build` to preserve that resolution; use this mode only in the release-owned candidate procedure described in [the release guide](../../release/README.md).

Tooling runs in the project's `fight-common` PHP container. For focused, noninteractive checks from the selected checkout:

```bash
docker run --rm -v "$PWD:/app:delegated" -w /app fight-common \
  php vendor/bin/phpunit tests/Domain/Specification/AndSpecificationTest.php
```

Use the same container pattern for PHPStan/Rector when appropriate. Inspect `./bin/*` before selecting them: interactive helpers may allocate a TTY or rebuild the image. Long detached gates must produce a complete log and explicit exit status; do not interpret timeout output as completion.

The coding-standard authority is `src/Standards/Phpcs/ruleset.xml` with the project's PHPCS composition. Fight Common must demonstrate its own strict conventions: root `phpcs.xml` enables multiline docblocks, checks declaration docblock alignment and ignores PHPCS suppression annotations. Repair violations instead of adding exclusions, lowering diagnostic severity, suppressing warnings or narrowing the scanned paths to make a gate pass. Changes to the published consumer defaults remain subject to [ADR 0004](../adr/0004-coding-standard-compatibility.md). Unit tests use the established UnitTestCase and Mockery helpers. Test methods follow `test_that_<subject>_<condition>()`, with `self::assert...`. Direct unit tests have `#[CoversClass(Target::class)]`; qualifying integration/journey tests may use `#[CoversNothing]`, never to hide missing direct coverage. Every PHPUnit test class carries explicit coverage metadata. Follow each suite's actual base-class contract.

Require exact 100% statement coverage of owned production code. Choose mocks/stubs/reals for the behavior being proved; real value objects and simple anonymous stubs are normally useful, and `$this->mock()` supplies Mockery collaborators. Keep release/tooling tests outside ordinary product CI/default builds. Direct validation of docs/planning is part of the gate, not justification for adding tests of Markdown or shell/configuration text.

## Planning and Git

Read [CONVENTIONS.md](../CONVENTIONS.md). EPIC → TICKET → TASK; normally one TASK per PR. The generated [Board](../tasks/BOARD.md) exposes the active task/human decision and ready frontier. TASK metadata owns state, priority, blockers and PR references. [MIGRATION.md](../MIGRATION.md) preserves legacy identities.

After record changes, run `./bin/planning-check --write`, then `./bin/planning-check`. Archive only on an explicit request, with an inspected `./bin/archive-planning` dry run before apply.

Use `feature/*` from develop to develop with merge commits. No direct commits to develop or main. Release branches start from develop and merge to main; then main merges back into develop. Released library repairs use patch branches and the approved supported-line policy rather than application hotfix branches. Read actual release tooling and accepted ADRs before any release mutation.

## Runtime, runs and delivery

Choose main checkout or isolated worktree for each TASK, retaining an existing user choice. Use ignored `.runs/worktrees/`, `.runs/notes/`, `.runs/handoffs/` and `.runs/archive/`. Keep environments through review; authorized landing cleans proven TASK-owned resources while preserving handoffs. Preserve unrelated services/worktrees.

No persistent HTTP runtime or LocalDevelopment enrollment is asserted by this adoption. Library-focused checks need no feature URL. Any later HTTP runtime/worktree integration must use the shared operator's documented enrollment procedure; record the actual portable operator contract when adopted.

[Release operations](../../release/README.md) own candidate resolution, certification, human signing and publication. Read the actual `bin/release` help and relevant accepted ADRs. Tags use vX.Y.Z; maintenance branches use major.minor. A full product build does not certify a release. Support dates/status require the actual support authority, not branch existence. Reconciling the intended support authority is separate work. This library has no production-site deployment procedure.
