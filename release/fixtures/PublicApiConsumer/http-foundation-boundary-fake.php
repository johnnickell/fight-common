<?php

declare(strict_types=1);

namespace Symfony\Component\HttpFoundation;

/**
 * Minimal disposable-consumer boundary fake for the optional HttpFoundation package.
 */
final class HeaderBag
{
    /** @var array<string, list<string>> */
    private array $headers = [];

    /** @param array<string, string|string[]> $headers */
    public function __construct(array $headers = [])
    {
        foreach ($headers as $name => $value) {
            $this->set($name, $value);
        }
    }

    /** @param string|string[] $value */
    public function set(string $name, string|array $value): void
    {
        $this->headers[strtolower($name)] = array_values((array) $value);
    }

    public function get(string $name): ?string
    {
        return $this->headers[strtolower($name)][0] ?? null;
    }

    /** @return array<string, list<string>> */
    public function all(): array
    {
        return $this->headers;
    }
}

/**
 * Reproduces only the public JsonResponse seam exercised by the installed-package probe.
 */
class JsonResponse
{
    public const int HTTP_OK = 200;
    public const int HTTP_ACCEPTED = 202;
    public const int HTTP_BAD_REQUEST = 400;
    public const int HTTP_UNPROCESSABLE_ENTITY = 422;
    public const int HTTP_INTERNAL_SERVER_ERROR = 500;
    public const int HTTP_BAD_GATEWAY = 502;

    protected string $data;
    public HeaderBag $headers;
    private int $encodingOptions = 15;

    /** @param array<string, string|string[]> $headers */
    public function __construct(
        mixed $data = null,
        private int $statusCode = self::HTTP_OK,
        array $headers = [],
        bool $json = false
    ) {
        $this->headers = new HeaderBag($headers);
        $this->data = $json ? (string) $data : json_encode($data, $this->encodingOptions, 512);
    }

    public function setEncodingOptions(int $encodingOptions): static
    {
        $decoded = json_decode($this->data, true, flags: JSON_THROW_ON_ERROR);
        $this->encodingOptions = $encodingOptions;
        $this->data = json_encode($decoded, $encodingOptions, 512);

        return $this;
    }

    public function getEncodingOptions(): int
    {
        return $this->encodingOptions;
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function getContent(): string
    {
        return $this->data;
    }
}
