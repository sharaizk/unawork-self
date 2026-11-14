<?php

namespace App\Services;

use App\Models\Pod;
use App\Models\PodUser;
use App\Models\User;
use App\Models\UserWorkspace;
use App\Models\Utility;
use App\Models\Workspace;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;

class PodService
{
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

    public function createPod(array $data): Pod
    {
        if (isset($data['image'])) {
            $settings = Utility::getAdminPaymentSetting();
            $data['image'] = $this->uploadImage($data['image'], $settings);
        }

        // POD, its workspace and both owner memberships are created together or not at all.
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
        PodUser::findOrCreateMembership($userId, $pod->id, [
            'permission' => 'Owner',
            'status' => 'active',
        ]);
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
