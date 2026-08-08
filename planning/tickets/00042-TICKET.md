---
id: T-00042
prd: PRD-00012
title: Forward-port patches through newer affected lines
status: ready-for-agent
blocked_by: T-00037
---

# Forward-Port Patches Through Newer Affected Lines

## What to Build

Carry a released oldest-line patch through each newer affected supported line in order. Every forward port is
a separate adapted candidate with its own exact base, review provenance, compatibility classification,
certification, publication, and handoff; the current-line result is based on the exact `main` tip.

## Acceptance Criteria

- [ ] Forward ports proceed oldest to newest and no later line begins before its predecessor is reviewed,
      certified, published, and verified.
- [ ] Each step binds the source line, predecessor pull request and commit, exact current base, adapted
      candidate, approvals, required checks, merge receipt, and certification manifest.
- [ ] Textual conflict, semantic incompatibility, missing provenance, moved bases, incomplete hosted checks,
      and expired support stop the chain for the named human decision.
- [ ] Each line has independent compatibility, exception, authorization, effect, and recovery evidence; no
      candidate, approval, exception, or manifest is reused across lines.
- [ ] The final current-line port is a separate reviewed and certified change based on `main`; an older
      maintenance branch never replaces newer contents wholesale.

## Verification

Full submit gate and offline multi-line histories covering ordered success, adaptation, conflict, semantic
difference, missing provenance, moved refs, EOL, and the final `main`-based port.

## Parent

PRD-00012 — Maintenance-Line and Patch Workflows.
