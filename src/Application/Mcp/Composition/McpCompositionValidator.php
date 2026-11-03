<?php

declare(strict_types=1);

namespace Fight\Common\Application\Mcp\Composition;

use Fight\Common\Application\Mcp\Capability\McpCapability;
use Fight\Common\Application\Mcp\Skill\McpSkillDiscovery;
use Fight\Common\Application\Mcp\Subscription\McpSubscriptions;
use Fight\Common\Application\Mcp\Task\McpTasks;
use Fight\Common\Application\Mcp\Tool\McpToolInvocation;
use Fight\Common\Domain\Exception\DomainException;

/**
 * Class McpCompositionValidator
 *
 * @internal Registry-owned cross-feature consistency, not a consumer validation step
 */
final readonly class McpCompositionValidator
{
    /**
     * @var array<string, list<string>>
     */
    private const array SUBSCRIPTION_CAPABILITY_PROPERTIES = [
        'prompts'   => ['listChanged'],
        'resources' => ['subscribe', 'listChanged'],
        'tools'     => ['listChanged']
    ];

    /**
     * Validates complete feature wiring and returns only truthfully activated definitions
     *
     * @param array<string, McpCapability> $methods
     * @param array<string, array<mixed>>  $capabilities
     *
     * @return array<string, array<mixed>>
     */
    public function validate(array $methods, array $capabilities, ?McpSubscriptions $subscriptions): array
    {
        $this->validateSubscriptionCapabilityFlags($capabilities);
        $capabilities = $this->activateSubscriptions($methods, $capabilities, $subscriptions);
        $this->validateSkills($methods, $capabilities);
        $this->validateTasks($methods, $capabilities);

        return $capabilities;
    }

    /**
     * Rejects notification support declarations outside the foundation's subscription boundary
     *
     * @param array<string, array<mixed>> $capabilities
     */
    private function validateSubscriptionCapabilityFlags(array $capabilities): void
    {
        foreach (self::SUBSCRIPTION_CAPABILITY_PROPERTIES as $capabilityName => $properties) {
            foreach ($properties as $property) {
                if (($capabilities[$capabilityName][$property] ?? false) === true) {
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
     * Returns flags derived from actual subscription serving families and shared Task ownership
     *
     * @param array<string, McpCapability> $methods
     * @param array<string, array<mixed>>  $capabilities
     *
     * @return array<string, array<mixed>>
     */
    private function activateSubscriptions(array $methods, array $capabilities, ?McpSubscriptions $subscriptions): array
    {
        if ($subscriptions === null) {
            return $capabilities;
        }

        foreach ($subscriptions->supported() as $filter) {
            if ($filter === 'taskIds') {
                if ($subscriptions->tasks() !== ($methods['tasks/get'] ?? null)) {
                    throw new DomainException('Task notifications require the same complete Task capability.');
                }

                continue;
            }

            [$family, $property] = match ($filter) {
                'toolsListChanged' => ['tools', 'listChanged'],
                'promptsListChanged' => ['prompts', 'listChanged'],
                'resourcesListChanged' => ['resources', 'listChanged'],
                'resourceSubscriptions' => ['resources', 'subscribe']
            };
            if (!isset($capabilities[$family])) {
                throw new DomainException('Subscriptions require the corresponding serving capability.');
            }

            $capabilities[$family][$property] = true;
        }

        return $capabilities;
    }

    /**
     * Validates complete Skills advertisement and the built-in provider's shared read composition
     *
     * @phpstan-param array<string, McpCapability> $methods
     * @phpstan-param array<string, array<mixed>> $capabilities
     */
    private function validateSkills(array $methods, array $capabilities): void
    {
        $settings = $capabilities['extensions'][McpSkillDiscovery::EXTENSION] ?? null;
        if ($settings === null) {
            if (isset($methods['skills/list']) || isset($methods['skills/get'])) {
                throw new DomainException('Skills methods require the stable Skills extension declaration.');
            }

            return;
        }

        if (
            !isset($methods['skills/list'], $methods['skills/get'], $methods['resources/read'])
            || !isset($capabilities['resources'])
            || !array_all(
                $settings->properties(),
                fn($value, $property): bool => $property === 'directoryRead' && is_bool($value)
            )
            || $settings->get('directoryRead') === true
        ) {
            throw new DomainException(
                'Static Skills requires list, get and readable Resources without optional feature claims.'
            );
        }

        foreach (['skills/list', 'skills/get'] as $method) {
            $capability = $methods[$method];
            if (
                $capability instanceof McpSkillDiscovery
                && $capability->resourceDiscovery() !== $methods['resources/read']
            ) {
                throw new DomainException('Skills and file reads must share the configured Resources capability.');
            }
        }
    }

    /**
     * Requires the actual complete Task methods and the same Tool-start composition before advertisement
     *
     * @param array<string, McpCapability> $methods
     * @param array<string, array<mixed>> $capabilities
     */
    private function validateTasks(array $methods, array $capabilities): void
    {
        $settings = $capabilities['extensions'][McpTasks::EXTENSION] ?? null;
        $invocation = $methods['tools/call'] ?? null;
        $tasks = $methods['tasks/get'] ?? null;
        if ($settings === null) {
            if (
                isset($methods['tasks/get']) || isset($methods['tasks/update']) || isset($methods['tasks/cancel'])
                || ($invocation instanceof McpToolInvocation && $invocation->tasks() !== null)
            ) {
                throw new DomainException('Task composition requires the complete Tasks extension.');
            }

            return;
        }

        if (
            $settings->properties() !== [] || !$tasks instanceof McpTasks
            || ($methods['tasks/update'] ?? null) !== $tasks || ($methods['tasks/cancel'] ?? null) !== $tasks
            || !$invocation instanceof McpToolInvocation || $invocation->tasks() !== $tasks
        ) {
            throw new DomainException('Tasks requires shared start, get, input and cancellation composition.');
        }
    }
}
