<?php

declare(strict_types=1);

namespace Fight\Common\Application;

use Cron\FieldFactory;

final readonly class ApplicationDependsOnCronFieldFactory
{
    public function __construct(private FieldFactory $fieldFactory) {}
}
