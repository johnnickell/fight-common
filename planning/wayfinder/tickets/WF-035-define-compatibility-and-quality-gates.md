# Define compatibility and presentation quality gates

**Labels:** `wayfinder:grilling`, `wayfinder:domain-modeling`
**Mode:** HITL
**Status:** Closed
**Map:** [Fight Common Documentation Presentation](../fight-common-documentation-presentation-map.md)
**Depends on:** [Define the audience, promise, entry actions, and component taxonomy](WF-028-define-audience-promise-entry-actions-and-taxonomy.md)

## Question

What content, URL, accessibility, performance, SEO, rendering, and review evidence must pass before the new
documentation presentation is accepted?

## Must decide

- content accuracy, source-code alignment, snippet execution, component coverage, and link-integrity gates;
- stable URL inventory, redirect policy, `/fight-common/` asset-path behavior, canonical metadata, sitemap, and
  custom 404 acceptance;
- WCAG 2.2 AA contrast and interaction targets, semantic structure, keyboard navigation, focus visibility,
  reduced motion, zoom, and assistive-technology checks;
- responsive acceptance for representative mobile, tablet, and desktop widths;
- performance budgets for generated HTML, fonts, images, CSS, JavaScript, search, and key page loads;
- SEO and social-card requirements that remain truthful for an open-source library; and
- local, automated, browser, visual-regression, clean-checkout, and hosted Pages evidence boundaries.

## Resolution boundary

The eventual gate must cover asset paths, search, both themes, keyboard use, reduced motion, WCAG AA, mobile
layouts, component links, stable URLs, and the custom 404. This ticket defines evidence and thresholds; it does
not run production acceptance or claim hosted deployment success.

## Resolution

Accept the presentation when every capability advertised by the homepage atlas leads to trustworthy,
task-oriented documentation. Each component article identifies its purpose and architectural ownership,
installation or dependency requirements, a realistic example, genuinely supported framework or configuration
variants, consequential behavior, and useful next steps. Execute representative PHP journeys, apply pragmatic
syntax checks to configuration formats, and verify referenced public classes and repository links. Human review
remains the authority for explanatory accuracy; do not build machinery that attempts to execute every prose or
configuration fragment.

Use the grouped routes selected by the delivery architecture as the new canonical inventory. The current
low-traffic routes need no redirects during this one approved reset. After publication, canonical routes and
important linked anchors become compatibility commitments; later removal requires a working alias or redirect.
The generated site must resolve navigation, atlas cards, next-step links, article anchors, base-relative assets,
search entries, canonical URLs, and sitemap entries beneath `/fight-common/`. The custom 404 must preserve the
Fight Common presentation and provide a working return to the project base.

Target WCAG 2.2 AA in both light and dark themes while retaining the prototype's stronger 44px interactive
control target. Acceptance covers semantic landmarks and headings, skip navigation, keyboard operation, visible
and unobscured focus, color-independent meaning, contrast, reduced motion, reflow, zoom, and readable scrolling
code. Before the initial deployment, perform one lightweight keyboard and screen-reader smoke check. This is a
practical accessibility gate, not a claim of exhaustive assistive-technology certification.

Responsive visual acceptance is a human review in Brave: resize representative pages through narrow mobile,
tablet, and wide desktop layouts and confirm that content, navigation, diagrams, tabs, controls, and code remain
available without page-level overflow, clipping, or unusable layout. Do not add a routine viewport automation
matrix, cross-browser matrix, or visual-regression suite. Repeat this review when shared layout, theme,
navigation, search, or interactive presentation behavior changes, and when new content introduces a materially
new visual pattern; ordinary prose edits do not trigger it.

Do not impose Lighthouse scores, Core Web Vitals thresholds, or performance browser tests while no observed
performance problem exists. Record a simple launch baseline of generated HTML, CSS, JavaScript, font, image,
and search-index sizes so a future investigation has a comparison point. Keep fonts self-hosted and subset,
avoid unexpected third-party requests, and investigate material growth or user-visible slowness when it is
actually observed rather than extending every build preemptively.

SEO and sharing remain factual and modest: every canonical page has an accurate unique title and description,
indexable semantic HTML, canonical metadata, and sitemap membership. Supply the approved logo and favicons plus
one reusable Fight Common social card. Do not add popularity badges, inflated claims, per-page social-card
generation, or custom structured-data machinery without a demonstrated need.

Keep routine evidence fast and deterministic. Pull requests run the strict MkDocs build plus lightweight checks
for content/source contracts, links, canonical routes, `/fight-common/` assets, search-index coverage, snippets,
and the custom 404; they do not run browsers, Lighthouse, visual regression, or the manual acceptance review.
Before the separately approved first deployment, require a clean-checkout documentation build, John's Brave
responsive review, and the keyboard and screen-reader smoke check. After deployment, separately verify the real
Pages routes, assets, search, canonical metadata, sitemap, and 404. No pre-deployment result may be reported as
hosted Pages success.
