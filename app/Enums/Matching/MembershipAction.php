<?php

namespace App\Enums\Matching;

/** What applying a POD's matching mode did to a candidate's membership (UNA-014). */
enum MembershipAction: string
{
    // No action wanted: not qualified, recommend-only mode, or a draft POD.
    case NONE = 'none';
    // A new pod_users row was entered (pending approval or Outer Circle).
    case CREATED = 'created';
    // An applicant (matched / pending approval) was moved to the Outer Circle (automatic mode).
    case ADVANCED = 'advanced';
    // A membership already exists and is left as it is (any established decision).
    case UNCHANGED = 'unchanged';
    // The canonical transition refused (e.g. incomplete profile); nothing changed.
    case BLOCKED = 'blocked';
}
