---
template: atlas-home.html
title: Fight Common
hide:
  - toc
---

<section class="atlas-home__hero" aria-labelledby="atlas-home-title">
  <div class="atlas-home__promise">
    <h1 id="atlas-home-title">Build PHP apps with Fight Common</h1>
    <p class="atlas-home__lead">Check input, handle commands and events, or send email. Use just the parts you need, with or without a framework.</p>
    <p>Install with Composer, PHP’s package manager. You need PHP 8.5 or later.</p>

    <div class="atlas-install" aria-label="Composer installation command" markdown="1">
```bash
composer require johnnickell/fight-common
```
    </div>
  </div>

  <a class="atlas-home__architecture" href="architecture/">
    <strong>Architecture</strong>
    <span>See how to keep business rules separate from frameworks and services.</span>
    <span class="atlas-home__layer-flow" aria-label="Dependencies flow from Adapter through Application to Domain">
      <b>Adapter</b><i aria-hidden="true">→</i><b>Application</b><i aria-hidden="true">→</i><b>Domain</b>
    </span>
  </a>
</section>

<nav class="atlas-entry-routes" aria-label="Documentation starting points">
  <a class="atlas-route-card" href="quick-start/">
    <strong>Quick Start</strong>
    <span>Run an order example without a framework.</span>
  </a>
  <a class="atlas-route-card" href="#component-atlas">
    <strong>Components</strong>
    <span>Find a guide for the part you need.</span>
  </a>
  <a class="atlas-route-card" href="frameworks/framework-support/">
    <strong>Frameworks</strong>
    <span>Check support and find setup guides.</span>
  </a>
</nav>

<section class="atlas-catalog" aria-labelledby="component-atlas">
  <header class="atlas-catalog__heading">
    <div>
      <h2 id="component-atlas">Browse components</h2>
    </div>
    <p>Pick a guide by topic. The labels show where each part fits in the library.</p>
  </header>

  <div class="atlas-grid">
    <section class="atlas-card">
      <header><span>Domain</span><h3>Data and business rules</h3></header>
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
      <header><span>Domain · Application · Adapter</span><h3>Messages and app logic</h3></header>
      <div class="atlas-card__links">
        <a href="components/messaging/"><span>Messaging (CQRS)</span><small class="atlas-ownership-rail">Domain · Application · Adapter</small></a>
        <a href="components/validation/"><span>Validation</span><small class="atlas-ownership-rail">Application · Adapter</small></a>
        <a href="components/serialization/"><span>Serialization</span><small class="atlas-ownership-rail">Domain · Application</small></a>
        <a href="components/dependency-injection/"><span>Dependency Injection</span><small class="atlas-ownership-rail">Application</small></a>
      </div>
    </section>

    <section class="atlas-card atlas-card--active">
      <header><span>Application · Adapter</span><h3>Connect to services</h3></header>
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
      <header><span>Application · Adapter</span><h3>Monitor and run jobs</h3></header>
      <div class="atlas-card__links">
        <a href="components/observability/"><span>Observability</span><small class="atlas-ownership-rail">Domain · Application · Adapter</small></a>
        <a href="components/process/"><span>Process</span><small class="atlas-ownership-rail">Application · Adapter</small></a>
        <a href="components/scheduler/"><span>Scheduler</span><small class="atlas-ownership-rail">Application · Adapter</small></a>
      </div>
    </section>

    <section class="atlas-card">
      <header><span>Adapter</span><h3>Set up a framework</h3></header>
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
