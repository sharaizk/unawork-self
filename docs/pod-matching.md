# POD matching: scoring (UNA-013), configuration (UNA-014), user-to-POD matching (UNA-015), queued generation (UNA-016), recommendations (UNA-017) and business review (UNA-018)

This document describes the code as implemented. The only scoring code is
`App\Services\Matching\PodMatchScorer`. Configuration lives in `config/pod_matching.php`.

A match always compares **one employee** with **one active POD worker requirement**
(`pod_worker_requirements`). A POD with three requirements gives an employee up to three
independent matches. Nothing here chooses a "best" requirement.

There are three separate layers:

1. **Scoring** (UNA-013, §2–§8): `PodMatchScorer` / `PodMatchService` compute and persist a score,
   a status (`eligible` / `disqualified` / `not_scorable`) and reasons. Information only.
2. **Qualification** (UNA-014, §10): `MatchQualification` decides whether a persisted match is
   good enough for its POD (status, threshold, active requirement, published POD).
3. **Membership action** (UNA-014, §11): `PodMatchingWorkflowService` applies the POD's matching
   mode to a qualified match, only through `PodStateTransitionService`.

## 1. Code map

| Piece | Class / file |
|---|---|
| Criterion keys (backed enum) | `App\Enums\Matching\MatchCriterion` |
| Match status | `App\Enums\Matching\MatchStatus` (`eligible`, `disqualified`, `not_scorable`) |
| Reason codes | `App\Enums\Matching\MatchReasonCode` |
| Which criteria a requirement configures, and the mandatory rule | `App\Services\Matching\MatchingConfiguration` |
| Pure scorer | `App\Services\Matching\PodMatchScorer` → `MatchResult` / `CriterionResult` |
| Persistence | `App\Services\Matching\PodMatchService` (`score()`, `evaluate()`, `persist()`) |
| Display | `App\Services\Matching\PodMatchPresenter`, `App\Http\Resources\PodMatchResource` |
| Models | `PodMatch` (`pod_matches`), `PodMatchReason` (`pod_match_reasons`) |
| Requirement fields | `App\Models\Concerns\HasMatchingConfiguration` (used by `PodWorkerRequirement` and `WorkspaceWorkerRequirement`) |
| Config | `config/pod_matching.php` (`version`, `weights`, `availability`, `default_minimum_threshold`, `algorithm`, `algorithms`, `queue`, `jobs`, `max_timezone_tolerance_hours`) |
| POD matching mode (UNA-014) | `App\Enums\Pod\PodMatchingMode` (`recommend_only`, `pending_approval`, `automatic`) |
| Qualification (UNA-014) | `App\Services\Matching\MatchQualification`, `App\Enums\Matching\QualificationReason` |
| Mode application (UNA-014) | `App\Services\Matching\PodMatchingWorkflowService::evaluateAndApply()` → `PodMatchOutcome` (`App\Enums\Matching\MembershipAction`) |
| Change detection (UNA-014/016) | `App\Services\Matching\PodMatchRecalculationService` (POD), `EmployeeMatchingFingerprint` (employee) |
| Queued generation (UNA-016) | `App\Jobs\Matching\GenerateMatchesForEmployee`, `GenerateMatchesForPod`, `GenerateEmployeePodMatch`; `MatchGenerationDispatcher` |
| Algorithm contract (UNA-016) | `App\Contracts\PodMatchingAlgorithm`, `PodMatchingAlgorithmResolver` |
| Business match review (UNA-018) | `PodMatchReview` (`pod_match_reviews`), `PodMatchingExclusion` (`pod_matching_exclusions`), `PodMatchReviewService`, `PodMatchReviewDecisionService`, `PodMatchingExclusionService`, `PodMatchReviewResolver`, `PodMatchReviewController`, `resources/views/pod/match_reviews/*` |
| Employee recommendations (UNA-017) | `PodRecommendationService` → `PodRecommendation`; `PodRecommendationDismissalService`; `PodRecommendationController`; `resources/views/pod/recommendations/*` |
| User-to-POD matching (UNA-015) | `App\Services\Matching\DeterministicPodMatchingService` → `PodMatchingResult` |

## 2. Criteria

| # | Key | Weight | Active (configured) when the requirement has… | POD-side source | Employee-side source |
|---|---|---|---|---|---|
| 1 | `skill_coverage` | 25 | at least one required skill | `pod_worker_requirement_skill` | `user_skills` (`users.skills`) |
| 2 | `skill_proficiency` | 15 | at least one skill **and** `minimum_skill_proficiency` | `minimum_skill_proficiency` + skills | `user_skills.proficiency` |
| 3 | `experience` | 15 | `job_title_id` **and** `minimum_years_experience` > 0 | `job_title_id`, `minimum_years_experience` | `profile_experiences` (`job_title_id`, dates, `is_current`); fallback `profiles.years_experience` + `profiles.job_title_id` |
| 4 | `role` | 15 | `job_title_id` | `job_title_id` | `profiles.job_title_id`, `profile_preferred_roles` |
| 5 | `availability` | 10 | `required_start_date` and/or `minimum_hours_per_week` > 0 | `required_start_date`, `minimum_hours_per_week` | `profiles.availability_status`, `available_from`, `hours_per_week` |
| 6 | `location` | 10 | a `location` with at least one normalized token and/or a `timezone` | `location`, `timezone`, `timezone_tolerance_hours` | `profiles.city`, `state`, `country`, `timezone` |
| 7 | `project_type` | 10 | `project_type` | `project_type` (`ProjectType` enum) | `profile_project_types` |
| 8 | `work_mode` | 0 | `preferred_work_mode` | `preferred_work_mode` | `profiles.preferred_work_mode` |
| 9 | `engagement_type` | 0 | `preferred_engagement_type` | `preferred_engagement_type` | `profiles.preferred_engagement_type` |

Criteria 1–7 make up the weighted score (the weights total 100). Criteria 8–9 are **compatibility
checks**. They never add or remove points. They are explained in every match, and the business can
make them mandatory.

New POD-side columns (UNA-013, on both `workspace_worker_requirements` and
`pod_worker_requirements`, all nullable):

- `required_start_date` (date)
- `minimum_hours_per_week` (1–168)
- `timezone` (IANA identifier, validated with `timezone:all`)
- `timezone_tolerance_hours` (0–14)
- `project_type` (`ProjectType`)
- `mandatory_criteria`: a JSON list of `MatchCriterion` values, stored unique and in declaration order. Null or empty means nothing is mandatory.

## 3. Score formula

All arithmetic is integer. `round_half_up(a / b)` is `intdiv(2a + b, 2b)`.

1. Each **active** criterion `c` yields an attainment fraction `num_c / den_c` in `[0, 1]`. The rules for each criterion are in §4.
2. `awarded_c` (hundredths of a point) is `round_half_up(weight_c × num_c × 100 / den_c)`.
3. `active_weight` is `Σ weight_c` over active criteria. The compatibility checks add 0.
4. `score` (hundredths) is `round_half_up(Σ awarded_c × 100 / active_weight)`, capped at 10000. It is stored as `decimal(5,2)` (`"77.78"`).
5. If `active_weight = 0` (no weighted criterion is configured), the score is `0.00`. Nothing is divided.

