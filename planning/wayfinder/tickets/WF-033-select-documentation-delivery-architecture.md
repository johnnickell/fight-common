# Select the documentation delivery architecture

**Labels:** `wayfinder:grilling`, `wayfinder:domain-modeling`
**Mode:** HITL
**Status:** Open
**Map:** [Fight Common Documentation Presentation](../fight-common-documentation-presentation-map.md)
**Depends on:** [Build and review the selected responsive prototype](WF-032-build-and-review-responsive-prototype.md)

## Question

Which renderer, authoring model, search path, preview workflow, and GitHub Pages deployment architecture best
preserve the reviewed design and Fight Common's documentation behavior?

## Must decide

- whether MkDocs Material theme extension can express the reviewed fixed point without brittle forks;
- the renderer and exact dependency-pinning strategy;
- Markdown and snippet compatibility, navigation authority, search indexing, syntax highlighting, and custom
  template boundaries;
- local preview, deterministic production build, generated-output ownership, and CI validation;
- GitHub Pages publication through the existing `gh-pages` path or an artifact-based Pages workflow;
- `/fight-common/` base-path handling, canonical URLs, redirects, and custom 404 generation; and
- migration and rollback boundaries if the selected architecture replaces MkDocs.

## Resolution boundary

MkDocs Material is the default. A generator migration is accepted only if the reviewed direction cannot retain
search, snippets, navigation, and stable URLs cleanly, and the replacement proves those behaviors before the
handoff. This ticket selects architecture; it does not implement or deploy it.
