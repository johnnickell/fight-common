# Hexagonal Architecture and CQRS

Fight Common keeps business language at the center and makes frameworks replaceable at the edge. Source-code
dependencies point inward: Domain code knows no framework; Application code coordinates the Domain through ports;
Adapter code connects those ports to databases, transports, and frameworks.

## The dependency rule

<figure class="atlas-architecture-map" aria-labelledby="architecture-map-caption">
  <div class="atlas-architecture-map__flow">
    <section class="atlas-architecture-map__layer atlas-architecture-map__layer--adapter">
      <span class="atlas-architecture-map__path">src/Adapter</span>
      <strong>Adapter</strong>
      <p>Framework composition, persistence, queues, HTTP clients, files, and provider integrations.</p>
      <small>Replaceable edge</small>
    </section>
    <span class="atlas-architecture-map__edge" aria-hidden="true"><b>depends on</b><i>→</i></span>
    <section class="atlas-architecture-map__layer atlas-architecture-map__layer--application">
      <span class="atlas-architecture-map__path">src/Application</span>
      <strong>Application</strong>
      <p>Use-case coordination, ports, buses, handlers, filters, subscribers, and transaction boundaries.</p>
      <small>Stable boundary</small>
    </section>
    <span class="atlas-architecture-map__edge" aria-hidden="true"><b>depends on</b><i>→</i></span>
    <section class="atlas-architecture-map__layer atlas-architecture-map__layer--domain">
      <span class="atlas-architecture-map__path">src/Domain</span>
      <strong>Domain</strong>
      <p>Business values, rules, specifications, messages, collections, and repository vocabulary.</p>
      <small>Protected center</small>
    </section>
  </div>
  <figcaption id="architecture-map-caption"><span class="atlas-diagram-key atlas-diagram-key--solid">Solid arrow</span> means “source code may depend on.” No arrow points back toward a framework.</figcaption>
</figure>

This direction is about ownership, not request flow. A web request may enter through Symfony, Laravel, Yii,
CodeIgniter, Slim, or framework-free composition. The Adapter translates it into an Application operation; the
Application coordinates Domain behavior; results travel back out without making the Domain depend on the caller.

<code>src/Standards</code> is deliberately outside this chain. It publishes development-time coding policy and has
no runtime dependents. Domain, Application, and Adapter code cannot use it, and it cannot use runtime code.

## Ports and adapters

A **port** is a contract owned by the code that needs the capability. An **adapter** fulfills that contract using
a particular technology. Framework bootstrapping chooses the adapter; portable Domain and Application code does
not.

<div class="atlas-ownership-ledger">
  <section>
    <strong>Application owns the need</strong>
    <code>MailTransport</code>
    <p><code>MailService</code> sends an application message through this port without knowing which mailer exists.</p>
  </section>
  <span class="atlas-ownership-ledger__edge" aria-hidden="true">implemented by →</span>
  <section>
    <strong>Adapter owns the technology</strong>
    <code>SymfonyMailTransport</code>
    <p>The adapter translates the portable message into Symfony Mailer calls and infrastructure failures.</p>
  </section>
</div>

Repositories use the same separation. Domain-owned <code>Pagination</code> and <code>ResultSet</code> express
business-facing query vocabulary. Application-owned <code>TransactionalUnitOfWork</code> marks the atomic use-case
boundary. The <code>DoctrineTransactionalUnitOfWork</code> adapter supplies the ORM-specific implementation. Your
application owns its aggregate repository port because only your application can define what loading and saving
that aggregate means.

Framework packages belong at the composition edge. Their service providers, compiler passes, and configuration
bind Fight Common contracts to selected adapters. They may call inward; they do not move framework types into
Domain or portable Application code.

## CQRS message flows

CQRS separates requests to change state from requests to read it. Events then announce facts that have already
happened. Fight Common keeps the message payloads in Domain, the coordination contracts in Application, and the
routing or transport mechanisms in Adapter.

