<?php

declare(strict_types=1);

namespace Fight\Common\Adapter\DependencyInjection;

use Fight\Common\Adapter\ServiceContainer\Symfony\EventMappingProviderCompilerPass as
    CanonicalEventMappingProviderCompilerPass;

/**
 * Class EventMappingProviderCompilerPass
 *
 * @deprecated since 1.2.0, use Fight\Common\Adapter\ServiceContainer\Symfony\EventMappingProviderCompilerPass
 */
final class EventMappingProviderCompilerPass extends CanonicalEventMappingProviderCompilerPass
{
}
