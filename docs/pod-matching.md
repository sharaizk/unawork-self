# POD matching criteria and explainable scoring (UNA-013)

This document describes the code as implemented. The only scoring code is
`App\Services\Matching\PodMatchScorer`. Configuration lives in `config/pod_matching.php`.

A match always compares **one employee** with **one active POD worker requirement**
(`pod_worker_requirements`). A POD with three requirements gives an employee up to three
independent matches. Nothing here chooses a "best" requirement, and nothing changes POD membership.

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
| Config | `config/pod_matching.php` (`version`, `weights`, `availability`, `max_timezone_tolerance_hours`) |

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
- Matching never writes `pod_users`, workspace or project memberships, histories or notifications, and it never invites or accepts anyone. `pods.matching_mode` is not read.

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

`PodMatchResource` wraps the same data with ids, for JSON.

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
- No automatic invitations, acceptances or `PodUser` state transitions, whatever `pods.matching_mode` says.
- No queues, scheduled re-evaluation, AI/LLM calls, vector search, geocoding or external APIs.
- No extra weighted categories: work mode and engagement type stay unweighted compatibility checks.
- No caching or invalidation of stored matches when a profile or requirement changes. A later task decides when to call `PodMatchService::evaluate()`.
