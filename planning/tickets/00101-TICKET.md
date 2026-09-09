---
id: T-00101
prd: PRD-00015
title: Rewrite Root Authorship and Re-certify Framework Consumers
status: done
blocked_by:
---

# Rewrite Root Authorship and Re-certify Framework Consumers

## Outcome

Rewrote Fight Common's root authorship identity from the obsolete email associated with `jnickell-code` to
`john.r.nickell@gmail.com`, preserved the complete source history semantically, migrated every published ref,
and re-certified all five framework consumers against the exact tree-equivalent candidate commits.

## Scope

- In scope: all-ref recovery bundle, exact ruleset snapshots, authorship-only graph rewrite, leased atomic
  publication, ref and tree verification, consumer Composer locks and canonical receipts, hosted CI, and merged
  consumer maintenance PRs.
- Out of scope: runtime or public API changes, `.mailmap`, release immutability, retrospective GitHub Releases,
  release publication, backup deletion, worktree cleanup, and removal of legitimate future contributors.

## Fight Common Rewrite Map

The rewrite changed 408 of 410 reachable commits. Commit count and topology are unchanged; every mapped commit has
the same tree, raw message, author and committer timestamp, and non-email identity fields as its predecessor.
`gh-pages` is not descended from the rewritten root and remains byte-identical.

| Reference | Before | After |
| --- | --- | --- |
| Root commit | `e5afbe7d3f4c169372f8cb8cc8d1b7fa1cb0cb7d` | `5a7debc3ba5408609132af8b3db855f37ce2bb15` |
| `develop` | `f0ad2db8af5a77b483e180ae01c176fb5f549f24` | `286a82f728df441394fee7e97027ebd556166f79` |
| `main` | `1a1fd4cb8a9ce2acb9ae3f967d7277d2878f663b` | `50a04cec50d1fba8057b042115923818169d31cc` |
| `1.0` and `v1.0.0` | `bca016d7633e891ffda6e354fc35c0b1a0fe38a7` | `30422097c74501f6fdcb3cb8009e2c4797afd91a` |
| `1.1` and `v1.1.0` | `be965a0b94c9eed8646673418669c7f0b53c2a43` | `8e3a3b466e823694db8c76eaed89878edc418ab7` |
| Annotated tag `1.1.0` | `5f1c2f2a4a78741836003b0d6acd229569beb454` | `7b8e53a14eba4fb12f1d49139ca25d649248e507` |
| `1.1.0^{}` | `fdd48065c5527f4968943db7d61d6f1ad17619e7` | `1666dbaa503e40ea2fc652bccbeba22e6cda6b70` |
| `gh-pages` | `396a324012bc772f5721067395283edd996c3abc` | unchanged |

The two candidate identities used by the starters map as follows:

- `4a798b1db8fdb5e4af7d0ba8c98a88ac53c50c16` to
  `fad24ae9fdcf4ac00fa55c59ef7d35f7c7531911`
- `ceae16393fd15a2a20687b7533dc048ab1f6a1af` to
  `ce212af215d4ddf8d70f349b7a8c5e634dc9e539`

## Re-certified Consumer Receipts

Each PR passed hosted CI at the exact listed head before an independent merge into `develop`.

