<?php

namespace App\Services;

use App\Models\Pod;
use App\Models\PodUser;
use App\Models\User;
use App\Enums\PodUser\Permission;
use App\Enums\PodUser\Status;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

class PodUserService
{
    public function inviteExistedUser(int $userId = null, int $podId): void
    {
        $user = User::find($userId);

        if (is_null($user)) {
            throw new \Exception(__('This User is not connected with our system. Please fill out the below details to invite.'));
        }

        if ($this->isUserAssignedToPod($user, $podId)) {
            throw new \Exception(__('This user is already a member of this pod.'));
        }

        $this->attachUserToPod($user, $podId);

        try {
            $this->sendInvitationEmail($user, $podId);
        } catch (\Exception $e) {
            throw new \Exception(__('E-Mails has not been sent due to SMTP configuration'));
        }
    }

    public function inviteNewUser(array $data, int $podId): void
    {
        $user = $this->createNewUser($data);
        $this->attachUserToPod($user, $podId);

        try {
            $this->sendInvitationEmail($user, $podId);
            $this->sendRegistrationEmail($user);
        } catch (\Exception $e) {
            throw new \Exception(__('E-Mails has not been sent due to SMTP configuration'));
        }
    }

    public function requestJoinPod(int $userId, int $podId): void
    {
        $exists = PodUser::where('user_id', $userId)
            ->where('pod_id', $podId)
            ->exists();

        if ($exists) {
            throw new \Exception('You have already submitted a join request for this pod.');
        }

        PodUser::create([
            'user_id' => $userId,
            'pod_id' => $podId,
            'permission' => Permission::MEMBER->value,
            'status' => Status::PENDING->value
        ]);
    }

    public function getAllJoinRequests(int $podId)
    {
        return PodUser::where('pod_id', $podId)->where('status', Status::PENDING->value)->get();
    }

    public function moderateJoinRequest(int $requestId, string $action, ?string $reason = null): void
    {
        $request = PodUser::findOrFail($requestId);

        if ($request->status != 'pending') {
            throw new \Exception('You can moderate only pending requests');
        }

        if ($action === 'accept') {
            $request->status = Status::ACTIVE->value;
        } elseif ($action === 'decline') {
            if (is_null($reason)) {
                throw new \Exception('Reason is required for declining a request');
            }
            $request->status = Status::DECLINED->value;
            $request->reason = $reason;
        } else {
            throw new \Exception('Invalid action');
        }

        $request->save();
    }

    private function createNewUser(array $data): User
    {
        return User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'plan' => NULL,
            'password' => Hash::make($data['password']), // should use bcrypt for hashing password
            'email_verified_at' => date('Y-m-d i:h:s'),
            'is_enable_login' => 1,
        ]);
    }
    
    private function isUserAssignedToPod(User $user, int $podId): bool
    {
        return $user->pods()
            ->where('pods.id', $podId)
            ->exists();
    }

    private function attachUserToPod(User $user, int $podId): void
    {
        $user->pods()->attach($podId, [
            'permission' => Permission::MEMBER->value,
            'status' => Status::ACTIVE->value
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

    private function sendRegistrationEmail(User $user): void
    {
        $message = "You have been succesfully registered!";
        Mail::raw($message, function ($m) use ($user) {
            $m->to($user->email)->subject('Pod Invitation');
        });
    }

}
