<?php

namespace App\Services;

use App\Models\Profile;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class ProfileService
{
    public function createProfile(User $user, array $data): Profile
    {   
        $exist = $user->profile()->exists();

        if ($exist) {
            throw new \Exception('User can\t have more than one profile.');
        }

        if (isset($data['cv'])) {
            $data['cv'] = $this->uploadFile($data['cv']);
        }
    
        try {
            return $user->profile()->create($data);
        } catch (\Exception $e) {
            throw new \Exception('Failed to create profile.'.$e->getMessage());
        }
    
    }

    public function updateProfile(int $profileId, array $data): Profile
    {
        $profile = Profile::find($profileId);

        if (isset($data['cv'])) {
            //del old image if it was
            if ($profile->cv) {
                Storage::delete($profile->cv);
            }
            $data['cv'] = $this->uploadFile($data['cv']);
        }
        try {
            $profile->update($data);
        } catch (\Exception $e) {
            throw new \Exception('Failed to update profile.'.$e->getMessage());
        }

        return $profile;
    }

    protected function uploadFile(UploadedFile $image): string
    {
        $dir = 'profile-files';
        $imgName = uniqid() . '.pdf';
        $path = $image->storeAs($dir, $imgName);

        if ($path) {
            return $path;
        } else {
            throw new \Exception('Failed to upload image.');
        }
    }
}