<?php

declare(strict_types=1);

namespace Fight\Common\Adapter\Auth\Hmac;

use Fight\Common\Application\Auth\Authenticator;
use Fight\Common\Application\HttpFoundation\HttpStatus;
use Fight\Common\Application\Auth\Exception\AuthException;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Class HmacAuthenticator
 */
final class HmacAuthenticator implements Authenticator
{
    use HmacMethods;

    private string $secret;
    private static array $requiredHeaders = [
        'Authorization',
        'Credential',
        'Signature',
        'X-Timestamp',
        'X-Nonce'
    ];

    /**
     * Constructs HmacAuthenticator
     */
    public function __construct(private string $public, string $private, private int $timeTolerance)
    {
        $this->secret = hex2bin($private);
    }

    /**
     * @inheritDoc
     */
    public function validate(ServerRequestInterface $request): bool
    {
        $server = $request->getServerParams();

        // validate that required headers are present
        foreach (static::$requiredHeaders as $requiredHeader) {
            if (!$request->hasHeader($requiredHeader)) {
                $message = sprintf('%s is a required header', $requiredHeader);
                throw new AuthException($message, HttpStatus::UNPROCESSABLE_ENTITY);
            }
        }

        // validate that the timestamp is in bounds
        $requestTime = $server['REQUEST_TIME'] ?? time();
        $timestamp = (int) $request->getHeaderLine('X-Timestamp');
        $tolerance = $this->timeTolerance;
        if ($requestTime < $timestamp || $requestTime - $tolerance > $timestamp) {
            throw new AuthException('Timestamp out of bounds', HttpStatus::BAD_REQUEST);
        }

        // validate that the credential matches the public key
        if ($this->public !== $request->getHeaderLine('Credential')) {
            throw new AuthException('Invalid credential', HttpStatus::UNAUTHORIZED);
        }

        // validate that the content matches the content-sha256 hash
        $content = (string) $request->getBody();
        if (!empty($content) && !$request->hasHeader('X-Content-SHA256')) {
            throw new AuthException(
                'X-Content-SHA256 header is required with content',
                HttpStatus::UNPROCESSABLE_ENTITY
            );
        }
        if (!empty($content)) {
            $contentHash = hash('sha256', $content);
            if (!hash_equals($contentHash, $request->getHeaderLine('X-Content-SHA256'))) {
                throw new AuthException('Invalid content hash', HttpStatus::BAD_REQUEST);
            }
        }

        // validate HMAC request signature
        $method = strtoupper($request->getMethod());
        $uri = $this->normalizeUri($request->getUri());

        $authority = $uri->getAuthority();
        $path = $uri->getPath();
        $query = $uri->getQuery();

        $headers = [];
        $headers['X-Timestamp'] = (int) $request->getHeaderLine('X-Timestamp');
        $headers['X-Nonce'] = $request->getHeaderLine('X-Nonce');
        if ($request->hasHeader('X-Content-SHA256')) {
            $headers['X-Content-SHA256'] = $request->getHeaderLine(
                'X-Content-SHA256'
            );
        }

        $canonicalRequest = $this->createCanonicalRequestString(
            $method,
            $authority,
            $path,
            $query,
            $headers
        );

        $signature = $this->createSignature($canonicalRequest, (int) $request->getHeaderLine('X-Timestamp'));

        if (!hash_equals($signature, $request->getHeaderLine('Signature'))) {
            return false;
        }

        return true;
    }

    /**
     * @inheritDoc
     */
    protected function getSecret(): string
    {
        return $this->secret;
    }
}
