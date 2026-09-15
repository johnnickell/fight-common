# Fight Common Documentation Presentation

**Label:** `wayfinder:map`
**Status:** Closed

> This map is an **index, not a store**. Each material decision lives in exactly one linked ticket under
> `tickets/`; this map only summarizes the linked resolutions and shows the next decision frontier.

## Destination

Produce an implementation-ready presentation program in which Fight Common documentation and its repository
README are the primary product, a reusable Fight family mark anchors the identity, and a Fight Common lockup
identifies this package. Adapt that system to John's GitHub profile only far enough to showcase the open-source
Fight work clearly within GitHub README constraints.

The program must preserve the documentation's useful technical behavior: discoverable component guidance,
copyable examples, search, navigation, durable URLs after one deliberate route reset, responsive layouts,
accessible themes, and GitHub Pages deployment under `/fight-common/`. It may reshape information architecture,
visual presentation, assets, and documentation delivery, but it does not change Fight Common's PHP public API.

**Done** = every linked decision ticket is closed, the remaining fog is resolved or excluded, and the map links
to the implementation epic that carries these decisions into separately invoked `/to-spec` and `/to-tickets`
handoffs. The separately governed GitHub-profile adaptation may be planned later and does not block this map.

## Notes

- This is a planning-only Wayfinder. Do not implement the site, alter PHP public APIs, publish profile content,
  deploy Pages, or create public-facing assets while resolving this map.
- Fight Common documentation and its repository README are the primary product. The GitHub profile is a
  secondary adaptation, not a second design system.
- The reusable identity target is a Fight family mark plus product lockups, beginning with Fight Common.
- Implementation, commit, push, pull request, deployment, GitHub-profile publication, and cleanup are separate
  approvals.
- Frontend Design is the standing design philosophy throughout exploration and prototyping.
- Begin prototype work with a bespoke MkDocs Material presentation. Migrate generators only if the approved
  direction cannot preserve search, snippets, navigation, and stable URLs cleanly.
- Use the following design-skill sequence as the map advances:
  1. `$aios /wayfinder Fight Common Documentation Presentation` resumes the current frontier.
  2. `$aios /design-consultation` establishes the visual system and creative bets.
  3. `$aios /taste-design` explores logo references; convert the selected concept into editable SVG and
     README-safe exports.
  4. `$aios /design-shotgun` produces four to six genuinely different homepage and article-shell directions.
  5. `$aios /design-html` turns the selected direction into a runnable responsive prototype.
  6. `$aios /design-review` reviews and repairs hierarchy, typography, responsiveness, interaction,
     accessibility, and common AI-design clichés.
  7. `/wayfinder` resolves the remaining engineering and GitHub-profile decisions.
  8. `/to-spec` and `/to-tickets` create the implementation handoff only after the map closes.

## Decisions so far

- [Establish the presentation destination and ownership](../../tickets/archive/WF-026-establish-presentation-destination-and-ownership.md)
  makes Fight Common documentation and README primary, the GitHub profile a constrained secondary adaptation,
  and a reusable Fight family mark with product lockups the identity target.
- [Research documentation references and delivery constraints](../../tickets/archive/WF-027-research-documentation-references-and-delivery-constraints.md)
  confirms that the current MkDocs Material and GitHub Pages foundation can support substantial presentation
  work while preserving the existing Markdown, search, snippets, navigation, URLs, and `/fight-common/` base
  path; generator migration remains conditional.
- [Define the audience, promise, entry actions, and component taxonomy](../../tickets/archive/WF-028-define-audience-promise-entry-actions-and-taxonomy.md)
  prioritizes PHP developers and architects, gives the README and documentation surfaces distinct jobs, makes
  Architecture, Quick Start, and a directly linked problem-oriented component atlas the homepage entry routes,
  and fixes the real content used in later visual comparisons.
