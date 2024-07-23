<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class NotificationController extends Controller
{
    public function list()
    {
        $notifications = Notification::where('user_id', Auth::user()->id)
        ->orderBy('is_read', 'asc')
        ->orderBy('created_at', 'desc')
        ->paginate(10);

        return view('notifications', compact('notifications'));
    }

    public function read(Notification $notification)
    {
        $notification->update(["is_read" => 1]);
        return response()->json("success");
    }

    public function delete(Notification $notification)
    {
        $this->authorize('delete', $notification);
        $notification->delete();
        return redirect()->back()->with('success', __('Notification Deleted Successfully!'));
    }
}