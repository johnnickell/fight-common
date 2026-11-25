# Python tooling inventory

The quality runner moved from Bash to PHP without removing Python. This inventory records decisions for the remaining Python tooling and identifies alternatives to evaluate for open items. Those alternatives do not authorize new prerequisites.

## Offline planning — TASK-00222

| Tracked file | Entry point or caller | Runtime and disposition |
| --- | --- | --- |
| `scripts/planning_portfolio.py` | `bin/planning-check` (read-only or `--write`); `bin/build` calls the read-only path before setup and again through `bin/.build-full`. Planning and role handoffs also call it directly. | **Retain.** The Python 3 standard library handles record validation, dependency cycles, parent rollup and deterministic Markdown rendering. Git checks the selected checkout's ignore rules. No image, Composer dependency or network access is needed. |
| `scripts/archive_planning.py` | `bin/archive-planning` (dry run or explicit `--apply`); apply invokes `bin/planning-check --write` after moves and link rewrites. | **Retain.** The Python 3 standard library handles terminal-state checks, filesystem moves and relative Markdown links. Apply uses Git's NUL-separated file list to include tracked and unignored Markdown, preserves link anchors and delegates view refresh to the planner. Porting only archive would not remove Python from its apply path. |

[Local tooling](LOCAL_TOOLING.md) already requires host Python 3 and Git. Host PHP CLI, Node, Ruby or a standalone Perl runtime could implement the logic, but none is a declared host prerequisite. Container PHP (even without Composer autoload) needs an image before either command can run; the current build validates planning before preparing images. A precomputed view cannot validate uncommitted records, and Git can list files but cannot apply planning rules or rewrite Markdown links.

POSIX shell and standard text tools would avoid another interpreter, but this would not be a thin wrapper. The planner would need a replacement for the dependency graph and cycle walk, multi-block view replacement, and validation before writes. Archive would still need safe NUL-delimited path handling (not supported by POSIX `read`), normalized relative paths and link rebasing in both moved and unmoved documents. Replacing those standard-library operations with shell code would add parsing and portability work while the supported host already has Python. Keep both entry points unchanged.

