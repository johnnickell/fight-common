<?php

declare(strict_types=1);

namespace Fight\Test\Common\Standards\Phpcs\Fixture;

final class ExcludedLayoutTest
{
    private string $private;
    public string $public;

    public function first(): void
    {
    }
    public function second(): void
    {
    }
}

final class AnonymousLayoutFactory
{
    public function create(): object
    {
        return new class {
            private const string PRIVATE_VALUE = 'private';
            public const string PUBLIC_VALUE = 'public';

            public function first(): void
            {
            }
            public function second(): void
            {
            }
        };
    }
}

final class AttributeVariableSubject
{
    #[FixtureMarker($attributeValue)]
    public string $first;
    public string $second;
}
