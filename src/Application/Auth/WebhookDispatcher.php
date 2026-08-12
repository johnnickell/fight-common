<?php

declare(strict_types=1);

namespace Fight\Common\Application\Auth;

use Fight\Common\Application\Auth\Exception\CredentialsException;
use Fight\Common\Application\HttpClient\Exception\Exception as HttpException;

/**
 * Interface WebhookDispatcher
 */
interface WebhookDispatcher
{
     /**
      * Authenticates and dispatches an outbound webhook by signing its request
      *
      * @param string $url
      * @param string $action
      * @param array<string, mixed> $payload
      *
      * @throws CredentialsException When signing fails
      * @throws HttpException When the HTTP request fails
      */
    public function dispatch(string $url, string $action, array $payload = []): void;
}
