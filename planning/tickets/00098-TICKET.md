---
id: T-00098
prd: PRD-00022
title: Accept the Initial Documentation Release Candidate
status: ready-for-agent
blocked_by: T-00091,T-00092,T-00093,T-00094,T-00095,T-00096,T-00097
---

# Accept the Initial Documentation Release Candidate

## Outcome

Prove from a clean checkout that the complete Fight Common documentation candidate is accurate, internally
coherent, accessible, responsive, and ready for John's separately authorized `develop` to `main` merge without
turning that focused launch review into a permanent slow browser suite.

## Scope

- In scope: clean documentation artifact, complete deterministic contracts, representative content accuracy,
  John's Brave resize review, lightweight keyboard and screen-reader smoke check, both themes, reduced motion,
  zoom and reflow, launch asset-size baseline, and recorded predeployment evidence.
- Out of scope: merging, deploying, hosted-success claims, routine cross-browser or viewport matrices,
  Lighthouse thresholds, screenshot regression, exhaustive assistive-technology certification, or profile work.

## Acceptance Criteria

- [ ] A clean checkout produces the strict documentation artifact with every canonical route, atlas card,
      navigation target, article anchor, search entry, snippet, asset, canonical URL, sitemap entry, and custom
      404 valid beneath `/fight-common/`.
- [ ] Homepage, README, Quick Start, Architecture, Mail, Messaging, and representative remaining component and
      framework guides are editorially accurate and aligned with public source and support contracts.
- [ ] John resizes the representative pages in Brave through narrow mobile, tablet, and wide desktop layouts and
      accepts their hierarchy, navigation, diagrams, tabs, controls, code, and lack of page-level overflow.
- [ ] One keyboard and screen-reader smoke check proves landmarks, headings, skip navigation, visible and
      unobscured focus, search, menu, theme, copy, tabs, links, and state announcements.
- [ ] Light and dark themes meet WCAG 2.2 AA contrast, retain color-independent meaning, and preserve 44px target,
      reduced-motion, 200% zoom, reflow, and readable scrolling-code behavior.
- [ ] A simple baseline records generated HTML, CSS, JavaScript, font, image, and search-index sizes without
      imposing a Lighthouse or Core Web Vitals gate.
- [ ] Pull-request checks remain fast and deterministic and contain no browser, screenshot, or performance-score
      suite.
- [ ] The evidence explicitly describes the candidate as predeployment and does not claim hosted Pages success.
- [ ] The canonical `./bin/build` passes before the candidate is committed or offered for pull-request review.

## Verification

- Run the clean-checkout documentation build, artifact contracts, representative executable examples,
  `./bin/planning-check`, `git diff --check`, and `./bin/build`.
- Record John's focused Brave review and the keyboard/screen-reader smoke-check outcome separately from automation.

## Completion Notes

Pending T-00091 through T-00097.
