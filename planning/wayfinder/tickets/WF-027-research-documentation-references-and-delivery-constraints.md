# Research documentation references and delivery constraints

**Labels:** `wayfinder:research`
**Mode:** AFK
**Status:** Closed
**Map:** [Fight Common Documentation Presentation](../fight-common-documentation-presentation-map.md)
**Depends on:** [Establish the presentation destination and ownership](WF-026-establish-presentation-destination-and-ownership.md)

## Question

What do the current Fight Common site, strong PHP documentation references, GitHub profile rendering, GitHub
Pages, MkDocs customization, and credible alternative generators imply for the presentation program?

## Research scope

Inspect the live Fight Common documentation and repository configuration; compare the information architecture
of Symfony and Laravel documentation; inspect John's current public profile README; verify GitHub profile and
Pages constraints; identify the supported MkDocs Material customization seams; and compare plausible generator
alternatives without selecting the final architecture.

Record findings in
`planning/wayfinder/research/WF-027-documentation-references-and-delivery-constraints-research.md`.

## Resolution

Resolved by [documentation references and delivery constraints research](../research/WF-027-documentation-references-and-delivery-constraints-research.md).
The current Markdown corpus and MkDocs Material foundation already provide the required search, navigation,
syntax highlighting, snippets, light/dark themes, template extension, and GitHub Pages subpath behavior. The
lowest-risk starting point is a bespoke Material presentation using CSS, assets, and narrow template overrides.

Symfony and Laravel reinforce a layered entry model: orient newcomers, expose task or component routes, and
retain deep reference material. John's profile README is presently text-led and does not showcase Fight, so its
future adaptation should be a compact summary and durable link surface rather than a duplicate documentation
homepage. Docusaurus, Starlight, and VitePress are viable alternatives, but migration must earn its cost by
preserving the accepted design more cleanly without losing current Markdown behavior or URL compatibility.
