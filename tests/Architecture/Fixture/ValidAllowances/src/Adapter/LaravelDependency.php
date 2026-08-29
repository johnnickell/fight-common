<?php

declare(strict_types=1);

namespace Fight\Common\Adapter;

use Illuminate\Contracts\Queue\ShouldQueue;

final readonly class LaravelDependency
{
    public function __construct(private ShouldQueue $job)
    {
    }
}
