<?php

declare(strict_types=1);

namespace Fight\Release\Application;

/**
 * Class PatchExceptionAuthority
 *
 * Binds one narrowly approved incompatible-patch exception to immutable release evidence.
 */
final readonly class PatchExceptionAuthority
{
    /** @var list<string> */
    private const array FIELDS = [
        'authority_digest',
        'baseline_peeled_commit_oid',
        'baseline_tag_object_oid',
        'candidate_commit_oid',
        'compatibility_assessment',
        'consumer_impact',
        'emergency_class',
        'evidence_manifest_digest',
        'exact_version',
        'exception_id',
        'mitigation',
        'no_compatible_repair',
        'overridden_finding_ids',
        'recovery_posture',
        'release_authority_approval',
        'test_evidence'
    ];

    /**
     * Constructs PatchExceptionAuthority
     *
     * @param string                   $exceptionId                Stable exception authority identity.
     * @param string                   $exactVersion               Exact approved patch version.
     * @param string                   $candidateCommitOid         Exact candidate commit identity.
     * @param string                   $baselineTagObjectOid       Exact baseline tag-object identity.
     * @param string                   $baselinePeeledCommitOid    Exact baseline peeled-commit identity.
     * @param string                   $emergencyClass             Closed eligible emergency class.
     * @param array<string, mixed>     $noCompatibleRepair        Positive attestation and evidence.
     * @param list<array<string, string>> $compatibilityAssessment Canonical inspected category evidence.
     * @param array<int, string>       $overriddenFindingIds      Exact non-patch finding set.
     * @param string                   $consumerImpact             Documented consumer impact.
     * @param string                   $mitigation                 Documented mitigation.
     * @param array<int, string>       $testEvidence               Stable test-evidence identities.
     * @param string                   $recoveryPosture            Documented recovery posture.
     * @param string                   $evidenceManifestDigest     Exact evidence-manifest digest.
     * @param string                   $releaseAuthorityApproval   Repository approval identity.
     * @param string                   $authorityDigest            Canonical authority content identity.
     * @param string                   $minimumReleaseClass        Derived inspected minimum release class.
     */
    private function __construct(
        public string $exceptionId,
        public string $exactVersion,
        public string $candidateCommitOid,
        public string $baselineTagObjectOid,
        public string $baselinePeeledCommitOid,
        public string $emergencyClass,
        public array $noCompatibleRepair,
        public array $compatibilityAssessment,
        public array $overriddenFindingIds,
        public string $consumerImpact,
        public string $mitigation,
        public array $testEvidence,
        public string $recoveryPosture,
        public string $evidenceManifestDigest,
        public string $releaseAuthorityApproval,
        public string $authorityDigest,
        private string $minimumReleaseClass
    ) {
    }

    /**
     * Creates one complete authority record or rejects the whole record
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

        $assessment = new CompatibilityAssessment($authority)->assess($value['compatibility_assessment']);
        $noCompatibleRepair = self::noCompatibleRepair($value['no_compatible_repair'], $authority);

        if (
            !$authority->isAuthorityId($value['exception_id'])
            || !is_string($value['exact_version'])
            || !StableSemVer::isValid($value['exact_version'])
            || !$authority->isGitObjectId($value['candidate_commit_oid'])
            || !$authority->isGitObjectId($value['baseline_tag_object_oid'])
            || !$authority->isGitObjectId($value['baseline_peeled_commit_oid'])
            || !in_array(
                $value['emergency_class'],
                ['security', 'imminent-data-loss', 'critical-interoperability'],
                true
            )
            || $noCompatibleRepair === null
            || $assessment['status'] !== 'valid'
            || !self::isFindingSet($value['overridden_finding_ids'], $authority)
            || !self::isNonEmptyString($value['consumer_impact'])
            || !self::isNonEmptyString($value['mitigation'])
            || !self::isEvidenceSet($value['test_evidence'], $authority)
            || !self::isNonEmptyString($value['recovery_posture'])
            || !is_string($value['evidence_manifest_digest'])
            || preg_match('/\A[0-9a-f]{64}\z/D', $value['evidence_manifest_digest']) !== 1
            || !$authority->isAuthorityId($value['release_authority_approval'])
            || !is_string($value['authority_digest'])
            || preg_match('/\A[0-9a-f]{64}\z/D', $value['authority_digest']) !== 1
        ) {
            return null;
        }

        $findings = $value['overridden_finding_ids'];
        $tests = $value['test_evidence'];
        sort($findings, SORT_STRING);
        sort($tests, SORT_STRING);

        $actualFindings = [];

        foreach ($assessment['categories'] as $entry) {
            if ($entry['classification'] !== 'patch') {
                $actualFindings[] = $entry['finding_id'];
            }
        }

        sort($actualFindings, SORT_STRING);

        if ($actualFindings === [] || $findings !== $actualFindings) {
            return null;
        }

        /** @var string $minimumReleaseClass */
        $minimumReleaseClass = $assessment['minimum_increment'];

        $record = new self(
            $value['exception_id'],
            $value['exact_version'],
            $value['candidate_commit_oid'],
            $value['baseline_tag_object_oid'],
            $value['baseline_peeled_commit_oid'],
            $value['emergency_class'],
            $noCompatibleRepair,
            $assessment['categories'],
            $findings,
            $value['consumer_impact'],
            $value['mitigation'],
            $tests,
            $value['recovery_posture'],
            $value['evidence_manifest_digest'],
            $value['release_authority_approval'],
            $value['authority_digest'],
            $minimumReleaseClass
        );

        $content = $record->toArray();
        unset($content['authority_digest']);

        if (hash('sha256', new CanonicalJson()->encode($content)) !== $record->authorityDigest) {
            return null;
        }

        return $record;
    }

    /**
     * Returns the canonical complete authority record
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'authority_digest'           => $this->authorityDigest,
            'exception_id'               => $this->exceptionId,
            'exact_version'              => $this->exactVersion,
            'candidate_commit_oid'       => $this->candidateCommitOid,
            'baseline_tag_object_oid'    => $this->baselineTagObjectOid,
            'baseline_peeled_commit_oid' => $this->baselinePeeledCommitOid,
            'emergency_class'            => $this->emergencyClass,
            'no_compatible_repair'       => $this->noCompatibleRepair,
            'compatibility_assessment'   => $this->compatibilityAssessment,
            'overridden_finding_ids'     => $this->overriddenFindingIds,
            'consumer_impact'            => $this->consumerImpact,
            'mitigation'                 => $this->mitigation,
            'test_evidence'              => $this->testEvidence,
            'recovery_posture'           => $this->recoveryPosture,
            'evidence_manifest_digest'   => $this->evidenceManifestDigest,
            'release_authority_approval' => $this->releaseAuthorityApproval
        ];
    }

    /**
     * Verifies the record against the referenced exception and enclosing plan authority
     *
     * @param string       $exceptionId               Referenced exception identity.
     * @param string       $exactVersion              Referenced exact version.
     * @param string       $candidateCommitOid        Enclosing candidate identity.
     * @param string       $baselineTagObjectOid      Enclosing baseline tag-object identity.
     * @param string       $baselinePeeledCommitOid   Enclosing baseline peeled-commit identity.
     * @param string       $evidenceManifestDigest    Enclosing evidence-manifest digest.
     * @param array<int, string> $requiredApprovals   Enclosing plan approvals.
     * @param string|null  $minimumReleaseClass        Required enclosing inspected minimum class, when governed.
     */
    public function matches(
        string $exceptionId,
        string $exactVersion,
        string $candidateCommitOid,
        string $baselineTagObjectOid,
        string $baselinePeeledCommitOid,
        string $evidenceManifestDigest,
        array $requiredApprovals,
        ?string $minimumReleaseClass = null
    ): bool {
        return $this->exceptionId === $exceptionId
            && $this->exactVersion === $exactVersion
            && $this->candidateCommitOid === $candidateCommitOid
            && $this->baselineTagObjectOid === $baselineTagObjectOid
            && $this->baselinePeeledCommitOid === $baselinePeeledCommitOid
            && $this->evidenceManifestDigest === $evidenceManifestDigest
            && ($minimumReleaseClass === null || $this->minimumReleaseClass === $minimumReleaseClass)
            && in_array($this->releaseAuthorityApproval, $requiredApprovals, true);
    }

    /**
     * Checks one required narrative or identity value
     */
    private static function isNonEmptyString(mixed $value): bool
    {
        return is_string($value) && trim($value) !== '';
    }

    /**
     * Checks one unique non-empty set of stable evidence identities
     */
    private static function isEvidenceSet(mixed $values, ReleaseAuthorityValidator $authority): bool
    {
        if (!is_array($values) || !array_is_list($values) || $values === []) {
            return false;
        }

        foreach ($values as $value) {
            if (!$authority->isEvidenceRequirementId($value)) {
                return false;
            }
        }

        return count($values) === count(array_unique($values, SORT_STRING));
    }

    /**
     * Checks one unique non-empty set of category-scoped compatibility findings
     */
    private static function isFindingSet(mixed $values, ReleaseAuthorityValidator $authority): bool
    {
        if (!is_array($values) || !array_is_list($values) || $values === []) {
            return false;
        }

        foreach ($values as $value) {
            if (
                !is_string($value)
                || !str_starts_with($value, 'release.compatibility.')
                || !$authority->isAuthorityId($value)
            ) {
                return false;
            }
        }

        return count($values) === count(array_unique($values, SORT_STRING));
    }

    /**
     * Returns one canonical positive no-compatible-repair attestation
     *
     * @return array{attested: true, evidence_ids: list<string>}|null
     */
    private static function noCompatibleRepair(mixed $value, ReleaseAuthorityValidator $authority): ?array
    {
        if (!is_array($value) || array_is_list($value)) {
            return null;
        }

        $fields = array_keys($value);
        sort($fields, SORT_STRING);

        if (
            $fields !== ['attested', 'evidence_ids']
            || ($value['attested'] ?? null) !== true
            || !self::isEvidenceSet($value['evidence_ids'] ?? null, $authority)
        ) {
            return null;
        }

        $evidence = $value['evidence_ids'];
        sort($evidence, SORT_STRING);

        return ['attested' => true, 'evidence_ids' => $evidence];
    }
}
