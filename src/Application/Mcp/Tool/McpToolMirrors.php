<?php

declare(strict_types=1);

namespace Fight\Common\Application\Mcp\Tool;

use Fight\Common\Application\Mcp\McpMirrorDeclaration;
use Fight\Common\Domain\Exception\DomainException;
use stdClass;

/**
 * Class McpToolMirrors
 *
 * @internal
 */
final class McpToolMirrors
{
    /**
     * Creates validated input-schema mirror declarations for one Tool
     *
     * @return list<McpMirrorDeclaration>
     */
    public static function declarations(stdClass $schema): array
    {
        $declarations = [];
        self::collect($schema, ['arguments'], true, $declarations);

        return array_values($declarations);
    }

    /**
     * Collects only statically reachable primitive property annotations
     *
     * @phpstan-param list<string> $path
     * @phpstan-param array<string, McpMirrorDeclaration> $declarations
     */
    private static function collect(stdClass|bool $schema, array $path, bool $reachable, array &$declarations): void
    {
        if (is_bool($schema)) {
            return;
        }

        if (isset($schema->{'x-mcp-header'})) {
            $header = $schema->{'x-mcp-header'};
            $types = (array) ($schema->type ?? []);
            if (
                !$reachable || count($path) < 2 || $types === []
                || array_diff($types, ['string', 'integer', 'boolean', 'null']) !== []
                || array_diff($types, ['null']) === []
                || isset($declarations[strtolower($header)])
            ) {
                throw new DomainException('Tool mirrors require unique headers on reachable primitive properties.');
            }

            $declarations[strtolower($header)] = new McpMirrorDeclaration('tools/call', $path, $header);
        }

        foreach (get_object_vars($schema) as $keyword => $value) {
            if ($keyword === 'properties' || $keyword === '$defs') {
                foreach (get_object_vars($value) as $property => $child) {
                    self::collect(
                        $child,
                        [...$path, (string) $property],
                        $reachable && $keyword === 'properties',
                        $declarations
                    );
                }
            } elseif (in_array($keyword, ['items', 'additionalProperties', 'not'], true)) {
                self::collect($value, $path, false, $declarations);
            } elseif (in_array($keyword, ['allOf', 'anyOf', 'oneOf'], true)) {
                foreach ($value as $child) {
                    self::collect($child, $path, false, $declarations);
                }
            }
        }
    }
}
