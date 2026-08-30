<?php

declare(strict_types=1);

namespace Fight\Test\Common\TestCase\Routing;

use Fight\Common\Application\Routing\Exception\RouteNotFoundException;
use Fight\Common\Application\Routing\Exception\UrlGenerationException;
use Fight\Common\Application\Routing\UrlGenerator;
use Fight\Test\Common\TestCase\UnitTestCase;

/**
 * Defines common observable URL-generator behavior for framework adapters.
 */
abstract class UrlGeneratorConformanceTestCase extends UnitTestCase
{
    /**
     * Creates the configured URL generator.
     */
    abstract protected function urlGenerator(): UrlGenerator;

    /**
     * Returns the expected relative URL for the common route fixture.
     */
    abstract protected function expectedRelativeUrl(): string;

    /**
     * Returns the expected absolute URL for the common route fixture.
     */
    abstract protected function expectedAbsoluteUrl(): string;

    public function test_that_generate_returns_a_relative_named_route_with_arguments_and_separate_query_values(): void
    {
        self::assertSame(
            $this->expectedRelativeUrl(),
            $this->urlGenerator()->generate('account.show', ['id' => 42], ['view' => 'summary'])
        );
    }

    public function test_that_generate_returns_an_absolute_named_route_with_arguments(): void
    {
        self::assertSame(
            $this->expectedAbsoluteUrl(),
            $this->urlGenerator()->generate('account.show', ['id' => 42], absolute: true)
        );
    }

    public function test_that_generate_translates_a_missing_named_route(): void
    {
        $this->expectException(RouteNotFoundException::class);

        $this->urlGenerator()->generate('missing');
    }

    public function test_that_generate_rejects_missing_required_route_arguments(): void
    {
        $this->expectException(UrlGenerationException::class);

        $this->urlGenerator()->generate('account.show');
    }

    public function test_that_generate_rejects_invalid_constrained_route_arguments(): void
    {
        $this->expectException(UrlGenerationException::class);

        $this->urlGenerator()->generate('account.show', ['id' => 'not-an-integer']);
    }
}
