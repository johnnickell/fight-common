<?php

declare(strict_types=1);

namespace Fight\Test\Common\Documentation;

use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversNothing;

#[CoversNothing]
final class DocumentationThemeOverrideTest extends UnitTestCase
{
    public function test_that_the_not_found_override_is_owned_by_documentation(): void
    {
        $root = dirname(__DIR__, 2);
        $configuration = file_get_contents($root.'/mkdocs.yml');
        $override = file_get_contents($root.'/docs/overrides/404.html');
        $contributing = file_get_contents($root.'/docs/contributing.md');

        self::assertIsString($configuration);
        self::assertIsString($override);
        self::assertIsString($contributing);
        self::assertStringContainsString('  custom_dir: docs/overrides', $configuration);
        self::assertStringContainsString("exclude_docs: |\n  /overrides/", $configuration);
        self::assertDirectoryDoesNotExist($root.'/overrides');
        self::assertFileDoesNotExist($root.'/overrides/404.html');
        self::assertStringContainsString('{% extends "main.html" %}', $override);
        self::assertStringContainsString('<h1>Page not found</h1>', $override);
        self::assertStringContainsString('Return to the documentation home', $override);
        self::assertStringContainsString(
            'https://github.com/johnnickell/fight-common/blob/develop/release/README.md',
            $contributing,
        );
    }

    public function test_that_atlas_foundation_declares_the_approved_identity_typography_themes_and_footer(): void
    {
        $root = dirname(__DIR__, 2);
        $configuration = file_get_contents($root.'/mkdocs.yml');
        $stylesheet = file_get_contents($root.'/docs/stylesheets/extra.css');
        $notFound = file_get_contents($root.'/docs/overrides/404.html');
        $footer = file_get_contents($root.'/docs/overrides/partials/footer.html');
        $header = file_get_contents($root.'/docs/overrides/partials/logo.html');

        self::assertIsString($configuration);
        self::assertIsString($stylesheet);
        self::assertIsString($notFound);
        self::assertIsString($footer);
        self::assertIsString($header);

        self::assertStringContainsString('  logo: assets/identity/fight-mark-light.svg', $configuration);
        self::assertStringContainsString('  font: false', $configuration);
        self::assertFileExists($root.'/docs/assets/fonts/open-sans-v44-latin.woff2');
        self::assertFileExists($root.'/docs/assets/fonts/source-sans-3-v19-latin.woff2');
        self::assertFileExists($root.'/docs/assets/fonts/fira-code-v27-latin.woff2');
        self::assertStringContainsString('font-family: "Open Sans"', $stylesheet);
        self::assertStringContainsString('font-family: "Source Sans 3"', $stylesheet);
        self::assertStringContainsString('font-family: "Fira Code"', $stylesheet);
        self::assertStringContainsString('font-feature-settings: "calt" 1', $stylesheet);
        self::assertStringContainsString('--fight-canvas: #F4F6F7;', $stylesheet);
        self::assertStringContainsString('--fight-text: #182126;', $stylesheet);
        self::assertStringContainsString('--fight-kiln: #C2410C;', $stylesheet);
        self::assertStringContainsString('--fight-canvas: #101619;', $stylesheet);
        self::assertStringContainsString('--fight-text: #EEF2F3;', $stylesheet);
        self::assertStringContainsString('--fight-kiln: #FF7A45;', $stylesheet);
        self::assertStringContainsString(':focus-visible', $stylesheet);
        self::assertStringContainsString('@media (prefers-reduced-motion: reduce)', $stylesheet);
        self::assertStringContainsString('fight-mark-dark.svg', $notFound);
        self::assertStringContainsString('fight-mark-dark.svg', $footer);
        self::assertStringContainsString('config.theme.logo', $header);
        self::assertStringContainsString('fight-mark-dark.svg', $header);
        foreach ([
            '.md-header__button.md-logo .atlas-header__mark--dark {',
            '[data-md-color-scheme="slate"] .md-header__button.md-logo .atlas-header__mark--light {',
            '[data-md-color-scheme="slate"] .md-header__button.md-logo .atlas-header__mark--dark {',
            '.md-nav__title .md-nav__button.md-logo .atlas-header__mark--dark {',
            '[data-md-color-scheme="slate"] .md-nav__title .md-nav__button.md-logo .atlas-header__mark--light {',
            '[data-md-color-scheme="slate"] .md-nav__title .md-nav__button.md-logo .atlas-header__mark--dark {',
        ] as $requiredIdentityCascade) {
            self::assertStringContainsString($requiredIdentityCascade, $stylesheet);
        }
        self::assertStringContainsString('© 2026 John Nickell', $footer);
        self::assertStringContainsString('{{ config.repo_url }}', $footer);
        self::assertStringContainsString("{{ 'maintenance/contributing/' | url }}", $footer);
        self::assertStringContainsString('{{ config.repo_url }}/blob/main/LICENSE', $footer);
    }