[The TASK record](../../planning/tasks/00222-TASK.md#completion-notes) summarizes the offline command comparisons, including refused writes, byte-equivalent generated views and archive apply on synthetic records only.

## Host build, runtime and databases — TASK-00223

These paths operate where checkout bytes, native Docker/Compose, Git registrations and receipts are visible. A PHP container or Docker socket mount is not a replacement for this boundary. Evaluate thin host wrappers and removal of obsolete entry points before considering a language change that would add a host prerequisite.

| Tracked file | Entry point or caller | Runtime and disposition |
| --- | --- | --- |
| `scripts/build.py`, `scripts/runtime.py` | Former launchers for `bin/build` and `bin/runtime`. | **Remove.** The wrappers use `python3 -B -m` from the checkout root, avoiding a second Python launcher or a `PYTHONPATH` override. |
| `scripts/__init__.py`, `scripts/local_tooling/__init__.py` | Direct module entry points, relative imports and release cleanup's direct `local_tooling` import. | **Keep package markers.** The outer marker anchors module lookup to the checkout rather than an installed `scripts` package. Keeping the inner package avoids changing release cleanup's import layout. Neither marker runs tooling. |
| `scripts/local_tooling/build_cache.py` | `bin/build` and its CI and release callers. | **Retain.** `fcntl` takes a nonblocking host lock. The fingerprint combines file modes and bytes, symlink targets, native identities and database probes. Shell needs portable equivalents for locking, structured hashing and private JSON writes. Container PHP cannot replace the pre-image host planning/environment boundary. |
| `scripts/local_tooling/runtime.py` | `bin/runtime`, build cache and release cleanup. | **Retain.** Compose JSON drives checkout-specific ports, mounts and image tags. Compose alone does not check stopped consumers, bind ownership or source-free retirement. A shell rewrite would still need a JSON parser and the same ownership checks. |
| `scripts/local_tooling/preparation.py` | Runtime selection and guarded Worktrunk removal. | **Retain.** Git registrations plus canonical paths resolve exact and ambiguous locators. Git and Compose alone cannot recover a removed checkout from its private locator; a replacement would need identical path and identity checks. |
| `scripts/local_tooling/resources.py` | Runtime, build cache, removal guards and release cleanup. | **Retain.** Shared helpers treat failed native queries as errors, parse structured inventories and atomically write private state. Shell substitutions alone cannot preserve that contract; do not duplicate this in release or Compose-specific scripts. |
| `scripts/local_tooling/publication.py` | `runtime finish` delivery guard. | **Retain.** `gh` and Git report PR and ref facts but do not bind them to the checkout and exact published object. Shell would still have to parse and compare them. No publication executed for this TASK. |
| `scripts/local_tooling/source.py` | Guarded Worktrunk removal. | **Retain.** Clean Git status does not rule out private files, live mounts and host consumers, or filesystem protections. Keep the adopted host guard rather than approximating it with raw Git or shell. |
| `scripts/local_tooling/worktrunk.py` | `runtime finish` and the documented runtime-free fixture path. | **Retain.** Native Worktrunk performs non-force removal but does not supply the adapter's checks for effective hooks, exact ancestry, Docker absence and retained refs. |
| `scripts/retire-legacy-databases.py` | Runtime `down`, via `legacy_down` in `etc/local-tooling.json`. | **Retain.** The adapter selects receipts by exact recorded creator and calls the original owner. Compose labels cannot resolve old per-run liabilities; even a shell loop needs fail-closed JSON parsing. |
| `bin/.disposable-database-lifecycle.py` | Legacy adapter and direct `cleanup --receipt` recovery in [local tooling](LOCAL_TOOLING.md#maintainer-ownership). | **Retain.** Historical JSON receipts hold exact native IDs and partial lifecycle state. Their original reader checks objects and consumers before effects. Compose teardown cannot substitute for it; no receipt conversion or liability retirement was authorized. |
| `bin/database-runtime` | One-off locator `check` and `down` on 1.4. | **Remove from develop only.** No develop wrapper calls it. The same executable remains in `v1.4.2` and `origin/1.4`, where the one-off wrapper calls both actions. [Maintenance tooling](../maintenance-tooling.md) names the correct checkout. Develop's receipt recovery is unchanged. |
| `scripts/qualify_phpcs_enforcement.py` | Standalone command in [coding standard](../coding-standard.md), not a default build or release stage. | **Retain.** It checks native Docker status, JSON diagnostic identifiers and byte-exact fixer output through a read-only checkout mount with per-call timeouts. A shell rewrite needs JSON and timeout tools; container PHP would require a new assertion/process harness and no longer exercise the same host-to-Docker boundary. |

[The TASK record](../../planning/tasks/00223-TASK.md#completion-notes) links wrapper, runtime and receipt evidence. Removal and receipt policy are unchanged.

## Docs, identity and image trust — TASK-00224

The docs image (`etc/docker/python/Dockerfile`, `requirements.txt`) supplies pinned Python, MkDocs and validator dependencies. The Docs workflow calls `bin/docs validate`; `bin/.build-full` calls the same command; `bin/.build-prepare` prepares the image. `bin/docs preview` and `build` invoke third-party MkDocs, not project-owned Python. Keep those behaviors while evaluating whether first-party checks should remain beside their actual parser or generator dependencies.

| Tracked file or embedded path | Entry point or caller | Runtime and disposition |
| --- | --- | --- |
| `scripts/generate_identity_assets.py` | `bin/docs validate` uses `--check`; `docs/identity.md` also documents direct generation and check. | Python standard library and identity sources. Evaluate deterministic regeneration and byte-for-byte PNG/SVG parity before a port. |
| `scripts/identity_geometry.py` | Imported by generator and validator. | Shared Python standard-library geometry. Assess with both callers; avoid duplicating curve/path checks. |
| `scripts/validate_identity_assets.py` | `bin/docs validate` and the manual command in `docs/identity.md`. | Python standard library and generated identity assets. Assess parser/artifact parity with generation. |
| `scripts/validate_readme.py` | `bin/docs validate` checks repository README and mirrored site inputs. | Python Markdown package plus standard library. Assess Markdown rendering/link parity, not just file scanning. |
| `scripts/validate_docs_artifact.py` | `bin/docs validate` checks generated `site` after MkDocs. | Python HTML/XML/URL standard-library parsers and generated site. Evaluate a replacement against the same generated site contracts. |
| `scripts/validate_docs_workflow.py` | `bin/docs validate` checks `.github/workflows/docs.yml`. | PyYAML and the workflow source. Qualify YAML semantics and existing validator fixtures before replacing. |
| `etc/docker/python/Dockerfile` | `bin/docs` and build preparation. | Two embedded trust/version assertions: base pip-vendored certifi and SSL roots before install, installed certifi after `python -m pip install`. Keep the Python image and dependency installation; evaluate replacements for the assertions only against the same trust stores. |
| `etc/docker/php/Dockerfile` | `bin/up`, build preparation and release certifier image preparation. | Installs `python3` and uses one embedded `ssl` CA-root assertion. Evaluate a native equivalent and audit dependencies before removing the interpreter; preserve trust qualification in both images. |
| `docs/contributing.md` image audit | Manual `docker run … python3 - <<'PY'` against the PHP and docs images. | Standard-library SSL roots and Python/available certifi metadata in the actual images. Keep the runbook synchronized with the Dockerfile checks; do not confuse this with execution in the host interpreter. |

## Release — TASK-00225

| Tracked file or embedded path | Entry point or caller | Runtime and disposition |
| --- | --- | --- |
| `bin/release` lane setup heredoc | `bin/release certify`, before running default/latest/lowest dependency lanes; documented in `release/README.md`. | Host Python standard library edits only the lane runtime project identity in each exported candidate's JSON and prints its locator. Evaluate an equivalent host helper without changing identity, isolation or dependency profiles. |
| `bin/release` lane cleanup heredoc | Release cleanup, after the lane's guarded `bin/down`; imports the lane's own `local_tooling`. | Host Python, candidate checkout, Docker inventories and guards. Evaluate with TASK-00223's host modules; preserve exact project binding and source retention on failure. |

## Inventory boundary

Checked the tracked tree for `*.py`, interpreter shebangs, Python commands/imports, heredocs and documented invocations, then followed the scripts, wrappers, local/CI configs and release guide. The baseline has 21 tracked `.py` files and one extensionless Python executable (`bin/database-runtime`). Historical planning examples (including the older TASK-00174 image audit) are evidence, not additional live entry points. Generated `vendor`, bytecode, output and local evidence are not owned sources. Package dependencies and MkDocs plugins are not migration targets; the pinned image definition, Python calls in wrappers and trust probes above are owned.
