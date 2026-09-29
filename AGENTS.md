# Fight engineering instructions

**Scope:** these are shared engineering conventions. Read the project-specific instructions alongside them. All referenced guidance must be available in the checkout; no private skill installation is required.

**Orient:** read the project's AGENTS.md, `planning/agents/project-profile.md` when present, CONTEXT.md, and the selected work record. Inspect current source, Git state, and project-owned commands before relying on recorded examples. Preserve unrelated work.

**Load:** read only standards relevant to the work; builders, reviewers, and auditors use the same rules.

| Work | Read |
|---|---|
| Plan or update tracking | [Planning](docs/engineering/standards/Planning.md) and project planning conventions |
| Design or change business behavior | [Architecture](docs/engineering/standards/Architecture.md) and [Naming](docs/engineering/standards/Naming.md) |
| Write or review PHP | [PHP](docs/engineering/standards/PHP.md), project PHPCS configuration, and relevant architecture rules |
| Handle HTTP or present API data | [HTTP](docs/engineering/standards/HTTP.md) |
| Write or review React/TypeScript | [Frontend](docs/engineering/standards/Frontend.md) |
| Implement or verify behavior | [Testing](docs/engineering/standards/Testing.md) |
| Review or assess readiness | [Review](docs/engineering/standards/Review.md) |
| Worktrees, runtime, PRs, merge, release, deploy | [Delivery](docs/engineering/standards/Delivery.md) and the actual project profile/runbooks |
| Change these standards or adopt them elsewhere | [Governance](docs/engineering/standards/Governance.md) |

**Build:** one TASK normally owns one PR. Use dependency-ordered SUBTASKs for layer assignments. Features are code-first; bugs start with one failing regression test. Keep domain knowledge with its owner and runtime dependencies behind Domain/Application contracts. Common owns reusable package promises; consumers own application policy and composition unless a package contract says otherwise. See Architecture for ownership and compatibility boundaries.

**Verify:** use focused checks while iterating, then the complete project gate for implementation/build-input changes. Documentation-only follow-ups may retain prior full-gate evidence with verified input equivalence and targeted checks under the Testing standard. Cover meaningful product behavior, including the shipped coding standard and framework contracts. Preserve existing release tests/probes without adding packaging or release-process tests in any suite; see Testing and the project inventory. Use local build receipts for pre-publication acceptance in public and private repositories alike; hosted CI is not a review prerequisite. Any required hosted delivery checks occur after land publishes. Surface warnings and incomplete evidence. Keep CONTEXT.md and affected documentation aligned with the code.

**Authorize:** follow the current request's delivery scope. Implementation, publication, independent review, landing, signed library release, and production deployment are distinct operations. The normal workflow is work → local verification → independent review → authorized land; land owns push/PR publication. A local commit or accepted review grants no publication, merge, release, or deployment permission.

**Complete and review:** TASK `done` means implementation acceptance and required local verification, before PR publication; a green build alone is insufficient. Keep pending independent review explicit and record delivery separately. Review uses the independent Spec/Standards accept-or-revise contract in [Review](docs/engineering/standards/Review.md), never builder self-approval.

**Persist:** records own durable decisions and acceptance. Ignored `.runs` subfolders own coordination, review handoffs, logs, and screenshots. Preserve useful evidence through landing and remove only resources demonstrably owned by the work.

**Resolve:** explicit user decisions govern the work. Apply documented project exceptions within their stated scope; otherwise use these standards. If configuration conflicts with an approved preference, explain and reconcile the conflict rather than weakening the gate or silently inventing an exception.

## Fight Common project binding

Read [the project profile](planning/agents/project-profile.md) for namespaces, dependency boundaries, commands, coverage, release procedures and documented exceptions. Read [planning conventions](planning/CONVENTIONS.md) for record identity and generated views. The [adoption record](docs/engineering/STANDARDS.md) identifies the local standards baseline.
