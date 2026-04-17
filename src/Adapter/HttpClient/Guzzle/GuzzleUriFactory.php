<?php

declare(strict_types=1);

namespace Fight\Common\Adapter\HttpClient\Guzzle;

use Fight\Common\Application\HttpClient\Message\UriFactory;
use Fight\Common\Domain\Exception\DomainException;
use GuzzleHttp\Psr7\Utils;
use Psr\Http\Message\UriInterface;
use Throwable;

/**
 * Class GuzzleUriFactory
 */
final class GuzzleUriFactory implements UriFactory
{
    /**
     * @inheritDoc
     */
    public function createUri($uri): UriInterface
    {
        try {
            return Utils::uriFor($uri);
        } catch (Throwable $e) {
            throw new DomainException($e->getMessage(), $e->getCode(), $e);
        }
    }
}
