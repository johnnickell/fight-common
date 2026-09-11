<?php

declare(strict_types=1);

namespace Fight\Common\Adapter\HttpClient\Psr18\Exception;

use Psr\Http\Client\ClientExceptionInterface;
use RuntimeException;

/**
 * Class ClientException
 */
class ClientException extends RuntimeException implements ClientExceptionInterface
{
}
