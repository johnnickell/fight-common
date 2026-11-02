<?php

declare(strict_types=1);

namespace Fight\Common\Application\Mcp\Protocol;

/**
 * Class McpTraceContextGrammar
 *
 * @internal Syntax for reserved request trace context, not a consumer validator or trust decision
 */
final class McpTraceContextGrammar
{
    /**
     * Returns whether a reserved trace context value has its W3C-defined format
     */
    public static function hasValidValue(string $name, mixed $value): bool
    {
        return match ($name) {
            'traceparent' => self::hasValidTraceparent($value),
            'tracestate' => self::hasValidTracestate($value),
            'baggage' => self::hasValidBaggage($value),
            default => false,
        };
    }

    /**
     * Returns whether a traceparent has a valid W3C trace-context representation
     */
    private static function hasValidTraceparent(mixed $value): bool
    {
        if (
            !is_string($value)
            || preg_match(
                '/^([0-9a-f]{2})-([0-9a-f]{32})-([0-9a-f]{16})-([0-9a-f]{2})(?:-([\\x21-\\x7e]+))?$/',
                $value,
                $matches
            ) !== 1
        ) {
            return false;
        }

        return $matches[1] !== 'ff'
            && $matches[2] !== str_repeat('0', 32)
            && $matches[3] !== str_repeat('0', 16)
            && ($matches[1] !== '00' || !isset($matches[5]));
    }

    /**
     * Returns whether a tracestate has valid W3C list members
     */
    private static function hasValidTracestate(mixed $value): bool
    {
        if (!is_string($value)) {
            return false;
        }

        $members = explode(',', $value);
        if (count($members) > 32) {
            return false;
        }

        $standardKey = '[a-z][a-z0-9_\\-*\\/]{0,255}';
        $tenantKey = '[a-z0-9][a-z0-9_\\-*\\/]{0,240}@[a-z][a-z0-9_\\-*\\/]{0,13}';
        $keys = [];
        foreach ($members as $member) {
            $member = trim($member, " \t");
            if ($member === '') {
                continue;
            }

            if (
                preg_match(
                    sprintf('/^((?:%s)|(?:%s))=(.*)$/', $standardKey, $tenantKey),
                    $member,
                    $matches
                ) !== 1
                || strlen($matches[2]) > 256
                || preg_match(
                    '/^[\\x20-\\x2b\\x2d-\\x3c\\x3e-\\x7e]*[\\x21-\\x2b\\x2d-\\x3c\\x3e-\\x7e]$/',
                    $matches[2]
                ) !== 1
                || isset($keys[$matches[1]])
            ) {
                return false;
            }

            $keys[$matches[1]] = true;
        }

        return true;
    }

    /**
     * Returns whether baggage has valid W3C members and optional properties
     */
    private static function hasValidBaggage(mixed $value): bool
    {
        if (!is_string($value)) {
            return false;
        }

        $members = explode(',', $value);
        if (count($members) > 180) {
            return false;
        }

        return array_all($members, static function (string $member): bool {
            $parts = explode(';', $member);
            $entry = array_shift($parts);
            if (!self::hasValidBaggagePair($entry)) {
                return false;
            }

            return array_all($parts, static fn(string $property): bool => self::hasValidBaggageProperty($property));
        });
    }

    /**
     * Returns whether one baggage member is a valid key/value pair
     */
    private static function hasValidBaggagePair(string $pair): bool
    {
        $separator = strpos($pair, '=');
        if ($separator === false) {
            return false;
        }

        $key = trim(substr($pair, 0, $separator), " \t");
        $value = trim(substr($pair, $separator + 1), " \t");

        return self::isHttpToken($key) && self::hasValidBaggageValue($value);
    }

    /**
     * Returns whether an optional baggage property is valid
     */
    private static function hasValidBaggageProperty(string $property): bool
    {
        if (!str_contains($property, '=')) {
            return self::isHttpToken(trim($property, " \t"));
        }

        return self::hasValidBaggagePair($property);
    }

    /**
     * Returns whether a baggage value contains only permitted octets
     */
    private static function hasValidBaggageValue(string $value): bool
    {
        return preg_match('/^[\\x21\\x23-\\x5b\\x5d-\\x7e]*$/', $value) === 1
            && preg_match('/%(?![0-9A-Fa-f]{2})/', $value) !== 1;
    }

    /**
     * Returns whether a value is an HTTP token
     */
    private static function isHttpToken(string $value): bool
    {
        return preg_match("/^[!#$%&'*+.^_`|~0-9A-Za-z-]+$/", $value) === 1;
    }
}