    public function test_that_the_site_meta_override_projects_page_specific_descriptions_and_the_canonical_social_card(): void
    {
        $root = dirname(__DIR__, 2);
        $configuration = file_get_contents($root.'/mkdocs.yml');
        $override = file_get_contents($root.'/docs/overrides/main.html');

        self::assertIsString($configuration);
        self::assertIsString($override);

        self::assertStringContainsString('site_description: ""', $configuration);
        self::assertStringContainsString('{% block site_meta %}', $override);
        self::assertStringContainsString('page.title', $override);
        self::assertStringContainsString('meta name="description"', $override);
        self::assertStringContainsString('meta property="og:description"', $override);
        self::assertStringContainsString('meta property="og:image"', $override);
        self::assertStringContainsString('meta name="twitter:image"', $override);
        self::assertStringContainsString('fight-common-social-1280x640.png', $override);
    }

    public function test_that_the_homepage_and_navigation_publish_the_canonical_atlas_routes(): void
    {
        $root = dirname(__DIR__, 2);
        $configuration = file_get_contents($root.'/mkdocs.yml');
        $homepage = file_get_contents($root.'/docs/README.md');

        self::assertIsString($configuration);
        self::assertIsString($homepage);

        foreach ([
            '- Quick Start: quick-start/index.md',
            '- Architecture: architecture/index.md',
            '- Components:',
            '- Frameworks:',
            '- Maintenance:',
            '- Mail: components/mail/index.md',
            '- Framework Support: frameworks/framework-support/index.md',
            '- Contributing: maintenance/contributing/index.md',
        ] as $requiredNavigation) {
            self::assertStringContainsString($requiredNavigation, $configuration);
        }

        foreach ([
            'template: atlas-home.html',
            'Keep your core clean. Connect everything else.',
            'Adopt focused PHP components without coupling Domain or Application code to a framework.',
            'composer require johnnickell/fight-common',
            'Adapter',
            'Application',
            'Domain',
            'href="architecture/">',
            'Understand the architecture',
            'href="quick-start/">',
            'Start building',
            'href="#component-atlas">',
            'Explore components',
            '<h3>Model the Domain</h3>',
            '<h3>Coordinate Application Behavior</h3>',
            '<h3>Connect Systems</h3>',
            '<h3>Operate Workloads</h3>',
            '<h3>Integrate Frameworks</h3>',
            'href="components/mail/"',
        ] as $requiredHomepageContract) {
            self::assertStringContainsString($requiredHomepageContract, $homepage);
        }

        foreach ([
            '<a href="components/values/">Values</a>'
            ."\n  ".'<span class="atlas-ownership-rail">Ownership: Domain · Adapter</span>',
            '<a href="components/collections/">Collections</a>'
            ."\n  ".'<span class="atlas-ownership-rail">Ownership: Domain</span>',
            '<a href="components/specifications/">Specifications</a>'
            ."\n  ".'<span class="atlas-ownership-rail">Ownership: Domain</span>',
            '<a href="components/repositories/">Repositories</a>'
            ."\n  ".'<span class="atlas-ownership-rail">Ownership: Domain · Application · Adapter</span>',
            '<a href="components/event-sourcing/">Event Sourcing</a>'
            ."\n  ".'<span class="atlas-ownership-rail">Ownership: Domain · Application · Adapter</span>',
            '<a href="components/utilities/">Utilities</a>'
            ."\n  ".'<span class="atlas-ownership-rail">Ownership: Domain</span>',
            '<a href="components/messaging/">Messaging (CQRS)</a>'
            ."\n  ".'<span class="atlas-ownership-rail">Ownership: Domain · Application · Adapter</span>',
            '<a href="components/validation/">Validation</a>'
            ."\n  ".'<span class="atlas-ownership-rail">Ownership: Application · Adapter</span>',
            '<a href="components/serialization/">Serialization</a>'
            ."\n  ".'<span class="atlas-ownership-rail">Ownership: Domain · Application</span>',
            '<a href="components/dependency-injection/">Dependency Injection</a>'
            ."\n  ".'<span class="atlas-ownership-rail">Ownership: Application</span>',
            '<a href="components/http-client/">HTTP Client</a>'
            ."\n  ".'<span class="atlas-ownership-rail">Ownership: Application · Adapter</span>',
            '<a href="components/auth/">Auth</a>'
            ."\n  ".'<span class="atlas-ownership-rail">Ownership: Domain · Application · Adapter</span>',
            '<a href="components/cache/">Cache</a>'
            ."\n  ".'<span class="atlas-ownership-rail">Ownership: Application · Adapter</span>',
            '<a href="components/files/">Files</a>'
            ."\n  ".'<span class="atlas-ownership-rail">Ownership: Application · Adapter</span>',
            '<a href="components/file-transfer/">File Transfer</a>'
            ."\n  ".'<span class="atlas-ownership-rail">Ownership: Application · Adapter</span>',
            '<a href="components/templating/">Templating</a>'
            ."\n  ".'<span class="atlas-ownership-rail">Ownership: Application · Adapter</span>',
            '<a href="components/routing/">Routing</a>'
            ."\n  ".'<span class="atlas-ownership-rail">Ownership: Application · Adapter</span>',
            '<a href="components/mail/">Mail</a>'
            ."\n  ".'<span class="atlas-ownership-rail">Ownership: Application · Adapter</span>',
            '<a href="components/sms/">SMS</a>'
            ."\n  ".'<span class="atlas-ownership-rail">Ownership: Application · Adapter</span>',
            '<a href="components/sockets/">Sockets</a>'
            ."\n  ".'<span class="atlas-ownership-rail">Ownership: Application · Adapter</span>',
            '<a href="components/observability/">Observability</a>'
            ."\n  ".'<span class="atlas-ownership-rail">Ownership: Domain · Application · Adapter</span>',
            '<a href="components/process/">Process</a>'
            ."\n  ".'<span class="atlas-ownership-rail">Ownership: Application · Adapter</span>',
            '<a href="components/scheduler/">Scheduler</a>'
            ."\n  ".'<span class="atlas-ownership-rail">Ownership: Application · Adapter</span>',
            '<a href="frameworks/framework-support/">Framework Support</a>'
            ."\n  ".'<span class="atlas-ownership-rail">Ownership: Adapter</span>',
            '<a href="frameworks/codeigniter/">CodeIgniter</a>'
            ."\n  ".'<span class="atlas-ownership-rail">Ownership: Adapter</span>',
        ] as $requiredHomepageOwnershipRail) {
            $currentOwnershipRail = preg_replace(
                '#<a href="([^"]+)">([^<]+)</a>\n  <span class="atlas-ownership-rail">Ownership: ([^<]+)</span>#',
                '<a href="$1"><span>$2</span><small class="atlas-ownership-rail">$3</small></a>',
                $requiredHomepageOwnershipRail,
            );
            self::assertIsString($currentOwnershipRail);
            self::assertStringContainsString($currentOwnershipRail, $homepage);
        }
    }

