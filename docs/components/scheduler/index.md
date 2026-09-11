---
template: atlas-article.html
atlas_article: true
title: Scheduler
atlas_article_heading_id: scheduler
atlas_component_group: Operate Workloads
atlas_component_owner: Application and Adapter
atlas_component_dependencies: Cron Expression, ProcessRunner, optional ext-posix, PSR-3, and MailService
atlas_article_context: Application · Adapter
atlas_article_lead: Register due work in application code, execute commands through ProcessRunner, and make locking, failure notification, output, and time-zone policy explicit.
atlas_article_requires: PHP 8.5+, dragonmantank/cron-expression
atlas_article_optional: ext-posix for maxRuntime, psr/log, MailService, ProcessRunner adapter
atlas_article_package: johnnickell/fight-common
atlas_relationship_source_label: Application service
atlas_relationship_source: Scheduler
atlas_relationship_target_label: Application port
atlas_relationship_target: ProcessRunner
atlas_relationship_description: Scheduler delegates command execution through the portable process runner
atlas_relationship_caption: Scheduling remains application-owned while the composition root selects the process adapter and operational providers.
atlas_consequential_label: Overlap boundary
atlas_consequential_message: Job names determine lock-file identity; keep names stable and give every scheduler instance a shared writable temporary directory.
atlas_next_steps:
  - label: Compose the scheduler
    href: "#portable-processrunner-composition"
  - label: Register jobs
    href: "#registering-jobs"
  - label: Understand locking
    href: "#locking"
  - label: Configure notifications
    href: "#error-handling-and-notification"
  - label: Build process commands
    href: "../process/"
  - label: Configure framework support
    href: "../../frameworks/framework-support/"
atlas_local_contents:
  - label: Portable composition
    href: "#portable-processrunner-composition"
  - label: Compatibility paths
    href: "#legacy-1x-construction-compatibility"
  - label: Registering jobs
    href: "#registering-jobs"
  - label: Schedule formats
    href: "#schedule-formats"
  - label: Locking
    href: "#locking"
  - label: Output modes
    href: "#output-modes"
  - label: Failures and notifications
    href: "#error-handling-and-notification"
  - label: Time zone
    href: "#timezone"
---

Scheduler coordinates recurring command and callable work. New consumers should construct it with
`Scheduler::withProcessRunner()` so command execution stays behind the Application-owned
`ProcessRunner` port and framework selection remains in the composition root.

**Ownership.** `Scheduler` and its exceptions belong to the Application layer; `Timezone` is a
Domain value. Process execution, logging, and mail delivery arrive through Application contracts.
The consuming application owns the entry point, schedule definitions, shared lock storage, worker
invocation cadence, output retention, alert recipients, and recovery procedure.

**Dependencies.** Cron schedules use `dragonmantank/cron-expression`. Command jobs need a selected
`ProcessRunner` adapter; the supplied runner uses Symfony Process. The `maxRuntime` guard requires
`ext-posix`; PSR-3 logging and `MailService` failure notifications are optional.

**Install.**

```bash
composer require johnnickell/fight-common
```

--8<-- "docs/scheduler.md"
