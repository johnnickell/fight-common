---
id: T-00006
prd: PRD-00003
title: Implement the in-memory Event Store
status: ready-for-agent
blocked_by: T-00005
---

# Implement the In-Memory Event Store

## Acceptance

- Append is atomic and rejects stale expected versions and duplicate message IDs.
- Stream and global reads preserve order and limits.
- Its tests define reusable store conformance behavior.
