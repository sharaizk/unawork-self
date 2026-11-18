# AGENTS.md — UnaWork engineering guide for AI coding agents

This is the canonical, tool-agnostic guide for any AI agent (Claude Code, Cursor, Copilot, Codex, Aider, etc.) working in this repository. Read it fully before changing code. `CLAUDE.md` imports this file.

> Grounded in a read-only audit of the codebase (Nov 2026). If you find something here is no longer true, update this file in the same change.

---

## 1. What this project is

UnaWork is a **Laravel 10 monolith (PHP 8.1+, deployed on 8.2)** built on top of a purchased "Taskly" SaaS project-management script (workspaces, projects, tasks, invoices, contracts, plans, many payment gateways, Chatify chat). UnaWork-specific features were added on top: **PODs** (teams with inner circles and news), **Find Work**, **Hiring**, **public Company pages**, **Job vacancies and applications**, **Employee profiles and skills**, **Finance**.

- **Frontend reality:** ~100% of live screens are **Blade + Bootstrap 5 ("Dash" admin theme) + jQuery/vanilla JS**, with assets as static files under `public/assets/`.
- **Vue 3 / Inertia exists only as unused Jetstream scaffolding** (`resources/js/**`). Nobody calls `Inertia::render`, and nothing builds it. **Do not build features in Vue.**
- **Alpine, Livewire and Tailwind** appear only in dead Breeze/Jetstream leftovers. **Do not use them on live screens.**

---

## 2. Non-negotiable safety rules

1. **Branches:** `dev` is the main branch. Feature branches are named after the ticket (`UNW-58`) and merged by Bitbucket PR into `dev`.
2. **A push to `dev` deploys to the dev server and a push to `main` deploys to production** (`bitbucket-pipelines.yml` → `deploy.php`). Never push or merge unless a human explicitly asks you to.
3. **Deploys run `artisan migrate` automatically.** Migrations must be additive and safe on existing data. Never edit an already-merged migration; add a new one.
4. **Deploys run `artisan config:cache`.** Never call `env()` outside `config/*.php`, because it returns `null` once config is cached. Use `config('...')`.
5. **Deploys do not build frontend assets.** Anything a page loads must be a committed static file under `public/`. Do not run `npm run dev/prod` (it rewrites the committed `public/js/app.js` / `public/css/app.css`) unless explicitly asked.
6. **Tests are not isolated:** `phpunit.xml` has the sqlite in-memory DB commented out, so tests hit whatever DB `.env` points to. Do not run the test suite unless you have confirmed it targets a disposable database.
7. Never commit `.env`, credentials or keys. Don't add new payment gateways or SDKs without being asked.
8. Don't do drive-by refactors of legacy Taskly code. Fix only what the task needs, and mention other problems in your summary.

---

## 3. Repository map (what matters)

```
app/
  Http/Controllers/            # ~76 web controllers (legacy Taskly = large, logic-heavy)
  Http/Controllers/Api/        # UnaWork JSON endpoints used by Blade JS (Sanctum-stateful)
  Http/Controllers/Auth/       # Custom Blade auth (overrides Fortify/Jetstream routes)
  Http/Requests/<Feature>/     # FormRequests (newer UnaWork code)
  Http/Resources/              # PodResource, EmployeeResource
  Http/Middleware/             # XSS, CheckPlan, HandleInertiaRequests (vestigial)
  Services/                    # PodService, CompanyService, JobVacancyService, ... (newer code)
  Policies/                    # Pod, News, JobVacancy, JobApplication, Project(Workspace), ...
  Enums/                       # User/UserRole, UserWorkspace/Permission|Status, PodUser/Permission|Status
  Filters/Employee/            # query filters for hiring search
  Models/                      # Eloquent; Models/Utility.php = settings/file helpers (god class)
  Traits/ApiResponser.php      # {is_success, message, data} JSON helper
routes/web.php                 # ~1,300 lines; UnaWork routes at the top, Taskly workspace routes below
routes/api.php                 # Sanctum group: pod, news, job-vacancies, job-applications, skills, ...
routes/auth.php                # Blade auth routes
resources/views/               # ~250 Blade templates (THE frontend)
resources/lang/*.json          # 16 locales for __() strings
public/assets/                 # theme CSS/JS, plugins, landing CSS — loaded via asset()
Modules/LandingPage/           # nwidart module: marketing home page "/" and its CMS
```

**Ignore / don't extend** (dead or duplicate):
- `resources/js/**`, `resources/views/app.blade.php`
- `layouts/app|navigation|employee|client.blade.php`
- `resources/views/components/*`, `profile/*`, `api/*`, `dashboard.blade.php`, `navigation-menu.blade.php`
- `setting.blade (copy).php`, `vendor/Chatify (copy)/`
- `public/landing/`, `public/js/app.js`
- Files named like `*---`, `__*.js`, `*(1).js`

---

## 4. Backend conventions

### 4.1 Two generations of code — follow the newer one

