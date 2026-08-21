<?php

declare(strict_types=1);

namespace Fight\Common\Application\Release;

/**
 * Class ReleaseApprovalAuthority
 *
 * Binds one exact release decision to the immutable candidate, baseline, evidence, and exceptions.
 */
final readonly class ReleaseApprovalAuthority
{
    /** @var list<string> */
    private const array FIELDS = [
        'approval_id',
        'approved_version',
        'authorized_release_class',
        'baseline_peeled_commit_oid',
        'baseline_tag_name',
        'baseline_tag_object_oid',
        'candidate_commit_oid',
        'compatibility_exception_ids',
        'evidence_manifest_digest',
        'minimum_release_class',
        'patch_exception_authority_digests'
    ];
    /** @var list<string> */
    private const array RELEASE_CLASSES = ['patch', 'minor', 'major'];

    /**
     * Constructs ReleaseApprovalAuthority
     *
     * @param string             $approvalId                Stable repository approval identity.
     * @param string             $approvedVersion           Exact approved version.
     * @param string             $candidateCommitOid        Exact candidate commit identity.
     * @param string             $baselineTagName           Canonical baseline tag.
     * @param string             $baselineTagObjectOid      Exact baseline tag-object identity.
     * @param string             $baselinePeeledCommitOid   Exact baseline peeled-commit identity.
     * @param string             $evidenceManifestDigest    Exact evidence-manifest digest.
     * @param array<int, string> $compatibilityExceptionIds Exact compatibility-exception set.
     * @param array<int, string> $patchExceptionAuthorityDigests Approved authority identities.
     * @param string             $minimumReleaseClass       Inspected minimum release class.
     * @param string             $authorizedReleaseClass    Actual baseline-relative release class.
     */
    private function __construct(
        public string $approvalId,
        public string $approvedVersion,
        public string $candidateCommitOid,
        public string $baselineTagName,
        public string $baselineTagObjectOid,
        public string $baselinePeeledCommitOid,
        public string $evidenceManifestDigest,
        public array $compatibilityExceptionIds,
        public array $patchExceptionAuthorityDigests,
        public string $minimumReleaseClass,
        public string $authorizedReleaseClass
    ) {
    }

    /**
     * Creates one complete canonical approval or rejects the whole record
     */
    public static function tryFrom(mixed $value, ReleaseAuthorityValidator $authority): ?self
    {
        if (!is_array($value) || array_is_list($value)) {
            return null;
        }

        $fields = array_keys($value);
        sort($fields, SORT_STRING);

        if ($fields !== self::FIELDS) {
            return null;
        }

        if (
            !$authority->isAuthorityId($value['approval_id'])
            || !is_string($value['approved_version'])
            || !StableSemVer::isValid($value['approved_version'])
            || !$authority->isGitObjectId($value['candidate_commit_oid'])
            || !is_string($value['baseline_tag_name'])
            || trim($value['baseline_tag_name']) === ''
            || !$authority->isGitObjectId($value['baseline_tag_object_oid'])
            || !$authority->isGitObjectId($value['baseline_peeled_commit_oid'])
            || !is_string($value['evidence_manifest_digest'])
            || preg_match('/\A[0-9a-f]{64}\z/D', $value['evidence_manifest_digest']) !== 1
            || !self::isExceptionSet($value['compatibility_exception_ids'])
            || !self::isDigestSet($value['patch_exception_authority_digests'])
            || !in_array($value['minimum_release_class'], self::RELEASE_CLASSES, true)
            || !in_array($value['authorized_release_class'], self::RELEASE_CLASSES, true)
        ) {
            return null;
        }

        $exceptions = $value['compatibility_exception_ids'];
        $patchAuthorities = $value['patch_exception_authority_digests'];
        sort($exceptions, SORT_STRING);
        sort($patchAuthorities, SORT_STRING);

        return new self(
            $value['approval_id'],
            $value['approved_version'],
            $value['candidate_commit_oid'],
            $value['baseline_tag_name'],
            $value['baseline_tag_object_oid'],
            $value['baseline_peeled_commit_oid'],
            $value['evidence_manifest_digest'],
            $exceptions,
            $patchAuthorities,
            $value['minimum_release_class'],
            $value['authorized_release_class']
        );
    }

    /**
     * Returns the complete canonical approval record
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'approval_id'                       => $this->approvalId,
            'approved_version'                  => $this->approvedVersion,
            'candidate_commit_oid'              => $this->candidateCommitOid,
            'baseline_tag_name'                 => $this->baselineTagName,
            'baseline_tag_object_oid'           => $this->baselineTagObjectOid,
            'baseline_peeled_commit_oid'        => $this->baselinePeeledCommitOid,
            'evidence_manifest_digest'          => $this->evidenceManifestDigest,
            'compatibility_exception_ids'       => $this->compatibilityExceptionIds,
            'patch_exception_authority_digests' => $this->patchExceptionAuthorityDigests,
            'minimum_release_class'             => $this->minimumReleaseClass,
            'authorized_release_class'          => $this->authorizedReleaseClass
        ];
    }

    /**
     * Verifies this approval against every enclosing release authority binding
     *
     * @param string             $approvedVersion           Enclosing exact approved version.
     * @param string             $candidateCommitOid        Enclosing candidate commit identity.
     * @param string             $baselineTagName           Enclosing canonical baseline tag.
     * @param string             $baselineTagObjectOid      Enclosing baseline tag-object identity.
     * @param string             $baselinePeeledCommitOid   Enclosing baseline peeled-commit identity.
     * @param string             $evidenceManifestDigest    Enclosing evidence-manifest digest.
     * @param array<int, string> $compatibilityExceptionIds Enclosing compatibility exceptions.
     * @param array<int, string> $patchExceptionAuthorityDigests Enclosing patch authority identities.
     * @param string             $minimumReleaseClass       Enclosing inspected minimum class.
     * @param string             $authorizedReleaseClass    Enclosing actual release class.
     * @param array<int, string> $requiredApprovals         Enclosing required approvals.
     */
    public function matches(
        string $approvedVersion,
        string $candidateCommitOid,
        string $baselineTagName,
        string $baselineTagObjectOid,
        string $baselinePeeledCommitOid,
        string $evidenceManifestDigest,
        array $compatibilityExceptionIds,
        array $patchExceptionAuthorityDigests,
        string $minimumReleaseClass,
        string $authorizedReleaseClass,
        array $requiredApprovals
    ): bool {
        sort($compatibilityExceptionIds, SORT_STRING);
        sort($patchExceptionAuthorityDigests, SORT_STRING);

        return $this->approvedVersion === $approvedVersion
            && $this->candidateCommitOid === $candidateCommitOid
            && $this->baselineTagName === $baselineTagName
            && $this->baselineTagObjectOid === $baselineTagObjectOid
            && $this->baselinePeeledCommitOid === $baselinePeeledCommitOid
            && $this->evidenceManifestDigest === $evidenceManifestDigest
            && $this->compatibilityExceptionIds === $compatibilityExceptionIds
            && $this->patchExceptionAuthorityDigests === $patchExceptionAuthorityDigests
            && $this->minimumReleaseClass === $minimumReleaseClass
            && $this->authorizedReleaseClass === $authorizedReleaseClass
            && in_array($this->approvalId, $requiredApprovals, true);
    }

    /**
     * Checks one canonical set of complete compatibility-exception identifiers
     */
    private static function isExceptionSet(mixed $values): bool
    {
        if (!is_array($values) || !array_is_list($values)) {
            return false;
        }

        foreach ($values as $value) {
            if (!is_string($value) || trim($value) === '') {
                return false;
            }
        }

        return count($values) === count(array_unique($values, SORT_STRING));
    }

    /**
     * Checks the canonical content identities of every patch authority approved with this release
     */
    private static function isDigestSet(mixed $values): bool
    {
        if (!is_array($values) || !array_is_list($values)) {
            return false;
        }

        foreach ($values as $value) {
            if (!is_string($value) || preg_match('/\A[0-9a-f]{64}\z/D', $value) !== 1) {
                return false;
            }
        }

        return count($values) === count(array_unique($values, SORT_STRING));
    }
}
