---
id: T-00056
prd: PRD-00014
title: Certify the Fight Common 1.2 Compatibility Envelope
status: ready-for-agent
blocked_by: T-00034,T-00048,T-00049,T-00050,T-00051,T-00052,T-00053,T-00054,T-00058,T-00059,T-00060,T-00069,T-00070,T-00071,T-00073,T-00075
---

# Certify the Fight Common 1.2 Compatibility Envelope

## What to Build

Compose every WF-014 contract and package receipt through the release certification engine and prove the
complete Fight Common `1.2.0` compatibility envelope at the approved black-box consumer seam. A certifier
receives either one content-addressed successful manifest or an attributed stop handoff naming the missing
evidence and exactly one resumable next action.

## Acceptance Criteria

- [ ] Certification composes the intentional public API manifest and operation-level diff against the exact
      published `1.1.0` baseline.
- [ ] Scheduler positional, named, process-factory, legacy command, and portable runner consumer probes are
      present and passing.
- [ ] Old and new FQCN probes cover all nineteen Symfony-semantic and thirteen Doctrine migrations, including
      designated registration and identity behavior.
- [ ] Legacy raw-array and typed JSend semantics, encoding, HTTP status, headers, native response, single data,
      and paginated data behavior are present and passing.
- [ ] The additive transactional UnitOfWork contract, unchanged legacy commit path, Doctrine behavior, native
      adapter conformance, and explicit nested-call rejection are present and passing.
- [ ] The unchanged public Publisher path, independent PrivatePublisher contract, optional Mercure adapter, and
      supported private-publication protocol evidence are present and passing.
- [ ] The repository-locked full gate, exact complete statement coverage, and supported MySQL and PostgreSQL
      evidence are present and passing without skips.
- [ ] Root lowest and latest, exported-package consumer, archive, and production `--no-dev` lanes are present
      and passing with exact version and lock receipts.
- [ ] Shared PSR-15, PSR-17, PSR-6, PSR-16, and PSR-18 conformance plus Fight-container registrar and Slim
      routing evidence are present and passing.
- [ ] Symfony, Laravel, Yii, and CodeIgniter framework-native adapters and prototypes have complete conformance
      results, and every failed prototype identifies its exact gap and proven fallback.
- [ ] All five booted starter receipts bind lowest and latest dependency resolutions, selected capabilities,
      native registration and lifecycle journeys, and optional-package isolation to the certified candidate.
- [ ] Yii stable queue support is classified as unavailable for `1.2` rather than skipped or passed, without
      weakening the remaining Yii support claim.
- [ ] The immutable certification manifest binds candidate and baseline identities, resolved versions, lock
      digests, API and behavioral findings, package metadata, archive evidence, approvals, and exceptions.
- [ ] Unavailable, queued, running, skipped, cancelled, missing, failed, stale, or indeterminate evidence cannot
      produce a certified result or be replaced by a hosted check or raw log.
- [ ] Every stopped result names the failed or missing lane, preserves its evidence, and exposes exactly one
      resumable next action without granting a waiver.
- [ ] Traceability links EPIC-00004, PRD-00014, PRD-00015, WF-014, WF-024, ADRs 0017 through 0019, ADR 0024,
      T-00047 through T-00060, T-00069 through T-00075, the release certification engine, and all designated
      receipts without unresolved or contradictory edges.

## Verification

Full submit gate, `./bin/planning-check`, the complete black-box consumer certification harness, root dependency
and package modes, deterministic pass/fail/indeterminate probes, manifest identity tests, and traceability
validation. No live release publication is required.

## Parent

PRD-00014 — Fight Common Contract Repair and Compatibility Certification.
