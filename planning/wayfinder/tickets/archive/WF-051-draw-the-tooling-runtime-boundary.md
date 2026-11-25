# Draw the host and container tooling boundary

**Labels:** `wayfinder:research`, `wayfinder:grilling`
**Mode:** HITL
**Status:** Closed
**Map:** [Fight Common v2.0.0 readiness](../../archive/maps/fight-common-2-0-readiness-map.md)
**Depends on:** —

## Question

Which Python scripts should move to Composer-autoloaded PHP, and which operations must remain on the host?

## Must decide

- Inventory real entry points, callers, and runtime requirements. Compare Common's [host build wrapper](../../../../bin/build) with Agent OS `bin/build` → `scripts/build.php` and Access Control's `Fight\AccessControl\Tooling\` dev autoload and planning wrapper.
- Keep `bin/*` as host entry points and delegate eligible logic to PHP inside the project container. Keep bootstrap and Docker or host-only operations where they can run before containers or `vendor/` exist. Record exceptions rather than requiring a blanket Python rewrite.
- Choose the `autoload-dev` namespace, script families and migration order. Preserve supported command behavior, offline planning, exit status, build evidence, checkout and runtime isolation, and the installed consumer package surface.

## Evidence and recommendation

1. **Move the in-container quality runner first.** Common already invokes `bin/quality` inside the PHP service from [`bin/.build-full`](../../../../bin/.build-full). Move the sequence to `scripts/quality.php` and use Composer `autoload-dev` PSR-4 `Fight\Common\Tooling\` → `scripts/` for container-side classes. Keep `bin/*` callable from the host; move internal container entry points out of `bin/` as they are migrated. Preserve argument and exit behavior, fail-fast stage order, and coverage output. Agent OS’s `bin/build` → `scripts/build.php` is a useful boundary example, but Common should not inherit its requirement to start Compose manually.
2. **Keep preflight and host orchestration on the host.** Common runs `planning-check` before build preparation and checks it again afterward. Keep the current offline, no-image/no-vendor planning and archive path for 2.0 unless a replacement can demonstrate those same prerequisites. Access Control’s PHP planner uses a prepared no-network image and a direct bootstrap, not Composer; copying it would make a prepared image mandatory here. Keep Docker/Compose preparation and teardown, Git worktree/Worktrunk handling, guarded database retirement, and the host build receipt/cache admission where they can observe the host and the complete gate. Do not mount the Docker socket into the PHP service to port them.
3. **Do not port specialist tooling just to remove Python.** Keep MkDocs and its in-image documentation and identity tooling for this cut unless a bounded migration shows parity and a practical benefit. Leave release certification and package autoload unchanged. After moving the quality runner, use parity and timing evidence to decide whether to move any additional post-bootstrap, PHP-native verification helpers.

Check from a clean checkout with no image or vendor that planning still works offline and `build` admits and prepares its own runtime. Compare main and isolated worktree behavior, failure exits, `--force`, receipt reuse and invalidation, coverage artifacts, and the installed consumer package. Use existing verification paths rather than adding tests of wrapper text. The changed runner must participate in lint/static checks and build input hashing.

## Resolution

The maintainer approved the split above. Move the quality runner first, using container-run PHP and Composer dev autoload. Retain host runtime setup, receipt checks, and offline planning without an image or `vendor/`. Carry this scope and the parity checks into implementation planning; this approval does not port the scripts.

## Resolution boundary

Choose migration slices and parity checks. Do not port scripts or alter runtime prerequisites during mapping.
