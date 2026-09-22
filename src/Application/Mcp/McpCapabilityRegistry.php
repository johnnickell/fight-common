<?php

declare(strict_types=1);

namespace Fight\Common\Application\Mcp;

use Fight\Common\Domain\Exception\DomainException;
use JsonException;
use stdClass;

/**
 * Class McpCapabilityRegistry
 */
final readonly class McpCapabilityRegistry
{
    /**
     * @var array<string, string>
     */
    private const array STANDARD_METHOD_CAPABILITY_NAMES = [
        'completion/complete'      => 'completions',
        'prompts/get'              => 'prompts',
        'prompts/list'             => 'prompts',
        'resources/list'           => 'resources',
        'resources/read'           => 'resources',
        'resources/templates/list' => 'resources',
        'tools/call'               => 'tools',
        'tools/list'               => 'tools'
    ];
    /**
     * @var array<string, string>
     */
    private const array STANDARD_CAPABILITY_MANDATORY_METHODS = [
        'completions' => 'completion/complete',
        'prompts'     => 'prompts/list',
        'resources'   => 'resources/list',
        'tools'       => 'tools/list'
    ];
    /**
     * @var array<string, list<string>>
     */
    private const array SUBSCRIPTION_CAPABILITY_PROPERTIES = [
        'prompts'   => ['listChanged'],
        'resources' => ['subscribe', 'listChanged'],
        'tools'     => ['listChanged']
    ];

    /**
     * @var array<string, McpCapability>
     */
    private array $capabilitiesByMethod;
    /**
     * @var array<string, array<mixed>>
     */
    private array $advertisedCapabilities;
    /**
     * @var array<string, list<McpMirrorDeclaration>>
     */
    private array $mirrorDeclarationsByMethod;

    /**
     * Constructs McpCapabilityRegistry
     *
     * @param McpServerInfo|null  $serverInfo
     * @param array               $capabilities
     *
     * @phpstan-param array<array-key, mixed> $capabilities
     */
    public function __construct(private ?McpServerInfo $serverInfo, array $capabilities)
    {
        if ($this->serverInfo === null) {
            throw new DomainException('An MCP server identity is required.');
        }

        $capabilitiesByMethod = [];
        /**
         * @var array<string, array<mixed>> $advertisedCapabilities
         */
        $advertisedCapabilities = [];
        $mirrorDeclarationsByMethod = [];

        foreach ($capabilities as $capability) {
            if (!$capability instanceof McpCapability) {
                throw new DomainException('Every MCP capability must implement McpCapability.');
            }

            $methods = $this->methodsFor($capability);
            $this->registerMethods($capability, $methods, $capabilitiesByMethod);
            $capabilityNames = $this->registerCapabilities($capability, $advertisedCapabilities);
            $this->validateStandardMethodCapabilities($methods, $capabilityNames);
            $this->registerMirrorDeclarations($capability, $methods, $mirrorDeclarationsByMethod);
        }

        $this->validateStandardCapabilityMandatoryMethods($capabilitiesByMethod, $advertisedCapabilities);
        $this->validateSubscriptionCapabilityFlags($advertisedCapabilities);

        $this->capabilitiesByMethod = $capabilitiesByMethod;
        $this->advertisedCapabilities = $advertisedCapabilities;
        $this->mirrorDeclarationsByMethod = $mirrorDeclarationsByMethod;
    }

    /**
     * Returns the configured server identity
     */
    public function serverInfo(): McpServerInfo
    {
        return $this->serverInfo;
    }

    /**
     * Returns only capability definitions supplied by registered capabilities
     *
     * @return array<string, array<mixed>>
     */
    public function advertisedCapabilities(): array
    {
        return $this->advertisedCapabilities;
    }

    /**
     * Retrieves the capability that owns an MCP method
     */
    public function capabilityFor(string $method): ?McpCapability
    {
        return $this->capabilitiesByMethod[$method] ?? null;
    }

    /**
     * Returns custom parameter mirror declarations for one method
     *
     * @return list<McpMirrorDeclaration>
     */
    public function mirrorDeclarationsFor(string $method): array
    {
        return $this->mirrorDeclarationsByMethod[$method] ?? [];
    }

    /**
     * Returns a capability's non-empty method registration
     *
     * @return array
     *
     * @phpstan-return array<array-key, mixed>
     */
    private function methodsFor(McpCapability $capability): array
    {
        /**
         * @var array<array-key, mixed> $methods
         */
        $methods = $capability->methods();
        if ($methods === []) {
            throw new DomainException('An MCP capability must register at least one handler method.');
        }

        return $methods;
    }

    /**
     * Registers capability-owned methods
     *
     * @param McpCapability                $capability
     * @param array                         $methods
     * @param array<string, McpCapability> $capabilitiesByMethod
     *
     * @phpstan-param array<array-key, mixed> $methods
     */
    private function registerMethods(McpCapability $capability, array $methods, array &$capabilitiesByMethod): void
    {
        foreach ($methods as $method) {
            if (!is_string($method) || trim($method) === '') {
                throw new DomainException('An MCP capability method must be a non-empty string.');
            }

            if ($method === McpResponder::DISCOVER_METHOD) {
                throw new DomainException('The server/discover method is reserved for the MCP responder.');
            }

            if (array_key_exists($method, $capabilitiesByMethod)) {
                throw new DomainException(sprintf('The MCP method "%s" has more than one capability owner.', $method));
            }

            $capabilitiesByMethod[$method] = $capability;
        }
    }

    /**
     * Registers truthful capability definitions
     *
     * @param McpCapability               $capability
     * @param array<string, array<mixed>> $advertisedCapabilities
     *
     * @return list<string>
     */
    private function registerCapabilities(McpCapability $capability, array &$advertisedCapabilities): array
    {
        /**
         * @var array<array-key, mixed> $capabilities
         */
        $capabilities = $capability->capabilities();
        if ($capabilities === []) {
            throw new DomainException(
                'An MCP capability with registered handler methods must advertise at least one capability.'
            );
        }

        $capabilityNames = [];
        foreach ($capabilities as $name => $definition) {
            if (
                !is_string($name)
                || trim($name) === ''
                || !is_array($definition)
                || ($definition !== [] && array_is_list($definition))
                || !$this->isJsonEncodable($name)
            ) {
                throw new DomainException(
                    'An MCP capability declaration must have a non-empty name and object definition.'
                );
            }

            if (!$this->hasValidJsonObject($definition)) {
                throw new DomainException(
                    sprintf('The MCP capability "%s" must contain only JSON values.', $name)
                );
            }

            if (!$this->hasValidStandardCapabilityDefinition($name, $definition)) {
                throw new DomainException(
                    sprintf('The MCP capability "%s" has an invalid standard definition.', $name)
                );
            }

            if (
                array_key_exists($name, $advertisedCapabilities)
                && $advertisedCapabilities[$name] !== $definition
            ) {
                throw new DomainException(
                    sprintf('The MCP capability "%s" has contradictory advertised metadata.', $name)
                );
            }

            $advertisedCapabilities[$name] = $definition;
            $capabilityNames[] = $name;
        }

        return $capabilityNames;
    }

    /**
     * Validates standard capability bodies declared by the dated MCP schema
     *
     * @param string $name
     * @param array  $definition
     *
     * @phpstan-param array<mixed> $definition
     */
    private function hasValidStandardCapabilityDefinition(string $name, array $definition): bool
    {
        return match ($name) {
            'completions', 'logging' => $this->hasValidJsonObject($definition),
            'experimental' => $this->hasValidObjectMap($definition),
            'extensions' => $this->hasValidExtensionMap($definition),
            'prompts' => $this->hasOnlyBooleanProperties($definition, ['listChanged']),
            'resources' => $this->hasOnlyBooleanProperties($definition, ['subscribe', 'listChanged']),
            'tools' => $this->hasOnlyBooleanProperties($definition, ['listChanged']),
            default => true,
        };
    }

    /**
     * Validates the association between standard request methods and discovery capabilities
     *
     * @param array $methods
     * @param array $capabilityNames
     *
     * @phpstan-param array<mixed> $methods
     * @phpstan-param list<string> $capabilityNames
     */
    private function validateStandardMethodCapabilities(array $methods, array $capabilityNames): void
    {
        $methodCapabilityNames = [];
        foreach ($methods as $method) {
            $capabilityName = self::STANDARD_METHOD_CAPABILITY_NAMES[$method] ?? null;
            if ($capabilityName !== null && !in_array($capabilityName, $capabilityNames, true)) {
                throw new DomainException(
                    sprintf(
                        'The MCP method "%s" must advertise the "%s" capability.',
                        $method,
                        $capabilityName
                    )
                );
            }

            if ($capabilityName !== null) {
                $methodCapabilityNames[] = $capabilityName;
            }
        }

        foreach ($capabilityNames as $capabilityName) {
            if (
                in_array($capabilityName, self::STANDARD_METHOD_CAPABILITY_NAMES, true)
                && !in_array($capabilityName, $methodCapabilityNames, true)
            ) {
                throw new DomainException(
                    sprintf(
                        'The MCP capability "%s" must own at least one corresponding standard method.',
                        $capabilityName
                    )
                );
            }
        }
    }

    /**
     * Validates the mandatory methods for every advertised standard capability
     *
     * @param array<string, McpCapability> $capabilitiesByMethod
     * @param array<string, array<mixed>>  $advertisedCapabilities
     */
    private function validateStandardCapabilityMandatoryMethods(
        array $capabilitiesByMethod,
        array $advertisedCapabilities
    ): void {
        foreach (self::STANDARD_CAPABILITY_MANDATORY_METHODS as $capabilityName => $method) {
            if (
                array_key_exists($capabilityName, $advertisedCapabilities)
                && !array_key_exists($method, $capabilitiesByMethod)
            ) {
                throw new DomainException(
                    sprintf(
                        'The MCP capability "%s" must register the "%s" method.',
                        $capabilityName,
                        $method
                    )
                );
            }
        }
    }

    /**
     * Rejects notification support declarations outside the foundation's subscription boundary
     *
     * @param array<string, array<mixed>>  $advertisedCapabilities
     */
    private function validateSubscriptionCapabilityFlags(array $advertisedCapabilities): void
    {
        foreach (self::SUBSCRIPTION_CAPABILITY_PROPERTIES as $capabilityName => $properties) {
            foreach ($properties as $property) {
                if (($advertisedCapabilities[$capabilityName][$property] ?? false) === true) {
                    throw new DomainException(
                        sprintf(
                            'The MCP capability "%s.%s" requires a subscription contract outside this foundation.',
                            $capabilityName,
                            $property
                        )
                    );
                }
            }
        }
    }

    /**
     * Returns whether an object permits only defined Boolean properties
     *
     * @param array $definition
     * @param array $properties
     *
     * @phpstan-param array<mixed> $definition
     * @phpstan-param list<string> $properties
     */
    private function hasOnlyBooleanProperties(array $definition, array $properties): bool
    {
        return array_all(
            $definition,
            fn($value, $property): bool => is_string($property)
                && in_array($property, $properties, true)
                && is_bool($value)
        );
    }

    /**
     * Returns whether every map value is a JSON object
     *
     * @param array<mixed> $definition
     */
    private function hasValidObjectMap(array $definition): bool
    {
        return array_all(
            $definition,
            fn($value, $name): bool => is_string($name) && $this->hasValidJsonObjectValue($value)
        );
    }

    /**
     * Returns whether an extension map has valid identifiers and JSON-object values
     *
     * @param array<mixed> $definition
     */
    private function hasValidExtensionMap(array $definition): bool
    {
        return array_all(
            $definition,
            fn($value, $name): bool => is_string($name)
                && $this->hasValidExtensionName($name)
                && $this->hasValidJsonObjectValue($value)
        );
    }

    /**
     * Returns whether one extension identifier has the required metadata prefix
     */
    private function hasValidExtensionName(string $name): bool
    {
        $pattern = implode('', [
            '/^[A-Za-z](?:[A-Za-z0-9-]*[A-Za-z0-9])?',
            '(?:\\.[A-Za-z](?:[A-Za-z0-9-]*[A-Za-z0-9])?)*\\/',
            '(?:[A-Za-z0-9](?:[A-Za-z0-9_.-]*[A-Za-z0-9])?)?$/'
        ]);

        return preg_match($pattern, $name) === 1;
    }

    /**
     * Returns whether an array is a JSON object with only JSON values
     *
     * @param array<mixed> $value
     */
    private function hasValidJsonObject(array $value): bool
    {
        return !$this->isJsonList($value)
            && $this->hasOnlyJsonValues($value)
            && $this->isJsonEncodable($value);
    }

    /**
     * Returns whether a value serializes as a JSON object with only JSON values
     */
    private function hasValidJsonObjectValue(mixed $value): bool
    {
        if ($value instanceof stdClass) {
            return $this->hasOnlyJsonValues(get_object_vars($value)) && $this->isJsonEncodable($value);
        }

        return is_array($value)
            && $value !== []
            && !array_is_list($value)
            && $this->hasOnlyJsonValues($value)
            && $this->isJsonEncodable($value);
    }

    /**
     * Returns whether every value can be encoded as JSON
     *
     * @param array<mixed> $values
     */
    private function hasOnlyJsonValues(array $values): bool
    {
        foreach ($values as $value) {
            if (is_array($value)) {
                if (!$this->hasOnlyJsonValues($value)) {
                    return false;
                }

                continue;
            }

            if ($value instanceof stdClass) {
                if (!$this->hasOnlyJsonValues(get_object_vars($value))) {
                    return false;
                }

                continue;
            }

            if (!is_null($value) && !is_bool($value) && !is_int($value) && !is_string($value)) {
                if (!is_float($value) || !is_finite($value)) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Returns whether a value can be encoded as a JSON wire value
     */
    private function isJsonEncodable(mixed $value): bool
    {
        try {
            json_encode($value, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return false;
        }

        return true;
    }

    /**
     * Returns whether an array represents a JSON list
     *
     * @param array<mixed> $value
     */
    private function isJsonList(array $value): bool
    {
        return $value !== [] && array_is_list($value);
    }

    /**
     * Registers future transport-owned mirror declarations without validating headers
     *
     * @param McpCapability                              $capability
     * @param array                                       $methods
     * @param array<string, list<McpMirrorDeclaration>> $mirrorDeclarationsByMethod
     *
     * @phpstan-param array<array-key, mixed> $methods
     */
    private function registerMirrorDeclarations(
        McpCapability $capability,
        array $methods,
        array &$mirrorDeclarationsByMethod
    ): void {
        /**
         * @var array<array-key, mixed> $mirrorDeclarations
         */
        $mirrorDeclarations = $capability->mirrorDeclarations();
        foreach ($mirrorDeclarations as $declaration) {
            if (!$declaration instanceof McpMirrorDeclaration) {
                throw new DomainException('Every MCP mirror declaration must be an McpMirrorDeclaration.');
            }

            if (!in_array($declaration->method(), $methods, true)) {
                throw new DomainException(
                    'An MCP mirror declaration must belong to one method owned by its capability.'
                );
            }

            $method = $declaration->method();
            $mirrorDeclarationsByMethod[$method] ??= [];
            foreach ($mirrorDeclarationsByMethod[$method] as $registered) {
                if (strcasecmp($registered->headerName(), $declaration->headerName()) === 0) {
                    throw new DomainException(
                        sprintf('The MCP method "%s" has duplicate mirrored header declarations.', $method)
                    );
                }
            }

            $mirrorDeclarationsByMethod[$method][] = $declaration;
        }
    }
}
