<?php

namespace App\Enums\Matching;

/**
 * Stable reason codes stored in pod_match_reasons.code (UNA-013). The database never stores prose;
 * App\Services\Matching\PodMatchPresenter turns code + context into translated text.
 */
enum MatchReasonCode: string
{
    // Any criterion the worker requirement does not configure (inactive, excluded from the score).
    case NOT_CONFIGURED = 'not_configured';

    case SKILLS_ALL_MATCHED = 'skills_all_matched';
    case SKILLS_SOME_MISSING = 'skills_some_missing';
    case SKILLS_NONE_MATCHED = 'skills_none_matched';

    case PROFICIENCY_MET = 'proficiency_met';
    case PROFICIENCY_PARTIAL = 'proficiency_partial';
    case PROFICIENCY_NOT_MET = 'proficiency_not_met';

    case EXPERIENCE_MET = 'experience_met';
    case EXPERIENCE_PARTIAL = 'experience_partial';
    case EXPERIENCE_NONE = 'experience_none';

    case ROLE_PRIMARY = 'role_primary';
    case ROLE_PREFERRED = 'role_preferred';
    case ROLE_HISTORICAL_ONLY = 'role_historical_only';
    case ROLE_MISMATCH = 'role_mismatch';

    case AVAILABILITY_MET = 'availability_met';
    case AVAILABILITY_PARTIAL = 'availability_partial';
    case AVAILABILITY_NOT_MET = 'availability_not_met';
    case AVAILABILITY_UNAVAILABLE = 'availability_unavailable';

    case LOCATION_MET = 'location_met';
    case LOCATION_PARTIAL = 'location_partial';
    case LOCATION_NOT_MET = 'location_not_met';

    case PROJECT_TYPE_MATCHED = 'project_type_matched';
    case PROJECT_TYPE_NOT_MATCHED = 'project_type_not_matched';

    case WORK_MODE_COMPATIBLE = 'work_mode_compatible';
    case WORK_MODE_INCOMPATIBLE = 'work_mode_incompatible';

    case ENGAGEMENT_COMPATIBLE = 'engagement_compatible';
    case ENGAGEMENT_INCOMPATIBLE = 'engagement_incompatible';
}
