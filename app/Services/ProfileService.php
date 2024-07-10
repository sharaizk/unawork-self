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

    public function updateProfile(Profile $profile, array $data): Profile
    {
        if (isset($data['cv'])) {
            //del old image if it was
            if ($profile->cv) {
                Storage::delete($profile->cv);
            }
            $data['cv'] = $this->uploadFile($data['cv']);
        }

        if (isset($data['skill_ids'])) {
            try {
                $this->linkSkills($data['skill_ids'], $profile->user_id);
            } catch (\Exception $e) {
                throw new \Exception('Failed to update skills.'.$e->getMessage());
            }
        }

        try {
            $profile->update($data);
        } catch (\Exception $e) {
            throw new \Exception('Failed to update profile.'.$e->getMessage());
        }

        return $profile;
    }

    protected function uploadFile(UploadedFile $file): string
    {
        $dir = 'profile-files';
        $fileName = uniqid() . '.pdf';
        $path = $file->storeAs($dir, $fileName, 'public');

        if ($path) {
            return $path;
        } else {
            throw new \Exception('Failed to upload file.');
        }
    }

    protected function linkSkills(array $skills, int $user_id): void
    {
        $user = User::find($user_id);
        $user->skills()->sync($skills);
    }
}