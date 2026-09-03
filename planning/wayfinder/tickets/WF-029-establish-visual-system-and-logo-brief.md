# Establish the Fight visual system and logo brief

**Labels:** `wayfinder:prototype`
**Mode:** HITL
**Status:** Closed
**Map:** [Fight Common Documentation Presentation](../fight-common-documentation-presentation-map.md)
**Depends on:** [Define the audience, promise, entry actions, and component taxonomy](WF-028-define-audience-promise-entry-actions-and-taxonomy.md)

## Question

What visual system should express Fight's character across documentation and README constraints, and what brief
should govern a reusable Fight family mark and the Fight Common lockup?

## Must decide

- the desired emotional register and the visual clichés to reject;
- typography roles and practical web/code-font constraints;
- accessible light and dark palettes, semantic colors, contrast targets, and surface hierarchy;
- spacing, shape, border, texture, illustration, diagram, and motion principles;
- two or three deliberate creative bets that make the system recognizably Fight; and
- the logo brief: meaning, geometry, wordmark relationship, small-size behavior, monochrome use, and prohibited
  motifs.

## Resolution boundary

Use `$aios /design-consultation` with Frontend Design as the standing philosophy. This ticket approves the
system and logo brief, not a final logo asset or page composition.

## Resolution

Adopt the [Fight design system](../../../DESIGN.md) as the presentation source of truth. Fight expresses
**tempered precision**: calm, rigorous, durable, and quietly forceful. Its signature metaphor is a boundary under
load, not literal combat.

- Use Open Sans SemiBold for headings and structural labels, Source Sans 3 for long-form reading, and Fira Code
  with programming ligatures for code. The responsive-prototype review replaced the initially approved Barlow
  Semi Condensed because Open Sans kept long technical headings cleaner at intermediate widths. Self-host
  release-pinned WOFF2 files with system fallbacks; disable ligatures only when exact character comparison is the
  lesson.
- Use cool mineral light surfaces and carbon dark surfaces with kiln orange reserved for actions, crossings, and
  active seams. Theme-specific text, semantic, and essential-boundary pairs meet their applicable WCAG AA
  contrast thresholds; labels, position, shape, and icons prevent color-only meaning.
- Use a 4px spacing grid, mostly square structural frames, 2–6px radii on interactive surfaces, restrained
  two-to-four-percent gradients, and low-opacity shadows only where elevation or action needs depth.
- Treat architecture diagrams as the illustration system. Dependency direction and layer ownership remain clear
  through labels, position, and edge styles before color. Decorative stock illustration is excluded.
- Keep motion responsive and nearly still: 120–180ms interaction feedback, no ambient or scroll animation, and
  no meaning that depends on motion. Respect reduced-motion preferences.
- Make the load-bearing seam, ownership rail, and structural F the deliberate creative bets. Preserve oversized
  real-code treatment as a later page-direction experiment rather than a system-wide requirement.
- Build the reusable family mark from substantial opposing forms that hold protected negative space and resolve
  into a structural F. Pair it with a stable `FIGHT` wordmark and replaceable `COMMON` descriptor. The mark must
  survive at 16px, in one color, reversed, and without effects.
- Reject fists, gloves, weapons, shields, mascots, flames, military insignia, hacker neon, generic code brackets,
  literal hexagons, comic-book aggression, and gradient-dependent identity.

The next logo ticket may explore drawings within this brief, but it may not silently replace the system or treat
a generated raster as the editable source asset.
