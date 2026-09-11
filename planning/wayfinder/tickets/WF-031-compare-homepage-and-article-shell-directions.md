# Compare homepage and article-shell directions

**Labels:** `wayfinder:prototype`
**Mode:** HITL
**Status:** Closed
**Map:** [Fight Common Documentation Presentation](../fight-common-documentation-presentation-map.md)
**Depends on:** [Generate and select reusable logo directions](WF-030-generate-and-select-reusable-logo-directions.md)

## Question

Which paired homepage and article-shell direction best delivers the approved audience promise, component atlas,
and Fight visual system using real Fight Common content?

## Must decide

- four to six directions that differ in composition, density, hierarchy, navigation, and content emphasis rather
  than surface color alone;
- how each direction handles the primary promise, proof, entry actions, component discovery, code, architecture,
  and maintenance content;
- how the matching article shell handles title context, local navigation, table of contents, examples, notes,
  and next steps;
- desktop and mobile intent plus light and dark theme behavior; and
- one selected direction, rejected alternatives, and the reasons for selection.

## Resolution boundary

Use `$aios /design-shotgun` and real approved content. These are comparison artifacts, not production templates;
interaction details and responsive repair belong to the selected prototype.

## Resolution

Select **Atlas Deck** as the paired homepage and article-shell direction.

The homepage opens with the framework-neutral promise and shortest Composer installation, backed by a compact
three-layer architecture proof. Architecture, Quick Start, and Explore Components remain prominent parallel
routes. The complete problem-grouped component atlas follows in the main page body, where each category exposes
its component links directly and carries a Domain, Application, and/or Adapter ownership rail. A reader can
therefore find Mail under Connect Systems at a glance without navigating through an intermediate category page.

The matching article shell uses a conventional three-column documentation structure: component navigation on
the left, focused prose and code in the center, and a local table of contents on the right. On compact screens,
the component navigation becomes a horizontally scrollable local rail and the table of contents leaves the
primary reading flow. Articles keep breadcrumbs, layer ownership, dependency metadata, copyable examples,
architectural notes, relationship diagrams, and explicit next steps.

Both shells use the approved Inward Port mark, cold-steel and kiln palette, typography, restrained gradients and
shadows, light and dark themes, visible focus treatment, and nearly still motion. The global footer must display
`© 2026 John Nickell` and link to GitHub, contribution guidance, and the MIT license. Implementation should derive
the year from the build or site configuration when practical rather than require routine prose edits.

The comparison considered six genuinely different directions:

- **Atlas Deck** won because it best balances first-time evaluation, direct component discovery, architecture,
  and guided adoption while matching the approved Symfony-style glanceable taxonomy.
- **Boundary Map** made the architecture memorable but asked component-first readers to decode the model before
  locating a service.
- **Operator Console** optimized dense lookup and code access but made the first visit feel maintainer-oriented.
- **Field Manual** offered the calmest reading experience but reduced at-a-glance component visibility.
- **Component Workbench** made wiring paths concrete but introduced application-like controls beyond the needs of
  a documentation homepage.
- **Search Switchyard** served mixed known-item queries well but made the architecture promise feel secondary.

The HTML comparison remains disposable Wayfinder evidence. WF-032 owns the runnable responsive prototype,
interaction completion, accessibility repair, and final proof against the approved content and delivery
constraints.
