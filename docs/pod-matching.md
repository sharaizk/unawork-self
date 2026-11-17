# POD matching: scoring (UNA-013), configuration (UNA-014) and user-to-POD matching (UNA-015)

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
| Config | `config/pod_matching.php` (`version`, `weights`, `availability`, `default_minimum_threshold`, `recalculation_chunk_size`, `max_timezone_tolerance_hours`) |
| POD matching mode (UNA-014) | `App\Enums\Pod\PodMatchingMode` (`recommend_only`, `pending_approval`, `automatic`) |
| Qualification (UNA-014) | `App\Services\Matching\MatchQualification`, `App\Enums\Matching\QualificationReason` |
| Mode application (UNA-014) | `App\Services\Matching\PodMatchingWorkflowService::evaluateAndApply()` → `PodMatchOutcome` (`App\Enums\Matching\MembershipAction`) |
| Recalculation (UNA-014) | `App\Services\Matching\PodMatchRecalculationService` → `RecalculationSummary` |
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

## 12. Recalculation (UNA-014)

`PodService` detects matching-relevant changes inside its existing configuration transaction:
`PodMatchRecalculationService::configurationSnapshot()` runs before and after the change, and
`scheduleChanges()` compares the two. The snapshot holds the POD's mode, threshold and status, and
a fingerprint per requirement made of:
- role, minimum years, minimum proficiency, work mode, engagement type, location
- active flag, start date, minimum hours, timezone, tolerance, project type, mandatory criteria
- sorted skill ids

| Change | Effect |
|---|---|
| POD mode or threshold | after commit: every persisted match of the POD is re-evaluated and re-applied |
| A requirement's matching fingerprint | after commit: that requirement's persisted matches only |
| A requirement made inactive | its `pod_matches` are deleted in the same transaction (no stale recommendation) |
| A requirement deleted | its matches are removed by the FK cascade |
| A new requirement | nothing: there are no persisted candidates yet |
| Publication (`PodService::publish()`) | after commit: every persisted match of the POD is processed |
| Title, description, image, POD skills, headcount, notes | nothing |
| Company template edits | nothing: POD snapshots are independent |
| Company default changes | nothing: existing PODs are not touched |

- The re-evaluation is registered with `DB::afterCommit()`. It therefore runs only after the
  configuration (and any enclosing transaction) commits, never after a rollback, and outside the
  configuration transaction.
- Scope is limited to candidates already in `pod_matches`; there is no candidate discovery.
- Matches are processed with `chunkById(config('pod_matching.recalculation_chunk_size'))` and the
  relations in `CHUNK_RELATIONS` eager-loaded. Each one goes through `evaluateAndApply()`.
- A candidate who is no longer an employee, or whose requirement is inactive, is counted as
  `skipped`. Any other exception is `report()`ed and counted as `failed`; the run continues.
  Exception details never reach users.
- `RecalculationSummary` counts `evaluated`, `qualified`, `created`, `advanced`, `unchanged`,
  `skipped`, `failed` and `invalidated`. `lastSummary()` returns the latest run.

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
   - one `DB::transaction(..., 3)`, so a run is all or nothing
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
