---
id: T-00058
prd: PRD-00015
title: Publish the Normative Supported-Line Window and Activation Suggestions
status: ready-for-agent
blocked_by:
---

# Publish the Normative Supported-Line Window and Activation Suggestions

## What to Build

Record the supported-line window as normative documentation and name the exact optional activation packages
in Composer suggestions. A consumer can read, for each of the five frameworks, the current-only supported
range, the widen trigger, the tighten trigger, the PHP 8.6 horizon, and exactly which packages a
composition activates without any framework dependency entering production requirements.

## Acceptance Criteria

- [ ] Documentation records the current-only window per framework with the exact supported constraints
      (Symfony `^8.1`, Laravel `^13.0`, CodeIgniter `^4.7`, Slim `^4.15` plus the opinionated stack, and the
      current Yii 3 `yiisoft/*` set).
- [ ] Each framework documents its widen trigger (Symfony 8.2 ≈Nov 2026, Laravel 14 ≈Q1 2027, Slim 5
      stable, CodeIgniter 4.8) and the tighten trigger (dropping the previous line when the framework stops
      maintaining it).
- [ ] The PHP 8.6 horizon and its re-resolution requirement are documented for every range.
- [ ] Composer `suggest` entries name the exact optional activation packages for each default composition,
      including the formerly-gap fills (CodeIgniter queue/tasks, Yii portable fills, Laravel event-store and
      metrics, Symfony metrics and Messenger-composed event store, Slim stack fills).
- [ ] Suggestion text stays descriptive and never becomes a version constraint or a production dependency.
- [ ] The documented window, suggest entries, and ADR 0020/0021 tables agree without unresolved or
      contradictory edges.

## Verification

`./bin/planning-check`, a documentation review against ADR 0020 and ADR 0021, and a composer-meta audit
proving production requirements are unchanged and all named suggestions resolve.

## Parent

PRD-00015 — Framework Supported Lines and Default Capability Compositions.