An inactive criterion is stored with `active = false`, `code = not_configured` and 0/weight points.
It is not part of `active_weight`, so it never lowers the score. The weights of the active criteria
are renormalized to 100.

A criterion **passes** when `num = den`, which means full attainment.

## 4. Criterion rules

### 4.1 Required skill coverage
- `num / den` is the number of required skills the employee holds, divided by the number of required skills.
- Skills are compared by id only, never by label.
- Context: `matched` and `missing` (`[{id, name}]`), `required_count`, `matched_count`.
- Codes: `skills_all_matched`, `skills_some_missing`, `skills_none_matched`.

### 4.2 Skill proficiency
- The ordered scale (`SkillProficiency::rank()`) is beginner 1 < intermediate 2 < advanced 3 < expert 4.
- Only the requirement's skills are evaluated. Each skill contributes `min(actual_rank, required_rank)`.
- Rank 0 applies when the employee does not hold the skill, or holds it with a null (legacy) proficiency. A null proficiency therefore never meets a minimum.
- `num / den` is `Σ min(actual, required)` divided by `required_rank × number of required skills`.
- Context: `required` (enum value), `skills` (`[{id, name, held, actual, met}]` where `actual` is the enum value or null), `met_count`, `required_count`.
- Codes: `proficiency_met`, `proficiency_partial`, `proficiency_not_met`.

### 4.3 Relevant experience
- **Relevant** means a `profile_experiences` row with the requirement's `job_title_id`. Experience in other roles is ignored.
- A row is measurable when it has a `start_date` and an end. The end is `end_date`, or the evaluation date when `is_current`.
- A non-current row without an `end_date` is skipped (`skipped_count`). Ends after the evaluation date are clipped to it, and empty or negative ranges are skipped.
- The measurable ranges are half-open `[start, end)` day ranges. They are merged, and overlapping or touching ranges are counted once.
- `relevant_days` is the merged total. `relevant_years` is `round_half_up(days × 1000 / 36525)` tenths, using 365.25 days per year.
- Structured: `num` is `min(days × 1000, minimum_tenths × 36525)` and `den` is `minimum_tenths × 36525`. Experience above the minimum is capped at the weight.
- **Fallback**: only when there is no measurable relevant row **and** `profiles.job_title_id` equals the required role **and** `years_experience` is set. Then `num` is `min(years_tenths, minimum_tenths)` and `den` is `minimum_tenths`, with context `source = aggregate_fallback` and `fallback_used = true`.
- Otherwise `num = 0` (`source = none`).
- Codes: `experience_met`, `experience_partial`, `experience_none`.

### 4.4 Role
- The criterion passes (1/1) when the required role is the employee's primary role (`role_primary`) or one of their preferred roles (`role_preferred`).
- A role held only historically is reported as `role_historical_only` and scores 0. It is **not** a role match.
- Otherwise the code is `role_mismatch`.

### 4.5 Availability
- `availability_status` resolves to an "available on" date relative to the evaluation date:

  | Status | Available on |
  |---|---|
  | `immediately` | evaluation date |
  | `within_two_weeks` | evaluation date + `within_two_weeks_days` (14) |
  | `within_one_month` | evaluation date + `within_one_month_months` (1, `addMonthsNoOverflow`) |
  | `specific_date` | `max(available_from, evaluation date)`; unknown if `available_from` is null |
  | `unavailable` or null | unknown |

- **Start check** (when `required_start_date` is set) passes if the available-on date is on or before `max(required_start_date, evaluation date)`. A start date already in the past means "as soon as possible".
- **Hours check** (when `minimum_hours_per_week` is set) passes if `hours_per_week ≥ minimum`. A null `hours_per_week` fails.
- `num / den` is passed checks divided by configured checks.
- `unavailable` fails every check and gives code `availability_unavailable`.
- Other codes: `availability_met`, `availability_partial`, `availability_not_met`.
- The scorer takes the evaluation time as an argument (`PodMatchService` defaults to `now()`), so results are reproducible.

### 4.6 Location and timezone
- There is no geocoding and no external call.
- **Normalization:** values are lower-cased (multibyte). Every run of characters that is not a letter or digit becomes one space, and the result is trimmed. For example, `" SRI-LANKA "` becomes `"sri lanka"`.
- **Location check** (when the requirement `location` has tokens):
  - The requirement location is split on commas, and each part is normalized. Empty and duplicate parts are dropped.
  - The check passes when every token equals the employee's normalized `city`, `state` or `country`. `"Colombo, Sri Lanka"` needs both.
  - Free text such as `"Remote"` is a token too, and it will not match a city or country.
- **Timezone check** (when the requirement `timezone` is set):
  - Offsets are taken from the IANA rules **at the evaluation timestamp**, so they follow DST.
  - The check passes when `|employee offset − required offset|` in minutes is at most `timezone_tolerance_hours × 60`. A null tolerance is 0, meaning the same offset.
  - A missing employee timezone (`missing`) fails. So does one that is not a known IANA identifier (`invalid`), including offsets like `+05:30` and abbreviations like `IST`.
  - Backward-compatible aliases such as `Asia/Calcutta` are accepted.
- `num / den` is passed checks divided by configured checks.
- Context: `required_location`, `candidate_location`, `location_met`, `unmatched_tokens`, `required_timezone`, `candidate_timezone`, `tolerance_hours`, `offset_difference_minutes`, `timezone_status`, `timezone_met`.
- Codes: `location_met`, `location_partial`, `location_not_met`.

### 4.7 Project type
- The criterion passes (1/1) on an exact enum match between `project_type` and any of the employee's `profile_project_types`.
- Codes: `project_type_matched`, `project_type_not_matched`.

### 4.8 Compatibility checks (unweighted)
- `work_mode` is compatible when the employee's preferred work mode equals the required one, or either side is `flexible`. A null employee value is incompatible.
- `engagement_type` is compatible only on an exact match. A null employee value is incompatible.

## 5. Mandatory criteria and status

- A company marks criteria as mandatory per requirement (`mandatory_criteria`), both on company templates (`/company/onboarding`) and on POD snapshots (`/pods/{pod}/setup`).
- Only a **configured** criterion may be mandatory. This is enforced in three places:
  - `ValidatesMatchingConfiguration`: the `Enum` rule rejects unknown keys, and an after-hook rejects criteria that are not configured.
  - `MatchingConfiguration::assertValid()`: called by `PodService` and `BusinessOnboardingService` after a requirement and its skills are saved. It runs inside their transaction and throws `InvalidMatchingConfiguration`, which is a `ValidationException`.
  - The same `assertValid()` call runs on template import.
- **Status:**
  - `disqualified` when any active mandatory criterion did not pass. Every failed mandatory criterion is listed in `MatchResult::$failedMandatory` and stored with `disqualifying = true`.
  - Otherwise `not_scorable` when `active_weight = 0`.
  - Otherwise `eligible`.