- [Establish the Fight visual system and logo brief](../../tickets/archive/WF-029-establish-visual-system-and-logo-brief.md)
  adopts tempered precision, cold-steel surfaces with a kiln-orange active seam, Open Sans SemiBold with Source
  Sans 3 and ligature-enabled Fira Code, restrained depth and motion, architecture-led illustration, and a
  reusable structural-F family-mark brief.
- [Generate and select reusable logo directions](../../tickets/archive/WF-030-generate-and-select-reusable-logo-directions.md)
  selects Inward Port — No Lower Rail: a dark structural `F`, one inbound steel approach rail, an orange active
  port, one upper ownership rail, and open lower counterspace, with deterministic SVG and small-size proof
  required during implementation.
- [Compare homepage and article-shell directions](../../tickets/archive/WF-031-compare-homepage-and-article-shell-directions.md)
  selects Atlas Deck: a promise and architecture proof, three prominent entry routes, the complete directly
  linked problem-grouped atlas, a conventional three-column article shell, responsive local navigation, and a
  restrained John Nickell copyright footer.
- [Build and review the selected responsive prototype](../../tickets/archive/WF-032-build-and-review-responsive-prototype.md)
  approves the repaired Atlas Deck fixed point with Open Sans SemiBold headings, the deterministic Inward Port
  SVG, resilient flex and layer diagrams, 375px-to-1440px reflow, accessible interactions, equivalent-format
  configuration tabs, consequential kiln warning callouts, and the representative Mail article.
- [Select the documentation delivery architecture](../../tickets/archive/WF-033-select-documentation-delivery-architecture.md)
  retains pinned MkDocs Material with Markdown and explicit navigation authority, confines customization to
  narrow Atlas Deck presentation seams, validates pull requests without publication, deploys `main` through
  artifact-based GitHub Pages, and permits one grouped-route reset before the new URLs become compatibility
  commitments.
- [Design the GitHub-profile adaptation](../../tickets/archive/WF-034-design-github-profile-adaptation.md)
  keeps John as the profile identity, presents a compact Fight family section as proof of his architecture work,
  makes Fight Common documentation its primary call to action, curates Fight Access Control and the grouped
  framework starters beneath it, and defers a hand-maintained profile publication until canonical assets and
  final documentation URLs have landed.
- [Define compatibility and presentation quality gates](../../tickets/archive/WF-035-define-compatibility-and-quality-gates.md)
  requires trustworthy component guidance, fast deterministic documentation checks, WCAG 2.2 AA in both themes,
  one human Brave responsive review and launch accessibility smoke check, modest truthful metadata, stable new
  routes, and separate hosted Pages verification without routine browser, visual-regression, or performance
  suites.
- [Produce the implementation planning handoff](../../tickets/archive/WF-036-produce-implementation-planning-handoff.md)
  creates [Fight Common Documentation Presentation](../../../epics/archive/00005-EPIC.md) as the permanent implementation
  destination. [TICKET-00020](../../../tickets/archive/00020-TICKET.md) through [TICKET-00022](../../../tickets/archive/00022-TICKET.md) now hold the approved
  identity, content, presentation, and delivery requirements, while [TASK-00088](../../../tasks/archive/00088-TASK.md) through
  [TASK-00099](../../../tasks/archive/00099-TASK.md) form the dependency-ordered implementation graph. The separately governed
  profile adaptation remains a later non-blocking follow-up.

## Tickets

