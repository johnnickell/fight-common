---
id: T-00088
prd: PRD-00022
title: Build One Reproducible Documentation Artifact
status: done
blocked_by:
---

# Build One Reproducible Documentation Artifact

## Outcome

Give maintainers one exact, repository-owned path that previews, strictly builds, validates, and packages the
current MkDocs site. Pull requests prove the artifact without publication, while `main` is prepared to deploy
that same artifact through the protected GitHub Pages environment rather than writing a generated branch.

## Scope

- In scope: exact Python and documentation dependency pins, noninteractive preview/build/validation commands,
  disposable generated output, fast composition into the canonical build, pull-request validation, artifact
  upload and Pages deployment workflow, project-base handling, and rollback guidance.
- Out of scope: changing information architecture or prose, implementing Atlas Deck styling, deploying Pages,
  merging branches, or changing PHP runtime behavior.

## Acceptance Criteria

- [x] One exact Python version and one complete exact documentation dependency set drive local and hosted builds.
- [x] Repository-owned commands provide local preview, strict production build, and deterministic artifact
      validation without requiring an interactive terminal.
- [x] Generated output remains disposable and untracked; only documentation inputs are repository-owned.
- [x] Pull requests build and validate the site without publishing it.
- [x] The `main` workflow uploads the generated site as a Pages artifact and deploys through the protected
      `github-pages` environment with narrowly scoped permissions.
- [x] Direct generated-branch deployment is removed rather than retained as a second publication path.
- [x] The current documentation builds with correct `https://johnnickell.github.io/fight-common/` production
      context and `/fight-common/` base-relative behavior.
- [x] Fast documentation checks compose into `./bin/build` without browsers, Lighthouse, or visual snapshots.
- [x] Maintainer guidance distinguishes local artifact success, hosted workflow success, deployment, and rollback.

## Verification

- Run the repository-owned strict documentation build and artifact checks from a clean checkout.
- Run `./bin/planning-check`, workflow syntax checks, `git diff --check`, and the canonical `./bin/build`.
- Confirm no command in pull-request context can publish or mutate hosted Pages state.

## Completion Notes

Added a repository-owned Python 3.13.7 documentation image with a completely exact dependency set and split the
Docker contexts into `etc/docker/php` and `etc/docker/python` without changing PHP runtime behavior. `bin/docs`
now provides noninteractive preview, strict build, and deterministic artifact validation; the validator proves
generated routes, assets, canonicals, search targets, sitemap membership, the production project base, and 404
behavior. Pull requests now build and upload the artifact without publishing, while protected `main` deployment
uses the uploaded Pages artifact with narrow permissions and serialized deployment concurrency.

Focused post-review verification passed with 59 PHP tests and 466 assertions plus the repository-owned strict
documentation validation and nine workflow fixtures. The final canonical `./bin/build` passed all quality,
architecture, compatibility, and exact-coverage gates with 4,084 tests, 15,356 assertions, and 18,579/18,579
statements covered. Hosted workflow execution and Pages deployment were not performed; they remain separate
post-publication evidence and authorization boundaries.