- The numeric score is **kept** when a match is disqualified. It is never forced to zero.
- A mandatory criterion that is somehow not configured (legacy data) is shown with `mandatory = true` and `active = false`, and it never disqualifies.
- Scoring (`PodMatchScorer`, `PodMatchService`) never writes `pod_users`, workspace or project memberships, histories or notifications, and never reads `pods.matching_mode`. Membership actions belong to `PodMatchingWorkflowService` (§11).
- **Optional vs mandatory:** a configured criterion that is not ticked as mandatory is optional. An optional weighted criterion only adds to or subtracts from the score; an optional compatibility check (work mode, engagement type) only explains. A mandatory criterion that fails disqualifies whatever the score. There is no second mandatory/optional structure: `mandatory_criteria` is the only list.

## 6. Persistence

`pod_matches`:
- one row per `(user_id, pod_worker_requirement_id)`, enforced by a unique index
- columns: `pod_id`, `score decimal(5,2)`, `status`, `active_weight`, `scoring_version` (from `config('pod_matching.version')`), `evaluated_at`, timestamps
- indexes: `(pod_worker_requirement_id, status, score)` and `(pod_id, status, score)`
- foreign keys: `pod_id`, `pod_worker_requirement_id` and `user_id` all **cascade on delete**, so deleting the POD, the requirement or the user removes the match

`pod_match_reasons`:
- one row per criterion (nine per match, inactive ones included), unique on `(pod_match_id, criterion)`
- columns: `position` (enum order, used for display order), `active`, `possible_points` (the criterion weight), `awarded_points decimal(5,2)`, `passed`, `mandatory`, `disqualifying`, `code` (`MatchReasonCode`), `context` (JSON structured values: ids, enum values, dates and numbers, never prose)
- foreign key: `pod_match_id` **cascades on delete**, so a reason never outlives its match
- indexes: `(pod_match_id, position)` and `(criterion, passed)`

`PodMatchService::evaluate()` checks the candidate first. Only an account that passes `isRegularUser()` and `isEmployee()` and has a saved profile can be matched (`IneligibleCandidate`), and the requirement must be active (`InactiveRequirement`).

It then loads `PodMatchScorer::REQUIREMENT_RELATIONS` / `CANDIDATE_RELATIONS` once; the scorer itself throws a `LogicException` rather than lazy-load.

Finally, in one `DB::transaction(..., 3)`, it upserts the match on the unique pair and replaces all of its reasons. Re-evaluation updates the same row.

## 7. Display

`PodMatchPresenter::present($match)` returns the match-level fields:
- `score`, `status`, `status_label`, `eligible`
- a translated `summary`
- `failed_mandatory` (`[{criterion, label}]`)
- `scoring_version`, `evaluated_at`

It also returns the ordered `criteria` entries. Each entry has:
- `criterion` and `label` (translated)
- `active`, `passed`, `mandatory`, `disqualifying`, `weighted`
- `awarded_points` and `possible_points`
- `code`
- `explanation` (translated with `__()` from code and context)
- `details` (the structured context, e.g. `details.missing` skills)

