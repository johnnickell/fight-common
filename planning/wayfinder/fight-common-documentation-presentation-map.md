# Fight Common Documentation Presentation

**Label:** `wayfinder:map`
**Status:** Active

> This map is an **index, not a store**. Each material decision lives in exactly one linked ticket under
> `tickets/`; this map only summarizes the linked resolutions and shows the next decision frontier.

## Destination

Produce an implementation-ready presentation program in which Fight Common documentation and its repository
README are the primary product, a reusable Fight family mark anchors the identity, and a Fight Common lockup
identifies this package. Adapt that system to John's GitHub profile only far enough to showcase the open-source
Fight work clearly within GitHub README constraints.

The program must preserve the documentation's useful technical behavior: discoverable component guidance,
copyable examples, search, navigation, stable URLs, responsive layouts, accessible themes, and GitHub Pages
deployment under `/fight-common/`. It may reshape information architecture, visual presentation, assets, and
documentation delivery, but it does not change Fight Common's PHP public API.

**Done** = every linked decision ticket is closed, the remaining fog is resolved or excluded, and the map links
to the resulting implementation epic, PRDs, and executable tickets for the documentation, repository README,
reusable Fight identity assets, and constrained GitHub-profile adaptation.

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

- [Establish the presentation destination and ownership](tickets/WF-026-establish-presentation-destination-and-ownership.md)
  makes Fight Common documentation and README primary, the GitHub profile a constrained secondary adaptation,
  and a reusable Fight family mark with product lockups the identity target.
- [Research documentation references and delivery constraints](tickets/WF-027-research-documentation-references-and-delivery-constraints.md)
  confirms that the current MkDocs Material and GitHub Pages foundation can support substantial presentation
  work while preserving the existing Markdown, search, snippets, navigation, URLs, and `/fight-common/` base
  path; generator migration remains conditional.
- [Define the audience, promise, entry actions, and component taxonomy](tickets/WF-028-define-audience-promise-entry-actions-and-taxonomy.md)
  prioritizes PHP developers and architects, gives the README and documentation surfaces distinct jobs, makes
  Architecture, Quick Start, and a directly linked problem-oriented component atlas the homepage entry routes,
  and fixes the real content used in later visual comparisons.

## Tickets

| Ticket | Type | Mode | Status | Depends On |
|---|---|---|---|---|
| [Establish the presentation destination and ownership](tickets/WF-026-establish-presentation-destination-and-ownership.md) | Grilling / Domain Modeling | HITL | **Closed** | — |
| [Research documentation references and delivery constraints](tickets/WF-027-research-documentation-references-and-delivery-constraints.md) | Research | AFK | **Closed** | Presentation destination |
| [Define the audience, promise, entry actions, and component taxonomy](tickets/WF-028-define-audience-promise-entry-actions-and-taxonomy.md) | Grilling / Domain Modeling | HITL | **Closed** | Destination and research |
| [Establish the Fight visual system and logo brief](tickets/WF-029-establish-visual-system-and-logo-brief.md) | Prototype / Design Consultation | HITL | **Open** | Audience and promise |
| [Generate and select reusable logo directions](tickets/WF-030-generate-and-select-reusable-logo-directions.md) | Prototype / Taste Design | HITL | **Open** | Visual system and logo brief |
| [Compare homepage and article-shell directions](tickets/WF-031-compare-homepage-and-article-shell-directions.md) | Prototype / Design Shotgun | HITL | **Open** | Selected logo direction |
| [Build and review the selected responsive prototype](tickets/WF-032-build-and-review-responsive-prototype.md) | Prototype / Design HTML and Review | HITL | **Open** | Selected page direction |
| [Select the documentation delivery architecture](tickets/WF-033-select-documentation-delivery-architecture.md) | Grilling / Domain Modeling | HITL | **Open** | Reviewed prototype |
| [Design the GitHub-profile adaptation](tickets/WF-034-design-github-profile-adaptation.md) | Prototype / Grilling | HITL | **Open** | Audience and promise |
| [Define compatibility and presentation quality gates](tickets/WF-035-define-compatibility-and-quality-gates.md) | Grilling / Domain Modeling | HITL | **Open** | Audience and promise |
| [Produce the implementation planning handoff](tickets/WF-036-produce-implementation-planning-handoff.md) | Grilling / Domain Modeling | HITL | **Open** | Delivery architecture, profile adaptation, and quality gates |

## Blocking relationships

```text
Audience and promise ──→ Visual system ──→ Logo ──→ Page directions ──→ Prototype ──→ Delivery architecture
          ├────────────→ GitHub-profile adaptation
          └────────────→ Compatibility and quality gates

Delivery architecture + GitHub-profile adaptation + Compatibility and quality gates
    ──→ Implementation planning handoff
```

## Frontier

The audience decision exposes three unblocked tickets. Work through one per session; the recommended next ticket
in the program sequence is listed first:

1. [Establish the Fight visual system and logo brief](tickets/WF-029-establish-visual-system-and-logo-brief.md)
2. [Design the GitHub-profile adaptation](tickets/WF-034-design-github-profile-adaptation.md)
3. [Define compatibility and presentation quality gates](tickets/WF-035-define-compatibility-and-quality-gates.md)

## Not yet specified (fog)

- No additional decision is precise enough to ticket. Graduate new questions one at a time when the visual and
  delivery decisions expose them.

## Out of scope

- PHP public API or runtime behavior changes.
- Implementing documentation, README, logo, profile, renderer, search, or deployment changes during Wayfinding.
- Publishing the GitHub-profile adaptation or deploying the documentation site.
- Committing, pushing, opening or merging a pull request, and removing this planning worktree without their own
  approvals.
