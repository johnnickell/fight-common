# Replace project context with a concise glossary

**Labels:** `wayfinder:research`, `wayfinder:grilling`
**Mode:** HITL
**Status:** Closed
**Map:** [Fight Common v2.0.0 readiness](../fight-common-2-0-readiness-map.md)
**Depends on:** —

## Question

Which terms need short, canonical definitions in `GLOSSARY.md`, and which material in [CONTEXT.md](../../../CONTEXT.md) needs a different home?

## Must decide

- Use Fight Access Control's `GLOSSARY.md` as the concrete reference: grouped terms, brief definitions and links to authoritative explanations when needed. Verify current Matt Pocock skill expectations before changing agent guidance.
- Separate current vocabulary from architecture rules, component/API explanations, and historical delivery evidence. Preserve unique material in its owning document; link rather than duplicate it.
- Inventory current links and tooling references, and choose whether the cutover removes `CONTEXT.md` or retains a short pointer for a verified need. Preserve historical records without breaking useful navigation.

## Evidence and recommendation

- Matt Pocock’s current [domain-modeling skill](https://github.com/mattpocock/skills/blob/abeeaf95660ba9e8b7c753eb38f441ca707a8823/skills/engineering/domain-modeling/SKILL.md) and [glossary format](https://github.com/mattpocock/skills/blob/abeeaf95660ba9e8b7c753eb38f441ca707a8823/skills/engineering/domain-modeling/GLOSSARY-FORMAT.md) recommend one root glossary by default, one- or two-sentence project-specific definitions, and preferred wording where terms are confused. They exclude implementation details and decisions. Fight Access Control’s current root glossary uses short grouped definitions and selective links.
- Recommend one root `GLOSSARY.md`, grouped by capability rather than by source layer. Start with distinctions Common owns: message envelope vs payload; File Storage vs Filesystem vs File Transfer; execution observation vs durable Task vs live subscription vs retained Notice; ordinary aggregate root vs the Event Sourcing reference base. Include only terms whose Common meaning needs explaining; avoid generic PHP, CQRS, and workflow definitions or a catalog of classes.
- Keep architecture rules in the engineering standards, contracts and state transitions in component guides, tooling in local tooling or release docs, and decisions and delivery history in ADRs, planning records and Git. Move only material that is not already covered. Do not create a second long context document.
- Removal needs a real cutover: update AGENTS.md, the engineering standards, project profile and workflow binding. The [compatibility manifest](../../../compatibility/manifest.json) still names `CONTEXT.md` → `Messaging and CQRS` as the normative source for complete event fan-out and message metadata isolation. Move those promises to the messaging component docs and update both manifest entries without changing their fixtures or semantics. Preserve historical task links by pinning the source revision where appropriate, not rewriting what those records said.

## Resolution

The maintainer chose to delete `CONTEXT.md`, not leave a pointer. The cutover will introduce one short root `GLOSSARY.md`, move unique material into its owning docs, migrate the two messaging contract references, and verify live and historical links before removing the old file. Carry this decision into the map’s implementation planning handoff; this record does not delete the file.

## Resolution boundary

Choose the glossary contents and relocation/cutover rules. Do not rewrite the source documents during mapping.