UNA-014 adds the POD's current qualification to the match-level fields:
- `threshold` (the POD's `minimum_match_threshold` now)
- `qualified` (bool)
- `qualification` (`{code, label}`, a `QualificationReason`)

The threshold is read live from the POD, so a threshold change is visible immediately without
re-scoring; the stored score never changes because of it.

`PodMatchResource` wraps the same data with ids, for JSON. Eager-load `reasons` and
`requirement.pod` for collections.

## 8. Worked example

Evaluation time: 2026-06-15 12:00 UTC.

**Requirement:**
- role Backend Developer
- skills PHP, SQL and Docker
- minimum proficiency advanced; minimum experience 4 years
- start by 2026-07-01, at least 30 h/week
- location "Sri Lanka"; no timezone; no project type
- work mode remote, engagement contract
- **mandatory: availability**

**Employee:**
- primary role Backend Developer
- PHP expert, SQL intermediate, no Docker
- Backend Developer experience 2022-01-01 → 2023-12-31 and 2023-07-01 → current
- `within_one_month`, 40 h/week
- Kandy, Sri Lanka
- remote, full-time

| Criterion | Weight | Attainment | Awarded |
|---|---:|---|---:|
| Required skills | 25 | 2/3 (Docker missing) | 16.67 |
| Skill proficiency | 15 | (3 + 2 + 0) / (3 × 3) = 5/9 | 8.33 |
| Relevant experience | 15 | ranges merge to 2022-01-01 → 2026-06-15 = 1626 days ≈ 4.5 y ≥ 4 y | 15.00 |
| Professional role | 15 | primary role | 15.00 |
| Availability | 10 | start: available 2026-07-15 > 2026-07-01 ✗; hours 40 ≥ 30 ✓ → 1/2 | 5.00 |
| Location and timezone | 10 | "sri lanka" = country ✓ (no timezone configured) → 1/1 | 10.00 |
| Project type | 10 | not configured, so inactive | 0.00 |
| Work mode | 0 | remote = remote, compatible | 0.00 |
| Engagement type | 0 | full-time ≠ contract, incompatible (not mandatory) | 0.00 |

- Active weight = 25 + 15 + 15 + 15 + 10 + 10 = **90**
- Awarded = 1667 + 833 + 1500 + 1500 + 500 + 1000 = 7000 hundredths
- Score = round_half_up(7000 × 100 / 90) = 7778 hundredths, so **77.78**

Availability is mandatory and did not pass, so the status is **`disqualified`**:
- `failedMandatory = [availability]`
- the availability reason row has `disqualifying = true`
- the score stays 77.78 for diagnostics

The incompatible engagement type is explained but does not disqualify, because it is not mandatory.

This example is asserted number for number by
`tests/Feature/Matching/PodMatchScorerTest::test_documented_worked_example_mandatory_failure_keeps_the_score`.

## 9. Non-goals (UNA-013)

- No recommendations or matching page, and no candidate search UI.
- No selection of an employee's best requirement within a POD.
- No queues, scheduled re-evaluation, AI/LLM calls, vector search, geocoding or external APIs.
- No extra weighted categories: work mode and engagement type stay unweighted compatibility checks.
- No re-evaluation when an employee profile changes (configuration changes are handled by UNA-014, §12).

## 10. POD matching configuration and qualification (UNA-014)

### 10.1 Configuration

| Column | Values | Notes |
|---|---|---|
| `pods.matching_mode` | `recommend_only`, `pending_approval`, `automatic` (or null on a draft) | `pending_approval` and `automatic` rows from UNA-011 stay valid. A null mode acts like recommend only. |
| `pods.minimum_match_threshold` | integer 0–100, not null | Existing PODs got `config('pod_matching.default_minimum_threshold')` (70) from the column default. |
| `workspaces.default_minimum_match_threshold` | integer 0–100 or null | Company default for **new** PODs; null means the config default. |

The POD setup "Matching" step edits both POD values. The form always sends `matching_present`, so the
request requires an integer 0–100 there. `PodService::validThreshold()` rejects anything else
(negative, above 100, fractions, booleans, null) for every other caller with a `ValidationException`.

`PodConfigurationService`: the matching section is complete only with a mode **and** a threshold
from 0 to 100. A published POD still cannot lose a complete section.

### 10.2 Company defaults

Company onboarding ("Matching" step) stores `workspaces.matching_mode` and
`default_minimum_match_threshold`. `PodService::createDraft()` copies them into the new POD (unless
the input sets them explicitly):

| Company `matching_mode` | New POD `matching_mode` |
|---|---|
| `manual` | `recommend_only` |
| `assisted` | `pending_approval` |
| `automatic` | `automatic` |
| none | none (the company must choose before publishing) |

These are snapshots. Changing company defaults later never touches existing PODs, and
`PodService::companyDefaults()` returns the values a new POD would get (`pod_matching_mode`,
`minimum_match_threshold`).

### 10.3 Qualification formula

`MatchQualification::of($match)` is the only qualification rule:

```
qualified = match.status == eligible
        AND score_hundredths >= threshold × 100
        AND requirement.is_active
        AND pod.status == published
```

- Equality qualifies: 70.00 with threshold 70 qualifies; 69.99 does not.
- The score is compared in integer hundredths.
- A disqualified match (failed mandatory criterion) or a `not_scorable` match is never qualified,
  however high its score.
- The reason is the first failing condition, in this order: `not_eligible`, `below_threshold`,
  `requirement_inactive`, `pod_not_published`; otherwise `qualified`.

## 11. Mode application and membership safety (UNA-014)

`PodMatchingWorkflowService::evaluateAndApply($requirement, $candidate, $at = null)` is the single
entry point for future workflows:

1. `PodMatchService::evaluate()` scores and persists the match (employees with a profile only).
2. `MatchQualification::of()` qualifies it with the POD's current threshold.
3. If qualified and the mode acts on memberships, it applies the mode through
   `PodStateTransitionService` with `actor = null` (a system action).
4. It returns a `PodMatchOutcome`: the match, the qualification, a `MembershipAction`
   (`none`, `created`, `advanced`, `unchanged`, `blocked`), the membership state afterwards and a
   short reason.

| Mode | Candidate without a membership | Applicant (`matched` / `pending_business_approval`) | Any other existing membership |
|---|---|---|---|
| `recommend_only` (or null) | nothing (`none`, reason `recommend_only`) | nothing | nothing |
| `pending_approval` | enters `pending_business_approval` (`created`) | unchanged | unchanged |
| `automatic` | enters `outer_circle` (`created`) if the profile is complete, else `blocked` / `profile_incomplete` | advances to `outer_circle` (`advanced`) if the profile is complete, else `blocked` | unchanged |

Details:
- New memberships use `PodStateTransitionService::start(..., allowReentry: false)`, and advances
  use `apply(..., allowedFrom: applicants)`. Both run inside one `DB::transaction(..., 3)` that
  first locks the candidate's `pod_users` row. The transition service writes the state, the
  history row and the workspace access.
- History reasons are `Matched by POD matching, awaiting approval` (pending) and
  `Qualified automatically by POD matching` (automatic).
- Notifications are the transition service's existing effects only, run through `AfterCommit`
  after the membership transaction commits:
  - a new pending applicant: the owner gets `pod_request`
  - an applicant advanced to the Outer Circle: the owner and the member get the acceptance notifications
  - a direct automatic placement (no previous membership): no notification, because the transition
    service defines no effect for entering `outer_circle` from nothing. See the follow-ups.
- Automatic placement gives Outer Circle access only: `users_index` + `news_index`, never
  `projects_index`.

Membership safety rules (all enforced by the code above):
- Nothing writes `pod_users.state` directly or reads workspace permissions to decide a state.
- Nobody is moved into the Inner Circle, demoted, suspended or removed by matching.
- `rejected`, `removed` and `suspended` rows are never re-entered or resumed. Neither are Outer
  Circle, screening, pending-inner or Inner Circle members.
- A falling score, a raised threshold or a switch to recommend only never undoes a membership.
- Draft PODs can store scores but never create or advance memberships (`pod_not_published`).
- Repeated processing is idempotent: the second call finds the membership and returns `unchanged`.
  Duplicates are prevented by the row lock, the `pod_users` unique index and
  `findOrCreateMembership`.
- Headcount is not enforced (no existing documented rule requires it).

## 12. Recalculation after configuration changes (UNA-014, queued since UNA-016)

`PodService` detects matching-relevant changes inside its existing configuration transaction:
`PodMatchRecalculationService::configurationSnapshot()` runs before and after the change, and
`scheduleChanges()` compares the two. The snapshot holds the POD's mode, threshold and status, and
a fingerprint per requirement made of:
- role, minimum years, minimum proficiency, work mode, engagement type, location
- active flag, start date, minimum hours, timezone, tolerance, project type, mandatory criteria
- sorted skill ids

| Change on a **published** POD | Effect |
|---|---|
| mode, threshold, any requirement fingerprint, a requirement added/removed/activated | `GenerateMatchesForPod` queued after commit (§16) |
| a requirement made inactive | its `pod_matches` deleted in the same transaction, plus the job above |
| a requirement deleted | its matches removed by the FK cascade, plus the job above |
| publication (`PodService::publish()`) | `GenerateMatchesForPod` queued after commit |
| title, description, image, POD skills, headcount, notes | nothing |
| company template or company default edits | nothing (POD snapshots are independent) |

Drafts never queue generation (an inactivated draft requirement still loses its matches);
publication picks their configuration up. Nothing is scored in the web request any more: the
synchronous recalculation of UNA-014 was replaced by the queued jobs, so there is one generation
path, not two.

## 13. Examples (UNA-014)

The candidate below has a complete profile (role Engineer; PHP, Laravel and SQL).

- **Eligible but below threshold:** the requirement is Engineer + PHP + Docker, so the score is
  (12.50 + 15.00) / 40 = **68.75**, status eligible. With threshold 70 the result is
  `qualified = false` (`below_threshold`) and nothing happens in any mode. With threshold 68 it is
  qualified.
- **Disqualified despite a high score:** Engineer + PHP + project type `hourly`, which is mandatory.
  The score is (25 + 15 + 0) / 50 = **80.00**, but the status is disqualified. It is not qualified
  even with threshold 0.
- **Recommend only, qualified:** score 100 with threshold 70 gives `qualified = true` and
  `membership action = none`. The match is just a stored recommendation.
- **Pending approval, qualified:** the candidate has no membership, so a `pending_business_approval`
  row is created with a history row (`actor_id = null`). The company owner gets the usual
  `pod_request` notification after commit.
- **Automatic, qualified:**
  - With no membership, the candidate enters `outer_circle` with history reason `Qualified
    automatically by POD matching` and gets active Outer Circle workspace access.
  - An incomplete profile leaves them untouched (`blocked`).
  - An existing applicant is advanced to `outer_circle`.
- **Threshold change:** the 68.75 candidate in a pending-approval POD with threshold 70 has no
  membership. The company lowers the threshold to 65; after the save commits, the POD's matches
  are reprocessed and the candidate becomes `pending_business_approval`. Raising it to 100 later
  re-evaluates the match (now not qualified) and leaves the pending membership as it is.

## 14. Non-goals (UNA-014)

- Candidate discovery across all employees (only already persisted matches are reprocessed).
- Automatic removal, demotion or suspension.
- Inner Circle placement.
- Reopening rejected, removed or suspended memberships.
- Headcount/staffing enforcement.
- Re-evaluation on employee profile changes, queues or scheduled jobs.

## 15. Deterministic user-to-POD matching (UNA-015)

UNA-015 adds no new scorer, table or formula. The UNA-013 algorithm (`config('pod_matching.version')`
= `una-013.1`) is the **first production matching version**. UNA-015 adds a POD-level facade over
it:

| Layer | Scope | Writes |
|---|---|---|
| `PodMatchScorer` | one employee vs one requirement, pure | nothing |
| `PodMatchService` | one employee vs one requirement (`evaluate()`), or several results of one employee (`persistMany()`) | `pod_matches`, `pod_match_reasons` |
| `DeterministicPodMatchingService` | one employee vs every active requirement of one POD | through `PodMatchService::persistMany()` only |
| `PodMatchingWorkflowService` (UNA-014) | applies the POD mode to a qualified match | memberships, via `PodStateTransitionService` only |

### 15.1 API

```php
public function match(Pod $pod, HasAccountType $candidate, ?DateTimeInterface $evaluatedAt = null): PodMatchingResult;
public function matchMany(Pod $pod, iterable $candidates, ?DateTimeInterface $evaluatedAt = null): \Generator; // user id => PodMatchingResult
```

`match()`:
1. **Validates the candidate** with `PodMatchService::assertCandidate()`. Only a `User` that passes
   `isRegularUser()` and `isEmployee()` and has a saved profile is accepted. Companies,
   administrators, clients and unclassified accounts throw `IneligibleCandidate::notEmployee()`; an
   employee without a profile throws `IneligibleCandidate::withoutProfile()`.
2. **Loads the candidate relations once** (`PodMatchScorer::CANDIDATE_RELATIONS`): skills (with
   proficiency), and the profile's experiences, preferred roles and project types.
3. **Loads the POD's active requirements once**, ordered by id, with `REQUIREMENT_RELATIONS`
   (skills, job title). The given `$pod` is attached as their `pod` relation. Inactive requirements
   are neither evaluated nor selected.
4. **Fixes one evaluation time.** `$evaluatedAt`, or `now()` taken once at the service boundary, is
   passed to the scorer for every requirement. No criterion reads the clock.
5. **Scores every active requirement** with `PodMatchScorer::score()`.
6. **Persists all results** with `PodMatchService::persistMany()`:
   - one `DB::transaction(..., 3)`, so a run is all or nothing (UNA-016: `persistGeneration()`,
     with the stale-generation guard of §16.5)
   - one upsert on the unique `(user_id, pod_worker_requirement_id)` pair
   - one locked read
   - one delete of the old reasons and one insert of the new reasons

   The query count does not grow with the number of requirements or criteria.
7. **Returns a `PodMatchingResult`.** Its persisted matches have their ordered reasons loaded, and it
   names the best match.

`matchMany()` loads the requirements once and uses one timestamp for an **explicit** list of
candidates. For an Eloquent collection it eager-loads every candidate's relations in one go. It never
looks for candidates itself, and an ineligible candidate throws, just like in `match()`.

### 15.2 Result

`PodMatchingResult` exposes:
- `podId`, `userId`, `evaluatedAt`, `scoringVersion`
- `matches`: every evaluated requirement match, keyed and ordered by requirement id
- `best`, `bestRequirementId()`, `bestScore()`, `bestStatus()`
- `bestFailedMandatory()`: criteria in declaration order
- `bestMeetsThreshold()`: the best match is eligible and its score ≥ the POD threshold
- `bestQualification` / `bestIsQualified()`: the full UNA-014 rule, which also requires an active
  requirement and a published POD
- `reason`

When the POD has **no active requirement**, `matches` is empty, `best` is null, `bestStatus()` is
`not_scorable` and `reason` is `no_active_requirements`. Nothing is written, because a match always
belongs to a worker requirement.

### 15.3 Best requirement

`DeterministicPodMatchingService::rank()` gives each match the key
`[status precedence, −score in hundredths, pod_worker_requirement_id]`, and the smallest key wins:

1. Status: `eligible` (0) before `disqualified` (1) before `not_scorable` (2).
2. Within a status, the higher score.
3. On an exact tie, the lowest requirement id.

A failed mandatory criterion therefore always loses to an eligible requirement, whatever the
scores. The POD threshold does not take part in the selection and never changes a score or a
status. It only decides `bestMeetsThreshold()` and `bestQualification`.

Raw score, eligibility and qualification are three different things:
- **score**: the weighted points.
- **status**: eligible, disqualified or not scorable, decided by mandatory criteria and active
  weight.
- **qualification**: eligible + threshold + active requirement + published POD (UNA-014 §10.3).

### 15.4 Repeatability and versioning

For the same candidate data, requirement data, configuration, evaluation timestamp and
`pod_matching.version`, the service returns the same scores, statuses, reasons and best
requirement. There is no randomness, no free-text similarity, no embeddings and no external call.

Every stored row carries `scoring_version` (unchanged by UNA-015: no formula changed) and the
run's `evaluated_at`. Running again updates the same rows in place and replaces their reasons.
Matches of other POD requirements or other users are never touched.

`Pod::matches()`, `User::podMatches()` and `PodMatch::forPodAndUser($podId, $userId)` read the
stored results.

### 15.5 No membership changes

The deterministic service never:
- creates or changes a `pod_users` row or its state history
- creates or activates a workspace membership, or grants permissions
- sends a notification
- calls `PodStateTransitionService`

It never grants Outer or Inner Circle access. Acting on a result is the job of UNA-014's
`PodMatchingWorkflowService`, e.g. `applyPersisted($result->best)`. That applies the POD mode to the
stored match without re-scoring it, may place a qualified employee in the **Outer Circle only**, and
keeps every UNA-014 safety rule (§11).

### 15.6 Worked example (two requirements)

POD in recommend-only mode, threshold 70. The candidate has a complete profile with role Engineer,
PHP/Laravel/SQL at advanced, and project type long-term.

| Requirement | Configuration | Score | Status |
|---|---|---:|---|
| #1 | Engineer, PHP, project type `hourly` (**mandatory**) | (25 + 15 + 0) / 50 = **80.00** | `disqualified` (project type) |
| #2 | Engineer, PHP + Docker | (12.50 + 15.00) / 40 = **68.75** | `eligible` |

- **Ranking:** #1 has the key `[1, −8000, 1]` and #2 has `[0, −6875, 2]`, so the best requirement is
  **#2**.
- **Best score and status:** `bestScore()` is 68.75 and `bestStatus()` is `eligible`.
- **Mandatory failures:** `bestFailedMandatory()` is empty for #2. #1 keeps its 80.00 score and its
  `project_type` reason with `mandatory = disqualifying = true`.
- **Threshold:** 68.75 < 70, so `bestMeetsThreshold()` is false and `bestIsQualified()` is false.
- **Stored rows:** both requirement rows are stored with nine reasons each, and no membership changes.

This example is asserted by
`tests/Feature/Matching/DeterministicPodMatchingTest::test_an_eligible_requirement_outranks_a_higher_scoring_disqualified_one`.

## 16. Asynchronous match generation (UNA-016)

Matching runs in queued jobs on the `matching` queue (`config('pod_matching.queue')`). Request-time
code only records changes and queues work after commit; no web request scores anything.

### 16.1 Jobs

All three jobs:
- implement `ShouldQueue` and `ShouldBeUniqueUntilProcessing`
- carry only scalar ids and a generation timestamp (ISO-8601, whole seconds), never models
- get their queue and limits from `config('pod_matching.jobs')`:
  - `tries` 5
  - `backoff` 30 / 120 / 300 s
  - `timeout` 80 s, kept below the database connection's `retry_after` of 90 s; raise both together
  - `unique_for` 3600 s
  - `coordinator_chunk_size` 200

| Job | Unique id | Does |
|---|---|---|
| `GenerateMatchesForEmployee(userId, generatedAt)` | `matching:employee:{userId}` | Coordinator: no-op unless the user is a regular employee. When the profile is below the matching minimum, deletes that user's `pod_matches` (memberships, access and history untouched). Otherwise queues one pair job per published POD with an active requirement, `chunkById` in POD id order. Scores nothing. |
| `GenerateMatchesForPod(podId, generatedAt)` | `matching:pod:{podId}` | Coordinator: no-op unless the POD exists, is published and has an active requirement. Otherwise queues one pair job per `User::employees()` account with a saved profile that `ProfileCompletionService::isEligible()`, `chunkById` in user id order, relations eager-loaded per chunk. Scores nothing. There is no lossy pre-filter. |
| `GenerateEmployeePodMatch(userId, podId, generatedAt)` | `matching:employee-pod:{userId}:{podId}` | The atomic retry unit, described below. |

`GenerateEmployeePodMatch` runs these steps:
1. Load both records. A missing record, an unpublished POD or a non-employee is a no-op. An
   ineligible profile deletes that pair's matches.
2. Run the configured `PodMatchingAlgorithm` **once for the whole POD** with the job's timestamp.
3. Stop if the generation was stale (§16.5).
4. Pass the stored best match to `PodMatchingWorkflowService::applyPersisted()`. That method
   re-qualifies the stored row and applies the UNA-014 mode (§11) without re-scoring.

Expected skip conditions return normally. Unexpected exceptions escape, so Laravel retries the job
and finally records it in `failed_jobs`.

### 16.2 Triggers

| Trigger | Where | Job |
|---|---|---|
| A matching-relevant profile change (§16.3), including first reaching or losing the profile minimum | `ProfileService::saveProfile()` (`/my-profile`) and the legacy `createProfile()` / `updateProfile()` (`api/profile`) | `GenerateMatchesForEmployee` |
| POD publication, or a configuration change of a published POD (§12) | `PodService::publish()` / `updateDraft()` | `GenerateMatchesForPod` |

`MatchGenerationDispatcher` is the only dispatcher:
- It registers each dispatch with `DB::afterCommit()`, so dispatch happens only after the outermost
  transaction commits, never on rollback, and only for the attempt that committed.
- It takes the generation timestamp at that moment.
- It reports and swallows an enqueue failure. The committed profile or POD stays as saved; nothing
  is rolled back or marked failed.

There are no model observers.

### 16.3 Employee fingerprint

`EmployeeMatchingFingerprint::of($userId)` is the single list of what affects employee matching.
Writers compare it before and after their change, inside their transaction.

Included:
- skills with proficiency
- primary role, preferred roles
- experiences (role, dates, current)
- years of experience
- availability status and date, weekly hours
- city, state, country, timezone
- project types, work mode, engagement type
- profile eligibility

Not included on their own:
- phone, ZIP code, street address
- CV, portfolio
- LinkedIn, GitHub, website
- about text, industry
- education, certifications
- relocation, travel

Any of these can still trigger generation if changing it moves the profile across the eligibility
minimum.

### 16.4 Idempotency and retries

Queue uniqueness (`ShouldBeUniqueUntilProcessing`) only collapses duplicates that are still queued.
A change made while a job is already running queues a follow-up job. Correctness comes from the
database:
- `pod_matches` unique `(user_id, pod_worker_requirement_id)` and an atomic upsert
- reasons replaced in the same transaction, so there is never a partial set
- deterministic best-match selection
- `findOrCreateMembership`, the row lock and the conditional transitions of
  `PodStateTransitionService`, with after-commit effects

Running a pair job twice changes nothing: no duplicate matches, reasons, memberships, workspace
access, history or notifications.

A failure at any step is safe to retry:

| Failure point | What has happened | Retry |
|---|---|---|
| Before scoring | nothing | Runs the job from the start. |
| During match persistence | the whole transaction rolled back | Runs the job from the start. |
| After persistence, before the membership step | the match is committed | Rewrites the same generation and finishes the membership step. |
| In a notification | the match and membership are committed | Nothing to redo; the failure is reported by `AfterCommit`. |

Matching jobs never write onboarding fields:
- business onboarding timestamps and step
- POD `published_at` and configuration timestamps
- profile data

### 16.5 Generation timestamp, stale jobs, algorithm metadata

- **One timestamp per run.** Every pair job carries one `generatedAt` that stays the same across
  retries. Every requirement of the run is scored at that time and stored with it in
  `pod_matches.evaluated_at`, converted to the application timezone because the column stores no
  offset. Resources expose it as `generated_at` too.
- **Stale guard.** `PodMatchService::persistGeneration()` locks the candidate's rows and skips any
  requirement whose stored `evaluated_at` is **newer** than the incoming one.
  - Newer rows and their reasons are never overwritten.
  - The result is flagged `stale`; the pair job returns `stale`, and `evaluateAndApply()` returns
    reason `stale_generation`. Neither applies a membership.
  - An equal timestamp is rewritten idempotently.
- **Algorithm metadata.** Every row stores:
  - `algorithm`: NOT NULL, rows from before UNA-016 backfilled as `deterministic`
  - `scoring_version`: the algorithm version
  - `evaluated_at`: the generation timestamp

  `PodMatchPresenter` / `PodMatchResource` add `algorithm`, `algorithm_version` and
  `generated_at` next to the existing `scoring_version` / `evaluated_at`.

The deterministic version stays `una-013.1`, because no formula changed.

### 16.6 Algorithm contract and future AI

`App\Contracts\PodMatchingAlgorithm` has three methods: `key()`, `version()` and
`match(Pod, HasAccountType, ?DateTimeInterface): PodMatchingResult`. There is no membership API on
it. The pair job depends only on this contract.

`PodMatchingAlgorithmResolver` resolves `config('pod_matching.algorithm')` (default
`deterministic`) from `config('pod_matching.algorithms')`. It throws an `InvalidArgumentException`
when:
- the key is unknown
- the class does not implement the contract
- the class reports a different key

It never instantiates user-supplied class names. Tests bind a fake with
`app()->instance(PodMatchingAlgorithm::class, ...)`.

Rules for a future AI ranker (none is implemented: no SDK, prompt, credentials or remote call):
- It may only enrich or re-rank on top of the deterministic baseline.
- Deterministic mandatory failures stay authoritative, and it cannot turn a disqualification into
  an eligible result. `MatchQualification` treats a match with any stored disqualifying reason as
  not eligible, whatever status an algorithm reports; a test proves this with a fake that promotes
  a disqualified requirement.
- It never touches memberships. Only `PodMatchingWorkflowService` applies the mode, through
  `PodStateTransitionService`. Profile eligibility, publication and the threshold are re-checked
  there.
- Its output needs a stable `key()` and `version()`.
- Its failure policy (fall back to deterministic, or fail the job) must be decided explicitly
  when it is built.
- Storing several algorithms side by side (shadow mode) would need a later schema design. It is not
  supported: one row per candidate × requirement.

No matching path grants Inner Circle access. Automatic mode places a qualified employee in the
Outer Circle at most.

### 16.7 Flow example

1. The employee changes their weekly hours on `/my-profile`.
2. `ProfileService` sees the fingerprint change. The transaction commits, and
   `GenerateMatchesForEmployee(42, 2026-11-17T10:00:00+05:30)` is queued on `matching`.
3. A worker runs the coordinator. Profile 42 is eligible, so it queues
   `GenerateEmployeePodMatch(42, 7, same timestamp)` for each published POD with an active
   requirement, such as POD 7.
4. The pair job scores every active requirement of POD 7 at that timestamp and stores them atomically
   (`algorithm = deterministic`, `scoring_version = una-013.1`). The best requirement is eligible
   with 81.25 against threshold 70.
5. POD 7 is in pending-approval mode, so `applyPersisted()` enters `pending_business_approval`
   through `PodStateTransitionService`. The owner's `pod_request` notification goes out after that
   commit.
6. A retry or a duplicate later finds the membership and the same rows, and changes nothing.

### 16.8 Deployment requirement

`.env.example` now shows `QUEUE_CONNECTION=database`; `jobs` and `failed_jobs` already exist.
Production and dev need a persistent worker (Supervisor or systemd) that includes the `matching`
queue, for example:

```
php artisan queue:work database --queue=matching,default --tries=5
```

`deploy.php` defines a `supervisor:restart` task for `{{worker_name}}` (`app-worker`), but it is
commented out of the deploy flow. Whether a worker runs on the servers is not visible in the
repository. Until one does, with a non-sync connection, matches are queued but never generated.
After enabling it, deploys should restart the worker (`queue:restart`) so new code is loaded.

## 17. Employee POD recommendation screen (UNA-017)

`GET /recommendations/pods` (`pod-recommendations.index`) is part of the authenticated employee
dashboard (`layouts.admin`). It uses the `auth`, `verified`, `XSS` and `profile.complete`
middleware, and `PodRecommendationService::canView()` (`isRegularUser() && isEmployee()`).
Companies and administrators get 403; guests and clients are redirected. The sidebar entry
"PODs for you" appears for employees and runs no queries.

### 17.1 Selection and ranking (`PodRecommendationService`)

The service only reads stored `pod_matches`. It never scores, queues jobs or changes memberships.

A POD is recommended to the authenticated employee when one of **their** matches:
1. was generated by the active algorithm key **and** version
   (`app(PodMatchingAlgorithm::class)->key()/version()`). Results of an older version are hidden
   until UNA-016 regenerates them;
2. is qualified by `MatchQualification::of()`: eligible, score ≥ the POD's current threshold,
   active requirement, published POD;
3. belongs to a POD the employee has not dismissed;
4. belongs to a POD where the employee has no membership, or only an open applicant membership
   (`matched` / `pending_business_approval`). Those cards show "Invitation pending" or
   "Application sent" instead of Apply.

Active, suspended, rejected and removed memberships are left to manual discovery and are never
re-opened. A deleted POD or requirement has no matches left (FK cascade).

Several requirements of a POD collapse to its best match with
`DeterministicPodMatchingService::rank()`, the same ordering as UNA-015. PODs are then ranked by:
1. score, descending
2. generation time (`evaluated_at`), descending
3. POD id, ascending

Pagination is per POD (`config('pod_matching.recommendations.per_page')`, 9).

The candidate query loads `requirement` and `pod` in one pass. Each page then eager-loads
`reasons`, `requirement.jobTitle`, `requirement.pod`, `pod.creator.workspacesOwner` and the
employee's memberships, so the query count does not depend on the number of cards.

Summary metrics are computed exactly from stored data:
- number of recommendations
- best score
- strong matches at or above `recommendations.strong_score` (80, a display-only threshold)
- profile completion (`ProfileCompletionService`)

Empty state:
- **"preparing"**: no result of the active algorithm exists for the employee yet while published
  PODs with active requirements exist, so generation is presumably queued.
- **"no strong matches"**: every other case.

Both versions link to Browse all PODs and Update profile. A GET never queues matching.

### 17.2 Explanations

Cards use `PodMatchPresenter::present()` only. Each entry adds a translated `highlight` (a short
positive phrase for a passed criterion) and its `position`.

- **Collapsed card:** up to three highlight chips. Weighted criteria come first, ordered by awarded
  points, then compatibility checks.
- **Expanded "Why this matches":** every active criterion in stored order, with its label, Met,
  Partly met or Not met status, a "Required" badge when mandatory, awarded/possible points for
  weighted criteria, and the presenter's explanation.
- **Metadata:** a quiet footer shows the algorithm label, version and generation time.

No raw JSON context, internal codes or class names are rendered. Results are described as
"Profile match" or "Matched from your profile", never as AI.

### 17.3 Apply

The Apply button posts to the existing `api.pods.join` endpoint, which calls
`PodUserService::requestJoinPod()` and then `PodStateTransitionService` (`pending_business_approval`,
actor = the employee). UNA-017 hardened that path:
- The route now has the `profile.complete` middleware (403 JSON for an incomplete employee).
- `PodPolicy::requestToJoin` now also requires `isEmployee()`.
- A duplicate request throws `AlreadyRequestedToJoin` and answers **409**
  (`status = already_requested`) instead of 500.
- The success response includes `state`.

Applying never grants circle access. The JS disables the button while busy, replaces it with
"Application sent" on 200 or 409, and restores it on any other error.

### 17.4 Dismiss

`POST /recommendations/pods/{pod}/dismiss` (`pod-recommendations.dismiss`) uses the same middleware
as the screen, and `PodRecommendationDismissalService::dismiss()` stores the dismissal.

The table is `pod_recommendation_dismissals`:
- columns `user_id`, `pod_id`, `dismissed_at` and timestamps
- unique `(user_id, pod_id)`
- cascades when the user or the POD is deleted

Behaviour:
- **Idempotent:** writes use `insertOrIgnore`.
- **Server-side identity:** the user always comes from the session; a `user_id` in the request is
  ignored.
- **Scope:** it hides the POD from that employee's recommendations only. Matches, reasons and
  memberships are untouched, other employees are unaffected, and the POD stays in `pods.list`.
- **Regeneration:** a POD stays dismissed after its matches are regenerated.
- **Responses:** JSON for AJAX, with a redirect and flash message as the fallback.
- **In the page:** the card fades out (instantly with reduced motion), the count decrements, focus
  moves to the next card, and after the last card the empty state appears and takes focus.

### 17.5 Manual discovery

`pods.list`, `landing.find-work` and the POD search are unchanged and need no matching.
"Browse all PODs" is always visible: in the hero and in both empty states.

## 18. Business match-review queue (UNA-018)

### 18.1 Manual applications vs match proposals

`pending_business_approval` holds two different things:
- **Manual applications.** `PodUserService::requestJoinPod()` creates them, and they stay in the
  existing join-request listing and moderation API.
- **Match proposals.** `PodMatchingWorkflowService` creates them for a POD in `pending_approval`
  mode, and only they go to the review queue.

Origin is never inferred from the state, from a `PodMatch` or from a history reason. It is an
explicit row in `pod_match_reviews`:

| Column | Meaning |
|---|---|
| `pod_user_id` | the proposal's membership; unique; cascades with the membership |
| `source_pod_match_id` | the match the proposal was made from; `nullOnDelete`, so regenerating matches keeps provenance |
| `resolved_at`, `decision` (`MatchReviewDecision`: approved / rejected / excluded / automatically_advanced / otherwise_resolved), `decided_by` (`nullOnDelete`), `decision_reason` | review metadata |

`pod_users.state` stays the authoritative workflow state, and `pod_user_state_histories` stays the
authoritative decision record.

**Creation.** When `pending_approval` mode creates a new pending membership, the workflow inserts
the review in the **same transaction** with `insertOrIgnore` on the unique `pod_user_id`. Retries
and races create one row; a failure rolls back both.

No review is created for:
- manual applications
- recommend-only or automatic mode
- unqualified matches
- existing memberships
- excluded pairs

**Backfill.** Migration `2026_11_19_000500` creates a review (with a null source) only for
memberships that are pending now **and** whose history shows the exact UNA-014 workflow reason. Runtime
code never reads that string.

### 18.2 Keeping reviews consistent

`PodStateTransitionService::apply()` calls `PodMatchReviewResolver::onTransition()` in its own
transaction whenever a membership leaves `pending_business_approval`. Every write path therefore
resolves an open review together with the transition:

| Write path | Review decision |
|---|---|
| The review queue | `PodMatchReviewDecisionService` records `approved`, `rejected` or `excluded` itself; the hook then has nothing left to resolve |
| Generic moderation API, accept | `approved` (actor recorded) |
| Generic moderation API, decline | `rejected` (actor recorded); its required reason is unchanged |
| Web accept / decline | `approved` / `rejected` |
| Automatic mode advancing the proposal | `automatically_advanced` (actor null) |
| Removal | `otherwise_resolved` |

A resolved review stays resolved. A later manual re-entry of the same row is a manual application
and never reappears in the queue.

### 18.3 Queue (`PodMatchReviewService`)

`GET /account/pods/{pod}/match-reviews` (`pods.match-reviews.index`) runs behind `auth`, `verified`
and `XSS`, and `PodPolicy::viewMatchReviews` (owner or a member with `users_edit`, the same as
`manageMembers`).

Another company, a permissionless member, an employee or an administrator gets 403; a client is
redirected.

A review is listed when:
- it is unresolved,
- its membership belongs to this POD and is still pending,
- the membership is not the owner's,
- the employee still exists.

Each card shows:
- **Profile:** the employee's avatar or initials, name, primary role, years of experience,
  city/country and availability. Never their address, phone, postal code, e-mail or links.
- **Current match:** the employee's **current** best match for the POD (active algorithm/version,
  active requirement, `DeterministicPodMatchingService::rank()`) with its qualification.
