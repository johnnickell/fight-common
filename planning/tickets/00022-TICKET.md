---
id: T-00022
prd: PRD-00008
title: Introduce mandatory architecture enforcement
status: ready-for-agent
blocked_by:
---

# Introduce Mandatory Architecture Enforcement

## Acceptance

- Deptrac verifies the canonical `Domain <- Application <- Adapter` dependency direction.
- Domain cannot depend on Application or Adapter; Application cannot depend on Adapter; Adapter may depend on both inward layers.
- External dependency allowances are explicit and justified per layer.
- Existing violations are resolved without a baseline or skipped violations.
- Deptrac is a development dependency, is documented as optional for consumers, and becomes a mandatory build and CI gate.
