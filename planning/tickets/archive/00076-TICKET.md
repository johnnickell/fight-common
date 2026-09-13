---
id: T-00076
prd: PRD-00014
title: Correct Architecture Drift and Canonicalize Repository Guidance
status: done
blocked_by: T-00047
---

# Correct Architecture Drift and Canonicalize Repository Guidance

## What to Build

Move concrete serialization behavior to the Application layer without breaking the published Domain serializers,
retire the misplaced AI-operation path deliberately, and establish one accurate repository-instruction and
planning-convention authority. Preserve existing consumer behavior throughout `1.x` and keep the compatibility
manifest complete for every new production declaration.

## Acceptance Criteria

- [x] Application owns the canonical JSON and PHP serializers; Adapter consumers use them, while the deprecated
      Domain serializers remain independently functional through `1.x`.
- [x] The new Application serializers are deliberately classified as public additions with exact manifest
      inventory, classification-count, operation, and subject-digest authority.
- [x] The legacy AI operation, webhook port, and HMAC webhook adapter are deprecated for `2.0.0` removal without
      weakening the adapter's known-action validation or HMAC request behavior.
- [x] Public documentation and the changelog describe the deprecation and standalone compatibility strategy
      accurately without presenting the retired operation record as the active parsing API.
- [x] `AGENTS.md` is the canonical repository instruction file, `CLAUDE.md` delegates to it, and architecture
      tests verify the canonical instruction and executable quality-gate seams.
- [x] Planning conventions document ticket lifecycle, board order, Wayfinder, epic, PRD, naming, and pre-PR
      synchronization contracts.

## Verification

Full submit gate, `./bin/planning-check`, focused compatibility-manifest and architecture journeys, webhook
validation regression coverage, and independent Standards and Spec reviews.

## Implementation Evidence

- The compatibility authority validates 409 declarations: 408 public and one internal, including both canonical
  Application serializers as post-`1.1.0` public additions.
- Unknown webhook actions fail before request construction, signing, or transport; the deprecated path retains
  its established `DomainException` behavior without depending on the deprecated operation record.
- Focused verification passes 33 tests and 442 assertions. The canonical `./bin/build` passes 3,623 tests and
  12,944 assertions with exact 16,937/16,937 statement coverage.
- The repaired Spec review is clean. The Standards review's implementation and documentation findings are
  resolved, with this ticket and board receipt closing the remaining pre-PR synchronization finding.

## Parent

PRD-00014 — Fight Common Contract Repair and Compatibility Certification.
