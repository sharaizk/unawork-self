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
        ->get();

        return view('notifications', compact('notifications'));
    }

    public function read(Notification $notification)
    {
        $notification->update(["is_read" => 1]);
        return response()->json("success");
    }
}