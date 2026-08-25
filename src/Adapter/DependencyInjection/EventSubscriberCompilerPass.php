<?php

declare(strict_types=1);

namespace Fight\Common\Adapter\DependencyInjection;

use Fight\Common\Adapter\ServiceContainer\Symfony\EventSubscriberCompilerPass as CanonicalEventSubscriberCompilerPass;

/**
 * Class EventSubscriberCompilerPass
 *
 * @deprecated since 1.2.0, use Fight\Common\Adapter\ServiceContainer\Symfony\EventSubscriberCompilerPass
 */
final class EventSubscriberCompilerPass extends CanonicalEventSubscriberCompilerPass
{
}
