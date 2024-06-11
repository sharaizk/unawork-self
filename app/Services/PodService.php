<?php

namespace App\Services;

use App\Models\Pod;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class PodService
{
    public function createPod(array $data): Pod
    {
        if (isset($data['image'])) {
            $data['image'] = $this->uploadImage($data['image']);
        }
    
        $pod = Pod::create($data);
        
        if($pod){
            $this->attachPodToOwner($data['created_by'], $pod->id);
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

            $data['image'] = $this->uploadImage($data['image']);
        }

        $pod->update($data);

        if (isset($data['skills'])) {
            $pod->skills()->sync($data['skills']);
        }

        return $pod;
    }

    protected function uploadImage(UploadedFile $image): string
    {
        $dir = 'pod-images';
        $imgName = uniqid() . '.png';
        $path = $image->storeAs($dir, $imgName);

        if ($path) {
            return $path;
        } else {
            throw new \Exception('Failed to upload image.');
        }
    }

    protected function attachPodToOwner(int $userId, int $podId): void
    {
        $user = User::find($userId);
        $user->pods()->attach($podId, [
            'permission' => 'Owner',
            'status' => 'active'
        ]);
    }
}
