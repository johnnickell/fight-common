---
id: T-00091
prd: PRD-00020
title: Rebuild the Repository README as the Fight Common Entry Surface
status: done
blocked_by:
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

- [x] The README identifies Fight Common and its framework-neutral purpose immediately using the canonical mark
      and Fight Common lockup with meaningful alternative text.
- [x] The shortest valid Composer installation is prominent and current.
- [x] A compact explanation of Adapter, Application, and Domain establishes architectural fit without replacing
      the full Architecture guide.
- [x] Representative capabilities support the product promise and route into the canonical atlas and guides.
- [x] Quick Start, Architecture, Components, Frameworks, contribution guidance, repository, and license links use
      the final production routes and resolve correctly.
- [x] Detailed semantics remain in the documentation rather than creating a second corpus to maintain.
- [x] Badges and claims are factual, useful, and restrained; no popularity or inflated-quality claims are added.
- [x] The README remains understandable when imagery is unavailable and renders within GitHub constraints.

## Verification

- Render through a GitHub-compatible Markdown path and inspect desktop and narrow presentation.
- Validate all repository, documentation, identity, contribution, and license links deterministically.
- Run `./bin/planning-check`, `git diff --check`, and the canonical `./bin/build`.

## Completion Notes

Delivered a compact GitHub entry surface with the canonical themed lockup, visible product heading,
framework-neutral promise, exact Composer installation, restrained factual badges, inward architecture proof,
four capability groups, and direct authoritative project and documentation routes. Detailed Doctrine guidance
now stays in the canonical Repositories guide.

The read-only README validator renders the supported GitHub-safe Markdown subset, resolves local paths and
fragments, ties production routes back to canonical documentation sources, constrains raw HTML to the exact
themed identity treatment, and is composed with its fixture suite into `./bin/docs validate`. Focused fixtures,
documentation validation, planning validation, and Brave inspection at 375px and 1280px in both themes passed;
the unavailable-image pass preserved meaningful fallback text, the visible heading, and page width. Commit,
push, pull request, merge, hosted CI, Pages publication, and task-worktree cleanup remain separate, unperformed
effects.
