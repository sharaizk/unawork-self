<?php

namespace App\Services;

use App\Models\CmgmyrMessenger\Thread;
use App\Models\CmgmyrMessenger\Message;
use Cmgmyr\Messenger\Models\Participant;
use Illuminate\Support\Facades\Auth;

class ChatService
{
    public function createGroupChat($subject, $participants, $message = null, $slug = null, $attachmentData = null)
    {
        $userId = Auth::id();

        // create new chat
        $thread = Thread::create([
            'subject' => $subject,
            'slug' => $slug ?? \Str::slug($subject . '-' . time())
        ]);

        // add participants
        foreach ($participants as $participantId) {
            Participant::create([
                'thread_id' => $thread->id,
                'user_id' => $participantId,
                'last_read' => new \Carbon\Carbon,
            ]);
        }

        Participant::create([
            'thread_id' => $thread->id,
            'user_id' => $userId,
            'last_read' => new \Carbon\Carbon,
        ]);

        // create first message
        if ($message) {
            Message::create([
                'thread_id' => $thread->id,
                'user_id' => $userId,
                'body' => $message,
                'attachment' => $attachmentData ? json_encode((object)[
                    'new_name' => $attachmentData['new_name'],
                    'old_name' => $attachmentData['old_name'],
                ]) : null,
            ]);
        }

        return $thread;
    }

    public function inviteUserBySlug($slug, $userId)
    {
        $thread = Thread::where('slug', $slug)->first();

        if (!$thread) {
            return null;
        }

        // Add participant
        Participant::create([
            'thread_id' => $thread->id,
            'user_id' => $userId,
        ]);

        return $thread;
    }

    public function removeUserBySlug($slug, $userId)
    {
        $thread = Thread::where('slug', $slug)->first();

        if (!$thread) {
            throw new \Exception('Error occured on remove user from project chat!');
        }

        // Add participant
        $participant = Participant::where('thread_id', $thread->id)->where('user_id', $userId);

        if (!$participant) {
            throw new \Exception('Error occured on remove user from project chat!');
        }
        $participant->delete();

        return $thread;
    }
    
}