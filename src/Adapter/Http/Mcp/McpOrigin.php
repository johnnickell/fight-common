<?php

declare(strict_types=1);

namespace Fight\Common\Adapter\Http\Mcp;

use Fight\Common\Domain\Exception\DomainException;

/**
 * Class McpOrigin
 */
final readonly class McpOrigin
{
    /**
     * Constructs McpOrigin
     */
    private function __construct(private string $value)
    {
    }

    /**
     * Creates an exact HTTP origin without URL paths or proxy inference
     */
    public static function fromString(string $origin): self
    {
        if (preg_match('~\A(https?)://(\[[0-9a-f:.]+\]|[a-z0-9.-]+)(?::([0-9]{1,5}))?\z~i', $origin, $parts) !== 1) {
            throw new DomainException('An MCP origin must be an exact HTTP scheme, host and optional port.');
        }

        $scheme = strtolower($parts[1]);
        $host = strtolower($parts[2]);
        if (str_starts_with($host, '[')) {
            $address = substr($host, 1, -1);
            if (filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) === false) {
                throw new DomainException('An MCP origin requires a valid IPv6 host.');
            }

            $host = '['.inet_ntop(inet_pton($address)).']';
        } else {
            $label = '[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?';
            if (strlen($host) > 253 || preg_match('/\A'.$label.'(?:\.'.$label.')*\.?\z/', $host) !== 1) {
                throw new DomainException('An MCP origin requires a valid host.');
            }
        }

        $port = $scheme === 'https' ? 443 : 80;
        if (isset($parts[3])) {
            $port = (int) $parts[3];
            if ($port < 1 || $port > 65535) {
                throw new DomainException('An MCP origin requires a valid port.');
            }
        }

        return new self($scheme.'://'.$host.':'.$port);
    }

    /**
     * Returns the canonical scheme-host-port origin
     */
    public function toString(): string
    {
        return $this->value;
    }
}
