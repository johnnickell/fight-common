<?php

declare(strict_types=1);

namespace Fight\Common\Adapter\HttpKernel;

use Fight\Common\Adapter\Middleware\Symfony\JsonRequestMiddleware as CanonicalMiddleware;

/**
 * Class JsonRequestMiddleware
 *
 * @deprecated since 1.2.0, use Fight\Common\Adapter\Middleware\Symfony\JsonRequestMiddleware
 */
final readonly class JsonRequestMiddleware extends CanonicalMiddleware
{
}
