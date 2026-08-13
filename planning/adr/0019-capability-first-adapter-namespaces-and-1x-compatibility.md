# ADR 0019: Capability-First Adapter Namespaces and 1.x Compatibility

- Status: accepted
- Date: 2026-08-12

## Decision

Fight Common uses `Adapter\<Capability>\<Framework-or-Provider>\<Type>` as the canonical namespace structure
for every new adapter and every corrected public path. A type belongs to the capability it serves. Generic
top-level implementation buckets such as `DependencyInjection`, `HttpKernel`, and `EventSubscriber`, and
provider-first buckets such as top-level `Doctrine`, are not canonical destinations.

Fight Common `1.2.0` introduces corrected paths additively. Every superseded public FQCN remains functional
and is deprecated in PHPDoc and documentation throughout `1.x`. Runtime deprecation warnings are not emitted.
The old paths are removed only in `2.0.0`.

The compatibility mechanism is selected per declaration and its promised consumer operations.
`class_alias()` may be used for a pure relocation only when consumer probes prove that construction, static
factories, `instanceof`, serialization, and framework registration remain compatible. An explicit forwarding
class, compatibility implementation, or shared internal base is required when alias reflection identity,
attributes, service IDs, extension behavior, or framework registration would change a supported operation.

The old `Adapter\HttpFoundation\JSendResponse` remains an explicit compatibility implementation because its
raw-array inputs and caller-selected encoding options intentionally differ from the new typed response.
Compiler passes, subscribers, Doctrine types, and other framework extension points are treated as
identity-sensitive until designated probes prove an alias sufficient. Both old and new FQCNs are tested
independently.

The `1.2.0` migration worksheet covers 32 declarations: 19 Symfony-semantic adapters and the 13 data types
currently under top-level `Adapter\Doctrine`. The Symfony scope includes two documented Messenger handlers
that have no direct Symfony import. Existing framework-qualified Mail and Process adapters remain in place,
and Mercure remains provider-qualified under Socket.

## Consequences

Framework and provider ownership becomes visible without discarding the published `1.x` surface. Composer
autoloading, framework configuration, service IDs, tags, Doctrine type registration, reflection, static
returns, and native response behavior must be included in compatibility evidence where relevant.

Adding a corrected FQCN alone does not complete a migration. Documentation identifies the canonical path,
the deprecated path, the planned `2.0.0` removal, and any consumer configuration update. The compatibility
manifest classifies both declarations and their supported operations.

## Rejected Alternatives

Renaming declarations in place was rejected because removing a public FQCN is a major-version change.
Keeping inconsistent namespace families indefinitely was rejected because capability and implementation
ownership remain obscured and five-framework composition would continue inventing destinations.

Using `class_alias()` universally was rejected because name resolution alone does not preserve every
reflection, framework-extension, service-registration, or behavior contract. Requiring handwritten wrappers
universally was rejected because a proved pure alias avoids unnecessary duplicate code and maintenance.
