# Design the GitHub-profile adaptation

**Labels:** `wayfinder:prototype`, `wayfinder:grilling`
**Mode:** HITL
**Status:** Closed
**Map:** [Fight Common Documentation Presentation](../fight-common-documentation-presentation-map.md)
**Depends on:** [Define the audience, promise, entry actions, and component taxonomy](WF-028-define-audience-promise-entry-actions-and-taxonomy.md)

## Question

How should John's GitHub profile adapt the Fight identity and open-source story without duplicating the primary
documentation product or exceeding README rendering constraints?

## Must decide

- the profile audience and the relationship among John, The Thinking Engineer, Fight, and other public work;
- the minimum Fight family and Fight Common presence needed to make the open-source work discoverable;
- the profile's content order, project selection rule, calls to action, and durable maintenance ownership;
- which logo or banner exports remain readable in GitHub light and dark themes and when images are unavailable;
- how the adaptation behaves within GitHub-flavored Markdown, relative or hosted image URLs, and the profile
  README's fixed container; and
- the exact publication handoff while retaining separate approval for changes to `johnnickell/johnnickell`.

## Resolution boundary

This ticket produces a constrained content and presentation decision for the profile repository. It does not
publish the profile, create a second site, or make the profile the authority for Fight documentation.

## Resolution

Keep **John Nickell** as the profile's identity. Present **Fight** immediately after John's introduction as the
primary public proof of his architecture work, then present **The Thinking Engineer** as the place where he
teaches that thinking. Social and contact links close the profile. Fight remains a product family within John's
profile rather than taking over the surface as though it were a company account.

The profile order is:

1. John's existing concise introduction and current architectural focus;
2. a compact Fight open-source section;
3. The Thinking Engineer; and
4. social and contact links.

The Fight section uses a compact, left-aligned Fight family lockup rather than a full-width promotional banner.
It states the family promise in one sentence, features **Fight Common** as the flagship, and puts the Fight Common
documentation link before the source-repository link. **Fight Access Control** receives one supporting entry.
The Symfony, Laravel, Slim, CodeIgniter, and Yii starter repositories appear together as a restrained framework-
starters link row rather than five competing project cards. The section does not reproduce the documentation
homepage, component atlas, installation guide, or architecture explanation.

Only active public Fight projects with a distinct user-facing purpose and a credible README or documentation
path qualify for individual profile placement. Fight Common remains the flagship; supporting packages must earn
their place independently; related starters stay grouped. Repository counts, stars, contribution graphs,
dynamic badges, and other live popularity widgets do not determine inclusion and are excluded from the design.

Use a GitHub-compatible light/dark `<picture>` pair for the family lockup, with deterministic dimensions and
meaningful alternative text so the section remains understandable when images are unavailable. The canonical
editable assets remain with the Fight identity implementation. Copy the reviewed README-safe exports into
`johnnickell/johnnickell` under `assets/fight/` so the profile does not depend on another repository's raw asset
paths. Custom CSS, JavaScript, animation, precise responsive composition, and remote badge services are outside
the profile contract; the Markdown and links must remain clear in GitHub's fixed column at narrow widths.

Maintain the adaptation by hand. Update it only when the positioning, featured projects, or destination links
materially change; do not introduce a generator or metadata synchronization job. Land and verify the canonical
Fight assets and Fight Common documentation first. A later, separately approved change in
`johnnickell/johnnickell` then copies the final exports, uses the final documentation URLs, and verifies GitHub
light and dark rendering, image fallback text, every link, and narrow-width behavior before publication.
