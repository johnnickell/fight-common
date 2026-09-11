---
template: atlas-home.html
title: Fight Common
hide:
  - toc
---

<section class="atlas-home__hero" aria-labelledby="atlas-home-title">
  <div class="atlas-home__promise">
    <p class="atlas-context-label">Framework-neutral PHP building blocks</p>
    <h1 id="atlas-home-title">Keep your core clean. Connect everything else.</h1>
    <p class="atlas-home__lead">Adopt focused PHP components without coupling Domain or Application code to a framework.</p>

    <div class="atlas-install" aria-label="Composer installation command" markdown="1">
```bash
composer require johnnickell/fight-common
```
    </div>
  </div>

  <a class="atlas-home__architecture" href="architecture/">
    <strong>Adapter → Application → Domain</strong>
    <span>Inward dependencies enforced by Deptrac</span>
    <span class="atlas-home__layer-flow" aria-label="Dependencies flow from Adapter through Application to Domain">
      <b>Adapter</b><i aria-hidden="true">→</i><b>Application</b><i aria-hidden="true">→</i><b>Domain</b>
    </span>
  </a>
</section>

<nav class="atlas-entry-routes" aria-label="Documentation starting points">
  <a class="atlas-route-card" href="architecture/">
    <strong>Understand the architecture</strong>
    <span>Hexagonal Architecture, CQRS, and the dependency rules behind every component.</span>
  </a>
  <a class="atlas-route-card" href="quick-start/">
    <strong>Start building</strong>
    <span>Install Fight Common and wire one framework-neutral path end to end.</span>
  </a>
  <a class="atlas-route-card" href="#component-atlas">
    <strong>Explore components</strong>
    <span>Find the focused building block that matches the problem in front of you.</span>
  </a>
</nav>

<section class="atlas-catalog" aria-labelledby="component-atlas">
  <header class="atlas-catalog__heading">
    <div>
      <p class="atlas-context-label">Complete component atlas</p>
      <h2 id="component-atlas">Start with the problem you need to solve.</h2>
    </div>
    <p>Every component stays visible. Ownership rails show where it belongs before you open the guide.</p>
  </header>

  <div class="atlas-grid">
    <section class="atlas-card">
      <header><span>Domain</span><h3>Model the Domain</h3></header>
      <div class="atlas-card__links">
        <a href="components/values/"><span>Values</span><small class="atlas-ownership-rail">Domain · Adapter</small></a>
        <a href="components/collections/"><span>Collections</span><small class="atlas-ownership-rail">Domain</small></a>
        <a href="components/specifications/"><span>Specifications</span><small class="atlas-ownership-rail">Domain</small></a>
        <a href="components/repositories/"><span>Repositories</span><small class="atlas-ownership-rail">Domain · Application · Adapter</small></a>
        <a href="components/event-sourcing/"><span>Event Sourcing</span><small class="atlas-ownership-rail">Domain · Application · Adapter</small></a>
        <a href="components/utilities/"><span>Utilities</span><small class="atlas-ownership-rail">Domain</small></a>
      </div>
    </section>

    <section class="atlas-card atlas-card--active">
      <header><span>Domain · Application · Adapter</span><h3>Coordinate Application Behavior</h3></header>
      <div class="atlas-card__links">
        <a href="components/messaging/"><span>Messaging (CQRS)</span><small class="atlas-ownership-rail">Domain · Application · Adapter</small></a>
        <a href="components/validation/"><span>Validation</span><small class="atlas-ownership-rail">Application · Adapter</small></a>
        <a href="components/serialization/"><span>Serialization</span><small class="atlas-ownership-rail">Domain · Application</small></a>
        <a href="components/dependency-injection/"><span>Dependency Injection</span><small class="atlas-ownership-rail">Application</small></a>
      </div>
    </section>

    <section class="atlas-card atlas-card--active">
      <header><span>Application · Adapter</span><h3>Connect Systems</h3></header>
      <div class="atlas-card__links">
        <a href="components/http-client/"><span>HTTP Client</span><small class="atlas-ownership-rail">Application · Adapter</small></a>
        <a href="components/auth/"><span>Auth</span><small class="atlas-ownership-rail">Domain · Application · Adapter</small></a>
        <a href="components/cache/"><span>Cache</span><small class="atlas-ownership-rail">Application · Adapter</small></a>
        <a href="components/files/"><span>Files</span><small class="atlas-ownership-rail">Application · Adapter</small></a>
        <a href="components/file-transfer/"><span>File Transfer</span><small class="atlas-ownership-rail">Application · Adapter</small></a>
        <a href="components/templating/"><span>Templating</span><small class="atlas-ownership-rail">Application · Adapter</small></a>
        <a href="components/routing/"><span>Routing</span><small class="atlas-ownership-rail">Application · Adapter</small></a>
        <a href="components/mail/"><span>Mail</span><small class="atlas-ownership-rail">Application · Adapter</small></a>
        <a href="components/sms/"><span>SMS</span><small class="atlas-ownership-rail">Application · Adapter</small></a>
        <a href="components/sockets/"><span>Sockets</span><small class="atlas-ownership-rail">Application · Adapter</small></a>
      </div>
    </section>

    <section class="atlas-card atlas-card--active">
      <header><span>Application · Adapter</span><h3>Operate Workloads</h3></header>
      <div class="atlas-card__links">
        <a href="components/observability/"><span>Observability</span><small class="atlas-ownership-rail">Domain · Application · Adapter</small></a>
        <a href="components/process/"><span>Process</span><small class="atlas-ownership-rail">Application · Adapter</small></a>
        <a href="components/scheduler/"><span>Scheduler</span><small class="atlas-ownership-rail">Application · Adapter</small></a>
      </div>
    </section>

    <section class="atlas-card">
      <header><span>Adapter</span><h3>Integrate Frameworks</h3></header>
      <div class="atlas-card__links">
        <a href="frameworks/framework-support/"><span>Framework Support</span><small class="atlas-ownership-rail">Adapter</small></a>
        <a href="frameworks/framework-free/"><span>Framework-free</span><small class="atlas-ownership-rail">Application</small></a>
        <a href="frameworks/symfony/"><span>Symfony</span><small class="atlas-ownership-rail">Adapter</small></a>
        <a href="frameworks/laravel/"><span>Laravel</span><small class="atlas-ownership-rail">Adapter</small></a>
        <a href="frameworks/yii/"><span>Yii</span><small class="atlas-ownership-rail">Adapter</small></a>
        <a href="frameworks/codeigniter/"><span>CodeIgniter</span><small class="atlas-ownership-rail">Adapter</small></a>
        <a href="frameworks/slim/"><span>Slim</span><small class="atlas-ownership-rail">Adapter</small></a>
      </div>
    </section>
  </div>
</section>