    public function test_that_the_mail_component_article_declares_the_responsive_atlas_deck_shell_contract(): void
    {
        $root = dirname(__DIR__, 2);
        $article = file_get_contents($root.'/docs/components/mail/index.md');
        $override = file_get_contents($root.'/docs/overrides/atlas-article.html');
        $stylesheet = file_get_contents($root.'/docs/stylesheets/extra.css');

        self::assertIsString($article);
        self::assertIsString($override);
        self::assertIsString($stylesheet);

        foreach ([
            'atlas_article: true',
            'atlas_component_group: Connect Systems',
            'atlas_component_owner: Application and Adapter',
            'atlas_component_dependencies: MailTransport, MailFactory, Symfony Mailer',
            'atlas_article_context: Application · Adapter',
            'atlas_article_lead: Send email through an application-owned port, then choose the transport at the boundary.',
            'atlas_article_requires: PHP 8.5+',
            'atlas_article_optional: symfony/mailer',
            'atlas_article_package: johnnickell/fight-common',
            'atlas_relationship_source: Symfony Mailer',
            'atlas_relationship_target: MailTransport',
            'atlas_consequential_message: Recipient overrides replace every original To, Cc, and Bcc recipient.',
            'label: Configure Symfony Mailer',
        ] as $requiredArticleMetadata) {
            self::assertStringContainsString($requiredArticleMetadata, $article);
        }

        foreach ([
            'atlas-component-rail',
            'aria-label="Component navigation"',
            'atlas-breadcrumbs',
            'atlas-article-lead',
            'atlas-article-metadata',
            'atlas-local-contents',
            'atlas-relationship-diagram',
            'atlas-next-steps',
            'page.parent.children',
            'page.meta.atlas_relationship_source',
            'page.meta.atlas_consequential_message',
            'page.meta.atlas_next_steps',
        ] as $requiredShellContent) {
            self::assertStringContainsString($requiredShellContent, $override);
        }
        self::assertStringNotContainsString('components/mail/', $override);
        self::assertStringNotContainsString('Symfony Mailer', $override);

        foreach ([
            '.atlas-article-shell {',
            'grid-template-columns: 208px minmax(0, 736px) 176px;',
            'width: min(100%, 1408px);',
            '@media screen and (min-width: 60em)',
            '.md-main:has(.atlas-article-shell) .md-sidebar--secondary {',
            'display: none;',
            '.md-main:has(.atlas-article-shell) .md-content > .md-content__inner {',
            'margin-inline: 0;',
            '@media screen and (min-width: 76.25em)',
            '.md-main:has(.atlas-article-shell) .md-sidebar--primary {',
            '.atlas-article-content .highlight {',
            'overflow-x: auto;',
            '@media screen and (max-width: 76.1875em)',
            '.atlas-component-rail {',
            '.atlas-local-contents {',
            'display: none;',
            '.atlas-relationship-diagram__flow {',
        ] as $requiredResponsiveBehavior) {
            self::assertStringContainsString($requiredResponsiveBehavior, $stylesheet);
        }
    }

