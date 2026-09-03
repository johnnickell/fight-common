# Build and review the selected responsive prototype

**Labels:** `wayfinder:prototype`
**Mode:** HITL
**Status:** Open
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
