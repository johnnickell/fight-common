<?php

declare(strict_types=1);

namespace Fight\Common\Adapter\EventSubscriber;

use Fight\Common\Adapter\Http\Symfony\EventSubscriber\SymfonyValidationSubscriber as CanonicalSubscriber;

/**
 * Class SymfonyValidationSubscriber
 *
 * @deprecated since 1.2.0, use Fight\Common\Adapter\Http\Symfony\EventSubscriber\SymfonyValidationSubscriber
 */
final readonly class SymfonyValidationSubscriber extends CanonicalSubscriber
{
}
