<?php

namespace App\Services;

use App\Enums\Pod\PodMatchingMode;
use App\Enums\Pod\PodStatus;
use App\Models\Pod;
use App\Models\PodUser;
use App\Models\PodWorkerRequirement;
use App\Models\User;
use App\Models\UserWorkspace;
use App\Models\Utility;
use App\Models\Workspace;
use App\Models\WorkspaceWorkerRequirement;
use App\Services\Company\BusinessOnboardingCompletionService;
use App\Services\Matching\MatchingConfiguration;
use App\Services\Matching\PodMatchRecalculationService;
use App\Services\Pod\IncompleteBusinessOnboarding;
use App\Services\Pod\IncompletePodConfiguration;
use App\Services\Pod\PodAlreadyPublished;
use App\Services\Pod\PodConfigurationService;
use App\Services\Pod\UnauthorizedPodMutation;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * POD lifecycle (UNA-011, hardened UNA-005 style).
 *
 * - createDraft(): one DB::transaction(..., 3) creates the draft POD, its workspace, the owner's
 *   pod_users + user_workspaces memberships (findOrCreateMembership), the initial state-history row,
 *   skills and requirements. Any company may draft; nothing is public yet.
 * - updateDraft(): saves configuration in one transaction; a published POD may not lose a section
 *   that was complete (IncompletePodConfiguration) and is never silently unpublished.
 * - publish(): locks the POD and re-checks ownership, configuration (PodConfigurationService) and the
 *   company's business onboarding (UNA-010) before setting status/published_at atomically.
 *
 * Images are uploaded before the transaction, removed again if it fails, and an old image is deleted
 * only after the replacement has committed. No membership state is written outside
 * PodStateTransitionService except the owner's first state, as before.
 */
class PodService
{
    private const ATTEMPTS = 3;
    public const IMAGE_DIR = 'pod-images';

    public const REQUIREMENT_FIELDS = [
        'job_title_id', 'headcount', 'minimum_years_experience', 'minimum_skill_proficiency',
        'preferred_work_mode', 'preferred_engagement_type', 'location', 'description', 'is_active',
        // UNA-013 matching targets (mandatory_criteria is handled by MatchingConfiguration::fill()).
        'required_start_date', 'minimum_hours_per_week', 'timezone', 'timezone_tolerance_hours', 'project_type',
    ];

    public function __construct(
        private ?BusinessOnboardingCompletionService $businessProfiles = null,
        private ?PodConfigurationService $configuration = null,
        private ?PodMatchRecalculationService $recalculation = null,
    ) {
        $this->businessProfiles ??= app(BusinessOnboardingCompletionService::class);
        $this->configuration ??= app(PodConfigurationService::class);
        $this->recalculation ??= app(PodMatchRecalculationService::class);
    }

    /** Public listing: published PODs only. */
    public function getPods(?array $skillIds = null, $perPage = 15)
    {
        return Pod::published()
            ->when($skillIds, fn ($query) => $query->whereHas('skills', fn ($q) => $q->whereIn('skills.id', $skillIds)))
            ->paginate($perPage);
    }

    // ------------------------------------------------------------------ create

    /**
     * Create a draft POD owned by $actor (a company with its own workspace).
     *
     * @param array $data title (required by the request), description, matching_mode,
     *                    minimum_match_threshold, skills[], requirements[] (+ requirements_present),
     *                    import_template_ids[]. Mode and threshold default to a snapshot of the
     *                    company defaults (UNA-014).
     */
    public function createDraft(User $actor, array $data, ?UploadedFile $image = null): Pod
    {
        $companyWorkspace = $this->companyWorkspaceOf($actor);
        $newImage = $image ? $this->uploadImage($image) : null;

        try {
            return DB::transaction(function () use ($actor, $data, $newImage, $companyWorkspace) {
                $pod = new Pod($this->basics($data));
                $pod->forceFill([
                    'created_by' => $actor->id,
                    'status' => PodStatus::DRAFT,
                    'image' => $newImage,
                    'matching_mode' => $this->initialMatchingMode($data, $companyWorkspace),
                    'minimum_match_threshold' => $this->initialThreshold($data, $companyWorkspace),
                    'configuration_started_at' => now(),
                ])->save();

                $this->attachOwnerToPod($actor->id, $pod);
                $workspace = $this->createWorkspaceForPod($pod);
                $this->attachOwnerToWorkspace($workspace, $actor->id);

                $this->saveConfiguration($pod, $data, $companyWorkspace);

                return $pod->fresh(['skills', 'workerRequirements.skills', 'workspace']);
            }, self::ATTEMPTS);
        } catch (\Throwable $e) {
            $this->deleteImage($newImage);
            throw $e;
        }
    }

