<?php

declare(strict_types=1);

namespace Fight\Release\Application;

/**
 * Class CompatibilityFinding
 *
 * Authenticates one fail-closed compatibility-authority finding for the public result seam.
 */
final readonly class CompatibilityFinding
{
    private const string MESSAGE = 'Structural compatibility authority rejected the evidence.';
    private const array CONTRACTS = [
        'release.compatibility.structural-api.baseline-drift'                  => [
            'attribution' => 'baseline-inventory',
            'subject'     => false,
            'operation'   => false
        ],
        'release.compatibility.structural-api.candidate-drift'                 => [
            'attribution' => 'candidate-inventory',
            'subject'     => false,
            'operation'   => false
        ],
        'release.compatibility.structural-api.missing-classification'          => [
            'attribution' => 'compatibility-manifest',
            'subject'     => true,
            'operation'   => false
        ],
        'release.compatibility.structural-api.missing-member-classification'   => [
            'attribution' => 'compatibility-manifest',
            'subject'     => true,
            'operation'   => false,
            'member'      => true
        ],
        'release.compatibility.structural-api.unsupported-checker-output'      => [
            'attribution' => 'structural-checker',
            'subject'     => null,
            'operation'   => null
        ],
        'release.compatibility.structural-api.operation-promise-indeterminate' => [
            'attribution' => 'compatibility-manifest',
            'subject'     => true,
            'operation'   => true
        ]
    ];
    private const array OPERATIONS = ['callable', 'constructible', 'extensible', 'implementable'];

    /**
     * Constructs CompatibilityFinding
     */
    private function __construct(
        private string $id,
        private string $attribution,
        private ?string $subject,
        private ?string $operation,
        private ?string $member = null
    ) {
    }

    /**
     * Authenticates the sole rejection finding returned by structural authority
     *
     * @param array<string, mixed> $result
     */
    public static function fromStructuralResult(array $result): ?self
    {
        if (
            array_keys($result) !== ['status', 'classification', 'findings']
            || $result['status'] !== 'rejected'
            || $result['classification'] !== 'indeterminate'
            || !is_array($result['findings'])
            || count($result['findings']) !== 1
            || !is_array($result['findings'][0] ?? null)
        ) {
            return null;
        }

        $finding = $result['findings'][0];
        if (
            !in_array(array_keys($finding), [
            ['finding_id', 'attribution', 'subject', 'operation'],
            ['finding_id', 'attribution', 'subject', 'operation', 'member']
            ], true)
        ) {
            return null;
        }

        $id = $finding['finding_id'];
        $attribution = $finding['attribution'];
        $subject = $finding['subject'];
        $operation = $finding['operation'];
        $member = $finding['member'] ?? null;
        if (
            !is_string($id)
            || !is_string($attribution)
            || (!is_string($subject) && $subject !== null)
            || (!is_string($operation) && $operation !== null)
            || (!is_string($member) && $member !== null)
            || !self::matchesContract($id, $attribution, $subject, $operation, $member)
        ) {
            return null;
        }

        return new self($id, $attribution, $subject, $operation, $member);
    }

    /**
     * Returns the stable attributed finding for one unclassified manifest subject
     */
    public static function missingClassification(string $subject): self
    {
        return new self(
            'release.compatibility.structural-api.missing-classification',
            'compatibility-manifest',
            $subject,
            null
        );
    }

    /**
     * Checks one public machine finding against the closed compatibility vocabulary
     */
    public static function isMachineFinding(mixed $finding): bool
    {
        return is_array($finding)
            && in_array(array_keys($finding), [
                ['id', 'message', 'attribution', 'subject', 'operation'],
                ['id', 'message', 'attribution', 'subject', 'operation', 'member']
            ], true)
            && is_string($finding['id'])
            && $finding['message'] === self::MESSAGE
            && is_string($finding['attribution'])
            && (is_string($finding['subject']) || $finding['subject'] === null)
            && (is_string($finding['operation']) || $finding['operation'] === null)
            && (is_string($finding['member'] ?? null) || ($finding['member'] ?? null) === null)
            && self::matchesContract(
                $finding['id'],
                $finding['attribution'],
                $finding['subject'],
                $finding['operation'],
                $finding['member'] ?? null
            );
    }

    /**
     * Returns the exact stable public machine finding
     *
     * @return array{
     *     id: string,
     *     message: string,
     *     attribution: string,
     *     subject: string|null,
     *     operation: string|null,
     *     member?: string
     * }
     */
    public function machineFinding(): array
    {
        $finding = [
            'id'          => $this->id,
            'message'     => self::MESSAGE,
            'attribution' => $this->attribution,
            'subject'     => $this->subject,
            'operation'   => $this->operation
        ];
        if ($this->member !== null) {
            $finding['member'] = $this->member;
        }

        return $finding;
    }

    /**
     * Checks exact attribution and evidence requirements for one stable finding ID
     */
    private static function matchesContract(
        string $id,
        string $attribution,
        ?string $subject,
        ?string $operation,
        ?string $member = null
    ): bool {
        $contract = self::CONTRACTS[$id] ?? null;
        if (!is_array($contract) || $attribution !== $contract['attribution']) {
            return false;
        }

        $subjectRequirement = $contract['subject'];
        $operationRequirement = $contract['operation'];
        $memberRequirement = $contract['member'] ?? false;

        return match ($subjectRequirement) {
            true => is_string($subject) && $subject !== '',
            false => $subject === null,
            null => $subject === null || $subject !== ''
        }
            && match ($operationRequirement) {
                true => in_array($operation, self::OPERATIONS, true),
                false => $operation === null,
                null => $operation === null || in_array($operation, self::OPERATIONS, true)
            }
            && match ($memberRequirement) {
                true => is_string($member)
                    && preg_match('/\A(?:constant|case) [A-Za-z_][A-Za-z0-9_]*\z/D', $member) === 1,
                false => $member === null
            };
    }
}
