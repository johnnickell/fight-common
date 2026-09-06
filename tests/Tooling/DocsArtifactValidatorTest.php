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
        mkdir($this->directory.'/site/guide', 0777, true);
        mkdir($this->directory.'/site/search', 0777, true);
        file_put_contents(
            $this->directory.'/site/index.html',
            '<link rel="canonical" href="https://johnnickell.github.io/fight-common/">'
            .'<link rel="stylesheet" href="/fight-common/assets/site.css?cache=1#version">',
        );
        file_put_contents(
            $this->directory.'/site/404.html',
            '<a href="https://johnnickell.github.io/fight-common/">Return to documentation</a>',
        );
        file_put_contents(
            $this->directory.'/site/guide/index.html',
            '<link rel="canonical" href="https://johnnickell.github.io/fight-common/guide/">',
        );
        file_put_contents($this->directory.'/site/assets/site.css', 'body {}');
        file_put_contents($this->directory.'/site/search/search_index.json', '{"docs":[{"location":""},{"location":"guide/#heading"}]}');
        file_put_contents(
            $this->directory.'/site/sitemap.xml',
            '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"><url><loc>https://johnnickell.github.io/fight-common/</loc></url><url><loc>https://johnnickell.github.io/fight-common/guide/</loc></url></urlset>',
        );
    }

    private function validateArtifact(): Process
    {
        $process = new Process(
            ['python3', dirname(__DIR__, 2).'/scripts/validate_docs_artifact.py', $this->directory.'/site'],
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