    /**
     * Compatibility wrapper for the previous API: creates a draft through the same hardened path.
     * $data['created_by'] is resolved server-side by every caller.
     */
    public function createPod(array $data): Pod
    {
        $actor = User::findOrFail((int) ($data['created_by'] ?? 0));
        $image = ($data['image'] ?? null) instanceof UploadedFile ? $data['image'] : null;
        unset($data['image'], $data['created_by']);

        return $this->createDraft($actor, $data, $image);
    }

    // ------------------------------------------------------------------ update

    /** Save configuration of a draft or published POD owned by $actor. */
    public function updateDraft(Pod $pod, User $actor, array $data, ?UploadedFile $image = null): Pod
    {
        $companyWorkspace = $this->companyWorkspaceOf($actor);
        $newImage = $image ? $this->uploadImage($image) : null;

        try {
            [$saved, $previousImage] = DB::transaction(function () use ($pod, $actor, $data, $newImage, $companyWorkspace) {
                $locked = $this->lockOwned($pod, $actor);
                $before = $locked->isPublished() ? $this->configuration->breakdown($locked) : null;
                $matchingBefore = $this->recalculation->configurationSnapshot($locked->id);
                $previousImage = $locked->image;

                $locked->fill($this->basics($data));
                if (array_key_exists('matching_mode', $data)) {
                    $locked->matching_mode = $data['matching_mode'] ?: null;
                }
                if (array_key_exists('minimum_match_threshold', $data)) {
                    $locked->minimum_match_threshold = self::validThreshold($data['minimum_match_threshold']);
                }
                if ($newImage !== null) {
                    $locked->image = $newImage;
                }
                $locked->configuration_started_at ??= now();
                $locked->save();

                if (trim((string) $locked->title) !== '' && $locked->workspace) {
                    $locked->workspace->update(['name' => $locked->title . '-workspace']);
                }
                $this->saveConfiguration($locked, $data, $companyWorkspace);

                if ($before !== null) {
                    $locked->unsetRelations();
                    $after = $this->configuration->breakdown($locked);
                    if ($this->configuration->regressions($before, $after)) {
                        throw new IncompletePodConfiguration(
                            __('This change would leave the published POD incomplete, so it was not saved.'),
                            $locked,
                            ['percentage' => $after['percentage'], 'required' => $after['required'], 'missing' => $this->configuration->missingRequirements($locked)],
                            $this->configuration->companyStatus($actor),
                        );
                    }
                }

                // UNA-014/016: invalidate matches of inactivated requirements now; queue regeneration of
                // a published POD (GenerateMatchesForPod) only after this configuration has committed.
                $this->recalculation->scheduleChanges($locked->id, $matchingBefore, $this->recalculation->configurationSnapshot($locked->id));

                return [$locked, $previousImage];
            }, self::ATTEMPTS);
        } catch (\Throwable $e) {
            $this->deleteImage($newImage);
            throw $e;
        }

        if ($newImage !== null && $previousImage && $previousImage !== $newImage) {
            $this->deleteImage($previousImage);
        }

        return $saved->fresh(['skills', 'workerRequirements.skills', 'workspace']);
    }

    /** Compatibility wrapper for the previous API update: same path, the POD creator as actor. */
    public function updatePod(Pod $pod, array $data, ?User $actor = null): Pod
    {
        $image = ($data['image'] ?? null) instanceof UploadedFile ? $data['image'] : null;
        unset($data['image']);

        return $this->updateDraft($pod, $actor ?? User::findOrFail($pod->created_by), $data, $image);
    }

    // ------------------------------------------------------------------ publish

