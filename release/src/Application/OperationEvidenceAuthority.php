<?php

declare(strict_types=1);

namespace Fight\Release\Application;

use ReflectionClass;
use ReflectionException;
use ReflectionMethod;
use Throwable;

/**
 * Class OperationEvidenceAuthority
 *
 * Authenticates explicit operation policy against bound scanner facts.
 */
final readonly class OperationEvidenceAuthority
{
    private const array AFFIRMATIVE_EXTENSION_AUTHORITIES = [
        'fight-common.operation.abstract-extension-base',
        'fight-common.operation.exception-subtyping',
        'fight-common.operation.published-subtype'
    ];

    /**
     * Checks one operation decision has exact, affirmative or explicit-negative evidence
     *
     * @param array<string, mixed>                $entry
     * @param string                              $operationName
     * @param array<string, mixed>                $operation
     * @param array<string, array<mixed>>         $authorities
     * @param array<string, array<string, mixed>> $structuralFacts
     */
    public function isIntentional(
        array $entry,
        string $operationName,
        array $operation,
        array $authorities,
        array $structuralFacts
    ): bool {
        $decision = $operation['evidence'];
        if (
            !is_array($decision)
            || array_keys($decision) !== ['authority', 'rationale', 'binding']
            || !$this->bindingIsExact($decision['binding'], $entry, $operationName)
        ) {
            return false;
        }

        $authority = $decision['authority'];
        if (
            !isset($authorities[$authority])
            || !is_string($decision['rationale'])
            || $decision['rationale'] === ''
            || !str_contains($decision['rationale'], $entry['name'])
        ) {
            return false;
        }

        if (!$operation['promised']) {
            return $authority === 'fight-common.operation.not-promised'
                && str_contains($decision['rationale'], 'not promised');
        }

        $fact = $structuralFacts[$entry['name']];

        return match ($operationName) {
            'callable' => $authority === 'fight-common.operation.public-call'
                && $this->hasCallableFact($fact, $structuralFacts),
            'constructible' => $authority === 'fight-common.operation.public-construction'
                && $this->hasConstructibleFact($fact, $structuralFacts),
            'extensible' => $this->extensionAuthorityMatches($authority, $fact, $structuralFacts),
            'implementable' => $authority === 'fight-common.operation.interface-implementation'
                && ($fact['kind'] ?? null) === 'interface',
            default => false
        };
    }

    /**
     * Checks the operation-local subject, source, and axis binding
     *
     * @param mixed                $binding
     * @param array<string, mixed> $entry
     */
    private function bindingIsExact(mixed $binding, array $entry, string $operationName): bool
    {
        $entryBinding = $entry['evidence_binding'] ?? null;

        return is_array($binding)
            && array_keys($binding) === ['subject', 'source_locator', 'operation']
            && is_array($entryBinding)
            && $binding === [
                'subject'        => $entry['name'],
                'source_locator' => $entryBinding['source_locator'] ?? null,
                'operation'      => $operationName
            ];
    }

    /**
     * Reports whether direct or inherited callable scanner facts are non-empty
     *
     * @param array<string, mixed>                $fact
     * @param array<string, array<string, mixed>> $structuralFacts
     * @param array<string, bool>                 $visited
     */
    private function hasCallableFact(array $fact, array $structuralFacts, array $visited = []): bool
    {
        $name = $fact['name'] ?? null;
        if (!is_string($name) || isset($visited[$name])) {
            return false;
        }

        $visited[$name] = true;
        $callable = $fact['operations']['callable'] ?? null;
        if (!is_array($callable)) {
            return false;
        }

        if (
            array_any($callable, static fn (mixed $shape): bool => is_string($shape)
            && !str_starts_with($shape, 'parent '))
        ) {
            return true;
        }

        $parents = array_filter(
            $callable,
            static fn (mixed $shape): bool => is_string($shape)
                && str_starts_with($shape, 'parent ')
                && $shape !== 'parent none'
        );
        if (($fact['kind'] ?? null) === 'interface') {
            $parents = [
                ...$parents,
                ...array_filter(
                    $fact['operations']['implementable'] ?? [],
                    static fn (mixed $shape): bool => is_string($shape) && str_starts_with($shape, 'extends ')
                )
            ];
        }

        return array_any(
            $parents,
            fn (string $marker): bool => $this->inheritedCallableFact(
                ltrim(substr($marker, strpos($marker, ' ') + 1), '\\'),
                $structuralFacts,
                $visited
            )
        );
    }

    /**
     * Resolves one callable parent through scanned or runtime authority
     *
     * @param string                              $parent
     * @param array<string, array<string, mixed>> $structuralFacts
     * @param array<string, bool>                 $visited
     */
    private function inheritedCallableFact(string $parent, array $structuralFacts, array $visited): bool
    {
        if (isset($structuralFacts[$parent])) {
            return $this->hasCallableFact($structuralFacts[$parent], $structuralFacts, $visited);
        }

        try {
            $reflection = new ReflectionClass($parent);
        } catch (ReflectionException) {
            return false;
        }

        return array_any(
            $reflection->getMethods(),
            static fn (ReflectionMethod $method): bool => $method->isPublic()
                && !in_array(strtolower($method->getName()), ['__construct', '__destruct'], true)
        );
    }

    /**
     * Reports whether a concrete declaration has authenticated construction facts
     *
     * @param array<string, mixed>                $fact
     * @param array<string, array<string, mixed>> $structuralFacts
     * @param array<string, bool>                 $visited
     */
    private function hasConstructibleFact(array $fact, array $structuralFacts, array $visited = []): bool
    {
        if (in_array('abstract class', $fact['operations']['extensible'] ?? [], true)) {
            return false;
        }

        return $this->hasAvailableConstructorFact($fact, $structuralFacts, $visited);
    }

    /**
     * Resolves direct, implicit, or inherited constructor availability
     *
     * @param array<string, mixed>                $fact
     * @param array<string, array<string, mixed>> $structuralFacts
     * @param array<string, bool>                 $visited
     */
    private function hasAvailableConstructorFact(array $fact, array $structuralFacts, array $visited): bool
    {
        $name = $fact['name'] ?? null;
        if (!is_string($name) || isset($visited[$name])) {
            return false;
        }

        $visited[$name] = true;
        $constructible = $fact['operations']['constructible'] ?? null;
        if (!is_array($constructible)) {
            return false;
        }

        if (
            array_any($constructible, static fn (mixed $shape): bool => is_string($shape)
            && !str_starts_with($shape, 'inherits constructor '))
        ) {
            return true;
        }

        $inherited = array_find(
            $constructible,
            static fn (mixed $shape): bool => is_string($shape) && str_starts_with($shape, 'inherits constructor ')
        );
        if (!is_string($inherited)) {
            return false;
        }

        $parent = ltrim(substr($inherited, strlen('inherits constructor ')), '\\');
        if (isset($structuralFacts[$parent])) {
            return $this->hasAvailableConstructorFact($structuralFacts[$parent], $structuralFacts, $visited);
        }

        try {
            $constructor = new ReflectionClass($parent)->getConstructor();
        } catch (ReflectionException) {
            return false;
        }

        return $constructor === null || $constructor->isPublic();
    }

    /**
     * Authenticates an affirmative extension authority against scanner facts
     *
     * @param string                              $authority
     * @param array<string, mixed>                $fact
     * @param array<string, array<string, mixed>> $structuralFacts
     */
    private function extensionAuthorityMatches(string $authority, array $fact, array $structuralFacts): bool
    {
        if (!in_array($authority, self::AFFIRMATIVE_EXTENSION_AUTHORITIES, true)) {
            return false;
        }

        return match ($authority) {
            'fight-common.operation.abstract-extension-base' => ($fact['kind'] ?? null) === 'class'
                && in_array('abstract class', $fact['operations']['extensible'] ?? [], true),
            'fight-common.operation.exception-subtyping' => $this->isThrowableClass($fact, $structuralFacts),
            'fight-common.operation.published-subtype' => $this->hasPublishedSubtype($fact, $structuralFacts)
        };
    }

    /**
     * Reports whether one scanned class has a closed ancestry path to Throwable
     *
     * @param array<string, mixed>                $fact
     * @param array<string, array<string, mixed>> $structuralFacts
     * @param array<string, bool>                 $visited
     */
    private function isThrowableClass(array $fact, array $structuralFacts, array $visited = []): bool
    {
        if (($fact['kind'] ?? null) !== 'class' || isset($visited[$fact['name']])) {
            return false;
        }

        $visited[$fact['name']] = true;
        $parentMarker = array_find(
            $fact['operations']['callable'] ?? [],
            static fn (mixed $shape): bool => is_string($shape) && str_starts_with($shape, 'parent ')
        );
        if (!is_string($parentMarker) || $parentMarker === 'parent none') {
            return false;
        }

        $parent = ltrim(substr($parentMarker, strlen('parent ')), '\\');
        if (isset($structuralFacts[$parent])) {
            return $this->isThrowableClass($structuralFacts[$parent], $structuralFacts, $visited);
        }

        return is_a($parent, Throwable::class, true);
    }

    /**
     * Reports whether another scanned class publishes this declaration as its parent
     *
     * @param array<string, mixed>                $fact
     * @param array<string, array<string, mixed>> $structuralFacts
     */
    private function hasPublishedSubtype(array $fact, array $structuralFacts): bool
    {
        $parentMarker = 'parent \\'.$fact['name'];

        return array_any(
            $structuralFacts,
            static fn (array $candidate): bool => ($candidate['kind'] ?? null) === 'class'
                && in_array($parentMarker, $candidate['operations']['callable'] ?? [], true)
        );
    }
}
