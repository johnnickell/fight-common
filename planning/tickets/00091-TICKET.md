---
id: T-00091
prd: PRD-00020
title: Rebuild the Repository README as the Fight Common Entry Surface
status: ready-for-agent
blocked_by: T-00089,T-00090
---

# Rebuild the Repository README as the Fight Common Entry Surface

## Outcome

Give GitHub visitors a concise, trustworthy Fight Common entry point that uses the canonical identity, explains
the framework-neutral value, shows the shortest installation path and architecture proof, and routes readers to
the final documentation rather than duplicating it.

## Scope

- In scope: README-safe identity, purpose and trust signals, Composer installation, compact architecture and
  capability discovery, final documentation routes, contribution guidance, license, and factual badges.
- Out of scope: reproducing detailed component guides, publishing John's profile adaptation, popularity claims,
  unsupported GitHub styling, or changing package behavior.

## Acceptance Criteria

- [ ] The README identifies Fight Common and its framework-neutral purpose immediately using the canonical mark
      and Fight Common lockup with meaningful alternative text.
- [ ] The shortest valid Composer installation is prominent and current.
- [ ] A compact explanation of Adapter, Application, and Domain establishes architectural fit without replacing
      the full Architecture guide.
- [ ] Representative capabilities support the product promise and route into the canonical atlas and guides.
- [ ] Quick Start, Architecture, Components, Frameworks, contribution guidance, repository, and license links use
      the final production routes and resolve correctly.
- [ ] Detailed semantics remain in the documentation rather than creating a second corpus to maintain.
- [ ] Badges and claims are factual, useful, and restrained; no popularity or inflated-quality claims are added.
- [ ] The README remains understandable when imagery is unavailable and renders within GitHub constraints.

## Verification

- Render through a GitHub-compatible Markdown path and inspect desktop and narrow presentation.
- Validate all repository, documentation, identity, contribution, and license links deterministically.
- Run `./bin/planning-check`, `git diff --check`, and the canonical `./bin/build`.

## Completion Notes

Pending T-00089 and T-00090.