    /**
     * Publish a draft. Re-checks everything under a row lock; a repeated request throws
     * PodAlreadyPublished and changes nothing.
     */
    public function publish(Pod $pod, User $actor): Pod
    {
        return DB::transaction(function () use ($pod, $actor) {
            $locked = Pod::whereKey($pod->id)->lockForUpdate()->firstOrFail();
            if ($locked->isPublished()) {
                throw new PodAlreadyPublished($locked);
            }
            if (!$this->configuration->isOwner($locked, $actor)) {
                throw new UnauthorizedPodMutation();
            }

            $podStatus = [
                'percentage' => $this->configuration->percentage($locked),
                'required' => $this->configuration->required(),
                'missing' => $this->configuration->missingRequirements($locked),
            ];
            $companyStatus = $this->configuration->companyStatus($actor);

            if ($podStatus['percentage'] < $podStatus['required']) {
                throw new IncompletePodConfiguration(
                    __('Complete the POD setup before publishing. It is :percentage% configured; :required% is required.', ['percentage' => $podStatus['percentage'], 'required' => $podStatus['required']]),
                    $locked, $podStatus, $companyStatus,
                );
            }
            if (!$this->configuration->companyComplete($actor)) {
                throw new IncompleteBusinessOnboarding(
                    __('Complete your company profile before publishing a POD. Your profile is :percentage% complete; :required% is required.', ['percentage' => $companyStatus['percentage'], 'required' => $companyStatus['required']]),
                    $locked, $podStatus, $companyStatus,
                );
            }

            $now = now();
            $changed = Pod::whereKey($locked->id)->where('status', PodStatus::DRAFT->value)->update([
                'status' => PodStatus::PUBLISHED->value,
                'published_at' => $now,
                'configuration_completed_at' => $now,
                'updated_at' => $now,
            ]);
            if ($changed !== 1) {
                throw new PodAlreadyPublished($locked);
            }

            // UNA-016: matches are generated by a queued GenerateMatchesForPod once publication has committed.
            $this->recalculation->schedulePod($locked->id);

            return $locked->fresh();
        }, self::ATTEMPTS);
    }

    /**
     * Company defaults for matching and approvals (UNA-010), for POD and future matching workflows.
     * Stored preferences only: nothing is approved automatically.
     *
     * UNA-014: pod_matching_mode / minimum_match_threshold are what a new POD of this company starts with.
     *
     * @return array{matching_mode:?\App\Enums\Company\MatchingMode, pod_matching_mode:?PodMatchingMode, minimum_match_threshold:int, pod_application_approval:?\App\Enums\Company\ApprovalMode, inner_circle_approval:?\App\Enums\Company\ApprovalMode}
     */
    public function companyDefaults(User $owner): array
    {
        $workspace = $this->businessProfiles->companyWorkspace($owner);

        return [
            'matching_mode' => $workspace?->matching_mode,
            'pod_matching_mode' => PodMatchingMode::fromCompanyPreference($workspace?->matching_mode),
            'minimum_match_threshold' => self::defaultThreshold($workspace),
            'pod_application_approval' => $workspace?->default_pod_application_approval,
            'inner_circle_approval' => $workspace?->default_inner_circle_approval,
        ];
    }

    public function destroyPod(Pod $pod)
    {
        $workspace = $pod->workspace;
        $users = User::where('currant_workspace', $workspace->id)->get();

        $workspace->delete();
        $pod->delete();

        //set new currant_workspace for users who was in this pod (to avoid crashes)
        foreach ($users as $user) {
            $currantWorkspace = Workspace::where('created_by', $user->id)->first();
            $user->currant_workspace = $currantWorkspace->id;
            $user->update();
        }
    }

    // ------------------------------------------------------------------ internals

    /** The actor's own company workspace; only companies own and configure PODs. */
    private function companyWorkspaceOf(User $actor): Workspace
    {
        return $this->businessProfiles->companyWorkspace($actor)
            ?? throw new UnauthorizedPodMutation(__('Only a company can create and configure PODs.'));
    }

    private function lockOwned(Pod $pod, User $actor): Pod
    {
        $locked = Pod::whereKey($pod->id)->lockForUpdate()->firstOrFail();
        if (!$this->configuration->isOwner($locked, $actor)) {
            throw new UnauthorizedPodMutation();
        }

        return $locked;
    }

