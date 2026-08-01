---
id: T-00007
prd: PRD-00004
title: Implement the Doctrine DBAL Event Store
status: ready-for-agent
blocked_by: T-00006
---

# Implement the Doctrine DBAL Event Store

## Acceptance

- SQLite and MySQL-compatible schema definitions preserve stream and global uniqueness.
- Expected-version validation and append occur transactionally.
- DBAL behavior conforms to the in-memory contract.
