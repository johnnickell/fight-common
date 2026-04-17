<?php

declare(strict_types=1);

namespace Fight\Common\Application\Service\Exception;

use Exception;
use Psr\Container\NotFoundExceptionInterface;

/**
 * Class NotFoundException
 */
class NotFoundException extends Exception implements NotFoundExceptionInterface
{
}
