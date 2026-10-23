# Contributing

Use this guide when changing Fight Common itself. Consumer installation, component adoption, and framework
composition belong in their respective guides; this route covers repository workflow, verification, and release
evidence for maintainers.

Read the repository-local `AGENTS.md`, `planning/agents/project-profile.md`, and the applicable documents in
`docs/engineering/standards/`. These committed instructions are self-contained; they require no private skills
or external checkout. `docs/engineering/STANDARDS.md` records the adopted baseline and update policy.

## Choose the change boundary

Start with the smallest owned boundary that can deliver the requested behavior:

- **Domain** contains framework-free business rules and value objects.
- **Application** coordinates use cases and may depend on Domain, PHP internals, PSR contracts, and the
  allowlisted scheduler expression contract.
- **Adapter** integrates frameworks and infrastructure through Application or Domain contracts.
- **Standards** publishes the orthogonal coding standard and has no runtime dependants.

The enforced dependency direction is `Adapter -> Application -> Domain`. Keep public behavior backwards
compatible: a deprecated public API remains supported for at least one released minor and is removed only in the
next major.

Before editing, read the relevant task, its parent TICKET, and any accepted ADR named by the task. The live
[Board](https://github.com/johnnickell/fight-common/blob/develop/planning/tasks/BOARD.md) is the execution
frontier; `planning/CONVENTIONS.md` defines status, ordering, and completion updates. The Roadmap has a separate
planning frontier for undecomposed parents. Close eligible parents in the same child-completion operation under
[Automatic parent completion](https://github.com/johnnickell/fight-common/blob/develop/planning/CONVENTIONS.md#automatic-parent-completion), counting live and archived children.
Common owns reusable package promises; consumer applications own policy, permissions, routes and composition
unless an explicit package contract says otherwise. Use public package contracts directly without behaviorless
wrappers; preserve required compatibility aliases and framework integrations.

## Create an isolated branch

Choose the main checkout or an isolated worktree with the maintainer. New TASK branches use
`feature/task-NNNNN-<slug>` from `develop`, never from `main`; do not commit directly to either protected branch.
Preserve established branch/PR identities and existing release/patch conventions.

```bash
git switch develop
git pull --ff-only
git switch -c feature/task-NNNNN-short-description
```

For coordinated or concurrent work, use a linked checkout under the repository's ignored run area:

```bash
git worktree add -b feature/task-NNNNN-short-description \
  .runs/worktrees/short-description develop
```

Run every command from the selected checkout. Keep investigation notes in `.runs/notes/`, reusable local handoffs
in `.runs/handoffs/`, canonical independent reports in the base worktree's `.runs/reviews/<TASK-ID>/review.md`,
and retired scratch in `.runs/archive/`. Those paths are local evidence and must not be
staged. Removing a worktree or other run material is a separate cleanup action.

## Implement and verify

Production tests cover owned production code and meaningful behavior. Every PHPUnit class extends
`Fight\Test\Common\TestCase\UnitTestCase` and carries explicit coverage metadata. Direct unit tests use
`#[CoversClass]`; `#[CoversNothing]` is reserved for qualifying integration or product-journey tests and cannot
hide missing direct coverage.

Documentation, generated files, wrappers, build orchestration, configuration text, and tooling are checked with
their owning commands and human inspection. Do not add tests that merely inspect those surfaces. Preserve existing
release tests and probes without adding packaging or release-process tests in any suite or renamed harness. The
project profile inventories retained checks; do not recreate a removed suite. Meaningful runtime, shipped coding
standard and framework integration tests remain product contracts.

Use a non-interactive container command for focused feedback:

```bash
docker container run --rm -v "$PWD:/app:delegated" -w /app fight-common \
  php vendor/bin/phpunit tests/Domain/Specification/AndSpecificationTest.php
```

Focused PHPStan uses the same explicit 512M per-process budget as the full gate and interactive wrapper:

```bash
docker container run --rm -v "$PWD:/app:delegated" -w /app fight-common \
  php vendor/bin/phpstan analyse --memory-limit=512M
```

This is a limit for each PHPStan process, including parallel workers, not a total container memory allocation.
Existing concurrency and analysis findings remain unchanged.

Useful interactive wrappers include:

```bash
./bin/phpunit --filter test_method_name
./bin/phpstan
./bin/deptrac
./bin/rector process src/ --dry-run
./bin/docs validate
```

The canonical pre-submit gate is:

```bash
./bin/build
```

It resolves and installs dependencies with `composer update`, validates the documentation artifact, provisions
disposable MySQL and PostgreSQL services, and runs Composer validation, syntax checks, PHPCS, PHPStan, Deptrac,
Rector's dry run, direct unit tests with exact statement coverage, integration tests, functional tests, and planning
integrity. This library ignores `composer.lock`; every full build resolves its supported dependency constraints
instead of reusing a stale local lock. A focused run provides iteration feedback; implementation completion requires
the full gate. Documentation-only follow-ups may retain a verified earlier full pass with input-equivalence evidence
and current targeted checks under `docs/engineering/standards/Testing.md`.

Hosted CI runs the same `./bin/build` command in the runner's Docker environment. Its result is separate hosted
delivery evidence for the checked-out SHA, not a second dependency or quality lane. The workflow is **work → local
verification → independent Review → independent QA → authorized Land → human merge**. Local evidence permits
technical acceptance without a PR or hosted run, including in this public repository. QA challenges applicable
behavior, including libraries and executable instructions; it is not replaced by a green build. Authorized Land
owns the later push and PR creation. Installed shared skills use the repository's
`planning/agents/shared-workflows.md` binding; automation is optional for contributors.

To enable the tracked pre-commit gate:

```bash
git config core.hooksPath .githooks
```

The hook runs `./bin/build`. If it fails or is interrupted, diagnose the cause and run it again to completion.
Never use `git commit --no-verify`, disable, or otherwise bypass the hook merely because the gate is slow,
interrupted, or inconvenient. An exception requires explicit authorization for that exact commit and leaves
delivery unverified.

## Refresh build runtimes and TLS trust

The PHP image follows the stable 8.5 line; image-building wrappers use `--pull` and stop if retrieval or
building fails. PHP 8.5.12 is the minimum patched interpreter for CVE-2026-109618 and CVE-2026-109619.
This does not change the library's public support floor or Composer's simulated platform. A Composer update
cannot patch a consumer's web-serving PHP: its operator must urgently upgrade the interpreter using the
[PHP security guidance](https://www.php.net/archive/2026.php) and restart the affected serving processes.
Common's CLI verification is not a consumer deployment or proof of web-runtime safety.

The documentation base is deliberately digest-pinned. `--pull` retrieves that digest; it **cannot** advance the
pin. When maintaining trust inputs, inspect the official Python 3.13 image manifest, update both its version
and digest in `etc/docker/python/Dockerfile`, and update the default local tag in `bin/docs`. Check the actual
OS bundle and pip-vendored certifi before using that base to retrieve packages. For the
[emSign incident](https://discuss.python.org/t/information-for-python-users-regarding-emudhra-emsign-ca-certificate-security-incident/109576),
certifi must be at least 2026.10.19 and pip at least 26.2.2; updating top-level certifi does not repair pip's
separate vendor bundle. Keep unrelated documentation dependencies pinned.

From the selected checkout, force package-install layers to run as well as pulling bases:

```bash
set -euo pipefail
docker build --pull --no-cache -t fight-common ./etc/docker/php/
docker build --pull --no-cache -t fight-common-docs-python-3.13.17 ./etc/docker/python/
docker image inspect fight-common fight-common-docs-python-3.13.17 \
  --format '{{.RepoTags}} {{.Id}} {{json .RepoDigests}}'
docker run --rm --pull=never --network=none --read-only fight-common php -v
```

Inspect each trust store separately; the affected `emSign ECC Root CA - G3` root must be absent. This diagnostic
prints bundle identities and rejects that root without contacting an untrusted TLS endpoint:

```bash
for image in fight-common fight-common-docs-python-3.13.17; do
  docker run --rm -i --pull=never --network=none --read-only "$image" python3 - <<'PY'
import hashlib
import importlib.metadata
import ssl

paths = {"os": "/etc/ssl/certs/ca-certificates.crt"}
for name in ("certifi", "pip._vendor.certifi"):
    try:
        module = __import__(name, fromlist=["where"])
    except ImportError:
        continue
    print(name, module.__version__)
    if name == "pip._vendor.certifi":
        print("pip", importlib.metadata.version("pip"))
    paths[name] = module.where()
for name, path in paths.items():
    with open(path, "rb") as bundle:
        print(name, path, hashlib.sha256(bundle.read()).hexdigest())
    context = ssl.SSLContext(ssl.PROTOCOL_TLS_CLIENT)
    context.load_verify_locations(cafile=path)
    assert not any(value == "emSign ECC Root CA - G3"
                   for cert in context.get_ca_certs()
                   for rdn in cert["subject"]
                   for key, value in rdn if key == "commonName"), name
PY
  if [ "$?" -ne 0 ]; then exit 1; fi
done
```

Record image IDs, actual interpreter/certifi/pip versions, OS `ca-certificates` package provenance, bundle
hashes, commands and exit results with the TASK evidence. The Dockerfiles reject known-affected inputs; do not
remove those checks, disable TLS verification, or reuse an old image after a failed refresh. Unavailable fixed
inputs or uncertain vendor backports are prerequisites to resolve, not successful maintenance. After the focused
checks, run `./bin/docs validate` and the complete `./bin/build`; retain their exact results and warnings.

## Prepare the pull request

Before the final commit or pull request:

1. Verify every acceptance criterion with current evidence.
2. Keep implementation awaiting independent Review or required QA non-terminal, with its next stage explicit.
   Mark `done` only after independent acceptance and satisfied required QA (or justified overall N/A), plus required
   verification. Authorized Land normally records that closeout. Preserve historical completion meanings and
   record publication, merge, release and deployment separately.
3. Update the TASK metadata and PR link, preserving dependency edges as history.
4. Run `./bin/planning-check --write` to refresh Board, parent, index, and Roadmap tables.
5. Run the read-only `./bin/planning-check`, inspect the complete diff, and satisfy `./bin/build` or Testing's
   documented input-equivalence rule for documentation-only follow-ups.

Independent review follows the Spec/Standards accept/revise contract in `docs/engineering/standards/Review.md`:
one independent reviewer may do both passes, material contributors cannot accept, and any failed or unverified
criterion requires revise. Review does not repair, change status, publish or merge. Moving revisions retain
acceptance only through the proven mechanical-reconciliation bridge in Delivery; semantic or uncertain changes
need renewed independent review.

Independent QA follows `docs/engineering/standards/QA.md` and writes the canonical base-worktree report at
`.runs/qa/<TASK-ID>/qa.md`. PASS, FAIL, INCOMPLETE and justified N/A have distinct meanings; missing prerequisites
are not a pass, and no UI does not mean no QA. Confirmed defects return to Work, then fresh Review and affected QA.

After independent acceptance and satisfied QA, authorized Land opens the feature pull request against `develop`
with title `TASK-NNNNN — <TASK title>` and the Common PR body template; preserve an established PR identity.
The hosted Tests workflow runs the complete pre-submit gate; the documentation workflow validates the generated
site. Their results are advisory unless an accepted TASK or actual host rule requires them. Disclose failed,
missing or skipped jobs and their cause without calling them passed. A demonstrated product defect still requires
repair; required delivery proof retains resources when pending. The repository's
`planning/agents/project-profile.md` owns these requirements. Human review/merge remains separate.

Commit, push, pull-request creation, merge, deployment, and cleanup are distinct effects. Perform only the effects
that have been explicitly authorized.

## Certify a release candidate

A successful `./bin/build` proves the checkout's submit gate; it does not certify or publish a release.
Certification requires a clean, committed candidate and resolves its own baseline, latest-compatible, and
lowest-compatible dependency lanes in exported workspaces:

```bash
./bin/release certify <version>
```

It binds evidence to the exact `HEAD`, runs the complete product gate for those three lanes, builds the Composer
archive, probes an installed consumer, and writes the result under `.runs/handoffs/`. A pre-existing root lockfile
is not a certification prerequisite. See the
[release module guide](https://github.com/johnnickell/fight-common/blob/develop/release/README.md) for the full contract.

Certification does not merge, tag, push, create a GitHub release, publish to Packagist, or deploy documentation.
Promotion from `develop` to `main`, tagging, publication, rollback, and maintenance-branch work each require their
own accepted plan and authorization. Do not infer a hotfix or backport route from urgency alone.

## Documentation workflow

Use the pinned documentation runtime:

```bash
./bin/docs preview
./bin/docs build
./bin/docs validate
```

`preview` serves the local site at `127.0.0.1:8000`. `build` creates the disposable, ignored `site/` artifact.
`validate` performs the strict render and current artifact checks. Review changed routes in the supported viewport
and color-scheme combinations before treating the documentation as accepted.

Pull requests validate documentation but do not deploy it. The hosted workflow deploys only after a push to
`main`; a local artifact, successful build, or uploaded workflow artifact is not production publication evidence.

## Related maintenance routes

- Adopt or configure the package's optional [Coding Standard](../coding-standard/index.md).
- Review the consumer-facing [Architecture](../../architecture/index.md) before changing layer ownership.
- Check [Framework Support](../../frameworks/framework-support/index.md) before changing adapter or provider claims.
- Use the [Quick Start](../../quick-start/index.md) as the representative portable consumer journey.
