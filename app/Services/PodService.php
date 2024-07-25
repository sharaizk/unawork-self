<?php

namespace App\Services;

use App\Models\Pod;
use App\Models\User;
use App\Models\UserWorkspace;
use App\Models\Utility;
use App\Models\Workspace;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;

class PodService
{
    public function getPods(?array $skillIds = null)
    {
        if ($skillIds) {
            $pods = Pod::whereHas('skills', function($query) use ($skillIds) {
                $query->whereIn('skills.id', $skillIds);
            })->get();
        } else {
            $pods = Pod::all();
        }

        return $pods;
    }

    public function createPod(array $data): Pod
    {
        if (isset($data['image'])) {
            $settings = Utility::getAdminPaymentSetting();
            $data['image'] = $this->uploadImage($data['image'], $settings);
        }
    
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

        $pod->update($data);

        if (isset($data['skills'])) {
            $pod->skills()->sync($data['skills']);
        }

        return $pod;
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
        $pod->users()->attach($userId, [
            'permission' => 'Owner',
            'status' => 'active'
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
        $workspace->users()->attach($userId, ['permission' => 'Owner', 'is_active' => 1]);

        $userWorkspace = UserWorkspace::where('workspace_id', $workspace->id)->where('user_id', $userId)->first();
        $userWorkspace->assignAllPermissionsToCreator();
    }
}