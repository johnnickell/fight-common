<?php

declare(strict_types=1);

namespace Fight\Test\Common\Tooling;

use Closure;
use Fight\Test\Common\TestCase\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Process\Process;

#[CoversNothing]
final class DocsArtifactValidatorTest extends UnitTestCase
{
    private string $directory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->directory = sys_get_temp_dir().'/fight-common-docs-artifact-'.bin2hex(random_bytes(8));
        $this->createKnownGoodArtifact();
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->directory);

        parent::tearDown();
    }

    public function test_that_the_known_good_generated_artifact_is_accepted(): void
    {
        $process = $this->validateArtifact();

        self::assertSame(0, $process->getExitCode(), $process->getErrorOutput());
    }

    public function test_that_the_known_good_generated_artifact_is_accepted_from_a_relative_site_path(): void
    {
        $process = $this->validateArtifact('site');

        self::assertSame(0, $process->getExitCode(), $process->getErrorOutput());
    }

    public function test_that_generated_mail_skip_navigation_requires_a_focusable_article_start(): void
    {
        $accessibilityScript = $this->directory.'/site/javascripts/atlas-article-accessibility.js';
        $artifact = file_get_contents($accessibilityScript);

        self::assertIsString($artifact);
        file_put_contents($accessibilityScript, str_replace('articleStart.focus();', '', $artifact));

        $process = $this->validateArtifact();

        self::assertSame(1, $process->getExitCode());
        self::assertStringContainsString(
            'Generated Mail skip navigation must target the focusable article start',
            $process->getErrorOutput(),
        );
    }

    public function test_that_generated_mail_configuration_requires_semantic_format_tab_and_copy_controls(): void
    {
        $mailArticle = $this->directory.'/site/components/mail/index.html';
        $artifact = file_get_contents($mailArticle);

        self::assertIsString($artifact);
        file_put_contents($mailArticle, str_replace('data-atlas-copy aria-label', 'aria-label', $artifact));

        $process = $this->validateArtifact();

        self::assertSame(1, $process->getExitCode());
        self::assertStringContainsString(
            'Generated Mail article is missing semantic configuration tab and copy controls',
            $process->getErrorOutput(),
        );
    }

    public function test_that_generated_mail_configuration_requires_controls_in_their_corresponding_panels(): void
    {
        $mailArticle = $this->directory.'/site/components/mail/index.html';
        $artifact = file_get_contents($mailArticle);

        self::assertIsString($artifact);
        $yamlControls = '<code data-atlas-filename="config/services.yaml">config/services.yaml</code><button data-atlas-copy aria-label="Copy YAML configuration">Copy</button>';
        $xmlControls = '<code data-atlas-filename="config/services.xml">config/services.xml</code><button data-atlas-copy aria-label="Copy XML configuration">Copy</button>';
        file_put_contents(
            $mailArticle,
            str_replace(
                ['__YAML_CONTROLS__', $xmlControls],
                [$xmlControls, $yamlControls],
                str_replace($yamlControls, '__YAML_CONTROLS__', $artifact),
            ),
        );

        $process = $this->validateArtifact();

        self::assertSame(1, $process->getExitCode());
        self::assertStringContainsString(
            'Generated Mail article is missing semantic configuration tab and copy controls',
            $process->getErrorOutput(),
        );
    }

    public function test_that_generated_mail_configuration_requires_an_accessible_copy_status_announcement(): void
    {
        $mailArticle = $this->directory.'/site/components/mail/index.html';
        $artifact = file_get_contents($mailArticle);

        self::assertIsString($artifact);
        file_put_contents($mailArticle, str_replace('data-atlas-copy-status', '', $artifact));

        $process = $this->validateArtifact();

        self::assertSame(1, $process->getExitCode());
        self::assertStringContainsString(
            'Generated Mail article is missing an accessible copy status announcement',
            $process->getErrorOutput(),
        );
    }

    public function test_that_generated_quick_start_requires_its_event_anchor(): void
    {
        $quickStart = $this->directory.'/site/quick-start/index.html';
        $artifact = file_get_contents($quickStart);

        self::assertIsString($artifact);
        file_put_contents($quickStart, str_replace('id="the-event"', 'id="event"', $artifact));

        $process = $this->validateArtifact();

        self::assertSame(1, $process->getExitCode());
        self::assertStringContainsString(
            'Generated Quick Start article is missing required article anchor: #the-event',
            $process->getErrorOutput(),
        );
    }

    public function test_that_generated_quick_start_accepts_accurate_reworded_titles_and_consumer_symbols(): void
    {
        $quickStart = $this->directory.'/site/quick-start/index.html';
        $artifact = file_get_contents($quickStart);

        self::assertIsString($artifact);
        file_put_contents(
            $quickStart,
            str_replace(
                ['Framework-Neutral Quick Start', 'Complete executable example', 'SimpleEventDispatcher', 'final readonly class CustomerId'],
                ['Portable guided introduction', 'Executable reference', 'Event dispatcher', 'final readonly class Customer'],
                $artifact,
            ),
        );

        $process = $this->validateArtifact();

        self::assertSame(0, $process->getExitCode(), $process->getErrorOutput());
    }

    public function test_that_generated_quick_start_requires_each_class_surface_and_runtime_copy_feature(): void
    {
        $quickStart = $this->directory.'/site/quick-start/index.html';
        $artifact = file_get_contents($quickStart);

        self::assertIsString($artifact);
        file_put_contents(
            $quickStart,
            str_replace(
                'id="quick-start-order-processed-event" class="highlight"',
                'id="quick-start-order-processed-event" class="code-surface"',
                $artifact,
            ),
        );

        $process = $this->validateArtifact();

        self::assertSame(1, $process->getExitCode());
        self::assertStringContainsString(
            'Generated Quick Start article is missing syntax highlighting for executable surface: #quick-start-order-processed-event',
            $process->getErrorOutput(),
        );

        file_put_contents(
            $quickStart,
            str_replace(
                'final readonly class OrderProcessed implements Event',
                '',
                $artifact,
            ),
        );
        $process = $this->validateArtifact();

        self::assertSame(1, $process->getExitCode());
        self::assertStringContainsString(
            'Generated Quick Start article is missing nonempty executable PHP code surface: #quick-start-order-processed-event',
            $process->getErrorOutput(),
        );

        file_put_contents(
            $quickStart,
            str_replace(
                '<script id="__config" type="application/json">{"features":["content.code.copy"]}</script>',
                '<p>content.code.copy</p>',
                $artifact,
            ),
        );
        $process = $this->validateArtifact();

        self::assertSame(1, $process->getExitCode());
        self::assertStringContainsString(
            'Generated Quick Start article is missing Material runtime configuration',
            $process->getErrorOutput(),
        );

        file_put_contents(
            $quickStart,
            str_replace(
                '<script id="__config" type="application/json">{"features":["content.code.copy"]}</script>',
                '<p>content.code.copy</p><script id="__config" type="application/json">{</script>',
                $artifact,
            ),
        );
        $process = $this->validateArtifact();

        self::assertSame(1, $process->getExitCode());
        self::assertStringContainsString(
            'Generated Quick Start article has malformed Material runtime configuration',
            $process->getErrorOutput(),
        );
    }

    public function test_that_generated_quick_start_requires_its_installation_command_and_expected_output(): void
    {
        $quickStart = $this->directory.'/site/quick-start/index.html';
        $artifact = file_get_contents($quickStart);

        self::assertIsString($artifact);
        file_put_contents($quickStart, str_replace('composer require johnnickell/fight-common', 'composer install', $artifact));

        $process = $this->validateArtifact();

        self::assertSame(1, $process->getExitCode());
        self::assertStringContainsString(
            'Generated Quick Start article is missing required content: composer require johnnickell/fight-common',
            $process->getErrorOutput(),
        );

        file_put_contents(
            $quickStart,
            str_replace('Order ORDER-1001 processed for CUSTOMER-42; fulfillment requested.', 'Order processed.', $artifact),
        );

        $process = $this->validateArtifact();

        self::assertSame(1, $process->getExitCode());
        self::assertStringContainsString(
            'Generated Quick Start article is missing required content: Order ORDER-1001 processed for CUSTOMER-42; fulfillment requested.',
            $process->getErrorOutput(),
        );
    }

    public function test_that_generated_quick_start_requires_each_next_path(): void
    {
        $quickStart = $this->directory.'/site/quick-start/index.html';
        $artifact = file_get_contents($quickStart);

        self::assertIsString($artifact);
        file_put_contents($quickStart, str_replace('href="../frameworks/framework-support/"', 'href="../frameworks/codeigniter/"', $artifact));

        $process = $this->validateArtifact();

        self::assertSame(1, $process->getExitCode());
        self::assertStringContainsString(
            'Generated Quick Start article is missing required next path: ../frameworks/framework-support/',
            $process->getErrorOutput(),
        );
    }

    public function test_that_generated_search_index_requires_the_quick_start_entry(): void
    {
        $searchIndex = $this->directory.'/site/search/search_index.json';
        $artifact = file_get_contents($searchIndex);

        self::assertIsString($artifact);
        $index = json_decode($artifact, true, 512, JSON_THROW_ON_ERROR);

        self::assertIsArray($index);
        self::assertIsArray($index['docs']);
        foreach ($index['docs'] as &$entry) {
            if (is_array($entry) && ($entry['location'] ?? null) === 'quick-start/') {
                $entry['location'] = 'architecture/';
            }
        }
        unset($entry);
        file_put_contents($searchIndex, json_encode($index, JSON_THROW_ON_ERROR));

        $process = $this->validateArtifact();

        self::assertSame(1, $process->getExitCode());
        self::assertStringContainsString(
            'Generated search index is missing the Quick Start entry',
            $process->getErrorOutput(),
        );
    }

    public function test_that_generated_artifact_requires_44px_native_control_targets_across_reviewed_viewports(): void
    {
        $stylesheet = $this->directory.'/site/stylesheets/extra.css';
        $artifact = file_get_contents($stylesheet);

        self::assertIsString($artifact);
        file_put_contents($stylesheet, str_replace('min-inline-size: 44px;', '', $artifact));

        $process = $this->validateArtifact();

        self::assertSame(1, $process->getExitCode());
        self::assertStringContainsString(
            'Generated stylesheet is missing 44px native control targets across reviewed viewports',
            $process->getErrorOutput(),
        );
    }

    public function test_that_generated_artifact_requires_native_control_sizing_in_its_rule(): void
    {
        $stylesheet = $this->directory.'/site/stylesheets/extra.css';
        file_put_contents(
            $stylesheet,
            str_replace('.md-search__options > .md-search__icon', '.unrelated', self::requiredGeneratedStylesheet()),
        );

        $process = $this->validateArtifact();

        self::assertSame(1, $process->getExitCode());
        self::assertStringContainsString(
            'Generated stylesheet is missing 44px native control targets across reviewed viewports',
            $process->getErrorOutput(),
        );
    }

    public function test_that_generated_artifact_requires_custom_copy_sizing_in_its_rule(): void
    {
        $stylesheet = $this->directory.'/site/stylesheets/extra.css';
        file_put_contents(
            $stylesheet,
            str_replace(
                '.atlas-format-tabs__copy { flex: 0 0 44px; inline-size: 44px; min-block-size: 44px; }',
                '.atlas-format-tabs__copy { flex: 0 0 44px; }',
                self::requiredGeneratedStylesheet(),
            ),
        );

        $process = $this->validateArtifact();

        self::assertSame(1, $process->getExitCode());
        self::assertStringContainsString(
            'Generated stylesheet is missing approved copy control sizing',
            $process->getErrorOutput(),
        );
    }

    public function test_that_generated_artifact_requires_accessible_footer_link_contrast_in_light_theme(): void
    {
        $stylesheet = $this->directory.'/site/stylesheets/extra.css';
        $artifact = file_get_contents($stylesheet);

        self::assertIsString($artifact);
        file_put_contents($stylesheet, str_replace('--fight-footer-link: #FF7A45;', '--fight-footer-link: #C2410C;', $artifact));

        $process = $this->validateArtifact();

        self::assertSame(1, $process->getExitCode());
        self::assertStringContainsString(
            'Generated stylesheet footer link contrast is below 4.5:1',
            $process->getErrorOutput(),
        );
    }

    public function test_that_generated_artifact_requires_accessible_footer_focus_contrast_in_light_theme(): void
    {
        $stylesheet = $this->directory.'/site/stylesheets/extra.css';
        $artifact = file_get_contents($stylesheet);

        self::assertIsString($artifact);
        file_put_contents($stylesheet, str_replace('--fight-footer-focus: #FF9A72;', '--fight-footer-focus: #9A3412;', $artifact));

        $process = $this->validateArtifact();

        self::assertSame(1, $process->getExitCode());
        self::assertStringContainsString(
            'Generated stylesheet footer focus contrast is below 3:1',
            $process->getErrorOutput(),
        );
    }

    public function test_that_generated_artifact_requires_footer_link_style_to_outrank_pinned_material_rule(): void
    {
        $stylesheet = $this->directory.'/site/stylesheets/extra.css';
        $artifact = file_get_contents($stylesheet);

        self::assertIsString($artifact);
        file_put_contents(
            $stylesheet,
            str_replace(
                'html .atlas-footer .md-footer-meta.md-typeset .atlas-footer__links a',
                '.atlas-footer__links a',
                $artifact,
            ),
        );

        $process = $this->validateArtifact();

        self::assertSame(1, $process->getExitCode());
        self::assertStringContainsString(
            'Generated stylesheet is missing a footer link rule that outranks Material',
            $process->getErrorOutput(),
        );
    }

    public function test_that_generated_artifact_requires_focused_footer_link_outline_to_outrank_material(): void
    {
        $stylesheet = $this->directory.'/site/stylesheets/extra.css';
        $artifact = file_get_contents($stylesheet);

        self::assertIsString($artifact);
        file_put_contents(
            $stylesheet,
            str_replace(
                'html .atlas-footer .md-footer-meta.md-typeset .atlas-footer__links a.focus-visible',
                '.atlas-footer__links a.focus-visible',
                $artifact,
            ),
        );

        $process = $this->validateArtifact();

        self::assertSame(1, $process->getExitCode());
        self::assertStringContainsString(
            'Generated stylesheet is missing a focused footer link rule that outranks Material',
            $process->getErrorOutput(),
        );
    }

    public function test_that_allowlisted_external_and_non_navigating_hyperlinks_are_accepted(): void
    {
        $homepage = $this->directory.'/site/index.html';
        $artifact = file_get_contents($homepage);

        self::assertIsString($artifact);
        file_put_contents(
            $homepage,
            $artifact.implode('', [
                '<a href="">Empty</a>', '<a href="#">Top</a>', '<a href="mailto:docs@example.test">Email</a>',
                '<a href="tel:+15551234567">Phone</a>',
                '<a href="https://github.com/johnnickell/fight-common">GitHub</a>',
            ]),
        );

        $process = $this->validateArtifact();

        self::assertSame(0, $process->getExitCode(), $process->getErrorOutput());
    }

    /**
     * @param Closure(string): void $makeInvalid
     */
    #[DataProvider('invalidArtifacts')]
    public function test_that_invalid_generated_artifacts_are_rejected(Closure $makeInvalid, string $expectedError): void
    {
        $makeInvalid($this->directory.'/site');

        $process = $this->validateArtifact();

        self::assertSame(1, $process->getExitCode());
        self::assertStringContainsString($expectedError, $process->getErrorOutput());
    }

    /**
     * @return iterable<string, array{Closure(string): void, string}>
     */
    public static function invalidArtifacts(): iterable
    {
        yield 'missing required index page' => [
            static function (string $site): void {
                unlink($site.'/index.html');
            },
            'Generated artifact is missing required file: index.html',
        ];
        yield 'homepage missing Framework Support ownership rail' => [
            static function (string $site): void {
                $homepage = file_get_contents($site.'/index.html');

                if (!is_string($homepage)) {
                    throw new \RuntimeException('Could not read generated homepage fixture.');
                }
                file_put_contents(
                    $site.'/index.html',
                    str_replace(
                        '<a href="frameworks/framework-support/">Framework Support</a>'
                        ."\n  ".'<span class="atlas-ownership-rail">Ownership: Adapter</span>',
                        '',
                        $homepage,
                    ),
                );
            },
            'Generated homepage is missing complete ownership rail: Framework Support',
        ];
        yield 'header identity is missing the dark-surface canonical variant' => [
            static function (string $site): void {
                $homepage = file_get_contents($site.'/index.html');

                if (!is_string($homepage)) {
                    throw new \RuntimeException('Could not read generated homepage fixture.');
                }
                file_put_contents(
                    $site.'/index.html',
                    str_replace('fight-mark-dark.svg', 'fight-mark-light.svg', $homepage),
                );
            },
            'Generated homepage is missing the dark-surface header identity variant',
        ];
        yield 'drawer identity is missing its dark-surface canonical variant' => [
            static function (string $site): void {
                $homepage = file_get_contents($site.'/index.html');

                if (!is_string($homepage)) {
                    throw new \RuntimeException('Could not read generated homepage fixture.');
                }
                $drawerDarkMark = '<img class="atlas-header__mark atlas-header__mark--dark" src="assets/identity/fight-mark-dark.svg" alt="" aria-hidden="true">';
                $drawerOffset = strrpos($homepage, $drawerDarkMark);

                if ($drawerOffset === false) {
                    throw new \RuntimeException('Could not locate the generated drawer dark identity fixture.');
                }
                file_put_contents(
                    $site.'/index.html',
                    substr_replace(
                        $homepage,
                        str_replace('fight-mark-dark.svg', 'fight-mark-light.svg', $drawerDarkMark),
                        $drawerOffset,
                        strlen($drawerDarkMark),
                    ),
                );
            },
            'Generated homepage drawer is missing the dark-surface identity variant',
        ];
        yield 'not found identity is missing the dark-surface canonical variant' => [
            static function (string $site): void {
                $notFound = file_get_contents($site.'/404.html');

                if (!is_string($notFound)) {
                    throw new \RuntimeException('Could not read generated not found fixture.');
                }
                file_put_contents(
                    $site.'/404.html',
                    str_replace('fight-mark-dark.svg', 'fight-mark-light.svg', $notFound),
                );
            },
            'Generated 404 page is missing the dark-surface identity variant',
        ];
        yield 'page description falls back to a generic site description' => [
            static function (string $site): void {
                $homepage = file_get_contents($site.'/index.html');

                if (!is_string($homepage)) {
                    throw new \RuntimeException('Could not read generated homepage fixture.');
                }
                file_put_contents($site.'/assets/identity/unapproved-social-card.png', 'unapproved social card');
                file_put_contents(
                    $site.'/index.html',
                    str_replace(
                        'Framework-neutral PHP building blocks for Hexagonal Architecture, CQRS, and reusable domain primitives.',
                        'A shared PHP library for Hexagonal Architecture, CQRS, and reusable domain primitives.',
                        $homepage,
                    ),
                );
            },
            'Generated document description must be page-specific: index.html',
        ];
        yield 'page social card does not use the canonical identity asset' => [
            static function (string $site): void {
                $homepage = file_get_contents($site.'/index.html');

                if (!is_string($homepage)) {
                    throw new \RuntimeException('Could not read generated homepage fixture.');
                }
                file_put_contents(
                    $site.'/index.html',
                    str_replace('fight-common-social-1280x640.png', 'unapproved-social-card.png', $homepage),
                );
            },
            'Generated document must use the canonical Fight Common social card: index.html',
        ];
        yield 'font manifest without the required integrity schema' => [
            static function (string $site): void {
                file_put_contents($site.'/assets/fonts/manifest.json', '{}');
            },
            'Generated font manifest must declare schema version 1',
        ];
        yield 'font manifest with an unsafe distributed file name' => [
            static function (string $site): void {
                $manifest = file_get_contents($site.'/assets/fonts/manifest.json');

                if (!is_string($manifest)) {
                    throw new \RuntimeException('Could not read generated font manifest fixture.');
                }
                file_put_contents(
                    $site.'/assets/fonts/manifest.json',
                    str_replace('open-sans-v44-latin.woff2', '../open-sans-v44-latin.woff2', $manifest),
                );
            },
            'Generated font manifest has an unsafe file path: ../open-sans-v44-latin.woff2',
        ];
        yield 'font bytes that do not match the release-pinned digest' => [
            static function (string $site): void {
                file_put_contents($site.'/assets/fonts/open-sans-v44-latin.woff2', 'tampered font bytes');
            },
            'Generated font digest does not match manifest: open-sans-v44-latin.woff2',
        ];
        yield 'license material that does not match the release-pinned digest' => [
            static function (string $site): void {
                file_put_contents($site.'/assets/fonts/licenses/open-sans-v44-ofl-1.1.txt', 'tampered license material');
            },
            'Generated font license digest does not match manifest: licenses/open-sans-v44-ofl-1.1.txt',
        ];
        yield 'symbolic link in output' => [
            static function (string $site): void {
                symlink('assets/site.css', $site.'/linked.css');
            },
            'Generated artifact contains symbolic link: linked.css',
        ];
        yield 'source only overrides path' => [
            static function (string $site): void {
                mkdir($site.'/overrides', 0777, true);
                file_put_contents($site.'/overrides/main.html', 'source only');
            },
            'Generated artifact contains source-only overrides path: overrides',
        ];
        yield 'malformed search data' => [
            static function (string $site): void {
                file_put_contents($site.'/search/search_index.json', '{');
            },
            'Generated search index is malformed:',
        ];
        yield 'empty search data' => [
            static function (string $site): void {
                file_put_contents($site.'/search/search_index.json', '{"docs":[]}');
            },
            'Generated search index is empty or malformed',
        ];
        yield 'sitemap outside production root' => [
            static function (string $site): void {
                file_put_contents($site.'/sitemap.xml', '<urlset><url><loc>https://example.test/</loc></url></urlset>');
            },
            'Generated sitemap location is outside production docs root: https://example.test/',
        ];
        yield 'empty sitemap' => [
            static function (string $site): void {
                file_put_contents($site.'/sitemap.xml', '<urlset/>');
            },
            'Generated sitemap is empty',
        ];
        yield 'sitemap without the production root' => [
            static function (string $site): void {
                file_put_contents(
                    $site.'/sitemap.xml',
                    '<urlset><url><loc>https://johnnickell.github.io/fight-common/guide/</loc></url></urlset>',
                );
            },
            'Generated sitemap is missing the production docs root',
        ];
        yield 'sitemap location without generated output' => [
            static function (string $site): void {
                file_put_contents(
                    $site.'/sitemap.xml',
                    '<urlset><url><loc>https://johnnickell.github.io/fight-common/</loc></url><url><loc>https://johnnickell.github.io/fight-common/missing/</loc></url></urlset>',
                );
            },
            'Generated sitemap location does not resolve to output: https://johnnickell.github.io/fight-common/missing/',
        ];
        yield 'wrong root canonical URL' => [
            static function (string $site): void {
                file_put_contents($site.'/index.html', '<link rel="canonical" href="https://example.test/">');
            },
            'Generated document must have exactly one route-derived production canonical: index.html',
        ];
        yield 'incorrect child canonical URL' => [
            static function (string $site): void {
                file_put_contents($site.'/guide/index.html', '<link rel="canonical" href="https://johnnickell.github.io/fight-common/">');
            },
            'Generated document must have exactly one route-derived production canonical: guide/index.html',
        ];
        yield 'missing child canonical URL' => [
            static function (string $site): void {
                file_put_contents($site.'/guide/index.html', '<p>Guide</p>');
            },
            'Generated document must have exactly one route-derived production canonical: guide/index.html',
        ];
        yield 'search location without generated output' => [
            static function (string $site): void {
                file_put_contents($site.'/search/search_index.json', '{"docs":[{"location":"missing/#heading"}]}');
            },
            'Generated search location does not resolve to output: missing/#heading',
        ];
        yield 'search location without generated fragment' => [
            static function (string $site): void {
                file_put_contents($site.'/search/search_index.json', '{"docs":[{"location":"guide/#missing-heading"}]}');
            },
            'Generated search location does not resolve to output fragment: guide/#missing-heading',
        ];
        yield 'root relative URL outside docs base' => [
            static function (string $site): void {
                file_put_contents($site.'/index.html', '<link rel="canonical" href="https://johnnickell.github.io/fight-common/"><img src="/assets/site.css">');
            },
            'Generated document has root-relative URL outside docs base: index.html: /assets/site.css',
        ];
        yield 'missing local static asset' => [
            static function (string $site): void {
                file_put_contents($site.'/index.html', '<link rel="canonical" href="https://johnnickell.github.io/fight-common/"><img src="/fight-common/assets/missing.svg?cache=1#logo">');
            },
            'Generated document references missing local static asset: index.html: /fight-common/assets/missing.svg?cache=1#logo',
        ];
        yield 'local hyperlink without generated output' => [
            static function (string $site): void {
                $homepage = file_get_contents($site.'/index.html');

                if (!is_string($homepage)) {
                    throw new \RuntimeException('Could not read generated homepage fixture.');
                }
                file_put_contents(
                    $site.'/index.html',
                    $homepage.'<a href="/fight-common/contributing/">Contributing</a>',
                );
            },
            'Generated document links to missing local output: index.html: /fight-common/contributing/',
        ];
        yield 'local hyperlink without generated fragment' => [
            static function (string $site): void {
                $homepage = file_get_contents($site.'/index.html');

                if (!is_string($homepage)) {
                    throw new \RuntimeException('Could not read generated homepage fixture.');
                }
                file_put_contents(
                    $site.'/index.html',
                    $homepage.'<a href="#missing-section">Missing section</a>',
                );
            },
            'Generated document links to missing local fragment: index.html: #missing-section',
        ];
        foreach ([
            'javascript' => 'javascript:alert(1)',
            'data' => 'data:text/html,unsafe',
            'vbscript' => 'vbscript:msgbox("unsafe")',
        ] as $scheme => $href) {
            yield "unsupported $scheme hyperlink scheme" => [
                static function (string $site) use ($href): void {
                    $homepage = file_get_contents($site.'/index.html');

                    if (!is_string($homepage)) {
                        throw new \RuntimeException('Could not read generated homepage fixture.');
                    }
                    file_put_contents($site.'/index.html', $homepage.'<a href="'.$href.'">Unsafe</a>');
                },
                "Generated document uses an unsupported hyperlink scheme: index.html: $scheme",
            ];
        }
        yield 'protocol relative hyperlink' => [
            static function (string $site): void {
                $homepage = file_get_contents($site.'/index.html');

                if (!is_string($homepage)) {
                    throw new \RuntimeException('Could not read generated homepage fixture.');
                }
                file_put_contents($site.'/index.html', $homepage.'<a href="//cdn.example.test/library">CDN</a>');
            },
            'Generated document uses an unsupported hyperlink scheme: index.html: protocol-relative',
        ];
        yield 'mail article starts with a configuration heading rather than its title' => [
            static function (string $site): void {
                $mailArticle = file_get_contents($site.'/components/mail/index.html');

                if (!is_string($mailArticle)) {
                    throw new \RuntimeException('Could not read generated Mail article fixture.');
                }
                file_put_contents(
                    $site.'/components/mail/index.html',
                    str_replace('<h1 id="mail" tabindex="-1" data-atlas-article-start>Mail</h1>', '<h2 id="mail">Mail</h2>', $mailArticle),
                );
            },
            'Generated Mail article must render its title before supporting shell affordances',
        ];
        yield 'mail article has a duplicate title heading' => [
            static function (string $site): void {
                $mailArticle = file_get_contents($site.'/components/mail/index.html');

                if (!is_string($mailArticle)) {
                    throw new \RuntimeException('Could not read generated Mail article fixture.');
                }
                file_put_contents(
                    $site.'/components/mail/index.html',
                    str_replace(
                        '<h2 id="configuration-formats">Configuration formats</h2>',
                        '<h1 id="mail-details">Mail details</h1><h2 id="configuration-formats">Configuration formats</h2>',
                        $mailArticle,
                    ),
                );
            },
            'Generated Mail article must contain exactly one logical H1: Mail',
        ];
        yield 'mail article title follows its supporting shell affordances' => [
            static function (string $site): void {
                $mailArticle = file_get_contents($site.'/components/mail/index.html');

                if (!is_string($mailArticle)) {
                    throw new \RuntimeException('Could not read generated Mail article fixture.');
                }
                file_put_contents(
                    $site.'/components/mail/index.html',
                    str_replace('<h1 id="mail" tabindex="-1" data-atlas-article-start>Mail</h1>', '', $mailArticle).'<h1 id="mail" tabindex="-1" data-atlas-article-start>Mail</h1>',
                );
            },
            'Generated Mail article must render its title before supporting shell affordances',
        ];
        yield 'mail local contents label does not describe its linked heading' => [
            static function (string $site): void {
                $mailArticle = file_get_contents($site.'/components/mail/index.html');

                if (!is_string($mailArticle)) {
                    throw new \RuntimeException('Could not read generated Mail article fixture.');
                }
                file_put_contents(
                    $site.'/components/mail/index.html',
                    str_replace('>MailMessage</a>', '>Mail API</a>', $mailArticle),
                );
            },
            'Generated Mail article local contents must link to its semantic H2 sections',
        ];
        yield '404 page missing production return link' => [
            static function (string $site): void {
                file_put_contents($site.'/404.html', '<a href="/fight-common/">Return</a>');
            },
            'Generated 404 page is missing a return link to the production docs root',
        ];
    }

    private function createKnownGoodArtifact(): void
    {
        mkdir($this->directory.'/site/assets', 0777, true);
        mkdir($this->directory.'/site/assets/fonts', 0777, true);
        mkdir($this->directory.'/site/assets/identity', 0777, true);
        mkdir($this->directory.'/site/guide', 0777, true);
        mkdir($this->directory.'/site/javascripts', 0777, true);
        mkdir($this->directory.'/site/search', 0777, true);
        mkdir($this->directory.'/site/stylesheets', 0777, true);
        $routes = self::canonicalRoutes();
        $homepage = implode('', [
            '<link rel="canonical" href="https://johnnickell.github.io/fight-common/">',
            '<meta name="description" content="Framework-neutral PHP building blocks for Hexagonal Architecture, CQRS, and reusable domain primitives.">',
            '<meta property="og:title" content="fight-common">',
            '<meta property="og:description" content="Framework-neutral PHP building blocks for Hexagonal Architecture, CQRS, and reusable domain primitives.">',
            '<meta property="og:image" content="https://johnnickell.github.io/fight-common/assets/identity/fight-common-social-1280x640.png">',
            '<meta name="twitter:card" content="summary_large_image">',
            '<meta name="twitter:image" content="https://johnnickell.github.io/fight-common/assets/identity/fight-common-social-1280x640.png">',
            '<header><a class="md-header__button md-logo"><img class="atlas-header__mark atlas-header__mark--light" src="assets/identity/fight-mark-light.svg" alt="Fight"><img class="atlas-header__mark atlas-header__mark--dark" src="assets/identity/fight-mark-dark.svg" alt="" aria-hidden="true"></a></header>',
            '<nav><a class="md-nav__button md-logo"><img class="atlas-header__mark atlas-header__mark--light" src="assets/identity/fight-mark-light.svg" alt="Fight"><img class="atlas-header__mark atlas-header__mark--dark" src="assets/identity/fight-mark-dark.svg" alt="" aria-hidden="true"></a></nav>',
            '<footer><img class="atlas-footer__mark" src="assets/identity/fight-mark-dark.svg" alt="Fight"></footer>',
            '<link rel="stylesheet" href="/fight-common/assets/site.css?cache=1#version">',
            'Keep your core clean. Connect everything else.',
            'Adopt focused PHP components without coupling Domain or Application code to a framework.',
            'composer require johnnickell/fight-common',
            'Adapter Application Domain Understand the architecture Start building Explore components',
            'Model the Domain Coordinate Application Behavior Connect Systems Operate Workloads Integrate Frameworks',
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
        ]);
        foreach ($routes as $route) {
            mkdir($this->directory.'/site/'.$route, 0777, true);
            $description = ucfirst(str_replace(['/', '-'], [' ', ' '], trim($route, '/'))).' documentation for Fight Common.';
            $content = '<link rel="canonical" href="https://johnnickell.github.io/fight-common/'.$route.'">'
                .'<meta name="description" content="'.$description.'">'
                .'<meta property="og:title" content="'.ucfirst(trim($route, '/')).'">'
                .'<meta property="og:description" content="'.$description.'">'
                .'<meta property="og:image" content="https://johnnickell.github.io/fight-common/assets/identity/fight-common-social-1280x640.png">'
                .'<meta name="twitter:card" content="summary_large_image">'
                .'<meta name="twitter:image" content="https://johnnickell.github.io/fight-common/assets/identity/fight-common-social-1280x640.png">'
                .'<header><img class="atlas-header__mark atlas-header__mark--light" src="/fight-common/assets/identity/fight-mark-light.svg" alt="Fight"><img class="atlas-header__mark atlas-header__mark--dark" src="/fight-common/assets/identity/fight-mark-dark.svg" alt="" aria-hidden="true"></header>';
            if ($route === 'components/mail/') {
                $content .= implode('', [
                    '<nav class="atlas-component-rail">Connect Systems</nav>',
                    '<h1 id="mail" tabindex="-1" data-atlas-article-start>Mail</h1>',
                    '<nav class="atlas-breadcrumbs"></nav>',
                    '<p class="atlas-context-label">Application · Adapter</p>',
                    '<p class="atlas-article-lead">Send email through an application-owned port, then choose the transport at the boundary.</p>',
                    '<dl class="atlas-article-metadata">Requires PHP 8.5+ Optional symfony/mailer Package johnnickell/fight-common</dl>',
                    '<aside class="atlas-local-contents"><p>On this page</p><ul>',
                    '<li><a href="#configuration-formats">Configuration formats</a></li>',
                    '<li><a href="#mailmessage">MailMessage</a></li>',
                    '<li><a href="#mailservice-facade">MailService (Facade)</a></li>',
                    '<li><a href="#mailtransport">MailTransport</a></li>',
                    '<li><a href="#mailfactory">MailFactory</a></li>',
                    '<li><a href="#attachment">Attachment</a></li>',
                    '<li><a href="#priority">Priority</a></li>',
                    '<li><a href="#symfony-configuration">Symfony Configuration</a></li>',
                    '<li><a href="#usage-examples">Usage Examples</a></li></ul></aside>',
                    '<figure class="atlas-relationship-diagram"></figure>',
                    '<aside class="atlas-consequential-callout">Consequential behavior Recipient overrides replace every original To, Cc, and Bcc recipient.</aside>',
                    '<nav class="atlas-next-steps">Next steps</nav>',
                    '<h2 id="configuration-formats">Configuration formats</h2>',
                    '<h2 id="mailmessage">MailMessage</h2>',
                    '<h2 id="mailservice-facade">MailService (Facade)</h2>',
                    '<h2 id="mailtransport">MailTransport</h2>',
                    '<h2 id="mailfactory">MailFactory</h2>',
                    '<h2 id="attachment">Attachment</h2>',
                    '<h2 id="priority">Priority</h2>',
                    '<h2 id="symfony-configuration">Symfony Configuration</h2>',
                    '<h2 id="usage-examples">Usage Examples</h2>',
                    '<section class="atlas-format-tabs" data-atlas-format-tabs><div role="tablist" aria-label="Configuration format">',
                    '<button role="tab" id="atlas-mail-config-yaml-tab" aria-controls="atlas-mail-config-yaml-panel" aria-selected="true" tabindex="0" data-atlas-format="yaml">YAML</button>',
                    '<button role="tab" id="atlas-mail-config-xml-tab" aria-controls="atlas-mail-config-xml-panel" aria-selected="false" tabindex="-1" data-atlas-format="xml">XML</button>',
                    '<button role="tab" id="atlas-mail-config-php-tab" aria-controls="atlas-mail-config-php-panel" aria-selected="false" tabindex="-1" data-atlas-format="php">PHP</button></div><span data-atlas-copy-status role="status" aria-live="polite" aria-atomic="true"></span>',
                    '<section role="tabpanel" id="atlas-mail-config-yaml-panel" aria-labelledby="atlas-mail-config-yaml-tab" data-atlas-format-panel="yaml"><code data-atlas-filename="config/services.yaml">config/services.yaml</code><button data-atlas-copy aria-label="Copy YAML configuration">Copy</button></section>',
                    '<section role="tabpanel" id="atlas-mail-config-xml-panel" aria-labelledby="atlas-mail-config-xml-tab" data-atlas-format-panel="xml" hidden><code data-atlas-filename="config/services.xml">config/services.xml</code><button data-atlas-copy aria-label="Copy XML configuration">Copy</button></section>',
                    '<section role="tabpanel" id="atlas-mail-config-php-panel" aria-labelledby="atlas-mail-config-php-tab" data-atlas-format-panel="php" hidden><code data-atlas-filename="config/services.php">config/services.php</code><button data-atlas-copy aria-label="Copy PHP configuration">Copy</button></section></section>',
                ]);
            }
            if ($route === 'quick-start/') {
                $content .= implode('', [
                    '<h1 id="framework-neutral-quick-start">Framework-Neutral Quick Start</h1>',
                    '<h2 id="pick-your-framework">Pick your framework</h2>',
                    '<p>The starter repositories are still being prepared for that release.</p>',
                    '<a href="https://github.com/johnnickell/project-symfony">Symfony</a>',
                    '<a href="https://github.com/johnnickell/project-laravel">Laravel</a>',
                    '<a href="https://github.com/johnnickell/project-yii">Yii</a>',
                    '<a href="https://github.com/johnnickell/project-codeigniter">CodeIgniter</a>',
                    '<a href="https://github.com/johnnickell/project-slim">Slim</a>',
                    'composer require johnnickell/fight-common',
                    '<h2 id="the-supporting-domain-type">The supporting domain type</h2>',
                    '<h2 id="the-command">The command</h2>',
                    '<h2 id="the-event">The event</h2>',
                    '<h2 id="the-command-handler">The command handler</h2>',
                    '<h2 id="the-follow-up-command">The follow-up command</h2>',
                    '<h2 id="the-event-subscriber">The event subscriber</h2>',
                    '<h2 id="the-fulfillment-handler">The fulfillment handler</h2>',
                    '<h2 id="wire-the-application">Wire the application</h2>',
                    '<h2 id="dispatch-the-command">Dispatch the command</h2>',
                    'Order ORDER-1001 processed for CUSTOMER-42; fulfillment requested.',
                    '<p>InMemoryCommandRouter RoutingCommandBus SimpleEventDispatcher TransactionalUnitOfWork</p>',
                    '<div id="quick-start-order-id" class="highlight"><pre><code>final readonly class OrderId</code></pre></div>',
                    '<div id="quick-start-process-order-command" class="highlight"><pre><code>final readonly class ProcessOrder implements Command</code></pre></div>',
                    '<div id="quick-start-order-processed-event" class="highlight"><pre><code>final readonly class OrderProcessed implements Event</code></pre></div>',
                    '<div id="quick-start-process-order-handler" class="highlight"><pre><code>final readonly class ProcessOrderHandler implements CommandHandler</code></pre></div>',
                    '<div id="quick-start-fulfill-order-command" class="highlight"><pre><code>final readonly class FulfillOrder implements Command</code></pre></div>',
                    '<div id="quick-start-order-processed-subscriber" class="highlight"><pre><code>final readonly class OrderProcessedSubscriber implements EventSubscriber</code></pre></div>',
                    '<div id="quick-start-fulfill-order-handler" class="highlight"><pre><code>final readonly class FulfillOrderHandler implements CommandHandler</code></pre></div>',
                    '<div id="quick-start-composition" class="highlight"><pre><code>final <span class="k">class</span> OrderProcessingExample CustomerId::fromString(<span class="s1">\'CUSTOMER-42\'</span>) require $_SERVER[\'FIGHT_AUTOLOAD\'] ?? __DIR__.\'/vendor/autoload.php\';</code></pre></div><button class="md-code__button" data-md-type="copy" data-clipboard-target="#quick-start-composition code"></button>',
                    '<div id="quick-start-dispatch" class="highlight"><pre><code>OrderProcessingExample::process().PHP_EOL</code></pre></div>',
                    '<script id="__config" type="application/json">{"features":["content.code.copy"]}</script>',
                    '<p>FulfillmentRequester</p>',
                    'EventDispatchFailed PaymentNotSuccessful Only succeeded requests fulfillment. Both pending and failed redelivery or retry of FulfillOrder',
                    '<aside>Production transaction boundary no queue, saga, or durable outbox</aside>',
                    '<a href="../architecture/">Architecture</a>',
                    '<a href="../components/messaging/">Messaging</a>',
                    '<a href="../components/repositories/">Repositories</a>',
                    '<a href="../frameworks/framework-support/">Framework Support</a>',
                ]);
            }
            file_put_contents(
                $this->directory.'/site/'.$route.'/index.html',
                $content,
            );
            $homepage .= '<a href="'.$route.'">'.$route.'</a>';
        }
        file_put_contents(
            $this->directory.'/site/index.html',
            $homepage,
        );
        file_put_contents(
            $this->directory.'/site/404.html',
            '<img class="atlas-not-found__mark" src="/fight-common/assets/identity/fight-mark-dark.svg" alt="Fight"><a href="https://johnnickell.github.io/fight-common/">Return to documentation</a>',
        );
        file_put_contents(
            $this->directory.'/site/guide/index.html',
            '<link rel="canonical" href="https://johnnickell.github.io/fight-common/guide/">',
        );
        file_put_contents($this->directory.'/site/assets/site.css', 'body {}');
        $this->copyDirectory(dirname(__DIR__, 2).'/docs/assets/fonts', $this->directory.'/site/assets/fonts');
        file_put_contents($this->directory.'/site/assets/identity/fight-mark-light.svg', '<svg/>');
        file_put_contents($this->directory.'/site/assets/identity/fight-mark-dark.svg', '<svg/>');
        file_put_contents($this->directory.'/site/assets/identity/fight-common-social-1280x640.png', 'social card');
        file_put_contents($this->directory.'/site/javascripts/atlas-format-tabs.js', 'void 0;');
        file_put_contents(
            $this->directory.'/site/javascripts/atlas-article-accessibility.js',
            'const articleStart = document.querySelector(\'[data-atlas-article-start]\');'
            .'const skip = document.querySelector(\'[data-md-component="skip"] .md-skip\');'
            .'skip.href = `#${articleStart.id}`; articleStart.focus();',
        );
        file_put_contents(
            $this->directory.'/site/stylesheets/extra.css',
            self::requiredGeneratedStylesheet(),
        );
        file_put_contents(
            $this->directory.'/site/search/search_index.json',
            json_encode(['docs' => array_merge([['location' => '']], array_map(
                static fn (string $route): array => ['location' => $route],
                $routes,
            ))], JSON_THROW_ON_ERROR),
        );
        file_put_contents(
            $this->directory.'/site/sitemap.xml',
            '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"><url><loc>https://johnnickell.github.io/fight-common/</loc></url>'
            .implode('', array_map(
                static fn (string $route): string => '<url><loc>https://johnnickell.github.io/fight-common/'.$route.'</loc></url>',
                $routes,
            )).'</urlset>',
        );
    }

    private function copyDirectory(string $source, string $destination): void
    {
        foreach (scandir($source) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $sourcePath = $source.'/'.$entry;
            $destinationPath = $destination.'/'.$entry;
            if (is_dir($sourcePath)) {
                mkdir($destinationPath, 0777, true);
                $this->copyDirectory($sourcePath, $destinationPath);

                continue;
            }
            if (!copy($sourcePath, $destinationPath)) {
                throw new \RuntimeException('Could not copy generated font fixture.');
            }
        }
    }

    /**
     * @return list<string>
     */
    private static function canonicalRoutes(): array
    {
        return [
            'quick-start/', 'architecture/', 'components/values/', 'components/collections/',
            'components/specifications/', 'components/repositories/', 'components/event-sourcing/',
            'components/utilities/', 'components/messaging/', 'components/validation/',
            'components/serialization/', 'components/dependency-injection/', 'components/http-client/',
            'components/auth/', 'components/cache/', 'components/files/', 'components/file-transfer/',
            'components/templating/', 'components/routing/', 'components/mail/', 'components/sms/',
            'components/sockets/', 'components/observability/', 'components/process/', 'components/scheduler/',
            'frameworks/framework-support/', 'frameworks/codeigniter/', 'maintenance/contributing/',
            'maintenance/coding-standard/',
        ];
    }

    private static function requiredGeneratedStylesheet(): string
    {
        return '.atlas-format-tabs__copy { flex: 0 0 44px; inline-size: 44px; min-block-size: 44px; }'
            .' .md-header__button, .md-code__button, .md-clipboard, .md-search__form > .md-search__icon,'
            .' .md-search__options > .md-search__icon { flex: 0 0 44px; inline-size: 44px;'
            .' min-block-size: 44px; min-inline-size: 44px; }'
            .' :root, [data-md-color-scheme="default"] { --fight-footer-surface: #101619;'
            .' --fight-footer-text: #EEF2F3; --fight-footer-link: #FF7A45; --fight-footer-focus: #FF9A72; }'
            .' [data-md-color-scheme="slate"] { --fight-footer-surface: #101619;'
            .' --fight-footer-text: #EEF2F3; --fight-footer-link: #FF7A45; --fight-footer-focus: #FF9A72; }'
            .' .atlas-footer { background-color: var(--fight-footer-surface); color: var(--fight-footer-text); }'
            .' html .atlas-footer .md-footer-meta.md-typeset .atlas-footer__links a { color: var(--fight-footer-link); }'
            .' .atlas-footer a:focus-visible, .atlas-footer button:focus-visible,'
            .' .atlas-footer input:focus-visible, .atlas-footer select:focus-visible,'
            .' .atlas-footer summary:focus-visible, .atlas-footer textarea:focus-visible,'
            .' .atlas-footer [tabindex]:focus-visible { outline-color: var(--fight-footer-focus); }'
            .' html .atlas-footer .md-footer-meta.md-typeset .atlas-footer__links a:focus-visible,'
            .' html .atlas-footer .md-footer-meta.md-typeset .atlas-footer__links a.focus-visible'
            .' { outline-color: var(--fight-footer-focus); }';
    }

    private function validateArtifact(?string $siteDirectory = null): Process
    {
        $process = new Process(
            ['python3', dirname(__DIR__, 2).'/scripts/validate_docs_artifact.py', $siteDirectory ?? $this->directory.'/site'],
            $this->directory,
            null,
            null,
            20,
        );
        $process->run();

        return $process;
    }

    private function removeDirectory(string $directory): void
    {
        foreach (scandir($directory) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $path = $directory.'/'.$entry;
            if (is_link($path) || is_file($path)) {
                unlink($path);
            } else {
                $this->removeDirectory($path);
            }
        }

        rmdir($directory);
    }
}
