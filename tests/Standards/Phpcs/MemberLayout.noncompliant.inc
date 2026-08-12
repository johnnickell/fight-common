<?php

declare(strict_types=1);

namespace Fight\Test\Common\Standards\Phpcs\Fixture;

trait LayoutBehavior
{
}

final class BrokenLayoutSubject
{
    private const string PRIVATE_FIRST = 'private';
    #[FixtureMarker('first')]
    public const string PUBLIC_LATE = 'public';
    #[FixtureMarker('second')]
    public const string PUBLIC_SECOND = 'second';
    #[FixtureMarker('shared')]
    public static string $shared;

    public string $instance;
    protected string $protected;

    use LayoutBehavior;

    private function privateFirst(): void
    {
    }


    public function __construct()
    {
    }
}
