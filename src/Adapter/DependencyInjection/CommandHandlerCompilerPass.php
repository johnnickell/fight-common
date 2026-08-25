<?php

declare(strict_types=1);

namespace Fight\Common\Adapter\DependencyInjection;

use Fight\Common\Adapter\ServiceContainer\Symfony\CommandHandlerCompilerPass as CanonicalCommandHandlerCompilerPass;

/**
 * Class CommandHandlerCompilerPass
 *
 * @deprecated since 1.2.0, use Fight\Common\Adapter\ServiceContainer\Symfony\CommandHandlerCompilerPass
 */
final class CommandHandlerCompilerPass extends CanonicalCommandHandlerCompilerPass
{
}