- **Explanation:** top reason chips and the full `PodMatchPresenter` explanation, with algorithm,
  version, generation time and current minimum.

When the current match no longer qualifies, or none remains, the card stays but approval is
disabled with a clear message:
- below the minimum,
- a required criterion not met or the POD not open,
- details unavailable.

Reject and exclude remain available. The source match is kept only for provenance.

Ranking:
1. qualified first,
2. then score, descending,
3. then review creation, ascending,
4. then review id, ascending.

Pagination is per review (`config('pod_matching.reviews.per_page')`, 10). The query count does not
depend on the page size.

The page metrics (pending, highest current score, ready to approve, no longer qualified) are
computed in the controller and service. The POD management nav, the member-management screen and
the company POD list each link to the queue with `@can('viewMatchReviews')`. No counts are queried
in shared layouts.

### 18.4 Decisions (`PodMatchReviewDecisionService`)

`POST /account/pods/{pod}/match-reviews/{review}/decision` (`pods.match-reviews.decision`) is
validated by `DecideMatchReviewRequest`:
- `action` must be `approve`, `reject` or `exclude` (`MatchReviewAction`).
- `reason` is optional and at most 1000 characters.
- Any other input (user, POD, state, score) is ignored.
- A review of another POD gives 404, and an unauthorized user gets 403.

