<?php

declare(strict_types=1);

namespace Fight\Common\Adapter\Http\Mcp;

use Fight\Common\Application\Mcp\McpCapabilityRegistry;
use Fight\Common\Application\Mcp\McpProtocolError;
use Fight\Common\Application\Mcp\McpProtocolException;
use Fight\Common\Application\Mcp\McpRequest;
use Fight\Common\Application\Mcp\McpRequestMirrors;
use Fight\Common\Domain\Exception\DomainException;
use Fight\Common\Domain\Value\Basic\StrictJson;

/**
 * Class McpHeaderValidator
 */
final readonly class McpHeaderValidator
{
    /**
     * Constructs McpHeaderValidator
     */
    public function __construct(private McpCapabilityRegistry $registry)
    {
    }

    /**
     * Validates every standard and registered custom mirror before dispatch
     *
     * Returns only the validated standard operation name, never raw arguments.
     *
     * @param McpRequest                 $request
     * @param array<string, list<string>> $headers
     */
    public function validate(McpRequest $request, array $headers): ?string
    {
        $normalized = [];
        foreach ($headers as $name => $values) {
            $key = strtolower($name);
            $normalized[$key] = [...($normalized[$key] ?? []), ...$values];
        }

        $this->match($normalized, 'mcp-protocol-version', $request->metadata()->protocolVersion(), false);
        $this->match($normalized, 'mcp-method', $request->method(), false);

        $name = null;
        $property = match ($request->method()) {
            'tools/call', 'prompts/get' => 'name',
            'resources/read' => 'uri',
            default => null,
        };
        if ($property !== null) {
            $name = $request->parameters()[$property] ?? null;
            if (!is_string($name)) {
                throw new McpProtocolException(McpProtocolError::headerMismatch(), $request->id());
            }
        }

        $this->match($normalized, 'mcp-name', $name);
        $declarations = $this->registry->mirrorDeclarationsFor($request->method());
        $capability = $this->registry->capabilityFor($request->method());
        if ($capability instanceof McpRequestMirrors) {
            $declarations = [...$declarations, ...$capability->mirrorsFor($request)];
        }

        $seen = [];
        foreach ($declarations as $declaration) {
            $header = strtolower($declaration->headerName());
            if ($declaration->method() !== $request->method() || isset($seen[$header])) {
                throw new DomainException(
                    'Request mirrors must have unique headers and belong to the selected method.'
                );
            }

            $seen[$header] = true;
            $value = StrictJson::fromObject($request->parameters(), maxDepth: 511);
            foreach ($declaration->parameterPath() as $segment) {
                $value = $value instanceof StrictJson ? $value->get($segment) : null;
            }

            $this->match($normalized, 'mcp-param-'.strtolower($declaration->headerName()), $value);
        }

        return $name;
    }

    /**
     * Validates a single mirror without reflecting potentially sensitive values
     *
     * @param array<string, list<string>> $headers
     */
    private function match(array $headers, string $name, mixed $value, bool $encoded = true): void
    {
        if ($value === null && !array_key_exists($name, $headers)) {
            return;
        }

        $values = $headers[$name] ?? [];
        if ($value === null || count($values) !== 1) {
            throw new McpProtocolException(McpProtocolError::headerMismatch(), null);
        }

        $header = $this->decode($values[0], $encoded);
        $matches = match (true) {
            is_string($value) => $header === $value,
            is_bool($value) => $header === ($value ? 'true' : 'false'),
            is_int($value) => abs($value) <= 9007199254740991 && $this->matchesInteger($header, $value),
            is_float($value) => is_finite($value) && floor($value) === $value
                && abs($value) <= 9007199254740991 && $this->matchesInteger($header, (int) $value),
            default => false,
        };
        if (!$matches) {
            throw new McpProtocolException(McpProtocolError::headerMismatch(), null);
        }
    }

    /**
     * Decodes safe ASCII or the case-sensitive UTF-8 Base64 sentinel
     */
    private function decode(string $value, bool $encoded): string
    {
        if (preg_match('/[^\x09\x20-\x7e]/', $value) === 1 || trim($value, " \t") !== $value) {
            throw new McpProtocolException(McpProtocolError::headerMismatch(), null);
        }

        if ($encoded && str_starts_with($value, '=?base64?') && str_ends_with($value, '?=')) {
            $payload = substr($value, 9, -2);
            $decoded = base64_decode($payload, true);
            if ($decoded === false || base64_encode($decoded) !== $payload || preg_match('//u', $decoded) !== 1) {
                throw new McpProtocolException(McpProtocolError::headerMismatch(), null);
            }

            return $decoded;
        }

        return $value;
    }

    /**
     * Validates decimal numeric equality without floating-point rounding
     */
    private function matchesInteger(string $header, int $value): bool
    {
        if (preg_match('/\A(-?)(0|[1-9][0-9]*)(?:\.([0-9]+))?(?:[eE]([+-]?[0-9]+))?\z/', $header, $parts) !== 1) {
            return false;
        }

        $fraction = $parts[3] ?? '';
        $digits = ltrim($parts[2].$fraction, '0');
        if ($digits === '') {
            return $value === 0;
        }

        $exponent = (int) ($parts[4] ?? '0');
        if ($exponent > strlen($fraction) + 16 || $exponent < -strlen($digits)) {
            return false;
        }

        $scale = strlen($fraction) - $exponent;
        if ($scale > 0) {
            if ($scale >= strlen($digits) || trim(substr($digits, -$scale), '0') !== '') {
                return false;
            }

            $digits = substr($digits, 0, -$scale);
        } else {
            $digits .= str_repeat('0', -$scale);
        }

        return $parts[1].$digits === (string) $value;
    }
}