    public function test_that_the_mail_component_article_has_one_semantic_title_and_metadata_driven_local_contents(): void
    {
        $root = dirname(__DIR__, 2);
        $article = file_get_contents($root.'/docs/components/mail/index.md');
        $legacyGuide = file_get_contents($root.'/docs/mail.md');
        $override = file_get_contents($root.'/docs/overrides/atlas-article.html');

        self::assertIsString($article);
        self::assertIsString($legacyGuide);
        self::assertIsString($override);

        self::assertStringContainsString('title: Mail', $article);
        self::assertStringContainsString('atlas_article_heading_id: mail', $article);
        self::assertStringContainsString('atlas_local_contents:', $article);
        self::assertStringContainsString('href: "#configuration-formats"', $article);
        self::assertStringContainsString('href: "#mailmessage"', $article);
        self::assertStringContainsString('href: "#usage-examples"', $article);
        self::assertSame(0, preg_match('/^# Mail$/m', $legacyGuide));
        self::assertStringNotContainsString('## Table of Contents', $legacyGuide);
        self::assertStringContainsString('page.meta.atlas_local_contents', $override);
        self::assertStringNotContainsString('{% set toc = page.toc %}', $override);
    }

    public function test_that_the_article_header_renders_in_the_approved_prototype_order(): void
    {
        $root = dirname(__DIR__, 2);
        $article = file_get_contents($root.'/docs/components/mail/index.md');
        $override = file_get_contents($root.'/docs/overrides/atlas-article.html');
        $contentPartial = file_get_contents($root.'/docs/overrides/partials/content.html');
        $accessibilityScript = file_get_contents($root.'/docs/javascripts/atlas-article-accessibility.js');
        $configuration = file_get_contents($root.'/mkdocs.yml');

        self::assertIsString($article);
        self::assertIsString($override);
        self::assertIsString($contentPartial);
        self::assertIsString($accessibilityScript);
        self::assertIsString($configuration);
        self::assertStringContainsString('title: Mail', $article);
        self::assertStringContainsString('atlas_article_heading_id: mail', $article);
        self::assertMatchesRegularExpression(
            '/<nav class="atlas-breadcrumbs".*?<\/nav>\s*'
            .'<p class="atlas-context-label".*?<\/p>\s*'
            .'<h1 id="{{ page.meta.atlas_article_heading_id }}" tabindex="-1" data-atlas-article-start>{{ page.title \| e }}<\/h1>\s*'
            .'<p class="atlas-article-lead".*?<\/p>\s*'
            .'<dl class="atlas-article-metadata">/s',
            $override,
        );
        self::assertStringContainsString('not page.meta.atlas_article', $contentPartial);
        self::assertStringContainsString('{{ page.content }}', $contentPartial);
        self::assertStringContainsString('javascripts/atlas-article-accessibility.js', $configuration);
        self::assertStringContainsString('[data-atlas-article-start]', $accessibilityScript);
        self::assertStringContainsString('[data-md-component="skip"] .md-skip', $accessibilityScript);
        self::assertStringContainsString('skip.href = `#${articleStart.id}`', $accessibilityScript);
        self::assertStringContainsString('articleStart.focus()', $accessibilityScript);
    }
}
