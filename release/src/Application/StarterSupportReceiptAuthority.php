<?php

declare(strict_types=1);

namespace Fight\Release\Application;

/**
 * Class StarterSupportReceiptAuthority
 *
 * Validates committed booted-starter support receipts and their immutable pins.
 */
final class StarterSupportReceiptAuthority
{
    public const string SCHEMA_VERSION = 'fight-common.framework-support-receipt/v1';

    /** @var list<string> */
    private const array FRAMEWORKS = ['symfony', 'laravel', 'yii', 'codeigniter', 'slim'];
    /** @var list<string> */
    private const array RESULTS = ['passed', 'failed', 'unavailable', 'skipped', 'indeterminate'];

    /**
     * Checks the complete schema and result/next-action relationship of one receipt
     *
     * @param array<string, mixed> $receipt Receipt decoded from its committed JSON document.
     */
    public function isValid(array $receipt): bool
    {
        if (
            array_keys($receipt) !== [
                'schema_version', 'content_id', 'candidate', 'framework', 'lock_sha256', 'capabilities',
                'journeys', 'result', 'evidence', 'next_action'
            ]
            || ($receipt['schema_version'] ?? null) !== self::SCHEMA_VERSION
            || !$this->isSha256($receipt['content_id'] ?? null)
            || !$this->isCandidate($receipt['candidate'] ?? null)
            || !$this->isFramework($receipt['framework'] ?? null)
            || !$this->isSha256($receipt['lock_sha256'] ?? null)
            || !$this->isCapabilities($receipt['capabilities'] ?? null)
            || !$this->isJourneys($receipt['journeys'] ?? null)
            || !in_array($receipt['result'] ?? null, self::RESULTS, true)
            || !$this->isEvidence($receipt['evidence'] ?? null)
        ) {
            return false;
        }

        $nextAction = $receipt['next_action'] ?? null;

        if ($receipt['result'] === 'passed') {
            return $nextAction === null && $this->hasOnlyPassedJourneys($receipt['journeys']);
        }

        return $this->isNextAction($nextAction) && !$this->hasOnlyPassedJourneys($receipt['journeys']);
    }

    /**
     * Checks the immutable repository coordinates that identify one receipt
     *
     * @param array<string, mixed> $pin Immutable framework receipt reference.
     */
    public function isValidPin(array $pin): bool
    {
        return array_keys($pin) === ['framework', 'repository', 'commit', 'path', 'sha256']
            && is_string($pin['framework'] ?? null)
            && in_array($pin['framework'], self::FRAMEWORKS, true)
            && is_string($pin['repository'] ?? null)
            && preg_match('#^https://github\.com/[^/\s]+/[^/\s]+(?:\.git)?$#', $pin['repository']) === 1
            && is_string($pin['commit'] ?? null)
            && preg_match('/^[a-f0-9]{40}$/', $pin['commit']) === 1
            && ($pin['path'] ?? null) === 'evidence/framework-support/receipt-v1.json'
            && $this->isSha256($pin['sha256'] ?? null);
    }

    /**
     * Requires every framework receipt to pass for the exact candidate reference
     *
     * @param array<string, mixed> $pins
     * @param array<string, array<string, mixed>> $receipts
     */
    public function hasPassingComposition(array $pins, array $receipts, string $candidateReference): bool
    {
        if (!preg_match('/^[a-f0-9]{40}$/', $candidateReference) || array_keys($pins) !== self::FRAMEWORKS) {
            return false;
        }

        foreach (self::FRAMEWORKS as $framework) {
            $pin = $pins[$framework] ?? null;
            $receipt = $receipts[$framework] ?? null;
            if (
                !is_array($pin) || !is_array($receipt) || !$this->isValidPin($pin) || !$this->isValid($receipt)
                || $pin['framework'] !== $framework || $receipt['framework']['name'] !== $framework
                || $receipt['result'] !== 'passed' || $receipt['candidate']['reference'] !== $candidateReference
            ) {
                return false;
            }
        }

        return true;
    }

    /**
     * Identifies a lowercase SHA-256 digest
     */
    private function isSha256(mixed $value): bool
    {
        return is_string($value) && preg_match('/^[a-f0-9]{64}$/', $value) === 1;
    }

    /**
     * Checks the resolved Fight Common 1.2 candidate identity
     */
    private function isCandidate(mixed $candidate): bool
    {
        return is_array($candidate)
            && array_keys($candidate) === ['package', 'version', 'reference']
            && ($candidate['package'] ?? null) === 'johnnickell/fight-common'
            && is_string($candidate['version'] ?? null)
            && preg_match('/^1\.2\.\d+(?:-[0-9A-Za-z.-]+)?$/', $candidate['version']) === 1
            && is_string($candidate['reference'] ?? null)
            && preg_match('/^[a-f0-9]{40}$/', $candidate['reference']) === 1;
    }

    /**
     * Checks the framework and provider identity recorded by a starter
     */
    private function isFramework(mixed $framework): bool
    {
        return is_array($framework) && array_keys($framework) === ['name', 'version', 'providers']
            && is_string($framework['name'] ?? null) && in_array($framework['name'], self::FRAMEWORKS, true)
            && is_string($framework['version'] ?? null) && $framework['version'] !== ''
            && is_array($framework['providers'] ?? null) && $framework['providers'] !== []
            && array_all(
                $framework['providers'],
                static fn(mixed $provider): bool => is_string($provider) && $provider !== ''
            );
    }

    /**
     * Checks that selected capability states use the closed support vocabulary
     */
    private function isCapabilities(mixed $capabilities): bool
    {
        if (!is_array($capabilities) || $capabilities === []) {
            return false;
        }

        return array_all(
            $capabilities,
            fn($state, $name): bool => !(
                !is_string($name)
                || $name === ''
                || !in_array($state, ['ship', 'wire', 'unavailable'], true)
            )
        );
    }

    /**
     * Checks that every booted lifecycle journey has an evidence reference
     */
    private function isJourneys(mixed $journeys): bool
    {
        if (!is_array($journeys) || $journeys === []) {
            return false;
        }

        return array_all(
            $journeys,
            fn($journey): bool => !(
                !is_array($journey)
                || array_keys($journey) !== ['name', 'status', 'evidence']
                || !is_string($journey['name'] ?? null)
                || $journey['name'] === ''
                || !in_array($journey['status'] ?? null, self::RESULTS, true)
                || !is_string($journey['evidence'] ?? null)
                || $journey['evidence'] === ''
            )
        );
    }

    /**
     * Checks the required local build and planning evidence references
     */
    private function isEvidence(mixed $evidence): bool
    {
        return is_array($evidence) && array_keys($evidence) === ['build', 'planning_check', 'receipt_sha256']
            && is_string($evidence['build'] ?? null) && $evidence['build'] !== ''
            && is_string($evidence['planning_check'] ?? null) && $evidence['planning_check'] !== ''
            && $this->isSha256($evidence['receipt_sha256'] ?? null);
    }

    /**
     * Determines whether every recorded journey passed
     *
     * @param list<array<string, mixed>> $journeys Validated lifecycle journeys.
     */
    private function hasOnlyPassedJourneys(array $journeys): bool
    {
        return array_all($journeys, static fn(array $journey): bool => $journey['status'] === 'passed');
    }

    /**
     * Checks one resumable non-passing-state action
     */
    private function isNextAction(mixed $nextAction): bool
    {
        return is_array($nextAction) && array_keys($nextAction) === ['action']
            && is_string($nextAction['action'] ?? null) && $nextAction['action'] !== '';
    }
}
