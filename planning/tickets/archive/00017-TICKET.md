---
id: T-00017
prd: PRD-00006
title: Complete 1.2 compatibility and release acceptance
status: done
blocked_by:
---

# Complete 1.2 Compatibility and Release Acceptance

## What to Build

Prove that the additive Fight Common release preserves existing CQRS and Event Sourcing contracts,
intentionally isolates message metadata, satisfies every portable and durable conformance guarantee, carries
the complete certified `1.2.0` compatibility envelope, and passes the repository's complete acceptance gate.

## Acceptance

- [x] Existing public CQRS method signatures remain compatible, with message metadata isolation covered as an intentional behavioral change.
- [x] Contract and adapter conformance suites cover all Event Sourcing, projection, dispatcher, and publication guarantees.
- [x] Optional Symfony autoconfiguration is delivered, while portable manual composition remains the acceptance baseline.
- [x] T-00056 supplies a successful content-addressed thin certification record covering the public API,
      Scheduler, JSend, namespace, dependency, framework-fixture, package, and archive evidence required for
      `1.2.0`.
- [x] The fresh documentation-complete candidate at `d262866714fe1a3a60e086806cede526937d5874`
      is certified, and merged `develop` commit `ef64fcbbcccb2c1438a15f9c5b460cc2a96e9d13` has the same tree.
- [x] Planning validation and every non-interactive Docker submit gate pass with exact complete statement coverage.
- [x] Release notes target additive 1.2.0, explain the metadata behavior change, and do not alter existing tags.
- [x] The epic, PRDs, tickets, board, documentation, and release surfaces agree on delivered and deferred scope.

## Completion Notes

Accepted 2026-09-12. Certification bundle
`release-1.2.0-d262866714fe1a3a60e086806cede526937d5874` records ten passing ordered outcomes for
`d262866714fe1a3a60e086806cede526937d5874`, including the package surface (528 classified declarations,
zero unexpected), installed consumer, and five matching starter receipts. Its certified Composer tar,
`certification.json`, manifest, and operation-shape digests are retained in that bundle.

Merged `develop` commit `ef64fcbbcccb2c1438a15f9c5b460cc2a96e9d13` is tree-identical to the certified commit
at `837a863000d425eb6c5b404278f41f670bd7db70`. ADR 0027 accepts that relationship while requiring T-00035
to merge, fetch, and freshly certify the exact remote `main` commit before signing it. Historical
`f2a0d960e19fd9d9ddcf605345220a190374ef96` certification remains recorded in T-00102 but is superseded as
the current release-acceptance authority.
