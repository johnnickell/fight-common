<?php

declare(strict_types=1);

namespace Fight\Common\Application\Release\Boundary;

/**
 * Enum ReleasePlanAuthorityStatus
 *
 * Classifies current policy and approval authority without collapsing drift.
 */
enum ReleasePlanAuthorityStatus: string
{
    case VERIFIED = 'verified';
    case SUPPORT_POLICY_DRIFT = 'support_policy_drift';
    case APPROVAL_DRIFT = 'approval_drift';
    case EVIDENCE_DRIFT = 'evidence_drift';
    case COMPATIBILITY_DRIFT = 'compatibility_drift';
    case REFUSED = 'refused';
    case FAILED = 'failed';
    case UNCERTAIN = 'uncertain';
}
