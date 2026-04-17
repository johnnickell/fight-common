<?php

declare(strict_types=1);

namespace Fight\Common\Application\HttpClient\Transport;

use Fight\Common\Application\HttpClient\Exception\Exception;
use Fight\Common\Application\HttpClient\Message\Promise;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Interface HttpClient
 */
interface HttpClient
{
    /**
     * Sends a request
     *
     * @throws Exception When an error occurs
     */
    public function send(RequestInterface $request, array $options = []): ResponseInterface;

    /**
     * Sends a request asynchronously with options
     *
     * @throws Exception When an error occurs
     */
    public function sendAsync(RequestInterface $request, array $options = []): Promise;
}
