<?php

declare(strict_types=1);

namespace Fight\Common\Adapter\HttpClient\Guzzle;

use Fight\Common\Application\HttpClient\Message\StreamFactory;
use Fight\Common\Domain\Exception\DomainException;
use GuzzleHttp\Psr7\Utils;
use Psr\Http\Message\StreamInterface;
use Throwable;

/**
 * Class GuzzleStreamFactory
 */
final class GuzzleStreamFactory implements StreamFactory
{
    /**
     * @inheritDoc
     */
    public function createStream(mixed $body = null): StreamInterface
    {
        try {
            return Utils::streamFor($body);
        } catch (Throwable $e) {
            throw new DomainException($e->getMessage(), $e->getCode(), $e);
        }
    }
}
