<?php

declare(strict_types=1);

namespace Fight\Common\Application\Release;

/**
 * Enum ReleasePlanValidationFailure
 *
 * Names each invalid immutable plan input without collapsing policy reasons.
 */
enum ReleasePlanValidationFailure: string
{
    case SCHEMA_VERSION_MISSING = 'schema_version_missing';
    case SCHEMA_VERSION_INVALID = 'schema_version_invalid';
    case APPROVED_VERSION_MISSING = 'approved_version_missing';
    case APPROVED_VERSION_INVALID = 'approved_version_invalid';
    case BASELINE_MISSING = 'baseline_missing';
    case BASELINE_INVALID = 'baseline_invalid';
    case BASELINE_VERSION_MISSING = 'baseline_version_missing';
    case BASELINE_VERSION_INVALID = 'baseline_version_invalid';
    case BASELINE_TAG_NAME_MISSING = 'baseline_tag_name_missing';
    case BASELINE_TAG_NAME_INVALID = 'baseline_tag_name_invalid';
    case RELEASE_CLASS_MISSING = 'release_class_missing';
    case RELEASE_CLASS_INVALID = 'release_class_invalid';
    case SOURCE_COMMIT_OID_MISSING = 'source_commit_oid_missing';
    case SOURCE_COMMIT_OID_INVALID = 'source_commit_oid_invalid';
    case BASELINE_TAG_OBJECT_OID_MISSING = 'baseline_tag_object_oid_missing';
    case BASELINE_TAG_OBJECT_OID_INVALID = 'baseline_tag_object_oid_invalid';
    case BASELINE_PEELED_COMMIT_OID_MISSING = 'baseline_peeled_commit_oid_missing';
    case BASELINE_PEELED_COMMIT_OID_INVALID = 'baseline_peeled_commit_oid_invalid';
    case SUPPORT_POLICY_IDENTITY_MISSING = 'support_policy_identity_missing';
    case SUPPORT_POLICY_IDENTITY_INVALID = 'support_policy_identity_invalid';
    case EXPECTED_EFFECT_CLASSES_MISSING = 'expected_effect_classes_missing';
    case EXPECTED_EFFECT_CLASSES_INVALID = 'expected_effect_classes_invalid';
    case EVIDENCE_REQUIREMENTS_MISSING = 'evidence_requirements_missing';
    case EVIDENCE_REQUIREMENTS_INVALID = 'evidence_requirements_invalid';
    case COMPATIBILITY_EXCEPTIONS_MISSING = 'compatibility_exceptions_missing';
    case COMPATIBILITY_EXCEPTIONS_INVALID = 'compatibility_exceptions_invalid';
    case PATCH_EXCEPTION_AUTHORITIES_MISSING = 'patch_exception_authorities_missing';
    case PATCH_EXCEPTION_AUTHORITIES_INVALID = 'patch_exception_authorities_invalid';
    case PATCH_EXCEPTION_AUTHORITIES_DUPLICATE = 'patch_exception_authorities_duplicate';
    case PATCH_EXCEPTION_AUTHORITIES_AMBIGUOUS = 'patch_exception_authorities_ambiguous';
    case PATCH_EXCEPTION_AUTHORITY_MISMATCHED = 'patch_exception_authority_mismatched';
    case PATCH_EXCEPTION_NOT_ALLOWED = 'patch_exception_not_allowed';
    case REQUIRED_APPROVALS_MISSING = 'required_approvals_missing';
    case REQUIRED_APPROVALS_INVALID = 'required_approvals_invalid';
    case EVIDENCE_MANIFEST_DIGEST_MISSING = 'evidence_manifest_digest_missing';
    case EVIDENCE_MANIFEST_DIGEST_INVALID = 'evidence_manifest_digest_invalid';
    case RELEASE_APPROVAL_AUTHORITY_MISSING = 'release_approval_authority_missing';
    case RELEASE_APPROVAL_AUTHORITY_INVALID = 'release_approval_authority_invalid';
    case RELEASE_APPROVAL_AUTHORITY_MISMATCHED = 'release_approval_authority_mismatched';
    case VERSION_RELATION_INVALID = 'version_relation_invalid';
    case LOWER_VERSION_EXCEPTION_REQUIRED = 'lower_version_exception_required';

    /**
     * Returns the stable detailed finding identifier
     */
    public function findingId(): string
    {
        return 'release.plan.'.$this->value;
    }

    /**
     * Returns one stable operator-facing explanation
     */
    public function message(): string
    {
        return match ($this) {
            self::RELEASE_APPROVAL_AUTHORITY_MISMATCHED =>
                'Release approval does not match bound version, classes, candidate, baseline, evidence, or exceptions.',
            self::VERSION_RELATION_INVALID =>
                'The approved version is neither the minimum, a higher version, nor the next patch version.',
            self::LOWER_VERSION_EXCEPTION_REQUIRED =>
                'A lower approved patch version requires one matching complete patch-exception authority.',
            self::PATCH_EXCEPTION_AUTHORITIES_DUPLICATE =>
                'Patch-exception authority records must not be duplicated.',
            self::PATCH_EXCEPTION_AUTHORITIES_AMBIGUOUS =>
                'One patch-exception ID cannot resolve to conflicting authority records.',
            self::PATCH_EXCEPTION_AUTHORITY_MISMATCHED =>
                'Every patch exception must resolve to one authority matching the plan bindings and approvals.',
            self::PATCH_EXCEPTION_NOT_ALLOWED =>
                'Patch-exception references, records, and approval digests are allowed only for a lower patch.',
            default => sprintf(
                'The release plan %s is %s.',
                str_replace('_', ' ', $this->field()),
                $this->isMissing() ? 'missing' : 'malformed or unsupported'
            )
        };
    }

    /**
     * Returns exactly one actionable correction
     */
    public function nextAction(): string
    {
        if ($this === self::RELEASE_APPROVAL_AUTHORITY_MISMATCHED) {
            return 'obtain_current_release_approval';
        }

        if ($this === self::VERSION_RELATION_INVALID) {
            return 'approve_valid_version_relation';
        }

        if ($this === self::LOWER_VERSION_EXCEPTION_REQUIRED) {
            return 'provide_complete_patch_exception_authority';
        }

        if ($this === self::PATCH_EXCEPTION_AUTHORITIES_DUPLICATE) {
            return 'remove_duplicate_patch_exception_authority';
        }

        if ($this === self::PATCH_EXCEPTION_AUTHORITIES_AMBIGUOUS) {
            return 'resolve_patch_exception_authority_ambiguity';
        }

        if ($this === self::PATCH_EXCEPTION_AUTHORITY_MISMATCHED) {
            return 'correct_patch_exception_authority_bindings';
        }

        if ($this === self::PATCH_EXCEPTION_NOT_ALLOWED) {
            return 'remove_patch_exception_material';
        }

        return ($this->isMissing() ? 'provide_' : 'correct_').$this->field();
    }

    /**
     * Returns the authority field independent of failure kind
     */
    private function field(): string
    {
        return preg_replace('/_(?:missing|invalid)$/D', '', $this->value) ?? $this->value;
    }

    /**
     * Reports whether this reason identifies absence rather than malformed authority
     */
    private function isMissing(): bool
    {
        return str_ends_with($this->value, '_missing');
    }
}
