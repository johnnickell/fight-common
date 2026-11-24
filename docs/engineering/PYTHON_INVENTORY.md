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
| `scripts/build.py` | `bin/build` (maintainer, pre-commit hook, Tests workflow, release certification lanes). | Host Python 3 `runpy` bridge. Evaluate folding this bridge into a qualified host entry point without moving build admission. |
| `scripts/local_tooling/build_cache.py` | Loaded by `scripts/build.py`; calls `bin/planning-check`, `bin/.build-prepare` and `bin/.build-full` through `etc/local-tooling.json`. | Python standard library including `fcntl`, Git, Docker/Compose, images, vendor and artifact/runtime probes. Retain host receipt ownership; evaluate equivalent fingerprinting, locking and fail-closed verification before any replacement. |
| `scripts/runtime.py` | `bin/runtime`; also reached by `bin/up`, `bin/down`, `bin/exec` and focused wrappers, docs, quality/build and release. | Host Python 3 `runpy` bridge. Evaluate consolidation with the host runtime entry point. |
| `scripts/local_tooling/__init__.py` | Package marker for `runpy` and relative imports, also imported by the release cleanup snippet. | Keep with its modules until their import layout is retired or consolidated. |
| `scripts/local_tooling/runtime.py` | `scripts/runtime.py`, `build_cache.py`, release cleanup snippet; `bin/runtime` owns `up`, `down`, `docker`, `inspect`, `finish` and `image-name`. Worktrunk's `.config/wt.toml` hook calls `bin/down`. | Host Docker/Compose, Git and per-checkout configuration. Evaluate thin shell delegation without weakening selection, inventory or retirement checks. |
| `scripts/local_tooling/preparation.py` | Runtime selection and guarded Worktrunk removal. | Host Git registrations, canonical paths and project identity. Preserve exact/ambiguous locator behavior in any host replacement. |
| `scripts/local_tooling/resources.py` | Runtime and build cache; preparation, publication, source and Worktrunk modules; release cleanup snippet. | Host subprocess, Git/Docker queries and private receipts. Treat with its callers; do not duplicate cleanup checks in release. |
| `scripts/local_tooling/publication.py` | Runtime `finish` delivery guard. | Host Git/GitHub tooling and exact live PR state. Qualify separately from offline planning; do not introduce additional publication effects. |
| `scripts/local_tooling/source.py` | Guarded Worktrunk removal. | Host Git, filesystem and live source-consumer inspection. Compare any alternative against the adopted guard before changing it. |
| `scripts/local_tooling/worktrunk.py` | Runtime `finish`; `LOCAL_TOOLING.md` also documents the runtime-free qualification fixture entry point. | Host Worktrunk, Git and native inventories; preserve hook admission, clean-source and retained-ref behavior. |
| `scripts/retire-legacy-databases.py` | Runtime `down`, via `legacy_down` in `etc/local-tooling.json`. | Host Python and canonical `.runs/database-runs/` receipts. Evaluate retirement only after historical liabilities are resolved and the standalone recovery path is accounted for. |
| `bin/.disposable-database-lifecycle.py` | Legacy cleanup adapter above; direct `python3 … cleanup --receipt` recovery in `LOCAL_TOOLING.md`. | Host Python standard library, native Docker and original receipts. Keep the historical receipt reader available unless a proven equivalent or explicit retirement resolves those liabilities. |
| `bin/database-runtime` | Python executable for `check` and `down` of exact one-off Docker locators; documented in `docs/maintenance-tooling.md` for the 1.4 maintenance line. No ordinary develop gate caller. | Host Python standard library and native Docker. Check support/legacy callers before removing the develop copy; not the current Compose owner. |
| `scripts/qualify_phpcs_enforcement.py` | Standalone command in `docs/coding-standard.md`; not an ordinary build or certification stage. | Host Python `unittest` and native Docker with the documented PHP image. Evaluate alongside host Docker wrappers: a thin host delegate or equivalent container qualification, without making it a product or release test. |

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
