---
id: T-00019
prd: PRD-00007
title: Migrate and enable mechanical coding rules
status: ready-for-agent
blocked_by: T-00018
---

# Migrate and Enable Mechanical Coding Rules

## Acceptance

- Production imports are alphabetically ordered.
- Strict types, array comma and alignment conventions, and blank lines before non-trivial returns match the published standard.
- Automatically fixable changes are reviewed for semantic preservation before acceptance.
- The migrated rules are enabled with zero baseline or suppressed legacy violations.
- The complete submit gate remains green with exact complete coverage.
