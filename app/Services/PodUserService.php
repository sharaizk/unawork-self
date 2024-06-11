<?php

namespace App\Services;

use App\Models\Pod;
use App\Models\PodUser;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

class PodUserService
{
    public function inviteExistedUser(string $email = null, int $userId = null, int $podId): array
    {
        $response = [
            'code' => 404,
            'status' => 'Error',
            'error' => __('This User is not connected with our system. Please fill out the below details to invite.'),
        ];

        $user = !is_null($email) ? User::where('email', $email)->first() 
            : (!is_null($userId) ? User::find($userId) 
                : null);

        if (!is_null($user)) {
            if (!$this->isUserAssignedToPod($user, $podId)) {
                $this->attachUserToPod($user, $podId);

                try {
                    $this->sendInvitationEmail($user, $podId);
                } catch (\Exception $e) {
                    $smtp_error = __('E-Mail has not been sent due to SMTP configuration');
                }

                $response = [
                    'code' => 200,
                    'status' => 'Success',
                    'message' => __('User Invited Successfully!') . ((isset($smtp_error)) ? $smtp_error : ''),
                ];
            } else {
                $response = [
                    'code' => 202,
                    'status' => 'error',
                    'message' => __('This user is already a member of this pod.'),
                ];
            }
        }

        return $response;
    }

    private function isUserAssignedToPod(User $user, int $podId): bool
    {
        return $user->pods()
            ->wherePivot('user_id', $user->id)
            ->wherePivot('pod_id', $podId)
            ->exists();
    }

    private function attachUserToPod(User $user, int $podId): void
    {
        $user->pods()->attach($podId, [
            'permission' => 'Member',
            'status' => 'active'
        ]);
    }

    private function sendInvitationEmail(User $user, int $podId): void
    {
        $pod = Pod::find($podId);
        $message = "You have been invited to join the pod '{$pod->name}'.";
        Mail::raw($message, function ($m) use ($user) {
            $m->to($user->email)->subject('Pod Invitation');
        });
    }
}
