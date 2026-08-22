<?php

declare(strict_types=1);

namespace Fight\Release\Adapter;

use Fight\Release\Application\ReleaseTool;

final class ReleaseAdapter
{
    public function __construct(public ReleaseTool $releaseTool)
    {
    }
}
