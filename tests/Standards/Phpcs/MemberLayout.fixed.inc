<?php

declare(strict_types=1);

namespace Fight\Test\Common\Standards\Phpcs\Fixture;

trait LayoutBehavior
{
}

final class BrokenLayoutSubject
{
	use LayoutBehavior;

	#[FixtureMarker('first')]
	public const string PUBLIC_LATE = 'public';
	#[FixtureMarker('second')]
	public const string PUBLIC_SECOND = 'second';

    private const string PRIVATE_FIRST = 'private';

    #[FixtureMarker('shared')]
    public static string $shared;
    public string $instance;

    protected string $protected;

	public function __construct()
	{
	}

    private function privateFirst(): void
    {
    }
}
