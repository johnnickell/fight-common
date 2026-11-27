# Define the 2.0 removal and upgrade contract

**Labels:** `wayfinder:research`, `wayfinder:grilling`
**Mode:** HITL
**Status:** Closed
**Map:** [Fight Common v2.0.0 readiness](../../archive/maps/fight-common-2-0-readiness-map.md)
**Depends on:** —

## Question

What deprecated surface can 2.0 remove, how do existing consumers migrate, and what helper changes belong in this release?

## Must decide

- Inventory deprecations against the verified tagged 1.x baseline and current branch. Include compatibility aliases and old spellings that lack annotations, deprecated members, adapters and any configuration or behavior promises. Verify the released-minor window and flag conflicts explicitly rather than silently retaining or removing an item.
- Map each public break to its replacement or a clear no-replacement path. Specify a Symfony-style root `UPGRADE.md`, organized by component with before/after examples where useful. Check the package surface and representative consumers, starting with Fight Access Control and Fight Agent OS.
- Prefer [existing Domain helpers](../../../../src/Domain/functions.php) where behavior and ownership match. Decide whether to add public `base64_url_encode` and `base64_url_decode`; if so specify padding, canonical form, empty input and invalid-input behavior before reusing them. Standard Base64 is a different encoding contract. Preserve existing wire/storage bytes and rejection behavior unless a separately approved breaking migration documents the change.

## Evidence and recommendation

- Use [published v1.5.1](https://github.com/johnnickell/fight-common/releases/tag/v1.5.1) (`c73690164a9f112dbf14d774c56a7c01519b88aa`) as the upgrade baseline. The [deprecation inventory](../../../../docs/maintenance/deprecations.md) records first public deprecation in v1.2.0, v1.4.0, or v1.5.0. Those shipped minors meet the recorded window; a v1.6 release is not needed for these groups. Reconcile the inventory with the tag, current source and compatibility manifest before any deletes. A source annotation alone does not prove a released deprecation.
- Remove all verified, eligible deprecated contracts, including the old 17 MCP foundation spellings (not the shared declaration by accident), nine old MCP HTTP spellings, 50 readonly property reads, four schema `TABLES` constants, and the earlier deprecated adapter, transaction, serializer, scheduler, AI/webhook, pagination and legacy HTTP contracts. Retain non-deprecated Domain serializer interfaces and canonical spellings. The maintainer explicitly added a breaking exception for `ResultSet` construction: both `ResultSet` and `Pagination` must accept only inputs valid under their current `strict()` factories, with no permissive construction path. The existing constructors already take the same parameters as their factories; the change is to accepted values and validation, not merely the parameter lists. Document both changes in `UPGRADE.md`, including examples of formerly accepted inputs and how callers should correct them. Whether `strict()` remains a convenience factory is separate from requiring strict validation on every construction path. No new dependency floor is proposed.
- Write a root `UPGRADE.md` modeled on [Symfony’s major upgrade guide](https://github.com/symfony/symfony/blob/8.0/UPGRADE-8.0.md): introduction and supported starting point, then component headings with exact old/new names, before/after examples, and explicit no-replacement cases. Explain pre-upgrade queue/worker and stored-object handling wherever renamed runtime identities are involved; an import rename does not migrate data or service IDs. Verify the changes against representative Fight Access Control and Fight Agent OS call sites, configuration and saved data. That full consumer inventory is implementation planning evidence, not a completed migration.
- Recommend adding `Fight\Common\Domain\base64_url_encode()` and `base64_url_decode()` with canonical **unpadded Base64URL output** and bounded tolerance on decode. Encode arbitrary bytes; decoding an empty string returns an empty string. Decode either no padding or exactly the standard trailing padding the final group needs (one or two `=` characters). Use the standard alphabet mapping `+` → `-` and `/` → `_` for encoding, and the reverse for decoding. Return `false` for missing characters in a padded form, extra or misplaced padding, whitespace, `+` or `/`, impossible lengths, and noncanonical trailing bits. Use existing Domain helpers when behavior and layer ownership match. Reuse the new helpers only when existing output bytes, accepted input and caller-level failures remain equivalent. Standard Base64 stays standard Base64; do not replace signing, integrity, size or protocol validation with an encoding helper.

## Resolution

Use v1.5.1 as the baseline, remove verified eligible deprecations, require strict construction for `ResultSet` and `Pagination`, and document the breaks in root `UPGRADE.md`. The maintainer confirmed the exact lowercase, underscore-separated names `base64_url_encode` and `base64_url_decode` in `Fight\Common\Domain`. Emit unpadded output and accept only optional correct padding in addition to canonical unpadded input. Carry these decisions into implementation planning; no package code changed.

## Resolution boundary

Decide removals, migrations and helper contracts. Do not change package APIs or certify a release during mapping.