<!-- planning:decisions -->
| Decision ID | Title | Type | Mode | Status | Depends on |
|---|---|---|---|---|---|
| [WF-026](../../tickets/archive/WF-026-establish-presentation-destination-and-ownership.md) | Establish the presentation destination and ownership | wayfinder:grilling, wayfinder:domain-modeling | HITL | Closed | — |
| [WF-027](../../tickets/archive/WF-027-research-documentation-references-and-delivery-constraints.md) | Research documentation references and delivery constraints | wayfinder:research | AFK | Closed | [WF-026](../../tickets/archive/WF-026-establish-presentation-destination-and-ownership.md) |
| [WF-028](../../tickets/archive/WF-028-define-audience-promise-entry-actions-and-taxonomy.md) | Define the audience, promise, entry actions, and component taxonomy | wayfinder:grilling, wayfinder:domain-modeling | HITL | Closed | [WF-026](../../tickets/archive/WF-026-establish-presentation-destination-and-ownership.md), [WF-027](../../tickets/archive/WF-027-research-documentation-references-and-delivery-constraints.md) |
| [WF-029](../../tickets/archive/WF-029-establish-visual-system-and-logo-brief.md) | Establish the Fight visual system and logo brief | wayfinder:prototype | HITL | Closed | [WF-028](../../tickets/archive/WF-028-define-audience-promise-entry-actions-and-taxonomy.md) |
| [WF-030](../../tickets/archive/WF-030-generate-and-select-reusable-logo-directions.md) | Generate and select reusable logo directions | wayfinder:prototype | HITL | Closed | [WF-029](../../tickets/archive/WF-029-establish-visual-system-and-logo-brief.md) |
| [WF-031](../../tickets/archive/WF-031-compare-homepage-and-article-shell-directions.md) | Compare homepage and article-shell directions | wayfinder:prototype | HITL | Closed | [WF-030](../../tickets/archive/WF-030-generate-and-select-reusable-logo-directions.md) |
| [WF-032](../../tickets/archive/WF-032-build-and-review-responsive-prototype.md) | Build and review the selected responsive prototype | wayfinder:prototype | HITL | Closed | [WF-031](../../tickets/archive/WF-031-compare-homepage-and-article-shell-directions.md) |
| [WF-033](../../tickets/archive/WF-033-select-documentation-delivery-architecture.md) | Select the documentation delivery architecture | wayfinder:grilling, wayfinder:domain-modeling | HITL | Closed | [WF-032](../../tickets/archive/WF-032-build-and-review-responsive-prototype.md) |
| [WF-034](../../tickets/archive/WF-034-design-github-profile-adaptation.md) | Design the GitHub-profile adaptation | wayfinder:prototype, wayfinder:grilling | HITL | Closed | [WF-028](../../tickets/archive/WF-028-define-audience-promise-entry-actions-and-taxonomy.md) |
| [WF-035](../../tickets/archive/WF-035-define-compatibility-and-quality-gates.md) | Define compatibility and presentation quality gates | wayfinder:grilling, wayfinder:domain-modeling | HITL | Closed | [WF-028](../../tickets/archive/WF-028-define-audience-promise-entry-actions-and-taxonomy.md) |
| [WF-036](../../tickets/archive/WF-036-produce-implementation-planning-handoff.md) | Produce the implementation planning handoff | wayfinder:grilling, wayfinder:domain-modeling | HITL | Closed | [WF-033](../../tickets/archive/WF-033-select-documentation-delivery-architecture.md), [WF-034](../../tickets/archive/WF-034-design-github-profile-adaptation.md), [WF-035](../../tickets/archive/WF-035-define-compatibility-and-quality-gates.md) |
<!-- /planning:decisions -->

## Blocking relationships

```text
Audience and promise ──→ Visual system ──→ Logo ──→ Page directions ──→ Prototype ──→ Delivery architecture
          ├────────────→ GitHub-profile adaptation
          └────────────→ Compatibility and quality gates

Delivery architecture + GitHub-profile adaptation + Compatibility and quality gates
    ──→ Implementation planning handoff
```

## Frontier

None. The map is closed. Continue through the Board's implementation frontier, beginning with TASK-00088 and
TASK-00089.

## Not yet specified (fog)

- No additional decision is precise enough to ticket. Graduate new questions one at a time when the visual and
  delivery decisions expose them.

## Out of scope

- PHP public API or runtime behavior changes.
- Implementing documentation, README, logo, profile, renderer, search, or deployment changes during Wayfinding.
- Publishing the GitHub-profile adaptation or deploying the documentation site.
- Committing, pushing, opening or merging a pull request, and removing this planning worktree without their own
  approvals.