| | Legacy Taskly (don't copy) | UnaWork / newer (copy this) |
|---|---|---|
| Validation | `Validator::make` in controller, `redirect()->back()->with('error', $messages->first())` | `app/Http/Requests/<Feature>/StoreXRequest.php`, `$request->validated()` |
| Business logic | Inside giant controllers (`ProjectController` is 2,400+ lines) | `app/Services/<Feature>Service.php`, injected via the constructor |
| Authorization | `Auth::user()->type == 'admin'`, creator checks inline | Policies (`$this->authorize('update', $pod)`) registered in `AuthServiceProvider`, plus `UserWorkspace\Permission` enum checks |
| Writes | Web POST with redirect and flash | Blade JS → `routes/api.php` endpoint (`api.*` route names) → JSON / `JsonResource` |
| Reads | `view(..., compact(...))` | Same, plus JSON `{html, pagination}` built with `view(partial)->render()` for live filtering |

Reference implementations:
- `app/Http/Controllers/PodController.php` (web pages + search)
- `app/Http/Controllers/Api/PodController.php` (writes)
- `app/Services/PodService.php`
- `app/Http/Requests/Pods/*`
- `app/Policies/PodPolicy.php`

### 4.2 Routing

- **Public UnaWork pages** (no auth): top of `routes/web.php`, e.g. `/pods/list`, `/pods/{pod}`, `/company/{company}`, `/find-work`, `/hiring`, `/job-vacancies/{vacancy}`, `/employee/{employee}`.
- **Authenticated UnaWork management**: the `Route::middleware(['auth'])->group(...)` near the top, e.g. `/account/pods/{podId}`, `/account/finances`, `/account/job-vacancies`.
- **Workspace (Taskly) routes** live inside the `verified` group and are scoped by workspace slug: `/{slug}/projects/...`, usually with `->middleware(['auth', 'XSS'])`, plus `CheckPlan` for plan-gated pages.
- **Client portal** routes are the same URIs under `Route::prefix('client')->as('client.')` with `auth:client`. If you change a workspace feature, check whether a `client.*` twin exists.
- **API** (`routes/api.php`): inside the `auth:sanctum` group. Called from Blade pages with the session cookie (Sanctum stateful) plus CSRF. Name routes `api.<feature>.<action>`.
- Always name routes and reference them with `route('name', [...])`. Add `XSS` middleware to routes that accept user input, matching the neighbouring routes.

### 4.3 Domain concepts to respect

- **Users:** `users.type` is `admin` (super admin) or `user`. A separate **client** guard handles client logins. Spatie roles from `App\Enums\User\UserRole` are `company` and `employee`.
- **Account identity (UNA-002):** never read `users.type`, call `hasRole('company'|'employee')` or compare `getGuard()` to decide who someone is. Use the `App\Contracts\HasAccountType` API that both `User` and `Client` implement:
  - methods: `accountType()`, `isAdministrator()`, `isRegularUser()` (a non-admin web user), `isCompany()`, `isEmployee()`, `isClient()`
  - query scopes: `User::administrators()`, `nonAdministrators()`, `companies()`, `employees()`
  - precedence: administrator, then company, then employee
  - write path: create accounts with `User::createWithAccountRole($attrs, UserRole::X)` and change them with `$user->assignAccountRole(UserRole::X)`. Both set `type = user` and keep exactly one persona role, leaving other roles alone. Invited or generated members are employees; admin "Create Company" and CSV import create companies.
  - workspace and POD permissions (`Owner`, `users_edit`, ...) are a separate concept, not account identity.
- **Workspace:** the tenant. The current one is `Auth::user()->currant_workspace` (sic, the misspelling is real). URLs use `$currentWorkspace->slug`. A workspace's owner is `$workspace->creater` (sic).
- **POD:** each Pod owns a Workspace through the polymorphic `workspaces.ownerable`. Member permissions use `App\Enums\PodUser\Permission`, and workspace member permissions use `App\Enums\UserWorkspace\Permission` (`*_INDEX`, `*_EDIT`).
- **Settings and files:** `App\Models\Utility::getAdminPaymentSetting()`, `getcompanySettings($workspaceId)` and `get_file($path)`. Reuse them; don't re-implement them.
- **PayHere** (`laravel-payhere/laravel-payhere`): the package is in composer `extra.laravel.dont-discover` and loaded by `App\Providers\PayHereServiceProvider` with config + views only, because its own provider registers unauthenticated `/payhere/api/*` routes, a single-credential webhook, and auto-run migrations. Use `App\Services\PayHereService`: plans use admin credentials, invoices use workspace credentials; payments are confirmed only by the signed `payhere.callback.notify` callback against the `payhere_checkouts` table. Return/cancel pages must never mark a payment paid.

### 4.4 Database

- Migrations live in `database/migrations` (118 so far). Name new ones in Laravel's timestamped style, keep them reversible, and add nullable columns or defaults for existing rows.
- New models: put `$fillable` and relationships on the model, and add a Policy if it is user-owned.
- **Memberships are unique** (UNA-004). `pod_users(user_id, pod_id)`, `user_workspaces(user_id, workspace_id)` and `user_projects(user_id, project_id)` each have a unique index.
  - Create a membership only through `PodUser|UserWorkspace|UserProject::findOrCreateMembership($userId, $parentId, $values)`. Never use `attach()`, `create()`, `syncWithoutDetaching()` or `exists()` + `create()`.
  - The helper returns an existing row untouched and survives a concurrent duplicate insert. Run notifications, emails and grants only when `->wasRecentlyCreated` is true.
  - Change a pending membership's status with a conditional update (`where('status', <pending>)->update(...)`) and act only if exactly 1 row changed.
- **Membership operations are transactional** (UNA-005). Multi-record membership changes live in services, never controllers: `PodUserService`, `App\Services\Membership\WorkspaceMembershipService` and `ProjectMembershipService`.
  - Each operation runs in one `DB::transaction(..., 3)`, locks the rows it decides on, and returns a `MembershipResult` (`applied`, `state`, `reason`, named effects).
  - Notifications, emails and chat invitations are effects. `AfterCommit` runs them after the commit, and only for the attempt that committed. Never call `Utility::sendNotification()`, `Mail` or `SendEmail::dispatch()` inside a membership transaction.
  - Remove a `UserWorkspace` with `->delete()` so Spatie's hooks clean up its permissions and roles; never `detach()`.
  - Use `CurrentWorkspace::reassignIfLost()` whenever access to a workspace is removed.
- **POD membership state is explicit** (UNA-006).
  - `pod_users.state` (`App\Enums\PodUser\State`) is the single workflow and circle source. The legacy `status` column and `PodUser\Status` enum are gone.
  - Change it only through `App\Services\Membership\PodStateTransitionService`. It owns the graph (`State::transitions()`), the reason rules and access syncing, and appends one `pod_user_state_histories` row per change. The actor is passed explicitly.
  - Workspace permissions follow the state (`State::accessProfile()`) and are never read to decide it. Inner Circle pages need both the `inner_circle` state and `projects_index`.
  - Use the `PodUser` scopes (`current`, `applicants`, `activeMembers`, `outerCircle`, `innerCircle`) and `State` sets instead of string lists.
  - Removal sets `removed` and keeps the row. `Pod::users()` and `User::pods()` hide removed memberships.
- **Employee profiles are structured** (UNA-007).
  - Repeatable data lives in `profile_experiences`, `profile_educations`, `profile_certifications`, `profile_portfolios`, `profile_preferred_roles` (→ `job_titles`) and `profile_project_types`; skill proficiency is `user_skills.proficiency` (unique user/skill pair). Never store these as JSON. Bounded values are backed enums in `App\Enums\Profile`.
  - `App\Services\Profile\ProfileCompletionService` is the only scoring code (`percentage()`, `breakdown()`, `isEligible()`, `requiresOnboarding()`): ten 10% sections, calculated on the fly, never stored. The minimum is `config('profile.minimum_completion')` (80). Only employees are subject to it.
  - Employees edit their profile at `/my-profile` (`my-profile.edit` / `my-profile.update`, `MyProfileController`, `ProfileService::saveProfile()` in one transaction). `/my-account` keeps account, avatar and password only.
  - Gate employee job access with the `profile.complete` middleware (`RequireCompleteEmployeeProfile`); it lets guests, companies, administrators and clients through. `PodStateTransitionService` refuses an incomplete employee entering or progressing within an active circle (stepping back to the plain Outer Circle is not gated).
  - Test fixtures: `UsesInMemorySqlite::createIdentitySchema()` now builds the profile tables, `completeEmployeeProfile($user)` gives an employee a 100% profile for tests unrelated to eligibility, and `createAdminLayoutSchema()` adds the tables `layouts.admin` reads so admin pages can be rendered.
- **Reference catalogs are administrator-managed.** Industries (`industries.*`), Skills (`skills.*`) and Professional Roles (`JobTitle`, `job-titles.*`; a job title, never an authorization role) share `Models\Concerns\IsCatalogEntry`, `Services\Catalog\CatalogService`, `Http\Controllers\Catalog\CatalogController`, the `Requests\Catalog\*` FormRequests and the `catalog.*` views.
  - Labels are whitespace-normalized and unique case-insensitively. Usage is counted through each model's `usageRelations()`; an entry in use is never deleted (409 JSON / flash error). Don't rely on FK cascades for catalog values.
  - Only administrators create, rename or delete entries (policies accept `HasAccountType`, so clients are denied cleanly). Any authenticated account may read `GET /api/skill`; employees only select existing skills and roles.
  - Skills and roles are tagged with industries (`industry_skill`, `industry_job_title`; `tagRelations()`), and a profile has one optional `industry_id`. Tags are classification, not usage, in both directions: deleting a skill, role or industry drops its links. An industry's "Used by" counts only workspaces and profiles (linked skills/roles get their own column), and an industry used by either cannot be deleted. The bundled Tabler font is an old version (no `building-factory-2`, `id-badge-2`, `books`, …), so check `public/assets/fonts/tabler-icons.min.css` before using an icon. `scopeForIndustry($id)` returns that industry's entries plus untagged (general) ones; `/my-profile` filters its pickers client-side from `data-industries` and never rejects a skill or role from another industry.
  - Starter skills and roles per industry live in `database/seeders/data/catalog.php` and are installed by `CatalogSeeder` (also called from `DatabaseSeeder`). It reuses existing rows (case-insensitive, plus aliases such as "Devops" for "DevOps Engineer"), only adds industry tags, and never tags `general` entries. Deploys don't run it; run `php artisan db:seed --class=Database\\Seeders\\CatalogSeeder` per environment.
  - Required reference data ships as idempotent data migrations (deploys never run `db:seed`), e.g. `2026_11_18_000000_install_reference_industries.php`.
- **Company (business) profile and onboarding** (UNA-010).
  - The company profile lives on the company's own, non-POD workspace (`BusinessOnboardingCompletionService::companyWorkspace()` via `ownWorkspace()`, never `currant_workspace`), reusing the existing `workspaces` columns plus contact person, `matching_mode` (`Enums\Company\MatchingMode`), default approval preferences (`Enums\Company\ApprovalMode`) and onboarding progress. Worker requirements are `workspace_worker_requirements` (+ `workspace_worker_requirement_skill`) on the job-title and skill catalogs.
  - `BusinessOnboardingCompletionService` is the only scoring code (eight weighted sections, `config/company_onboarding.php`, calculated live). `business_onboarding_completed_at` is history only.
  - Companies edit it at `/company/onboarding` (`company.onboarding.*`, `CompanyOnboardingController`, `BusinessOnboardingService` in one transaction, `ProjectPolicy::manageCompanyProfile`). The legacy settings page shows a summary only; `WorkspaceController@settingsStore` no longer writes company fields.
  - Approval preferences are stored defaults only; membership changes still go through `PodStateTransitionService`.
- **POD draft/publish lifecycle** (UNA-011).
  - `pods.status` (`Enums\Pod\PodStatus`) is `draft` for new PODs; PODs that existed before UNA-011 were migrated to `published`. Use `Pod::published()` / `drafts()` for every query; public pages, search, Find Work, company pages and the public API show published PODs only, and a draft's public URL is 404.
  - Companies create drafts on the guided page (`pods.create` → `pods.drafts.store` → `pods.setup.edit|update` → `pods.publish`, `PodSetupController`, `PodPolicy::configure|publish`). `PodService::createDraft/updateDraft/publish` are the only write paths (`createPod/updatePod` are compatibility wrappers); each runs in one transaction with safe image handling.
  - `Services\Pod\PodConfigurationService` is the only POD scoring code (`config/pod_onboarding.php`). Publishing re-checks ownership, 100% configuration and the company's business onboarding under a row lock; a published POD may not lose a complete section. `pods.matching_mode` (`automatic`/`pending_approval`) is preselected from the company preference and never moves a membership by itself.
  - POD requirements are POD-owned snapshots (`pod_worker_requirements` + skills); importing a company template copies it (`source_workspace_requirement_id` records the origin only).
  - Post-login/registration/verification onboarding destinations come from `Services\Onboarding\OnboardingRedirect` (employee first, then company). Tests: `completeBusinessProfile($company)` gives a company a 100% profile (POD owners in unrelated tests).
- **POD matching is deterministic and explainable** (UNA-013, full rules in `docs/pod-matching.md`).
  - A match is one employee against one active `PodWorkerRequirement`. `App\Services\Matching\PodMatchScorer` is the only scoring code; it is pure (fixed evaluation time, declared eager loads, integer arithmetic) and the weights/version live in `config/pod_matching.php`. Criterion keys are `Enums\Matching\MatchCriterion`; reasons store `MatchReasonCode` + structured context, and `PodMatchPresenter` / `PodMatchResource` produce the translated text.
  - Matching targets (`required_start_date`, `minimum_hours_per_week`, `timezone`, `timezone_tolerance_hours`, `project_type`, `mandatory_criteria`) live on both requirement tables (`HasMatchingConfiguration`) and are copied on template import. `MatchingConfiguration` decides which criteria are configured; only configured criteria may be mandatory (request after-hook + `assertValid()` in every write path).
  - `PodMatchService::evaluate()` accepts only `isEmployee()` accounts with a saved profile, upserts `pod_matches` (unique user + requirement) and replaces its `pod_match_reasons` in one transaction. A failed mandatory criterion makes the match `disqualified` but keeps the score. Matching never touches memberships or `PodUser` state.
- **POD matching configuration** (UNA-014, `docs/pod-matching.md` §10–§14).
  - `pods.matching_mode` is `recommend_only` / `pending_approval` / `automatic` and `pods.minimum_match_threshold` is 0–100. Both are snapshotted from the company defaults (`workspaces.matching_mode`: manual→recommend_only, assisted→pending_approval, automatic→automatic; `default_minimum_match_threshold`, else `config('pod_matching.default_minimum_threshold')`) by `PodService::createDraft()` and never re-synced.
  - `MatchQualification::of()` is the only qualification rule (eligible, score ≥ threshold, active requirement, published POD). `PodMatchService` stays information-only; `PodMatchingWorkflowService::evaluateAndApply()` is the single entry point that acts on a qualified match, through `PodStateTransitionService` only (`start(..., allowReentry: false)`, applicants → Outer Circle). It is forward-only: it never demotes, removes, resumes or re-enters anyone, and never reaches the Inner Circle.
  - `PodService` snapshots the matching configuration before and after each save (`PodMatchRecalculationService::configurationSnapshot()`/`scheduleChanges()`). Matches of an inactivated requirement are deleted in the same transaction; persisted matches are re-processed via `DB::afterCommit()` only, in chunks, never with observers. Publication re-processes the POD's matches the same way.
- **User-to-POD matching** (UNA-015, `docs/pod-matching.md` §15). `DeterministicPodMatchingService::match($pod, $candidate, $at)` scores one employee against every active requirement of a POD with one timestamp, persists all results atomically through `PodMatchService::persistMany()` (constant query count), and returns a `PodMatchingResult` with the deterministic best match (status eligible > disqualified > not_scorable, then score, then lowest requirement id). It never touches memberships; act on a result with `PodMatchingWorkflowService::applyPersisted()`. Don't add another scorer or aggregate match table.
- **Match generation is queued** (UNA-016, `docs/pod-matching.md` §16). Never score in a web request. Profile writes (`ProfileService`, via `EmployeeMatchingFingerprint`) and POD configuration writes (`PodService` via `PodMatchRecalculationService`) queue `GenerateMatchesForEmployee` / `GenerateMatchesForPod` through `MatchGenerationDispatcher` (after commit; enqueue failures are reported, never rolled back). Coordinators fan out `GenerateEmployeePodMatch` pair jobs (scalar ids + generation timestamp, `matching` queue, unique until processing, 5 tries), which call the configured `App\Contracts\PodMatchingAlgorithm` (deterministic by default; resolve it, never `new` it) and then `PodMatchingWorkflowService::applyPersisted()`. `PodMatchService::persistGeneration()` refuses to overwrite a newer `evaluated_at` and stores `pod_matches.algorithm`. A queue worker for `matching` is required wherever `QUEUE_CONNECTION` is not `sync`.
- **Employee POD recommendations** (UNA-017, `docs/pod-matching.md` §17). `/recommendations/pods` reads stored matches through `PodRecommendationService` (active algorithm/version only, `MatchQualification` as the filter, best match per POD by `DeterministicPodMatchingService::rank()`, ranked by score, then generation time, then POD id) and renders presenter output only. Apply reuses `api.pods.join` (now `profile.complete`, employees only, 409 on duplicates). Dismissals live in `pod_recommendation_dismissals` (`PodRecommendationDismissalService`, user from the session) and only hide a POD from that employee's recommendations; manual discovery (`pods.list`) is untouched. Theme links without a class get the theme colour (`body.theme-N a:not([class])`), so give feature links a class.
- **Business match review** (UNA-018, `docs/pod-matching.md` §18). A matching proposal is identified ONLY by its `pod_match_reviews` row (created by `PodMatchingWorkflowService` in the membership transaction); never infer it from `pending_business_approval`, a `PodMatch` or a history reason. Decisions go through `PodMatchReviewDecisionService` (approve re-validates qualification; reject/exclude use stable fallback history reasons); any other transition out of pending resolves the review via `PodMatchReviewResolver`, called from `PodStateTransitionService::apply()`. Exclusions (`PodMatchingExclusionService`) are enforced by the jobs, discovery, workflow, recommendations and approval; they are not a POD ban. Note: `pod.manage.nav` legacy links reassign `$pod` to an id, so the partial captures the model first.
- **POD Knowledge Base documents and version history** (UNA-020).
  - Documents (`pod_documents`) are owned by a POD (`pod_id`), typed (`Enums\PodDocument\PodDocumentType`: guideline, screening, onboarding, policy, process, technical, general), and lifecycle-tracked (`Enums\PodDocument\PodDocumentStatus`: draft, published, archived).
  - Version history (`pod_document_versions`, `Enums\PodDocument\PodDocumentVersionStatus`) preserves full historical records: published and archived versions are immutable (`isImmutable()`), cannot be edited or deleted, and deleting a document with published history is strictly refused.
  - Publishing a new draft version archives the previously published version (`archived_at = now()`) and activates the new version (`published_at = now()`).
  - Access is policy-enforced on the server (`PodPolicy` / `PodDocumentPolicy`): management is restricted to authorized business users (owner or active workspace member with `users_edit`); associated applicants (`State::applicants()`) and active members can only view published documents and published versions; unrelated accounts, guests, and removed/rejected members have no access.
  - Screening consumers interface: `PodDocumentService::getPublishedVersionForScreening(Pod $pod, int $documentId)` returns only the active published version of a published document. Draft or archived versions are never returned. `getPublishedDocuments(Pod $pod)` loads exactly one active published version per document using deterministic ordering.
- **POD Onboarding material, private files, links and reordering** (UNA-021).
  - Extends UNA-020 Knowledge Base system with private file attachments (PDF, DOC, DOCX), external resource links, and text instructions. Config in `config/pod_materials.php` (`disk`, `directory`, `max_file_size_kb`, `allowed_extensions`, `allowed_mimes`).
  - Stored strictly on private storage disks (`local` or private cloud storage). Public disks and disks with public visibility/web roots are validated and rejected (`PodDocumentService::assertPrivateDisk()`). File downloads are served exclusively through protected controller routes (`pods.documents.download`, `pods.documents.versions.download`). Public storage URLs are never exposed.
  - Upload lifecycle & states (`Enums\PodDocument\PodDocumentUploadStatus`: ready, pending, failed). Incomplete or failed uploads cannot be published (`CannotPublishIncompleteUploadException`) and are never returned to applicants or screening interfaces.
  - Material replacement preserves published and archived versions and historical files without deletion or overwriting. When an un-published draft file is replaced or removed, the unreferenced old draft file is safely cleaned up from private storage after commit (`DB::afterCommit`); retained file paths and published/archived files remain untouched.
  - Storage failures during replacement in `updateDocument()`, `createDraftVersion()`, and `updateDraftVersion()` reject the update by throwing a translated `\DomainException`, keeping the draft's existing file metadata, content, attachment, and status completely unchanged. Transaction rollback guarantees that `DB::afterCommit()` cleanup never runs, no replacement is published, and existing draft files are preserved on private storage.
  - Reordering (`position` on `pod_documents`) is scoped strictly to the POD, server-validated, and transactionally persisted (`PodDocumentService::reorderDocuments()`).
- **Account provisioning is unified** (UNA-012). Every ordinary company/employee `User` is created through `App\Services\Provisioning\UserProvisioningService` (`provision()` owns a `DB::transaction(..., 3)` and schedules effects after commit; `apply()` joins the caller's transaction so membership services provision an invitee and create the invitation atomically). Never call `User::create`, `createWithAccountRole` or `assignAccountRole` from controllers/services; only seeders and tests may.
  - Every account gets exactly one persona role, `config('provisioning.default_plan_id')` (validated; `MissingDefaultPlan` otherwise), one owned non-POD workspace with an Owner membership and all owner permissions, `currant_workspace` on that workspace, default email templates, a referral code and (employees) a persisted empty `Profile`. Onboarding state comes from the onboarding services, never a stored flag.
  - Sources (`ProvisioningSource`): web registration (company/employee), API registration (always employee; 201 with token + onboarding), administrator "Create Company" (company), CSV import (company/employee; whole file validated first, existing accounts skipped, one transaction), and workspace/POD/project invitations (always employee; pending/target membership added, current workspace stays the owned one), Fortify (delegates, role required).
  - Emails are normalized (trim + lower case). Registration/admin/CSV refuse an existing email without changing it (`DuplicateRegistration` 422 / `ProvisioningConflict` 409); invitations reuse and repair a compatible employee and refuse companies, administrators and unclassified accounts (`IncompatibleInvitee`); a repeat invitation is `already_member` / `DuplicateInvitation` (409) with no effects. The `users.email` unique index settles races.
  - `SendLoginDetail` receives the password explicitly (never `$user->password`); self-registered users never get their password emailed.
- Roles and permissions are seeded from their enums. `DatabaseSeeder::FOUNDATION_SEEDERS` runs `RolesAndPermissionsSeeder` (every `UserRole` case) and then `PermissionsSeeder` (every `UserWorkspace\Permission` case) first, on every path. Both use the `web` guard and look records up by `name` + `guard_name`, so they are safe to rerun. To add a role or workspace permission, add an enum case and rerun `db:seed`; don't hand-write seeder lists. Workspace permissions belong to `UserWorkspace` records, never to the company/employee roles.

### 4.5 Translations

- Every user-facing string goes through `__('Text')`. If you add new keys, add them at least to `resources/lang/en.json`.

---

## 5. Frontend conventions (Blade-first)

### 5.1 Decide the approach from the surrounding feature

| Situation | Do this | Precedent |
|---|---|---|
| Authenticated workspace or account screen | `@extends('layouts.admin')` + Dash/Bootstrap markup | `projects/taskboard.blade.php`, `pod/manage/*`, `home.blade.php` |
| Public UnaWork page | `@extends('layouts.landing')` + page CSS in `public/assets/css/landing/` | `pod/list.blade.php`, `find_work/index.blade.php`, `company/landing/index.blade.php` |
| Auth page | `@extends('layouts.guest')` | `auth/login.blade.php` |
| Create/edit form | Blade **fragment** (no `@extends`) loaded into the global modal with `data-ajax-popup` | `projects/create.blade.php`, `pod/create_form.blade.php` |
| Live search, filter or pagination | `fetch` → controller returns JSON `{items: view(partial)->render(), pagination: (string) $p->links('vendor.pagination.custom')}` → swap `innerHTML` | `PodController@search` + `pod/list.blade.php` |
| Reusable card or row | A Blade partial in the feature folder, included with explicit params | `pod/landing/pod_card.blade.php`, `company/landing/company_card.blade.php` |
| Dashboard or charts | `layouts.admin` + ApexCharts via `@push('scripts')` | `home.blade.php` |
| Highly interactive UI (drag/drop, boards) | Blade + jQuery/vanilla + existing plugins (dragula, etc.) | `projects/taskboard.blade.php` |

**Never default to Vue, Inertia, Alpine, Livewire or Tailwind.** Introducing any of them first requires a team decision and a working asset build (see section 6).

### 5.2 Layout contracts

**`layouts.admin`**
- Sections: `page-title`, `links` (breadcrumb `<li>`s), `action-button` or `multiple-action-button`, `content`.
- Stacks: `@push('css-page')` and `@push('scripts')`.
- Provides the global `#commonModal` and `#commonModalOver` modals, the `show_toastr()` helper, `showNotification()`, SweetAlert2, select2, Choices, summernote, datepicker, feather, jQuery, and axios (from a CDN).

**`layouts.landing`**
- Sections: `title`, `body-class` (used to scope page CSS, e.g. `tpl-pods-list`), `content`, `scripts` (this layout uses **`@yield('scripts')`, not a stack**).
- Provides `#commonModal`, `showNotification()`, Bootstrap 5, and jQuery (bundled inside `assets/custom/js/site.core.js`). **No axios here, so use `fetch`.**

**`layouts.guest`**
- Sections: `page-title`, `language-bar`, `content`.
- Stack: `@push('custom-scripts')`.

### 5.3 Shared UI patterns (reuse them, don't reinvent)

- **Modal form:**
  ```html
  <a href="#" data-ajax-popup="true" data-url="{{ route('x.create', ...) }}" data-size="lg" data-title="{{ __('Create X') }}">
  ```
  Handled by `public/assets/custom/js/custom.js`. Use `data-ajax-popup-over="true"` for a modal on top of another modal.
- **Delete confirmation:** a hidden `<form id="delete-form-{{ $id }}" method="POST">@csrf @method('DELETE')</form>` plus a trigger with `class="bs-pass-para" data-confirm="{{ __('Are You Sure?') }}" data-text="{{ __('This action can not be undone. Do you want to continue?') }}" data-confirm-yes="delete-form-{{ $id }}"`.
- **Feedback:** after a redirect, set `->with('success'|'error', __('...'))` and the admin layout shows a toast. After AJAX, call `show_toastr(title, msg, 'success'|'error')` on admin pages or `showNotification(msg, 'success'|'danger')` on landing pages.
- **Validation errors:**
  - For full-page forms, use `@error('field') is-invalid @enderror` plus `<span class="invalid-feedback">`.
  - For AJAX forms, read Laravel's `422 { errors: {...} }` response and show the field messages. Don't replace them with a generic "failed" message, which several existing pages wrongly do.
- **Tables:** Bootstrap `.table` inside `.card` / `.table-responsive`. Adding `id="selection-datatable"` enables simple-datatables automatically.
- **Icons:** Tabler (`<i class="ti ti-plus"></i>`) first; Font Awesome is acceptable. Icon-only buttons need `title` or `aria-label`.
- **Grid and components:** Bootstrap 5 classes (`row`, `col-md-*`, `card`, `btn btn-sm btn-primary`, `form-control`, `form-label`). Use `data-bs-*` attributes (BS5), never `data-dismiss` / `data-toggle` (BS4).
- **Permissions in views:** mirror the server-side check, e.g. `$userWorkspace->hasPermissionTo(\App\Enums\UserWorkspace\Permission::X->value)` or the workspace-creator check, as in `partials/sidebar.blade.php`. The view is cosmetic; enforce the same check on the server.
- **Sidebar and nav:** new menu entries go in `resources/views/partials/sidebar.blade.php` inside the right role/guard branch.

### 5.4 JavaScript rules

- Plain JS or jQuery only, using libraries already loaded by the layout. Don't add CDN libraries if an equivalent already exists in `public/assets/js/plugins` or `public/assets/custom/libs`.
- Small page logic can stay in `@push('scripts')` (admin) or `@section('scripts')` (landing). For anything over ~50 lines, create `public/assets/custom/js/<feature>.js` and include it with `asset()`.
- Modal fragments are injected after page load, so bind with delegation (`$(document).on(...)`) or on `shown.bs.modal`.
- CSRF: jQuery is preconfigured by `$.ajaxSetup`. For `fetch`, send `'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content` and `'X-Requested-With': 'XMLHttpRequest'`.
- Build URLs with `route()` in Blade, using `':id'` placeholders plus `.replace()`. Encode query values with `encodeURIComponent` / `URLSearchParams`.
- **Escaping:** pass data to JS with `@json($value)` or `{{ }}`. Never put `{!! !!}` inside a JS string.

### 5.5 CSS rules

- Admin screens: use theme classes. Small additions go in `public/assets/custom/css/custom.css` or in `@push('css-page')`.
- Landing screens: add or extend a file in `public/assets/css/landing/` (and link it from `layouts.landing` if it is new). Scope styles under the page's `body-class`.
- Avoid new large inline `<style>` blocks. Don't write Tailwind utility classes; no live layout loads Tailwind, so they do nothing.
- Respect RTL (`style-rtl.css`) and dark mode (`style-dark.css`). Don't hardcode colours that should follow the theme; use `--color-customColor` or `bg-primary`.

---

## 6. Asset build — current reality

- `package.json` scripts run **Laravel Mix** (`webpack.mix.js` → `public/js/app.js`, `public/css/app.css`). Hardly any live page loads that output; only the invoice templates load `css/app.css`.
- `vite.config.js` exists, but **`vite` is not installed** and no npm script uses it. `resources/js/app.js` uses Vite-only syntax.
- **Live pages load hand-maintained files from `public/assets/**` and CDNs.** To add JS or CSS, add a static file under `public/assets/...`, commit it, and reference it with `{{ asset('...') }}`. No build step is needed.
- Fixing the build (choosing Mix or Vite, building in deploys) is a separate, explicitly requested task.

---

## 7. Recipes

### A. New admin CRUD feature inside a workspace (e.g. "Widgets")
1. Migration + model (`$fillable`, relationships) + Policy (register it in `AuthServiceProvider`).
2. `app/Http/Requests/Widget/StoreWidgetRequest.php` and `UpdateWidgetRequest.php`.
3. `app/Services/WidgetService.php` for create/update/delete logic.
4. Web routes in the right group: `/{slug}/widgets` (index), `/{slug}/widgets/create` (modal fragment), `/{slug}/widgets/{id}/edit` (fragment), named `widgets.*`, with `auth` and `XSS`.
5. Writes, choosing one of:
   - (a) web `POST`/`PUT`/`DELETE` routes that redirect back with a `success`/`error` flash; or
   - (b) `routes/api.php` `api.widgets.*` endpoints called by JS. Use (b) when the neighbouring feature does.
6. Views in `resources/views/widgets/`:
   - `index.blade.php` (`@extends('layouts.admin')`)
   - `create.blade.php` and `edit.blade.php` (fragments)
   - partials as needed
7. Sidebar entry in `partials/sidebar.blade.php`, permission-gated.
8. `__()` all strings and add them to `resources/lang/en.json`.

### B. New public UnaWork listing page
1. A controller method returns `view('feature.list', ...)` and a `search` method returns `response()->json(['items' => view('feature.partials.list', compact('items'))->render(), 'pagination' => (string) $items->links('vendor.pagination.custom')])`. This mirrors `PodController@search`.
2. View: `@extends('layouts.landing')`, `@section('body-class', 'tpl-feature')`, the card partial, and `@section('scripts')` with `fetch` filter logic.
3. CSS: `public/assets/css/landing/feature.css`, linked in `layouts/landing.blade.php`.

### C. New JSON endpoint used by Blade
- `Api\FeatureController` + FormRequest + Service + `$this->authorize()` + a `JsonResource` (or `ApiResponser`).
- Return 422 for validation (FormRequest does this automatically) and render the field errors in JS.

---

## 8. Known pitfalls (don't copy; fix only when asked)

- `layouts/admin.blade.php` and `partials/sidebar|topnav.blade.php` run DB queries and settings logic on every page, and call `env()`. Don't add more queries there; compute data in controllers.
- Admin and landing layouts inject flash messages with `{!! session('success') !!}` inside JS strings. This is an escaping/XSS risk. Don't replicate it.
- `layouts.landing` loads a second Bootstrap (CDN 5.3.3) over the theme CSS. Test landing pages visually.
- The Create Workspace modal in `layouts.admin` uses BS4 `data-dismiss`.
- Very large views (`setting.blade.php` and `users/setting.blade.php`, about 4k+ lines each). Make surgical edits only.
- Controllers reference views that don't exist (`chats.index`, `payment`, `users.rename_workspace`, `landingpage::create|edit|show`).
- `HandleInertiaRequests` is registered twice in `app/Http/Kernel.php`. `/` is defined twice in `routes/web.php`; the later `HomeController@landingPage` route wins.
- `php artisan route:list` crashes because `routes/api.php` references a missing `App\Http\Controllers\Api\ProjectController`. Until that is fixed, inspect routes by booting the app in a script and iterating `app('router')->getRoutes()`.
- `layouts.admin` already loads jQuery 3.6 and Chosen from cdnjs; pages must not add their own copies.
- Composer now effectively needs PHP 8.2+ (`laravel-payhere/laravel-payhere` v3 requires `php ^8.2`), even though `composer.json` still says `^8.1`.

---

## 9. Verifying your change

There is no frontend test suite, and backend tests are not DB-isolated (see section 2.6).

1. `php -l` on every PHP file you touched.
2. `php artisan route:list --name=<feature>` to confirm routes and names (needs `composer install` and a `.env`).
3. `php artisan view:clear`, then load the page in a browser:
   - check the console for JS errors
   - test the modal open/submit/error paths
   - test both an owner and a non-owner/permissioned member, and the client guard if relevant
   - check RTL and dark mode if you changed layout CSS
4. For migrations, run them only against a local or disposable database.
   - The migrations are MySQL-specific and fail on SQLite (`create_zoom_meetings_table`). For DB-backed tests, copy `tests/Feature/Authorization/PodAndProjectClaimAuthorizationTest.php`: it forces in-memory SQLite before boot, refuses to run otherwise, and builds only the tables it needs. Run it with `DB_CONNECTION=sqlite DB_DATABASE=:memory: vendor/bin/phpunit <path>`.
   - POD membership and project-claim authorization lives in `PodPolicy` (`viewMembers`, `manageMembers`, `acceptMember`, `declineMember`, `removeMember`, `updateMemberPermissions`, `toggleInnerCircle`, `requestToJoin`, `moderateJoinRequest`) and `UserProjectPolicy` (`create`, `accept`, `decline`). Reuse these abilities rather than re-checking `created_by` / `users_edit` inline.
   - For manual testing of those abilities, `database/seeders/Una001AuthorizationDemoSeeder.php` creates `una001.*@example.test` users, a demo POD in every membership state, a pending project request and a second POD. It is local-only (refuses unless `APP_ENV=local`) and is not called by `DatabaseSeeder`.
5. In your summary, state what you verified and what you could not.

---

## 10. Read these first

1. `resources/views/layouts/admin.blade.php` and `resources/views/layouts/landing.blade.php`
2. `resources/views/partials/sidebar.blade.php`
3. `public/assets/custom/js/custom.js` (modal, toast and AJAX conventions)
4. `app/Http/Controllers/PodController.php`, `app/Http/Controllers/Api/PodController.php`, `app/Services/PodService.php`, `app/Http/Requests/Pods/`
5. `resources/views/pod/list.blade.php` (public list + fetch pattern) and `resources/views/projects/taskboard.blade.php` (admin interaction pattern)
6. `routes/web.php` (top ~200 lines for UnaWork features; the `verified` group for workspace features) and `routes/api.php`
7. `deploy.php` and `bitbucket-pipelines.yml` (what happens on push)
