# Build and review the selected responsive prototype

**Labels:** `wayfinder:prototype`
**Mode:** HITL
**Status:** Closed
**Map:** [Fight Common Documentation Presentation](../fight-common-documentation-presentation-map.md)
**Depends on:** [Compare homepage and article-shell directions](WF-031-compare-homepage-and-article-shell-directions.md)

## Question

Does the selected direction work as a runnable responsive documentation experience, and what repairs are needed
before its architecture is selected?

## Must decide

- the representative homepage, article shell, component-atlas route, navigation, search, and theme interactions;
- mobile, tablet, desktop, keyboard, and reduced-motion behavior;
- hierarchy, typography, line length, code readability, focus treatment, and link affordance;
- whether the direction survives real Fight Common content without decorative filler or common AI-design
  clichés; and
- the reviewed fixed point that the delivery architecture must preserve.

## Resolution boundary

Use `$aios /design-html` to build the selected direction and `$aios /design-review` to inspect and repair it.
Start with a bespoke MkDocs Material presentation. Keep all disposable prototype output in the task-owned run
directory; this ticket does not authorize production documentation edits.

## Resolution

Approve the repaired **Atlas Deck** responsive prototype as the presentation fixed point for delivery
architecture. The self-contained HTML artifact remains disposable evidence in the task-owned run directory; the
decision, not the prototype file, is durable.

The homepage keeps the promise and Composer command first, makes Architecture, Quick Start, and Explore
Components equal entry routes, and exposes the complete problem-grouped atlas without intermediate category
pages. Its architecture proof distributes Adapter, Application, and Domain across one full-width flex chain and
stacks the layers only below 480px. The deterministic Inward Port SVG replaces the exploratory raster stand-in.

The representative Mail article keeps component navigation, prose and code, local table of contents, ownership,
dependency metadata, architecture diagram, and next action visible in a conventional documentation shell. At
compact widths, the component navigation becomes a horizontal rail and the table of contents leaves the reading
flow. Stable in-article anchors preserve the article context.

Replace Barlow Semi Condensed with **Open Sans SemiBold** for headings and structural labels. Source Sans 3
remains the reading face, and Fira Code remains the ligature-enabled code face. The less compressed heading face
keeps long technical titles calm and legible at intermediate widths while the structural mark and ownership rails
carry the distinctive Fight identity.

Configuration examples may expose accessible format tabs when the framework or tool genuinely supports
equivalent formats. The prototype proves YAML, XML, and PHP service-container examples with mouse and arrow-key
selection, stable tab and panel semantics, filenames, and per-format copy controls. Do not invent format parity
for presentation symmetry. Kiln-orange warning callouts are approved for consequential behavior; they require an
icon, explicit label, and explanatory copy so meaning never depends on color. The Mail specimen uses the real
recipient-override replacement behavior.

Review repairs included the copy control's flex sizing, resilient layer and port diagrams, equal dependency-cell
distribution, 44px compact controls, mobile overflow, configuration-tab behavior, and article-anchor routing.
Light and dark themes remain peers, code scrolls within its own surface where necessary, and motion stays limited
to interaction feedback with reduced-motion support.

## Design Review

| Dimension | Before | After | Reviewed fixed point |
|---|---:|---:|---|
| Visual hierarchy | 8 | 9 | Promise, entry routes, atlas, and article task form one clear sequence. |
| Typography | 7 | 9 | Open Sans SemiBold replaces the overly compressed Barlow treatment. |
| Color | 9 | 9 | Cold steel and scarce kiln orange remain consistent in both themes. |
| Spacing and rhythm | 8 | 9 | Copy, diagrams, routes, and article sections now hold at intermediate widths. |
| Consistency | 8 | 9 | Ownership rails, code heads, tabs, and consequential callouts share one grammar. |
| Responsiveness | 6 | 9 | Reported overflow and collapsed diagram failures are repaired from 375px to 1440px. |
| Interactive states | 7 | 9 | Search, theme, menu, copy, anchors, and format tabs have working state feedback. |
| AI slop | 8 | 9 | Architecture remains the visual material; decoration stays restrained and functional. |
| Accessibility | 7 | 9 | Semantic regions, tab roles, labels, focus, 44px controls, and color-independent warnings hold. |

The remaining step to reach a production-level ten is implementation through the selected renderer with
self-hosted pinned fonts, real generated navigation and search, every stable route, the custom 404, and automated
accessibility and performance evidence. Those are delivery-architecture and quality-gate decisions, not defects
in the approved prototype.

Verification covered the homepage and Mail article at 375px, 768px, 1280px, and 1440px without page overflow;
mouse and keyboard configuration-tab selection; visible keyboard focus beginning with the skip link; light and
dark themes; search, menu, copy, and local-anchor behavior; reduced-motion CSS; WCAG AA palette ratios; and a
clean browser console.
