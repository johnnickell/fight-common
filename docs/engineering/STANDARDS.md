# Engineering standards adoption

Adopted 2026-09-14 as the first Fight engineering baseline. This is a reviewed local copy, not a published skill-package release. It requires no private tooling or external checkout. Updates are explicit repository changes; local configuration and documented project exceptions remain visible in the [project profile](../../planning/agents/project-profile.md).

The root [AGENTS.md](../../AGENTS.md) contains the shared policy with project-relative links and a project binding. Its baseline hash below describes the source policy before those link/binding substitutions. Standard document hashes describe the exact installed bytes.

## Standards

- [Architecture](standards/Architecture.md)
- [Delivery](standards/Delivery.md)
- [Frontend](standards/Frontend.md)
- [Governance](standards/Governance.md)
- [HTTP](standards/HTTP.md)
- [Naming](standards/Naming.md)
- [PHP](standards/PHP.md)
- [Planning](standards/Planning.md)
- [Review](standards/Review.md)
- [Testing](standards/Testing.md)

## Baseline identity

| Document | SHA-256 |
|---|---|
| `AGENTS.md baseline` | `026dbae813a7962b104ecf5785429f24bb7dbcacd866a66cea9b9d1e7dae10db` |
| `standards/Architecture.md` | `3937a128e280f93bb9eece8dea9f612f02a63fdd79bd0f7d63b41a167e190980` |
| `standards/Delivery.md` | `f846b7dbb8977d7ef195cdbd339ce91a1632691a7664455ebdd2067de4ad925e` |
| `standards/Frontend.md` | `236d9fae3bd94af3a22b6158953979be04c0c8e388f62d56461579d58e9eb8ec` |
| `standards/Governance.md` | `54f36212c604615d7c6e2691de41b3b2c843c4f53e59800c9e330eeedf3d3ed5` |
| `standards/HTTP.md` | `456f7161f08bdd23063bab934ba9a26fb178f0e1ad35e0d898255dd9702626c9` |
| `standards/Naming.md` | `783c67a53b62f9a1576a3a0c00a6438f1b6c40b0df84f268874689b715e74907` |
| `standards/PHP.md` | `b102071e4939424796e4edc20d0b46373210634189c8f024038214e0e18cf623` |
| `standards/Planning.md` | `313bf61c904e9442aa38e28d6a60713fcea83a6cd8d83abbb2c6554150b062ad` |
| `standards/Review.md` | `e85e0e3b67ff34b47583985885501ee58c7456bfeea94ad39d1fc25193f31175` |
| `standards/Testing.md` | `fdab5a64ba92f406d63c28d48d6fdf800397c079c29932372746caf31f2c432f` |

## Project scope and unresolved work

PHP/library, planning, tests, and release guidance apply to this repository. Frontend and production deployment references apply only when such work is actually in scope; this baseline does not add those products. LocalDevelopment enrollment is not asserted. Existing public API compatibility and framework constraints are preserved in the profile. Detailed support-authority reconciliation remains separate release work.

The planning and standards adoption are implemented locally. Behavioral trials of the associated authoring workflows are separate from this content adoption.

## Targeted standards refresh — 2026-09-16

Planning now distinguishes unfinished, executable and attention-needed work, with truthful next-action fallbacks. Review now checks omitted states and cross-view contradictions against independent expected behavior. Only these approved clauses were applied; earlier baseline content, project bindings and exceptions remain unchanged. Installed digests above identify the resulting local documents.
