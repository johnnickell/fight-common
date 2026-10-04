# Select the first Access Control MCP proof

**Labels:** `wayfinder:grilling`, `wayfinder:domain-modeling`
**Mode:** HITL
**Status:** Closed
**Map:** [Fight Common MCP over HTTP Support](../fight-common-mcp-http-support-map.md)
**Depends on:** [Define MCP tool and CQRS integration conventions](WF-040-define-mcp-tool-and-cqrs-integration-conventions.md), [Define MCP presentation, errors, and response modes](WF-041-define-mcp-presentation-errors-and-response-modes.md), [Define reusable OAuth resource-server support](WF-043-define-reusable-oauth-resource-server-support.md)

## Question

What smallest Fight Access Control journey proves the shared MCP boundaries end to end?

## Why this matters

A deliberately narrow proof can demonstrate real command/query reuse, Agent authentication and permission enforcement, safe View output, and MCP error behavior without treating the map as a feature backlog.

## Evidence required

- The settled shared/consumer ownership and tool/presentation conventions.
- Existing Access Control Agent commands, queries, safe Views, and authorization services.

## Must decide

- which one read and one state-changing use case the paired proof exposes, or the evidenced condition that defers
  the mutation half until an eligible Agent command exists;
- Agent permission and authorization acceptance criteria;
- expected MCP client-visible results and rejections; and
- compatibility/public-API evidence required before a future implementation handoff.

## Resolution boundary

This selects one proof scenario only. It does not authorize implementation or create a product backlog.

## Current evidence

`GetAgentById` is the smallest existing read candidate: it returns the immutable, secret-free `AgentView`, and
its focused tests verify exact safe output and absence of credential material. `ListAgents` is also safe but is
broader than necessary.

No current state-changing Agent command is eligible for an authenticated-Agent proof. Granting, revoking, and
replacing Agent permissions all require a `UserId` actor and enforce User-administrator authorization. Credential
provisioning, rotation, and revocation are Application services rather than Commands, and some return raw HMAC
secret material. There is no supported bridge from `AuthenticatedAgentPrincipal` to the User administrator actor
required by the existing commands.

Therefore a User-admin command could technically demonstrate command reuse and mutation evidence, but it could
not validate the intended Agent permission-enforcement boundary. A faithful paired Agent proof must wait for an
eligible Agent-actor command; that future command is not created or required by this record.

## Resolution

Fight Common does not require a Fight Access Control proof. Common's own behavior-focused test suite uses
package-owned fixtures for representative query-backed and mutation-backed tools. Those fixtures prove the
shared contracts and protocol behavior without inventing an Access Control use case, coupling the packages, or
turning consumer adoption into a Common development or release gate.

Fight Access Control may later expose and validate a real Agent mutation when a suitable business use case
exists. That work remains owned by Access Control and its consuming application. The existing `GetAgentById`
evidence remains useful orientation, but it is not a required Common fixture or acceptance dependency. No
User-admin command is substituted merely to manufacture an Agent proof.

**Decision owner:** John

**Exit condition:** Met by the fixture-owned Common verification boundary and explicit deferral of any real Agent
mutation to a suitable future Access Control use case. Consumer implementation and release remain optional
adoption evidence, not planning, development, or release gates for either package.
