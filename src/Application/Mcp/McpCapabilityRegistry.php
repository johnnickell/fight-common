<?php

declare(strict_types=1);

namespace Fight\Common\Application\Mcp;

use Fight\Common\Domain\Exception\DomainException;

/**
 * Class McpCapabilityRegistry
 */
final readonly class McpCapabilityRegistry
{
    /** @var array<string, McpCapability> */
    private array $capabilitiesByMethod;
    /** @var array<string, array<mixed>> */
    private array $advertisedCapabilities;
    /** @var array<string, list<McpMirrorDeclaration>> */
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
        /** @var array<string, array<mixed>> $advertisedCapabilities */
        $advertisedCapabilities = [];
        $mirrorDeclarationsByMethod = [];

        foreach ($capabilities as $capability) {
            if (!$capability instanceof McpCapability) {
                throw new DomainException('Every MCP capability must implement McpCapability.');
            }

            $methods = $this->methodsFor($capability);
            $this->registerMethods($capability, $methods, $capabilitiesByMethod);
            $this->registerCapabilities($capability, $advertisedCapabilities);
            $this->registerMirrorDeclarations($capability, $methods, $mirrorDeclarationsByMethod);
        }

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
        /** @var array<array-key, mixed> $methods */
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
     */
    private function registerCapabilities(McpCapability $capability, array &$advertisedCapabilities): void
    {
        /** @var array<array-key, mixed> $capabilities */
        $capabilities = $capability->capabilities();
        foreach ($capabilities as $name => $definition) {
            if (
                !is_string($name)
                || trim($name) === ''
                || !is_array($definition)
                || ($definition !== [] && array_is_list($definition))
            ) {
                throw new DomainException(
                    'An MCP capability declaration must have a non-empty name and object definition.'
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
        }
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
        /** @var array<array-key, mixed> $mirrorDeclarations */
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
