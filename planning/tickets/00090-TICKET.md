---
id: T-00090
prd: PRD-00022
title: Deliver the Atlas Deck Homepage and Documentation Shell
status: ready-for-agent
blocked_by: T-00088,T-00089
---

# Deliver the Atlas Deck Homepage and Documentation Shell

## Outcome

Render Fight Common as the approved Atlas Deck experience: a capability-led homepage and resilient article
shell that preserve MkDocs Material search, navigation, copy, tabs, highlighting, palette, snippets, and project-
base behavior across accessible light, dark, desktop, and compact layouts.

## Scope

- In scope: narrow Material extensions, canonical grouped routes and navigation, homepage hierarchy and atlas,
  article shell, approved typography and themes, responsive navigation and diagrams, configuration tabs, copy
  controls, consequential callouts, footer, metadata, search, sitemap, and custom 404.
- Out of scope: rewriting every guide, repository README content, Pages deployment, browser test matrices,
  generator migration, or PHP public API changes.

## Acceptance Criteria

- [ ] The homepage leads with the framework-neutral promise, shortest Composer installation, and full-width
      Adapter to Application to Domain proof.
- [ ] Architecture, Quick Start, and Explore Components are equal prominent routes followed by the complete
      problem-grouped atlas with direct component links and ownership rails.
- [ ] Canonical routes follow the approved Quick Start, Architecture, Components, Frameworks, and Maintenance
      groups and require no redirects during the accepted one-time reset.
- [ ] The article shell supplies component navigation, breadcrumbs, ownership and dependency metadata, focused
      prose and code, local contents, diagrams, callouts, and next steps.
- [ ] Compact layouts use the approved horizontal component rail, remove local contents from the reading flow,
      stack the architecture chain only below the narrow breakpoint, and avoid page-level overflow.
- [ ] Open Sans SemiBold, Source Sans 3, and ligature-enabled Fira Code are self-hosted, pinned, appropriately
      subset, and paired with practical system fallbacks.
- [ ] Light and dark themes use the approved cold-steel, carbon, and scarce kiln palette with restrained depth,
      visible focus, color-independent meaning, and reduced-motion behavior.
- [ ] Configuration tabs support genuine equivalent formats, pointer and arrow-key selection, semantic state,
      format and filename labels, and correctly sized per-format copy controls.
- [ ] Search, navigation, snippets, highlighting, copy, palette, metadata, sitemap, anchors, base-relative assets,
      and the branded custom 404 work in the generated artifact beneath `/fight-common/`.
- [ ] The footer displays John Nickell's copyright with GitHub, contribution, and MIT-license routes.

## Verification

- Run the strict documentation build and deterministic artifact contract checks.
- Inspect the homepage and one representative article at narrow, intermediate, and wide sizes in both themes;
  this focused implementation check does not create a recurring viewport suite.
- Exercise keyboard navigation, search, copy, format tabs, menu, theme, local anchors, reduced motion, and 404.
- Run `./bin/planning-check`, `git diff --check`, and the canonical `./bin/build`.

## Completion Notes

Pending T-00088 and T-00089.
