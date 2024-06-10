<?php

namespace App\Services;

use App\Models\Pod;
use App\Models\Utility;
use Illuminate\Http\UploadedFile;

class PodService
{
    public function createPod(array $data, UploadedFile $image = null): Pod
    {
        if ($image !== null) {
            $data['image'] = $this->uploadImage($image);
        }
    
        $pod = Pod::create($data);
    
        if (isset($data['skills'])) {
            $pod->skills()->sync($data['skills']);
        }
    
        return $pod;
    }

    public function updatePod(Pod $pod, array $data, UploadedFile $image = null): Pod
    {
        if (isset($data['image'])) {
            $data['image'] = $this->uploadImage($image);
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
}
