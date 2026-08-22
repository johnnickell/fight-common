<?php

declare(strict_types=1);

namespace Fight\Common\Application;

use Fight\Release\Application\ReleaseTool;

final class RuntimeConsumer
{
    public function __construct(public ReleaseTool $releaseTool)
    {
    }
}
