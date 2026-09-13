# Ticket Board

Operational execution view for Fight Common. Ticket files are canonical for status and blocking edges; this
board is canonical for recommended order. IDs identify artifacts only. Update this file whenever new planning
creates an executable frontier.

Last updated: 2026-09-12

## “What’s Next?” Contract

When `/ask-matt` or a plain “What’s next?” is invoked:

1. **Human decision:** return the item under **Now** when it still requires judgment.
2. **Implementation:** return the first ticket under **Ready Frontier**.
3. If the question is unqualified, return both targets. Never choose by ticket number alone.

## Now

No current Fight Common human decision. All prior planning records are archived.

## Wayfinder Review

No active Wayfinder map has an unblocked review candidate. New uncertainty begins with a new map from the
[Wayfinder template](../wayfinder/_MAP_TEMPLATE.md).

## Ready Frontier

No ready Fight Common implementation ticket. New implementation work requires fresh planning and an approved
ticket.

## Waiting

| Suggested Order | Ticket | Parent PRD | Waiting On |
|-----------------|--------|------------|------------|
| — | No waiting tickets | — | — |

## Needs Info

No tickets currently require a decision authority.

## Future Planning

Potential 2.0 work is not planned here. Start a new Wayfinder map in the repository that owns the proposed
scope; do not reopen archived Fight Common records.