    /** Title/description from input; an emptied title is stored as '' (the column is not nullable). */
    private function basics(array $data): array
    {
        $basics = Arr::only($data, ['title', 'description']);
        if (array_key_exists('title', $basics)) {
            $basics['title'] = trim((string) $basics['title']);
        }

        return $basics;
    }

    /** Snapshot of the company default threshold (or the explicit value); never a live link. */
    private function initialThreshold(array $data, Workspace $companyWorkspace): int
    {
        if (isset($data['minimum_match_threshold']) && $data['minimum_match_threshold'] !== '') {
            return self::validThreshold($data['minimum_match_threshold']);
        }

        return self::defaultThreshold($companyWorkspace);
    }

    public static function defaultThreshold(?Workspace $companyWorkspace): int
    {
        $value = $companyWorkspace?->default_minimum_match_threshold ?? config('pod_matching.default_minimum_threshold', 70);

        return max(0, min(100, (int) $value));
    }

    /**
     * Domain guard for pods.minimum_match_threshold: an integer from 0 through 100 (the request
     * validates the same; this protects every other caller).
     */
    public static function validThreshold(mixed $value): int
    {
        $valid = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 100]]);
        if ($valid === false || is_bool($value)) {
            throw ValidationException::withMessages(['minimum_match_threshold' => __('The minimum match score must be a whole number from 0 to 100.')]);
        }

        return $valid;
    }

    private function initialMatchingMode(array $data, Workspace $companyWorkspace): ?PodMatchingMode
    {
        if (!empty($data['matching_mode'])) {
            return PodMatchingMode::from($data['matching_mode']);
        }

        // Preselection only; the company can change it before publishing.
        return PodMatchingMode::fromCompanyPreference($companyWorkspace->matching_mode);
    }

    /** Skills, requirements and imported templates (only the keys that were submitted). */
    private function saveConfiguration(Pod $pod, array $data, Workspace $companyWorkspace): void
    {
        if (array_key_exists('skills', $data) || !empty($data['skills_present'])) {
            $pod->skills()->sync(array_values(array_unique(array_map('intval', $data['skills'] ?? []))));
        }
        if (!empty($data['requirements_present'])) {
            $this->syncRequirements($pod, $data['requirements'] ?? [], $companyWorkspace);
        }
        if (!empty($data['import_template_ids'])) {
            $this->importTemplates($pod, $data['import_template_ids'], $companyWorkspace);
        }
        $pod->unsetRelation('skills')->unsetRelation('workerRequirements');
    }

    private function syncRequirements(Pod $pod, array $rows, Workspace $companyWorkspace): void
    {
        $existing = PodWorkerRequirement::where('pod_id', $pod->id)->lockForUpdate()->get()->keyBy('id');
        $keep = [];

        foreach ($rows as $index => $row) {
            $attributes = MatchingConfiguration::withMandatory(Arr::only($row, self::REQUIREMENT_FIELDS), $row);
            $attributes['is_active'] = filter_var($row['is_active'] ?? true, FILTER_VALIDATE_BOOLEAN);
            $id = isset($row['id']) && $row['id'] !== '' ? (int) $row['id'] : null;
            $source = isset($row['source_workspace_requirement_id']) && $row['source_workspace_requirement_id'] !== ''
                ? $this->ownedTemplate((int) $row['source_workspace_requirement_id'], $companyWorkspace)->id
                : null;

            if ($id !== null) {
                // Validation already rejects foreign ids; this is the last line of defence.
                $requirement = $existing->get($id) ?? throw new UnauthorizedPodMutation(__('This worker requirement does not belong to this POD.'));
                $requirement->fill($attributes)->save();
            } else {
                $requirement = new PodWorkerRequirement($attributes + ['source_workspace_requirement_id' => $source]);
                $requirement->forceFill(['pod_id' => $pod->id])->save();
            }
            $requirement->skills()->sync(array_values(array_unique(array_map('intval', $row['skill_ids'] ?? []))));
            // UNA-013: only configured criteria may be mandatory (rolls the whole save back otherwise).
            MatchingConfiguration::assertValid($requirement, "requirements.{$index}.mandatory_criteria");
            $keep[] = $requirement->id;
        }

        PodWorkerRequirement::where('pod_id', $pod->id)->whereNotIn('id', $keep)->get()
            ->each(function (PodWorkerRequirement $requirement) {
                $requirement->skills()->detach();
                $requirement->delete();
            });
    }

    /** Copy company templates into POD-owned snapshots (never a live link). */
    private function importTemplates(Pod $pod, array $templateIds, Workspace $companyWorkspace): void
    {
        foreach (array_unique(array_map('intval', $templateIds)) as $templateId) {
            $template = $this->ownedTemplate($templateId, $companyWorkspace)->load('skills');
            // A snapshot: UNA-010 fields plus the UNA-013 matching configuration (incl. mandatory criteria).
            $copy = new PodWorkerRequirement(
                $template->matchingAttributes()
                + Arr::only($template->getAttributes(), self::REQUIREMENT_FIELDS)
                + ['source_workspace_requirement_id' => $template->id]
            );
            $copy->forceFill(['pod_id' => $pod->id])->save();
            $copy->skills()->sync($template->skills->pluck('id')->all());
            MatchingConfiguration::assertValid($copy, 'import_template_ids');
        }
    }

    private function ownedTemplate(int $id, Workspace $companyWorkspace): WorkspaceWorkerRequirement
    {
        return WorkspaceWorkerRequirement::where('workspace_id', $companyWorkspace->id)->find($id)
            ?? throw new UnauthorizedPodMutation(__('This worker requirement template does not belong to your company.'));
    }

    protected function uploadImage(UploadedFile $image): string
    {
        $name = uniqid('', false) . bin2hex(random_bytes(4)) . '.' . strtolower($image->getClientOriginalExtension() ?: 'png');
        $path = Utility::store_file(self::IMAGE_DIR, $image, $name, $this->disk(), ['small', 'big']);

        if (!$path) {
            throw new \Exception('Failed to upload image.');
        }

        return $path;
    }

    /** Delete an uploaded POD image and its resized copies; never anything outside pod-images/. */
    private function deleteImage(?string $path): void
    {
        if (!$path || !str_starts_with($path, self::IMAGE_DIR . '/')) {
            return;
        }
        $name = basename($path);
        try {
            Storage::disk($this->disk())->delete([$path, self::IMAGE_DIR . '/small_' . $name, self::IMAGE_DIR . '/big_' . $name]);
        } catch (\Throwable $e) {
            report($e);
        }
    }

    private function disk(): string
    {
        return Utility::getAdminPaymentSetting()['storage_setting'] ?? 'public';
    }

    protected function attachOwnerToPod(int $userId, Pod $pod): void
    {
        // The owner starts (and stays) in the Inner Circle; this is the membership's first state.
        $owner = PodUser::findOrCreateMembership($userId, $pod->id, [
            'permission' => 'Owner',
            'state' => \App\Enums\PodUser\State::INNER_CIRCLE->value,
        ]);
        if ($owner->wasRecentlyCreated) {
            \App\Models\PodUserStateHistory::query()->forceCreate([
                'pod_user_id' => $owner->id, 'from_state' => null,
                'to_state' => \App\Enums\PodUser\State::INNER_CIRCLE->value, 'actor_id' => $userId, 'reason' => 'POD created',
            ]);
        }
    }

    protected function createWorkspaceForPod(Pod $pod): Workspace
    {
        $workspace = Workspace::create([
            'name' => (trim((string) $pod->title) !== '' ? $pod->title : __('Untitled POD')) . '-workspace',
            'created_by' => $pod->created_by,
            'currency_code' => 'USD',
            'paypal_mode' => 'sandbox',
            'lang' => config('app.locale', 'en') ?: 'en',
        ]);

        $pod->workspace()->save($workspace);
        return $workspace;
    }

    protected function attachOwnerToWorkspace(Workspace $workspace,  int $userId): void
    {
        $userWorkspace = UserWorkspace::findOrCreateMembership($userId, $workspace->id, ['permission' => 'Owner', 'is_active' => 1]);
        $userWorkspace->assignAllPermissionsToCreator();
    }
}
