<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Cmgmyr\Messenger\Models\Thread;
use Cmgmyr\Messenger\Models\Message;
use Cmgmyr\Messenger\Models\Participant;
use Illuminate\Support\Facades\Auth;

class MessagesController extends Controller
{
    public function store(Request $request)
    {
        $validatedData = $request->validate([
            'recipient_id' => 'required|exists:users,id',
            'message' => 'required|string|max:1000',
        ]);

        $userId = Auth::id();
        $recipientId = $validatedData['recipient_id'];
        $messageText = $request->input('message');


        $thread = Thread::where('slug', null)
        ->whereHas('participants', function ($query) use ($userId) {
            $query->where('user_id', $userId);
        })
        ->whereHas('participants', function ($query) use ($recipientId) {
            $query->where('user_id', $recipientId);
        })
        ->withCount('participants')
        ->having('participants_count', 2)
        ->first();

        if (!$thread) {
            $thread = Thread::create([
                'subject' => 'New Message'
            ]);

            Participant::create([
                'thread_id' => $thread->id,
                'user_id' => $userId,
                'last_read' => new \Carbon\Carbon,
            ]);

            Participant::create([
                'thread_id' => $thread->id,
                'user_id' => $recipientId,
                'last_read' => null,
            ]);
        }

        $message = Message::create([
            'thread_id' => $thread->id,
            'user_id' => $userId,
            'body' => $messageText,
        ]);

        $thread->touch();

        return response()->json([
            'status' => 'success',
            'message_id' => $message->id,
            'thread_id' => $thread->id,
        ], 200);
    }
}