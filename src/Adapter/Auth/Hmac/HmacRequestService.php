<?php

declare(strict_types=1);

namespace Fight\Common\Adapter\Auth\Hmac;

use Closure;
use Exception;
use Fight\Common\Application\Auth\Exception\CredentialsException;
use Fight\Common\Application\Auth\RequestService;
use Psr\Http\Message\RequestInterface;

/**
 * Class HmacRequestService
 */
final class HmacRequestService implements RequestService
{
    use HmacMethods;

    private string $secret;

    /**
     * Constructs HmacRequestService
     *
     * @param string                     $public         Public credential.
     * @param string                     $private        Hex-encoded private key.
     * @param Closure(int): string|null $nonceGenerator
     */
    public function __construct(
        private readonly string $public,
        string $private,
        private readonly ?Closure $nonceGenerator = null
    ) {
        $this->secret = hex2bin($private);
    }

    /**
     * @inheritDoc
     */
    public function signRequest(RequestInterface $request): RequestInterface
    {
        $method = strtoupper($request->getMethod());
        $uri = $this->normalizeUri($request->getUri());
        $request = $request->withUri($uri);

        $authority = $uri->getAuthority();
        $path = $uri->getPath();
        $query = $uri->getQuery();

        $content = (string) $request->getBody();
        $timestamp = time();

        try {
            $headers = $this->buildHeaders($timestamp, $content);
        } catch (Exception $exception) {
            throw new CredentialsException($exception->getMessage(), $exception->getCode(), $exception);
        }

        $canonicalRequest = $this->createCanonicalRequestString(
            $method,
            $authority,
            $path,
            $query,
            $headers
        );

        $headers['Authorization'] = 'HMAC-SHA256';
        $headers['Credential'] = $this->public;
        $headers['Signature'] = $this->createSignature(
            $canonicalRequest,
            $timestamp
        );

        ksort($headers);

        foreach ($headers as $name => $value) {
            $request = $request->withHeader($name, $value);
        }

        return $request;
    }

    /**
     * @inheritDoc
     */
    protected function getSecret(): string
    {
        return $this->secret;
    }

    /**
     * Builds standard headers
     *
     * @return array<string, string>
     *
     * @throws Exception
     */
    private function buildHeaders(int $timestamp, string $content): array
    {
        $headers = [];

        $headers['X-Timestamp'] = (string) $timestamp;
        if ($this->nonceGenerator instanceof Closure) {
            $headers['X-Nonce'] = ($this->nonceGenerator)(8);
        } else {
            $headers['X-Nonce'] = HmacKeyGenerator::generateSecureRandom(8);
        }

        if ($content !== '') {
            $contentHash = hash('sha256', $content);
            $headers['X-Content-SHA256'] = $contentHash;
        }

        return $headers;
    }
}