| Starter | PR head | Merged commit | Candidate | Content ID | Receipt digest |
| --- | --- | --- | --- | --- | --- |
| Symfony | [PR #7 at `ac5d2b1`](https://github.com/johnnickell/project-symfony/pull/7) | `1b6db511ebe39177be6195b6ad491534bd55f0ab` | `fad24ae9fdcf4ac00fa55c59ef7d35f7c7531911` | `42de887b7acbe1bcb63b42280427ac9ce3bdb5f4e060e99bf903c59772f15bbc` | `74ef9180ad4bbf4f44fe9d78cdc6c4e94260da50e6a2068ce05ac85e46186bcb` |
| Laravel | [PR #6 at `c8be2a2`](https://github.com/johnnickell/project-laravel/pull/6) | `42bc46f64321506c83fbbaded68cd37e5417fa63` | `ce212af215d4ddf8d70f349b7a8c5e634dc9e539` | `b2699b4d44a8542209fcfaf8d0f442d53db7f8a50653f31b566344f9755ad570` | `ed5d3d9da33f726c559d4f3a0329c918c6dd021a54388f25331b7fa5a28f8a59` |
| Yii | [PR #5 at `f36c712`](https://github.com/johnnickell/project-yii/pull/5) | `df169ef50ce711be6587271a47701cf0749e0a38` | `fad24ae9fdcf4ac00fa55c59ef7d35f7c7531911` | `c22bd80296ede72b3b4f5a8f576622a8073906b8b0f9f41578d7e2ef06dffe5f` | `17060c1523aeea3f67b4e8f2c482e3341f34b88f60c9e4e7bbfe2b0eaa6aaa81` |
| CodeIgniter | [PR #6 at `1732b3e`](https://github.com/johnnickell/project-codeigniter/pull/6) | `34481cb46f8dbf575de6a41f1d5a17dbc81235ac` | `fad24ae9fdcf4ac00fa55c59ef7d35f7c7531911` | `b3c25472a22f53dcc667ff4dedf63f2a2e11112bc46ce7d2c884319a836d09fd` | `ec7e414b7da1420fef3622fbf02822ccd674b04b4fcfe2ec87566b317edae978` |
| Slim | [PR #9 at `d05df65`](https://github.com/johnnickell/project-slim/pull/9) | `9c45b998a66b6e31374fb9a2ba8f1adad0fc535a` | `fad24ae9fdcf4ac00fa55c59ef7d35f7c7531911` | `6bb27de1ec6167d6820827a631cc8761ce871396ee98e0e23417403697a76292` | `72647850bb9d5e7a228bef8a58ff51d554d47fa14ede85047aeef435e5da7074` |

## Acceptance Criteria

- [x] A verified all-ref bundle, exact pre-rewrite refs, ruleset snapshots, SHA map, and dirty-worktree recovery
  material exist under the retained ignored maintenance notes.
- [x] The root author and committer email is `john.r.nickell@gmail.com`; no reachable commit retains
  `jnickell@bumperactive.com`.
- [x] Corresponding tree IDs, messages, timestamps, commit count, and topology are preserved.
- [x] The affected branches and tags were force-updated atomically with explicit old-SHA leases; `gh-pages`
  was restored to its unchanged pre-rewrite SHA after the main-branch Pages workflow ran.
- [x] The `develop` pull-request ruleset was temporarily disabled without deletion, restored immediately, and
  compared field-for-field with its captured configuration.
- [x] Fight Common's canonical build passed before publication and from a fresh clone of rewritten `develop`.
- [x] All five consumers resolve the mapped candidate, pass latest, lowest, receipt, full build, and production
  checks, and have exact-head hosted CI success before merge.

## Verification

The rewrite verifier reported 410 reachable commits, 408 rewritten commits, two unrelated unchanged commits,
identical trees, normalized raw-object equivalence, no reachable obsolete email, and the exact candidate mappings.
Both Fight Common builds passed 3,641 tests with 20,620 assertions and exact 10,089/10,089 statement coverage.
Remote branches and tags resolve to the table above. GitHub's root commit API attributes both author and committer
to `johnnickell` with `john.r.nickell@gmail.com`.

GitHub's aggregate contributors endpoint still returned its pre-refresh cached `jnickell-code` entry immediately
after the rewrite. Recheck that endpoint after GitHub's documented statistics refresh interval; the commit-level
attribution and reachable graph are already corrected.

## Completion Notes

Completed 2026-09-09. The pre-rewrite bundle, SHA maps, ruleset snapshots, and T-00087 dirty-worktree recovery
material remain retained. Cleanup and release-immutability changes require separate authorization.

## Parent

PRD-00015 — Framework Adapter Support and Capability Composition.
