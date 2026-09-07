---
atlas_article: true
title: Mail
atlas_article_heading_id: mail
atlas_component_group: Connect Systems
atlas_component_owner: Application and Adapter
atlas_component_dependencies: MailTransport, MailFactory, Symfony Mailer
atlas_relationship_source_label: Adapter
atlas_relationship_source: Symfony Mailer
atlas_relationship_target_label: Application port
atlas_relationship_target: MailTransport
atlas_relationship_description: Symfony Mailer adapter depends on the Mail application port
atlas_relationship_caption: Symfony Mailer fulfills the application mail transport port without moving transport concerns into application code.
atlas_consequential_label: Consequential behavior
atlas_consequential_message: Recipient overrides replace every original To, Cc, and Bcc recipient.
atlas_next_steps:
  - label: Configure Symfony Mailer
    href: "#symfony-configuration"
  - label: Review usage examples
    href: "#usage-examples"
atlas_local_contents:
  - label: Configuration formats
    href: "#configuration-formats"
  - label: MailMessage
    href: "#mailmessage"
  - label: MailService (Facade)
    href: "#mailservice-facade"
  - label: MailTransport
    href: "#mailtransport"
  - label: MailFactory
    href: "#mailfactory"
  - label: Attachment
    href: "#attachment"
  - label: Priority
    href: "#priority"
  - label: Symfony Configuration
    href: "#symfony-configuration"
  - label: Usage Examples
    href: "#usage-examples"
---

## Configuration formats

The Symfony service container supports these equivalent definitions. Choose the format already
used by the application; all three wire the same factory, transport, facade, and port aliases.

<section class="atlas-format-tabs" data-atlas-format-tabs aria-label="Symfony Mailer configuration formats">
  <div class="atlas-format-tabs__tabs" role="tablist" aria-label="Configuration format">
    <button type="button" role="tab" id="atlas-mail-config-yaml-tab" aria-controls="atlas-mail-config-yaml-panel" aria-selected="true" tabindex="0" data-atlas-format="yaml">YAML</button>
    <button type="button" role="tab" id="atlas-mail-config-xml-tab" aria-controls="atlas-mail-config-xml-panel" aria-selected="false" tabindex="-1" data-atlas-format="xml">XML</button>
    <button type="button" role="tab" id="atlas-mail-config-php-tab" aria-controls="atlas-mail-config-php-panel" aria-selected="false" tabindex="-1" data-atlas-format="php">PHP</button>
  </div>
  <span data-atlas-copy-status role="status" aria-live="polite" aria-atomic="true"></span>

  <section class="atlas-format-tabs__panel" role="tabpanel" id="atlas-mail-config-yaml-panel" aria-labelledby="atlas-mail-config-yaml-tab" data-atlas-format-panel="yaml" markdown="1">
    <div class="atlas-format-tabs__panel-head">
      <span>YAML</span>
      <code data-atlas-filename="config/services.yaml">config/services.yaml</code>
      <button class="atlas-format-tabs__copy" type="button" data-atlas-copy aria-label="Copy YAML configuration">Copy</button>
    </div>

    ```yaml
    services:
        Fight\Common\Adapter\Mail\Symfony\SymfonyMailFactory: ~

        Fight\Common\Adapter\Mail\Symfony\SymfonyMailTransport:
            arguments:
                $mailer: '@mailer.mailer'
                $overrides: []

        Fight\Common\Application\Mail\MailService:
            arguments:
                $transport: '@Fight\Common\Adapter\Mail\Symfony\SymfonyMailTransport'
                $factory: '@Fight\Common\Adapter\Mail\Symfony\SymfonyMailFactory'

        Fight\Common\Application\Mail\Transport\MailTransport:
            alias: Fight\Common\Adapter\Mail\Symfony\SymfonyMailTransport

        Fight\Common\Application\Mail\Message\MailFactory:
            alias: Fight\Common\Adapter\Mail\Symfony\SymfonyMailFactory
    ```
  </section>

  <section class="atlas-format-tabs__panel" role="tabpanel" id="atlas-mail-config-xml-panel" aria-labelledby="atlas-mail-config-xml-tab" data-atlas-format-panel="xml" hidden markdown="1">
    <div class="atlas-format-tabs__panel-head">
      <span>XML</span>
      <code data-atlas-filename="config/services.xml">config/services.xml</code>
      <button class="atlas-format-tabs__copy" type="button" data-atlas-copy aria-label="Copy XML configuration">Copy</button>
    </div>

    ```xml
    <container xmlns="http://symfony.com/schema/dic/services"
        xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
        xsi:schemaLocation="http://symfony.com/schema/dic/services https://symfony.com/schema/dic/services/services-1.0.xsd">
        <services>
            <service id="Fight\Common\Adapter\Mail\Symfony\SymfonyMailFactory" />
            <service id="Fight\Common\Adapter\Mail\Symfony\SymfonyMailTransport">
                <argument type="service" id="mailer.mailer" />
                <argument type="collection" />
            </service>
            <service id="Fight\Common\Application\Mail\MailService">
                <argument type="service" id="Fight\Common\Adapter\Mail\Symfony\SymfonyMailTransport" />
                <argument type="service" id="Fight\Common\Adapter\Mail\Symfony\SymfonyMailFactory" />
            </service>
            <service id="Fight\Common\Application\Mail\Transport\MailTransport" alias="Fight\Common\Adapter\Mail\Symfony\SymfonyMailTransport" />
            <service id="Fight\Common\Application\Mail\Message\MailFactory" alias="Fight\Common\Adapter\Mail\Symfony\SymfonyMailFactory" />
        </services>
    </container>
    ```
  </section>

  <section class="atlas-format-tabs__panel" role="tabpanel" id="atlas-mail-config-php-panel" aria-labelledby="atlas-mail-config-php-tab" data-atlas-format-panel="php" hidden markdown="1">
    <div class="atlas-format-tabs__panel-head">
      <span>PHP</span>
      <code data-atlas-filename="config/services.php">config/services.php</code>
      <button class="atlas-format-tabs__copy" type="button" data-atlas-copy aria-label="Copy PHP configuration">Copy</button>
    </div>

    ```php
    <?php

    use Fight\Common\Adapter\Mail\Symfony\SymfonyMailFactory;
    use Fight\Common\Adapter\Mail\Symfony\SymfonyMailTransport;
    use Fight\Common\Application\Mail\MailService;
    use Fight\Common\Application\Mail\Message\MailFactory;
    use Fight\Common\Application\Mail\Transport\MailTransport;
    use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

    use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

    return static function (ContainerConfigurator $container): void {
        $services = $container->services();
        $services->set(SymfonyMailFactory::class);
        $services->set(SymfonyMailTransport::class)
            ->arg('$mailer', service('mailer.mailer'))
            ->arg('$overrides', []);
        $services->set(MailService::class)
            ->arg('$transport', service(SymfonyMailTransport::class))
            ->arg('$factory', service(SymfonyMailFactory::class));
        $services->alias(MailTransport::class, SymfonyMailTransport::class);
        $services->alias(MailFactory::class, SymfonyMailFactory::class);
    };
    ```
  </section>
</section>

--8<-- "docs/mail.md"