<figure class="atlas-cqrs" aria-labelledby="cqrs-caption">
  <section class="atlas-cqrs__lane atlas-cqrs__lane--command">
    <header><strong>Command</strong><span>requests one mutation</span></header>
    <div class="atlas-cqrs__flow">
      <div class="atlas-cqrs__node"><b>Domain message</b><code>Command</code><span class="atlas-cqrs__edge" aria-hidden="true">execute <i>→</i></span></div>
      <div class="atlas-cqrs__node"><b>Application contract</b><code>CommandBus::execute()</code><small>filters wrap dispatch</small><span class="atlas-cqrs__edge" aria-hidden="true">route <i>→</i></span></div>
      <div class="atlas-cqrs__node"><b>One handler</b><code>CommandHandler::handle()</code><small>coordinates mutation</small></div>
    </div>
  </section>
  <section class="atlas-cqrs__lane atlas-cqrs__lane--query">
    <header><strong>Query</strong><span>asks for a result</span></header>
    <div class="atlas-cqrs__flow">
      <div class="atlas-cqrs__node"><b>Domain message</b><code>Query</code><span class="atlas-cqrs__edge" aria-hidden="true">fetch <i>→</i></span></div>
      <div class="atlas-cqrs__node"><b>Application contract</b><code>QueryBus::fetch()</code><small>filters wrap dispatch</small><span class="atlas-cqrs__edge atlas-cqrs__edge--return" aria-hidden="true">route <i>→</i><b>result <i>←</i></b></span></div>
      <div class="atlas-cqrs__node"><b>One handler</b><code>QueryHandler::handle()</code><small>returns read data</small></div>
    </div>
  </section>
  <section class="atlas-cqrs__lane atlas-cqrs__lane--event">
    <header><strong>Event</strong><span>announces a fact to zero or more listeners</span></header>
    <div class="atlas-cqrs__flow">
      <div class="atlas-cqrs__node"><b>Domain message</b><code>Event</code><span class="atlas-cqrs__edge" aria-hidden="true">trigger <i>→</i></span></div>
      <div class="atlas-cqrs__node"><b>Application contract</b><code>EventDispatcher::<wbr>trigger()</code><small>registration maps event types</small><span class="atlas-cqrs__edge atlas-cqrs__edge--fanout" aria-hidden="true">fan out <i>⇉</i></span></div>
      <div class="atlas-cqrs__node"><b>Subscribers</b><code>EventSubscriber</code><small>independent reactions</small></div>
    </div>
  </section>
  <figcaption id="cqrs-caption"><span class="atlas-diagram-key atlas-diagram-key--solid">Solid arrow</span> follows dispatch. <span class="atlas-diagram-key atlas-diagram-key--dashed">Dashed arrow</span> marks the query result returning to its caller. The doubled event arrow marks fan-out.</figcaption>
</figure>

### What each layer owns

- **Domain** owns <code>Command</code>, <code>Query</code>, and <code>Event</code> payload contracts plus
  <code>CommandMessage</code>, <code>QueryMessage</code>, and <code>EventMessage</code> envelopes. Messages carry
  identity, metadata, timestamp, and the typed payload without knowing how they will be delivered.
- **Application** owns <code>CommandBus</code>, <code>QueryBus</code>, <code>EventDispatcher</code>, handlers,
  filters, and subscribers. Handlers coordinate a use case; filters wrap command or query dispatch for concerns
  such as validation or metrics; subscribers declare which event facts they react to.
- **Adapter** owns routing, pipelines, service-container lookup, and transport. Routing command and query buses
  match one handler. Simple and service-aware event dispatchers invoke registered listeners. Messenger, Laravel,
  and CodeIgniter adapters can move supported commands and events through asynchronous infrastructure.

Queries are synchronous because <code>fetch()</code> returns a result. Commands and events can use synchronous or
asynchronous adapters, selected at composition time. Asynchronous delivery changes transport and failure timing;
it does not change which layer owns the message or its handler contract.

The [Quick Start](../quick-start/index.md) shows these parts in one order-processing path: a command handler uses
repository and payment ports, commits through <code>TransactionalUnitOfWork</code>, and triggers an event that a
subscriber turns into a follow-up command.

## Repository enforcement

The architecture exists in the public contracts and dependency direction first. Deptrac is repository evidence
that the implementation still matches that model.

~~~bash
./bin/deptrac
~~~

The runtime configuration classifies every production class, rejects outward dependencies, and gives each layer
an explicit external-package allowance. Domain remains framework-free. Application may use PHP internals, neutral
PSR contracts, and the allowlisted scheduler expression contract. Adapter integrations and their third-party
packages are named explicitly. A separate unassigned-token check fails if production or Standards code escapes
classification. There is no baseline of accepted violations.

Deptrac is a Fight Common development dependency, not a runtime requirement for consumers. A consuming project
can install it independently and define boundaries for its own namespaces; copying Fight Common’s package-specific
configuration would not describe that project’s architecture.

## Next steps

<nav class="atlas-next-steps" aria-label="Architecture next steps">
  <span>Continue from the model</span>
  <a href="../quick-start/">Build the complete framework-neutral Quick Start</a>
  <a href="../components/messaging/">Explore commands, queries, events, and adapters</a>
  <a href="../components/mail/">See an Application port fulfilled at the Adapter edge</a>
  <a href="../components/repositories/">Place repository vocabulary and transactions</a>
  <a href="../frameworks/framework-support/">Choose framework composition without moving the boundary</a>
</nav>
