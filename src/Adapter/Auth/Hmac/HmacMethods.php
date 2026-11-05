<?php

declare(strict_types=1);

namespace Fight\Common\Adapter\Auth\Hmac;

use Psr\Http\Message\UriInterface;

/**
 * Trait HmacMethods
 */
trait HmacMethods
{
    /**
     * Retrieves the secret key
     */
    abstract protected function getSecret(): string;

    /**
     * Normalizes the URI
     */
    protected function normalizeUri(UriInterface $uri): UriInterface
    {
        $queryString = $uri->getQuery();
        // normalize the query string
        if ($queryString !== '') {
            $parts = [];
            $parameterNames = [];
            foreach (explode('&', $queryString) as $queryParameter) {
                if ('' === $queryParameter) {
                    continue;
                }

                if ('=' === $queryParameter[0]) {
                    continue;
                }

                $parts[] = $queryParameter;
                $parameterParts = explode('=', $queryParameter, 2);
                $parameterNames[] = $parameterParts[0];
            }

            array_multisort($parameterNames, SORT_ASC, $parts);
            $queryString = implode('&', $parts);
            $uri = $uri->withQuery($queryString);
        }

        return $uri;
    }

    /**
     * Creates a canonical request string
     *
     * @param string $method
     * @param string $authority
     * @param string $path
     * @param string $query
     * @param array<string, int|string> $headers
     *
     * @return string
     */
    protected function createCanonicalRequestString(
        string $method,
        string $authority,
        string $path,
        string $query,
        array $headers
    ): string {
        if (empty($path)) {
            $path = '/';
        }

        $headerString = '';
        foreach ($headers as $name => $value) {
            $headerString .= strtolower((string) $name).':'.$value."\n";
        }

        if ($query !== '') {
            $query = '?'.$query;
        }

        return sprintf(
            "%s %s%s%s\n%s",
            $method,
            $authority,
            $path,
            $query,
            $headerString
        );
    }

    /**
     * Creates an HMAC signature
     */
    protected function createSignature(string $canonicalRequest, int $timestamp): string
    {
        $requestHash = hash('sha256', $canonicalRequest);

        $stringToSign = sprintf(
            "HMAC-SHA256\n%d\n%s",
            $timestamp,
            $requestHash
        );

        $dateKey = hash_hmac(
            'sha256',
            (string) $timestamp,
            'HMAC'.$this->getSecret(),
            true
        );

        $signingKey = hash_hmac(
            'sha256',
            'signed-request',
            $dateKey,
            true
        );

        return hash_hmac('sha256', $stringToSign, $signingKey);
    }
}
