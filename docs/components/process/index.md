---
template: atlas-article.html
atlas_article: true
title: Process
atlas_article_heading_id: process
atlas_component_group: Operate Workloads
atlas_component_owner: Application and Adapter
atlas_component_dependencies: Optional Symfony Process and PSR-3 logger
atlas_article_context: Application · Adapter
atlas_article_lead: Describe commands through a portable application model, then run them with explicit concurrency, retry, failure, and output policy.
atlas_article_requires: PHP 8.5+
atlas_article_optional: symfony/process, psr/log
atlas_article_package: johnnickell/fight-common
atlas_relationship_source_label: Adapter
atlas_relationship_source: SymfonyProcessRunner
atlas_relationship_target_label: Application port
atlas_relationship_target: ProcessRunner
atlas_relationship_description: The Symfony adapter executes portable Process descriptors
atlas_relationship_caption: Application code describes commands and policy; the adapter owns operating-system process execution.
atlas_consequential_label: Command boundary
atlas_consequential_message: shellCommand preserves shell operators and quoting without escaping; use tokenized builder methods for independently supplied values.
atlas_next_steps:
  - label: Build a safe command
    href: "#processbuilder"
  - label: Choose failure behavior
    href: "#processerrorbehavior"
  - label: Configure concurrent execution
    href: "#symfonyprocessrunner"
  - label: Schedule recurring work
    href: "../scheduler/"
  - label: Trace execution
    href: "../observability/"
  - label: Configure framework support
    href: "../../frameworks/framework-support/"
atlas_local_contents:
  - label: Process descriptor
    href: "#process-descriptor"
  - label: Safe construction
    href: "#processbuilder"
  - label: Runner port
    href: "#processrunner"
  - label: Failure behavior
    href: "#processerrorbehavior"
  - label: Symfony runner
    href: "#symfonyprocessrunner"
  - label: Configuration
    href: "#symfony-configuration"
  - label: Usage examples
    href: "#usage-examples"
---

Process separates the description of a command from the mechanism that launches it. Application
code creates immutable `Process` descriptors and depends on `ProcessRunner`; the composition root
selects an adapter and decides concurrency, retry, logging, and output policy.

**Ownership.** `Process`, `ProcessBuilder`, `ProcessRunner`, and `ProcessErrorBehavior` belong to the
Application layer. `SymfonyProcessRunner` is an Adapter implementation backed by Symfony Process.
The consuming application owns command allowlisting, worker capacity, retry suitability, timeout
budgets, and the destination and retention of process output.

**Dependencies.** Descriptor construction and the runner port require PHP 8.5+ and this package.
Install `symfony/process` for the supplied runner and provide a PSR-3 logger only when execution
events should be logged.

**Install.**

```bash
composer require johnnickell/fight-common
composer require symfony/process
```

--8<-- "docs/process.md"
