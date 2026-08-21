---
id: T-00032
prd: PRD-00010
title: Establish release inspection, plans, and boundary fakes
status: done
blocked_by:
---

# Establish Release Inspection, Plans, and Boundary Fakes

## What to Build

Implement the public inspection and planning journey behind `bin/release`. An operator can inspect a
candidate, approve an exact version, create an immutable content-addressed plan, and receive a versioned
machine result without performing a release effect. Establish the capability boundary and deterministic
fakes/effect ledgers required by later journeys.

## Acceptance Criteria

- [x] Inspection requires every ADR 0013 compatibility category with category-scoped stable finding and evidence
      IDs, recommends the maximum independently classified minimum SemVer increment without making it authoritative,
      and stops before Git effects for incomplete, duplicate, unknown, malformed, or indeterminate evidence or a
      caller-declared aggregate, recommendation, or class field such as legacy `change_class`; the closed
      top-level candidate schema permits only documented authority fields and controlled boundary fixtures.
- [x] Plan creation requires one typed approval binding the exact version, candidate OID, canonical baseline tag
      and OIDs, evidence-manifest digest, complete exception-ID set, inspected minimum class, and derived actual
      baseline-relative class; stale, incomplete, wildcard, or version-only approval stops before hash/write.
      Planning accepts the inspected minimum or any higher stable SemVer, and accepts a lower version only when it
      is the next patch and one complete repository-approved
      patch-exception authority binds the matching exception ID, exact version, candidate and baseline OIDs,
      one eligible emergency class, positive no-compatible-repair evidence, the complete inspected compatibility
      assessment, exactly its non-patch findings, impact, mitigation, test evidence, recovery posture, evidence
      digest, canonical authority digest, and approval; that approval also binds the authority digest set.
      The lower-patch plan contains exactly one matching reference and one authority record for its approved
      version, and the authority assessment derives exactly the plan's inspected minimum class.
      Plans approving the inspected minimum or a higher version contain no patch-exception reference or authority
      record, and their release approvals bind an empty patch-exception authority digest set.
      Incomplete, wildcard, duplicate, ambiguous, unrelated, stale, unreferenced, or mismatched records stop before
      hash/write.
- [x] Canonically equivalent inputs produce the same `plan_id`; any material bound-input change produces a
      different identity.
- [x] Every normally completed invocation emits the versioned machine result, stable exit classification,
      detailed findings, proposed effects, and exactly one next action.
- [x] Boundary fakes cover success, refusal, failure, uncertainty, drift, and configured crash points without
      loading production credentials or performing external mutation.
- [x] Configured crash points record only the attempted effect and interrupt before normal machine JSON, later
      effects, artifacts, or persisted postconditions; ordinary completed invocations retain the machine-result
      contract.
- [x] Runtime bootstrap failures alone use exit `70`; after the canonical PHP process or container starts,
      termination without a valid authenticated result uses exit `71`, status `infrastructure_terminated`, finding
      `release.runtime.result_unavailable`, empty effects and postconditions, and the single action
      `inspect_release_runtime_termination`, while governed results and authenticated crash `86` pass through.
- [x] `bin/release` rejects commands and effects outside their declared capability boundary before recording
      an effect.

## Verification

Full submit gate, `./bin/planning-check`, and public-command inspection and planning journey tests.

## Parent

PRD-00010 — Deterministic Release Foundation.

## Outcome

Delivered the executable Docker-facing `bin/release` inspection and immutable planning foundation. Inspection
derives the minimum release class from complete category-scoped compatibility evidence; planning binds typed
release approval, baseline resolution, exact version semantics, canonical exception authority, proposed effects,
and content-addressed artifacts confined below `.runs/`. The Application-owned capability ports and one
credential-free deterministic fake expose exact ordered outcomes, including verified already-satisfied state and
configured crash interruption, without performing an external release effect.

The canonical `./bin/build` passed 3,416 tests and 10,260 assertions with exact 11,338/11,338 statement coverage.
Planning integrity, PHPCS, PHPStan, Deptrac, Rector, PHP/Python syntax, PHPUnit, and exact coverage all passed.
Fresh independent Spec and Standards reviews reported no hard findings. No commit, push, PR, publication,
credential use, provider call, or external release mutation was performed by the Coordinate Build.
