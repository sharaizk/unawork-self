<?php

namespace App\Services;

use App\Models\Pod;
use App\Models\PodUser;
use App\Models\User;
use App\Models\UserWorkspace;
use App\Models\Utility;
use App\Models\Workspace;
use App\Services\Company\BusinessOnboardingCompletionService;
use App\Services\Company\IncompleteBusinessProfile;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;

class PodService
{
    public function __construct(private ?BusinessOnboardingCompletionService $businessProfiles = null)
    {
        $this->businessProfiles ??= app(BusinessOnboardingCompletionService::class);
    }

    public function getPods(?array $skillIds = null, $perPage = 15)
    {
        if ($skillIds) {
            $pods = Pod::whereHas('skills', function($query) use ($skillIds) {
                $query->whereIn('skills.id', $skillIds);
            })->paginate($perPage);
        } else {
            $pods = Pod::paginate($perPage);
        }

        return $pods;
    }

    /**
     * Create (= publish; PODs have no draft state) a POD. UNA-010: a company must have a complete
     * business profile; this is checked before anything is uploaded or written, so a refused attempt
     * leaves no POD, workspace, membership, history, file or notification behind.
     *
     * @throws IncompleteBusinessProfile
     */
    public function createPod(array $data): Pod
    {
        $this->assertCanPublish((int) ($data['created_by'] ?? 0));

        $settings = null;
        if (isset($data['image'])) {
            $settings = Utility::getAdminPaymentSetting();
            $data['image'] = $this->uploadImage($data['image'], $settings);
        }

        // POD, its workspace and both owner memberships are created together or not at all.
        try {
            return DB::transaction(function () use ($data) {
                $pod = Pod::create($data);

                if ($pod) {
                    $this->attachOwnerToPod($data['created_by'], $pod);
                    $workspace = $this->createWorkspaceForPod($pod);
                    $this->attachOwnerToWorkspace($workspace, $data['created_by']);
                }

                if (isset($data['skills'])) {
                    $pod->skills()->sync($data['skills']);
                }

                return $pod;
            });
        } catch (\Throwable $e) {
            if ($settings !== null && is_string($data['image'] ?? null)) {
                Storage::disk($settings['storage_setting'])->delete($data['image']);
            }
            throw $e;
        }
    }

    /**
     * UNA-010: companies publish PODs only with a complete business profile (current data, never the
     * stored completed_at). Other account types are not subject to business onboarding.
     *
     * @throws IncompleteBusinessProfile
     */
    public function assertCanPublish(int $creatorId): void
    {
        $creator = User::find($creatorId);
        if (!$creator || !$creator->isCompany()) {
            return;
        }

        $workspace = $this->businessProfiles->companyWorkspace($creator);
        if ($workspace === null) {
            throw new IncompleteBusinessProfile(0, $this->businessProfiles->required(), [__('Create your company profile')]);
        }
        $breakdown = $this->businessProfiles->breakdown($workspace);
        if (!$breakdown['complete']) {
            throw new IncompleteBusinessProfile($breakdown['percentage'], $breakdown['required'], $this->businessProfiles->missingRequirements($workspace));
        }
    }

    /**
     * Company defaults for matching and approvals (UNA-010), for POD and future matching workflows.
     * Stored preferences only: nothing is approved automatically.
     *
     * @return array{matching_mode:?\App\Enums\Company\MatchingMode, pod_application_approval:?\App\Enums\Company\ApprovalMode, inner_circle_approval:?\App\Enums\Company\ApprovalMode}
     */
    public function companyDefaults(User $owner): array
    {
        $workspace = $this->businessProfiles->companyWorkspace($owner);

        return [
            'matching_mode' => $workspace?->matching_mode,
            'pod_application_approval' => $workspace?->default_pod_application_approval,
            'inner_circle_approval' => $workspace?->default_inner_circle_approval,
        ];
    }

    public function updatePod(Pod $pod, array $data): Pod
    {
        if (isset($data['image'])) {
            //del old image if it was
            if ($pod->image) {
                Storage::delete($pod->image);
            }
            $settings = Utility::getAdminPaymentSetting();
            $data['image'] = $this->uploadImage($data['image'], $settings);
        }

        if (isset($data['title'])) {
            $this->updateWorkspaceName($data['title'], $pod);
        }

        $pod->update($data);

        if (isset($data['skills'])) {
            $pod->skills()->sync($data['skills']);
        }

        return $pod;
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

    protected function uploadImage(UploadedFile $image, array $settings): string
    {
        $dir = 'pod-images';
        $imgName = uniqid() . '.' . $image->getClientOriginalExtension();
        $resize_options = ['small', 'big'];

        $path = Utility::store_file($dir, $image, $imgName, $settings['storage_setting'], $resize_options);

        if ($path) {
            return $path;
        } else {
            throw new \Exception('Failed to upload image.');
        }
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
            'name' => $pod->title.'-workspace',
            'created_by' => $pod->created_by,
            'currency_code' => 'USD',
            'paypal_mode' => 'sandbox',
            'lang' => (env('DEFAULT_ADMIN_LANG')) ? env('DEFAULT_ADMIN_LANG') : 'en',
        ]);

        $pod->workspace()->save($workspace);
        return $workspace;
    }

    protected function attachOwnerToWorkspace(Workspace $workspace,  int $userId): void
    {
        $userWorkspace = UserWorkspace::findOrCreateMembership($userId, $workspace->id, ['permission' => 'Owner', 'is_active' => 1]);
        $userWorkspace->assignAllPermissionsToCreator();
    }

    protected function updateWorkspaceName(string $title, Pod $pod): void
    {
        $worskpace = $pod->workspace;
        $worskpace->update([
            'name' => $title.'-workspace',
        ]);
    }
}
