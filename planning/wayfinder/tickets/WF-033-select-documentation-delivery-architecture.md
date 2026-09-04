# Select the documentation delivery architecture

**Labels:** `wayfinder:grilling`, `wayfinder:domain-modeling`
**Mode:** HITL
**Status:** Closed
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
search, snippets, navigation, and durable URLs after any explicitly approved route reset, and the replacement
proves those behaviors before the handoff. This ticket selects architecture; it does not implement or deploy it.

## Resolution

Retain **MkDocs Material** as the renderer and keep the existing Markdown corpus as the content source. The
reviewed Atlas Deck direction fits Material's existing search, highlighting, copy, tab, navigation, palette, and
snippet behavior; no generator migration or theme fork is justified. Keep `mkdocs.yml` as the explicit
navigation authority. Express the presentation through repository-owned CSS and assets, page-specific templates,
and the narrowest practical block, partial, or JavaScript overrides. Prefer Material's native behavior whenever
it already satisfies the fixed point.

Use one repository-owned documentation requirements file with the complete Python documentation toolchain pinned
to exact versions, plus an explicit Python version in CI. Local preview and strict production builds use that
same dependency set through repository commands. Generated `site/` output is disposable and untracked; source
Markdown, configuration, templates, styles, scripts, and assets are the only repository-owned documentation
inputs.

Pull requests build and validate the documentation but never publish it. A successful build from `main` uploads
the generated site as a GitHub Pages artifact and deploys it through the protected `github-pages` environment.
This replaces branch-writing `mkdocs gh-deploy --force`; the two publication models do not run in parallel.
Rollback means redeploying the last known-good artifact when available or reverting the responsible source
commit and rebuilding it.

Use `https://johnnickell.github.io/fight-common/` as the production site URL and `/fight-common/` as the required
project base. The redesign is the one permitted URL reset while adoption is still negligible. Establish grouped
canonical routes such as `/quick-start/`, `/architecture/`, `/components/<name>/`, `/frameworks/<name>/`, and
`/maintenance/<name>/`; current low-traffic routes do not require redirects. Once published, the new routes
become compatibility commitments. The production build must emit correct base-relative assets, canonical URLs,
search entries, navigation targets, article anchors, and a custom 404 that returns readers to the project base.

This resolution selects the delivery architecture only. It does not implement the renderer, rewrite content,
publish GitHub Pages, or authorize any PHP public API change.
