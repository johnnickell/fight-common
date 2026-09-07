# Fight Common

## Framework-neutral PHP building blocks

Adopt focused PHP building blocks without coupling Domain or Application code to a framework.
Fight Common keeps the boundaries visible: use portable contracts where they belong, then choose only the adapters
your project needs.

```bash
composer require johnnickell/fight-common
```

<section class="atlas-architecture-proof" aria-label="Fight Common dependency direction">
  <div class="atlas-architecture-proof__layer atlas-architecture-proof__layer--adapter">
    <span class="atlas-architecture-proof__label">Adapter</span>
    <span>Frameworks, providers, and infrastructure</span>
  </div>
  <span class="atlas-architecture-proof__direction" aria-hidden="true">→</span>
  <div class="atlas-architecture-proof__layer atlas-architecture-proof__layer--application">
    <span class="atlas-architecture-proof__label">Application</span>
    <span>Use-case coordination and ports</span>
  </div>
  <span class="atlas-architecture-proof__direction" aria-hidden="true">→</span>
  <div class="atlas-architecture-proof__layer atlas-architecture-proof__layer--domain">
    <span class="atlas-architecture-proof__label">Domain</span>
    <span>Business rules and durable primitives</span>
  </div>
</section>

<div class="atlas-entry-routes">
  <a class="md-button" href="architecture/">Architecture</a>
  <a class="md-button" href="quick-start/">Quick Start</a>
  <a class="md-button" href="#component-atlas">Explore Components</a>
</div>

## Component Atlas

<div id="component-atlas" class="atlas-component-atlas">

<h3>Model the Domain</h3>

<div class="atlas-component-group">
  <a href="components/values/">Values</a>
  <span class="atlas-ownership-rail">Ownership: Domain · Adapter</span>
  <a href="components/collections/">Collections</a>
  <span class="atlas-ownership-rail">Ownership: Domain</span>
  <a href="components/specifications/">Specifications</a>
  <span class="atlas-ownership-rail">Ownership: Domain</span>
  <a href="components/repositories/">Repositories</a>
  <span class="atlas-ownership-rail">Ownership: Domain · Application · Adapter</span>
  <a href="components/event-sourcing/">Event Sourcing</a>
  <span class="atlas-ownership-rail">Ownership: Domain · Application · Adapter</span>
  <a href="components/utilities/">Utilities</a>
  <span class="atlas-ownership-rail">Ownership: Domain</span>
</div>

<h3>Coordinate Application Behavior</h3>

<div class="atlas-component-group">
  <a href="components/messaging/">Messaging (CQRS)</a>
  <span class="atlas-ownership-rail">Ownership: Domain · Application · Adapter</span>
  <a href="components/validation/">Validation</a>
  <span class="atlas-ownership-rail">Ownership: Application · Adapter</span>
  <a href="components/serialization/">Serialization</a>
  <span class="atlas-ownership-rail">Ownership: Domain · Application</span>
  <a href="components/dependency-injection/">Dependency Injection</a>
  <span class="atlas-ownership-rail">Ownership: Application</span>
</div>

<h3>Connect Systems</h3>

<div class="atlas-component-group">
  <a href="components/http-client/">HTTP Client</a>
  <span class="atlas-ownership-rail">Ownership: Application · Adapter</span>
  <a href="components/auth/">Auth</a>
  <span class="atlas-ownership-rail">Ownership: Domain · Application · Adapter</span>
  <a href="components/cache/">Cache</a>
  <span class="atlas-ownership-rail">Ownership: Application · Adapter</span>
  <a href="components/files/">Files</a>
  <span class="atlas-ownership-rail">Ownership: Application · Adapter</span>
  <a href="components/file-transfer/">File Transfer</a>
  <span class="atlas-ownership-rail">Ownership: Application · Adapter</span>
  <a href="components/templating/">Templating</a>
  <span class="atlas-ownership-rail">Ownership: Application · Adapter</span>
  <a href="components/routing/">Routing</a>
  <span class="atlas-ownership-rail">Ownership: Application · Adapter</span>
  <a href="components/mail/">Mail</a>
  <span class="atlas-ownership-rail">Ownership: Application · Adapter</span>
  <a href="components/sms/">SMS</a>
  <span class="atlas-ownership-rail">Ownership: Application · Adapter</span>
  <a href="components/sockets/">Sockets</a>
  <span class="atlas-ownership-rail">Ownership: Application · Adapter</span>
</div>

<h3>Operate Workloads</h3>

<div class="atlas-component-group">
  <a href="components/observability/">Observability</a>
  <span class="atlas-ownership-rail">Ownership: Domain · Application · Adapter</span>
  <a href="components/process/">Process</a>
  <span class="atlas-ownership-rail">Ownership: Application · Adapter</span>
  <a href="components/scheduler/">Scheduler</a>
  <span class="atlas-ownership-rail">Ownership: Application · Adapter</span>
</div>

<h3>Integrate Frameworks</h3>

<div class="atlas-component-group">
  <a href="frameworks/framework-support/">Framework Support</a>
  <span class="atlas-ownership-rail">Ownership: Adapter</span>
  <a href="frameworks/codeigniter/">CodeIgniter</a>
  <span class="atlas-ownership-rail">Ownership: Adapter</span>
</div>

</div>