Each decision is one `DB::transaction(..., 3)` that:
1. locks the review and the membership,
2. checks they belong to the POD, the review is unresolved and the membership is still pending
   (otherwise **409**),
3. records the review decision,
4. applies the transition through `PodStateTransitionService::apply()`, restricted to pending.

That transition writes exactly one history row with the business user as actor. Notifications
run after commit.

| Action | Checks | Transition | History reason |
|---|---|---|---|
| approve | not excluded, current best match still qualified (**422** otherwise; profile gate 422 too) | pending → `outer_circle` (Outer Circle access only, never Inner Circle) | `Match approved by business` |
| reject | — | pending → `rejected` | the business reason, or `Match rejected by business` |
| exclude | — | create the exclusion idempotently, then pending → `rejected` | `Excluded from future POD matching` (+ `: <reason>`) |

Exclusion and rejection commit together or not at all.

### 18.5 Exclusions (`pod_matching_exclusions`, `PodMatchingExclusionService`)

The table has one row per `(pod_id, user_id)`, with `excluded_by` (`nullOnDelete`) and an optional
`reason`. It cascades when the POD or the user is deleted. Repeated exclusion keeps the first row.

Enforcement layers:
1. `GenerateEmployeePodMatch` skips the pair before running the algorithm (`skipped_excluded`).
2. `GenerateMatchesForPod` leaves excluded employees out of the discovery query.
3. `GenerateMatchesForEmployee` leaves PODs that excluded the employee out of discovery.
4. `PodMatchingWorkflowService` re-checks inside its membership transaction and never creates or
   advances a membership for an excluded pair (`none`, reason `excluded`), whoever calls it.
5. `PodRecommendationService` never recommends the POD to the employee.
6. Approval refuses an excluded pair, and an excluded review is resolved.

An exclusion is **not** a POD ban:
- It never removes or demotes an existing member.
- It deletes no match, reason or history.
- It keeps the POD in manual discovery.
- The employee can still apply manually under the existing `requestToJoin` policy.

Other PODs still match the employee, and other employees still match the POD.

### 18.6 Sequences

**Approval**

    qualified match → pending_business_approval + pod_match_reviews row (one transaction)
    → business approves → re-validated qualification, pending → outer_circle
    → history row (actor = business user, "Match approved by business"), review = approved

**Exclusion**

    qualified match → pending review → business excludes
    → exclusion record + pending → rejected (history "Excluded from future POD matching…") + review = excluded, atomically
    → future automated matching skips this employee/POD pair
