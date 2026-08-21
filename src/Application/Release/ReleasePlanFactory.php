<?php

declare(strict_types=1);

namespace Fight\Common\Application\Release;

use Fight\Common\Application\Release\Boundary\ReleaseEffect;

/**
 * Class ReleasePlanFactory
 *
 * Validates and binds immutable release-plan inputs.
 */
final readonly class ReleasePlanFactory
{
    private const string SCHEMA_VERSION = 'fight-common.release-plan/v1';
    /** @var list<string> */
    private const array RELEASE_CLASSES = ['patch', 'minor', 'major'];

    /**
     * Constructs ReleasePlanFactory
     */
    public function __construct(
        private ReleaseAuthorityValidator $authority = new ReleaseAuthorityValidator(),
        private BaselineTagVerifier $baselineTags = new BaselineTagVerifier()
    ) {
    }

    /**
     * Creates a plan only when all required immutable inputs are present
     *
     * @param array<string, mixed> $candidate Candidate plan input.
     *
     * @return array<string, mixed>|null
     */
    public function create(array $candidate): ?array
    {
        if (
            $this->validationFailure($candidate) instanceof ReleasePlanValidationFailure
            || $this->versionAuthorizationFailure($candidate) instanceof ReleasePlanValidationFailure
        ) {
            return null;
        }

        /** @var array<string, mixed> $baseline */
        $baseline = $candidate['baseline'];
        /** @var ReleaseApprovalAuthority $releaseApproval */
        $releaseApproval = ReleaseApprovalAuthority::tryFrom(
            $candidate['release_approval_authority'],
            $this->authority
        );

        return [
            'schema_version'              => $candidate['schema_version'],
            'approved_version'            => $candidate['approved_version'],
            'minimum_release_class'       => $candidate['release_class'],
            'release_class'               => $this->authorizedReleaseClass(
                $baseline['version'],
                $candidate['approved_version']
            ),
            'source_commit_oid'           => $candidate['source_commit_oid'],
            'baseline'                    => [
                'version'           => $baseline['version'],
                'tag_name'          => $baseline['tag_name'],
                'tag_object_oid'    => $baseline['tag_object_oid'],
                'peeled_commit_oid' => $baseline['peeled_commit_oid']
            ],
            'support_policy_identity'     => $candidate['support_policy_identity'],
            'expected_effect_classes'     => $this->canonicalSet($candidate['expected_effect_classes']),
            'evidence_requirements'       => $this->canonicalSet($candidate['evidence_requirements']),
            'evidence_manifest_digest'    => $candidate['evidence_manifest_digest'],
            'compatibility_exceptions'    => $this->canonicalSet($candidate['compatibility_exceptions']),
            'patch_exception_authorities' => array_map(
                static fn (PatchExceptionAuthority $record): array => $record->toArray(),
                $this->patchExceptionAuthorities($candidate['patch_exception_authorities'])
            ),
            'required_approvals'          => $this->canonicalSet($candidate['required_approvals']),
            'release_approval_authority'  => $releaseApproval->toArray()
        ];
    }

    /**
     * Returns the first exact invalid authority reason before identity or persistence work
     *
     * @param array<string, mixed> $candidate Candidate plan input.
     */
    public function validationFailure(array $candidate): ?ReleasePlanValidationFailure
    {
        $simple = [
            'schema_version'               => [
                ReleasePlanValidationFailure::SCHEMA_VERSION_MISSING,
                ReleasePlanValidationFailure::SCHEMA_VERSION_INVALID,
                static fn (mixed $value): bool => $value === self::SCHEMA_VERSION
            ],
            'approved_version'             => [
                ReleasePlanValidationFailure::APPROVED_VERSION_MISSING,
                ReleasePlanValidationFailure::APPROVED_VERSION_INVALID,
                static fn (mixed $value): bool => is_string($value) && StableSemVer::isValid($value)
                ],
                'release_class'            => [
                ReleasePlanValidationFailure::RELEASE_CLASS_MISSING,
                ReleasePlanValidationFailure::RELEASE_CLASS_INVALID,
                static fn (mixed $value): bool => in_array($value, self::RELEASE_CLASSES, true)
                ],
                'source_commit_oid'        => [
                ReleasePlanValidationFailure::SOURCE_COMMIT_OID_MISSING,
                ReleasePlanValidationFailure::SOURCE_COMMIT_OID_INVALID,
                $this->authority->isGitObjectId(...)
                ],
                'support_policy_identity'  => [
                ReleasePlanValidationFailure::SUPPORT_POLICY_IDENTITY_MISSING,
                ReleasePlanValidationFailure::SUPPORT_POLICY_IDENTITY_INVALID,
                $this->authority->isSupportPolicyIdentity(...)
                ],
                'expected_effect_classes'  => [
                ReleasePlanValidationFailure::EXPECTED_EFFECT_CLASSES_MISSING,
                ReleasePlanValidationFailure::EXPECTED_EFFECT_CLASSES_INVALID,
                fn (mixed $value): bool => $this->isClosedStringList(
                    $value,
                    ReleaseEffect::canonicalValues(),
                    true
                )
                ],
                'evidence_requirements'    => [
                ReleasePlanValidationFailure::EVIDENCE_REQUIREMENTS_MISSING,
                ReleasePlanValidationFailure::EVIDENCE_REQUIREMENTS_INVALID,
                $this->isEvidenceRequirementList(...)
                ],
                'evidence_manifest_digest' => [
                    ReleasePlanValidationFailure::EVIDENCE_MANIFEST_DIGEST_MISSING,
                    ReleasePlanValidationFailure::EVIDENCE_MANIFEST_DIGEST_INVALID,
                    static fn (mixed $value): bool => is_string($value)
                        && preg_match('/\A[0-9a-f]{64}\z/D', $value) === 1
                ],
                'compatibility_exceptions' => [
                ReleasePlanValidationFailure::COMPATIBILITY_EXCEPTIONS_MISSING,
                ReleasePlanValidationFailure::COMPATIBILITY_EXCEPTIONS_INVALID,
                $this->isCompatibilityExceptionList(...)
                ]
        ];

        foreach ($simple as $field => [$missing, $invalid, $validator]) {
            if (!array_key_exists($field, $candidate)) {
                return $missing;
            }

            if (!$validator($candidate[$field])) {
                return $invalid;
            }
        }

        if (!array_key_exists('baseline', $candidate)) {
            return ReleasePlanValidationFailure::BASELINE_MISSING;
        }

        if (!is_array($candidate['baseline'])) {
            return ReleasePlanValidationFailure::BASELINE_INVALID;
        }

        $baseline = $candidate['baseline'];

        foreach (
            [
            'version'           => [
                ReleasePlanValidationFailure::BASELINE_VERSION_MISSING,
                ReleasePlanValidationFailure::BASELINE_VERSION_INVALID,
                static fn (mixed $value): bool => is_string($value) && StableSemVer::isValid($value)
            ],
            'tag_name'          => [
                ReleasePlanValidationFailure::BASELINE_TAG_NAME_MISSING,
                ReleasePlanValidationFailure::BASELINE_TAG_NAME_INVALID,
                fn (mixed $value): bool => is_string($value)
                    && is_string($baseline['version'] ?? null)
                    && $this->baselineTags->isCanonical($value, $baseline['version'])
            ],
            'tag_object_oid'    => [
                ReleasePlanValidationFailure::BASELINE_TAG_OBJECT_OID_MISSING,
                ReleasePlanValidationFailure::BASELINE_TAG_OBJECT_OID_INVALID,
                $this->authority->isGitObjectId(...)
            ],
            'peeled_commit_oid' => [
                ReleasePlanValidationFailure::BASELINE_PEELED_COMMIT_OID_MISSING,
                ReleasePlanValidationFailure::BASELINE_PEELED_COMMIT_OID_INVALID,
                $this->authority->isGitObjectId(...)
            ]
            ] as $field => [$missing, $invalid, $validator]
        ) {
            if (!array_key_exists($field, $baseline)) {
                return $missing;
            }

            if (!$validator($baseline[$field])) {
                return $invalid;
            }
        }

        if (!array_key_exists('required_approvals', $candidate)) {
            return ReleasePlanValidationFailure::REQUIRED_APPROVALS_MISSING;
        }

        if (!$this->isIdentifierList($candidate['required_approvals'])) {
            return ReleasePlanValidationFailure::REQUIRED_APPROVALS_INVALID;
        }

        $patchAuthorityFailure = $this->patchExceptionAuthorityValidationFailure($candidate);

        if ($patchAuthorityFailure instanceof ReleasePlanValidationFailure) {
            return $patchAuthorityFailure;
        }

        /** @var string $approvedVersion */
        $approvedVersion = $candidate['approved_version'];
        /** @var string $releaseClass */
        $releaseClass = $candidate['release_class'];
        /** @var string $baselineVersion */
        $baselineVersion = $candidate['baseline']['version'];
        $minimumVersion = StableSemVer::increment($baselineVersion, $releaseClass);
        assert(is_string($minimumVersion));
        $minimumComparison = StableSemVer::compare($approvedVersion, $minimumVersion);
        assert(is_int($minimumComparison));
        $patchExclusivityFailure = $this->patchExceptionExclusivityFailure(
            $candidate,
            $minimumComparison >= 0
        );

        if ($patchExclusivityFailure instanceof ReleasePlanValidationFailure) {
            return $patchExclusivityFailure;
        }

        $releaseApprovalFailure = $this->releaseApprovalAuthorityValidationFailure($candidate);

        if ($releaseApprovalFailure instanceof ReleasePlanValidationFailure) {
            return $releaseApprovalFailure;
        }

        return null;
    }

    /**
     * Reports a canonical but mismatched baseline, release-class and approved-version relation
     *
     * @param array<string, mixed> $candidate Candidate plan input.
     */
    public function versionAuthorizationFailure(array $candidate): ?ReleasePlanValidationFailure
    {
        $approvedVersion = $candidate['approved_version'] ?? null;
        $releaseClass = $candidate['release_class'] ?? null;
        $baselineVersion = $candidate['baseline']['version'] ?? null;

        if (
            !is_string($approvedVersion)
            || !StableSemVer::isValid($approvedVersion)
            || !is_string($releaseClass)
            || !in_array($releaseClass, self::RELEASE_CLASSES, true)
            || !is_string($baselineVersion)
            || !StableSemVer::isValid($baselineVersion)
        ) {
            return null;
        }

        $minimumVersion = StableSemVer::increment($baselineVersion, $releaseClass);
        $minimumComparison = StableSemVer::compare($approvedVersion, $minimumVersion ?? '') ?? 0;

        if (
            $minimumComparison < 0
            && $approvedVersion !== StableSemVer::increment($baselineVersion, 'patch')
        ) {
            return ReleasePlanValidationFailure::VERSION_RELATION_INVALID;
        }

        $patchExclusivityFailure = $this->patchExceptionExclusivityFailure(
            $candidate,
            $minimumComparison >= 0
        );

        if ($patchExclusivityFailure instanceof ReleasePlanValidationFailure) {
            return $patchExclusivityFailure;
        }

        $patchAuthorityFailure = $this->patchExceptionAuthorityReferenceFailure(
            $candidate,
            $approvedVersion,
            $releaseClass,
            $minimumComparison < 0
        );

        if ($patchAuthorityFailure instanceof ReleasePlanValidationFailure) {
            return $patchAuthorityFailure;
        }

        return null;
    }

    /**
     * Rejects lower-patch authority material when the approved version meets or exceeds the minimum
     *
     * @param array<string, mixed> $candidate Candidate plan input.
     */
    private function patchExceptionExclusivityFailure(
        array $candidate,
        bool $isMinimumOrHigher
    ): ?ReleasePlanValidationFailure {
        if (!$isMinimumOrHigher) {
            return null;
        }

        $references = $this->patchExceptionReferences($candidate['compatibility_exceptions'] ?? null);
        $records = $candidate['patch_exception_authorities'] ?? null;
        $approval = $candidate['release_approval_authority'] ?? null;
        $releaseApproval = ReleaseApprovalAuthority::tryFrom($approval, $this->authority);
        $approvalDigests = $releaseApproval?->patchExceptionAuthorityDigests;

        if (
            $references !== []
            || (is_array($records) && $records !== [])
            || (is_array($approvalDigests) && $approvalDigests !== [])
        ) {
            return ReleasePlanValidationFailure::PATCH_EXCEPTION_NOT_ALLOWED;
        }

        return null;
    }

    /**
     * Checks one opaque authority identity without inventing an external registry
     */
    private function isNonEmptyString(mixed $value): bool
    {
        return is_string($value) && trim($value) !== '';
    }

    /**
     * Checks a unique list against one schema-owned vocabulary
     *
     * @param mixed              $values     Candidate values.
     * @param array<int, string> $known      Known schema values.
     * @param boolean            $allowEmpty Whether an explicit empty set is meaningful.
     */
    private function isClosedStringList(mixed $values, array $known, bool $allowEmpty = false): bool
    {
        return $this->isIdentifierList($values, $allowEmpty)
            && array_diff($values, $known) === [];
    }

    /**
     * Checks an exact list of opaque non-empty identifiers
     */
    private function isIdentifierList(mixed $values, bool $allowEmpty = false): bool
    {
        if (!is_array($values) || !array_is_list($values) || (!$allowEmpty && $values === [])) {
            return false;
        }

        foreach ($values as $value) {
            if (!$this->isNonEmptyString($value)) {
                return false;
            }
        }

        return count($values) === count(array_unique($values, SORT_STRING));
    }

    /**
     * Checks a unique non-empty set of stable evidence identifiers
     */
    private function isEvidenceRequirementList(mixed $values): bool
    {
        if (!$this->isIdentifierList($values)) {
            return false;
        }

        foreach ($values as $value) {
            if (!$this->authority->isEvidenceRequirementId($value)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Checks opaque exception IDs and the exact lower-version exception payload
     */
    private function isCompatibilityExceptionList(mixed $values): bool
    {
        if (!$this->isIdentifierList($values, true)) {
            return false;
        }

        foreach ($values as $value) {
            if (str_starts_with($value, 'patch-exception:') && !$this->isExactPatchException($value)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Validates patch-exception:<id>:exact-version:<stable-semver>
     */
    private function isExactPatchException(string $exception): bool
    {
        $matches = [];

        if (
            preg_match(
                '/^patch-exception:([^:]+):exact-version:([^:]+)$/D',
                $exception,
                $matches
            ) !== 1
        ) {
            return false;
        }

        return $this->authority->isAuthorityId($matches[1])
            && StableSemVer::isValid($matches[2]);
    }

    /**
     * Returns canonical typed patch-exception authorities
     *
     * @return list<PatchExceptionAuthority>
     */
    private function patchExceptionAuthorities(mixed $values): array
    {
        if (!is_array($values) || !array_is_list($values)) {
            return [];
        }

        $records = [];

        foreach ($values as $value) {
            $record = PatchExceptionAuthority::tryFrom($value, $this->authority);

            if (!$record instanceof PatchExceptionAuthority) {
                return [];
            }

            $records[] = $record;
        }

        usort($records, static fn (PatchExceptionAuthority $left, PatchExceptionAuthority $right): int =>
            $left->exceptionId <=> $right->exceptionId);

        return $records;
    }

    /**
     * Reports incomplete, duplicate, or ambiguous patch-exception authority records
     *
     * @param array<string, mixed> $candidate Candidate plan input.
     */
    private function patchExceptionAuthorityValidationFailure(array $candidate): ?ReleasePlanValidationFailure
    {
        if (!array_key_exists('patch_exception_authorities', $candidate)) {
            return ReleasePlanValidationFailure::PATCH_EXCEPTION_AUTHORITIES_MISSING;
        }

        $values = $candidate['patch_exception_authorities'];

        if (!is_array($values) || !array_is_list($values)) {
            return ReleasePlanValidationFailure::PATCH_EXCEPTION_AUTHORITIES_INVALID;
        }

        $records = [];

        foreach ($values as $value) {
            $record = PatchExceptionAuthority::tryFrom($value, $this->authority);

            if (!$record instanceof PatchExceptionAuthority) {
                return ReleasePlanValidationFailure::PATCH_EXCEPTION_AUTHORITIES_INVALID;
            }

            if (isset($records[$record->exceptionId])) {
                if ($records[$record->exceptionId]->toArray() === $record->toArray()) {
                    return ReleasePlanValidationFailure::PATCH_EXCEPTION_AUTHORITIES_DUPLICATE;
                }

                return ReleasePlanValidationFailure::PATCH_EXCEPTION_AUTHORITIES_AMBIGUOUS;
            }

            $records[$record->exceptionId] = $record;
        }

        return null;
    }

    /**
     * Reports unresolved or mismatched patch-exception authority references
     *
     * @param array<string, mixed> $candidate Candidate plan input.
     */
    private function patchExceptionAuthorityReferenceFailure(
        array $candidate,
        string $approvedVersion,
        string $minimumReleaseClass,
        bool $requiresSingleAuthority
    ): ?ReleasePlanValidationFailure {
        $references = $this->patchExceptionReferences($candidate['compatibility_exceptions'] ?? null);
        $records = $this->patchExceptionAuthorities($candidate['patch_exception_authorities'] ?? null);

        if ($requiresSingleAuthority && $references === [] && $records === []) {
            return ReleasePlanValidationFailure::LOWER_VERSION_EXCEPTION_REQUIRED;
        }

        if (
            ($requiresSingleAuthority && (count($references) !== 1 || count($records) !== 1))
            || (!$requiresSingleAuthority && count($references) !== count($records))
            || ($requiresSingleAuthority && $references[0]['exact_version'] !== $approvedVersion)
        ) {
            return ReleasePlanValidationFailure::PATCH_EXCEPTION_AUTHORITY_MISMATCHED;
        }

        /** @var array<string, mixed> $baseline */
        $baseline = $candidate['baseline'];
        /** @var list<string> $approvals */
        $approvals = $candidate['required_approvals'];

        foreach ($references as $reference) {
            $matching = array_filter(
                $records,
                static fn (PatchExceptionAuthority $record): bool => $record->matches(
                    $reference['exception_id'],
                    $reference['exact_version'],
                    $candidate['source_commit_oid'],
                    $baseline['tag_object_oid'],
                    $baseline['peeled_commit_oid'],
                    $candidate['evidence_manifest_digest'],
                    $approvals,
                    $requiresSingleAuthority ? $minimumReleaseClass : null
                )
            );

            if (count($matching) !== 1) {
                return ReleasePlanValidationFailure::PATCH_EXCEPTION_AUTHORITY_MISMATCHED;
            }
        }

        return null;
    }

    /**
     * Returns structured patch-exception references from the compatibility set
     *
     * @return list<array{exception_id: string, exact_version: string}>
     */
    private function patchExceptionReferences(mixed $exceptions): array
    {
        if (!is_array($exceptions)) {
            return [];
        }

        $references = [];

        foreach ($exceptions as $exception) {
            $matches = [];

            if (
                is_string($exception)
                && preg_match('/^patch-exception:([^:]+):exact-version:([^:]+)$/D', $exception, $matches) === 1
            ) {
                $references[] = ['exception_id' => $matches[1], 'exact_version' => $matches[2]];
            }
        }

        return $references;
    }

    /**
     * Normalizes one validated set without changing list semantics globally
     *
     * @param array<int, string> $values Validated set members.
     *
     * @return list<string>
     */
    private function canonicalSet(array $values): array
    {
        sort($values, SORT_STRING);

        return $values;
    }

    /**
     * Reports missing, malformed, or stale release approval authority
     *
     * @param array<string, mixed> $candidate Candidate plan input.
     */
    private function releaseApprovalAuthorityValidationFailure(array $candidate): ?ReleasePlanValidationFailure
    {
        if (!array_key_exists('release_approval_authority', $candidate)) {
            return ReleasePlanValidationFailure::RELEASE_APPROVAL_AUTHORITY_MISSING;
        }

        $approval = ReleaseApprovalAuthority::tryFrom(
            $candidate['release_approval_authority'],
            $this->authority
        );

        if (!$approval instanceof ReleaseApprovalAuthority) {
            return ReleasePlanValidationFailure::RELEASE_APPROVAL_AUTHORITY_INVALID;
        }

        /** @var array<string, mixed> $baseline */
        $baseline = $candidate['baseline'];
        $authorizedClass = $this->authorizedReleaseClass(
            $baseline['version'],
            $candidate['approved_version']
        );

        if ($authorizedClass === null) {
            return null;
        }

        if (
            !$approval->matches(
                $candidate['approved_version'],
                $candidate['source_commit_oid'],
                $baseline['tag_name'],
                $baseline['tag_object_oid'],
                $baseline['peeled_commit_oid'],
                $candidate['evidence_manifest_digest'],
                $candidate['compatibility_exceptions'],
                array_map(
                    static fn (PatchExceptionAuthority $record): string => $record->authorityDigest,
                    $this->patchExceptionAuthorities($candidate['patch_exception_authorities'])
                ),
                $candidate['release_class'],
                $authorizedClass,
                $candidate['required_approvals']
            )
        ) {
            return ReleasePlanValidationFailure::RELEASE_APPROVAL_AUTHORITY_MISMATCHED;
        }

        return null;
    }

    /**
     * Derives the actual baseline-relative class of one higher exact version
     */
    private function authorizedReleaseClass(string $baselineVersion, string $approvedVersion): ?string
    {
        if ((StableSemVer::compare($approvedVersion, $baselineVersion) ?? 0) <= 0) {
            return null;
        }

        [$baselineMajor, $baselineMinor] = explode('.', $baselineVersion);
        [$approvedMajor, $approvedMinor] = explode('.', $approvedVersion);

        if (StableSemVer::compare($approvedMajor.'.0.0', $baselineMajor.'.0.0') === 1) {
            return 'major';
        }

        if (StableSemVer::compare('0.'.$approvedMinor.'.0', '0.'.$baselineMinor.'.0') === 1) {
            return 'minor';
        }

        return 'patch';
    }
}
