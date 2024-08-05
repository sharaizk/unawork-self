<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Response;
use App\Models\ChMessage as Message;
use App\Models\ChFavorite as Favorite;
use App\Models\CmgmyrMessenger\Message as CustomMessage;
use Chatify\Facades\ChatifyMessenger as Chatify;
use App\Models\User;
use App\Models\Utility;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request as FacadesRequest;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Cmgmyr\Messenger\Models\Thread;
use Cmgmyr\Messenger\Models\Message as LaravelMessage;
use Cmgmyr\Messenger\Models\Participant;

class MessagesController extends Controller
{

    public function pusherAuth(Request $request)
    {
        // Auth data
        $authData = json_encode([
            'user_id' => Auth::user()->id,
            'user_info' => [
                'name' => Auth::user()->name
            ]
        ]);
        // check if user authorized
        if (Auth::check()) {
            return Chatify::pusherAuth(
                $request['channel_name'],
                $request['socket_id'],
                $authData
            );
        }
        // if not authorized
        return new Response('Unauthorized', 401);
    }


    public function index($id = null)
    {
        $currentWorkspace = Utility::getWorkspaceBySlug('');

        if (Auth::user()->type != 'admin') {

            $routeName = FacadesRequest::route()->getName();
            $route = (in_array($routeName, ['user', config('chatify.routes.prefix')]))
                ? 'user'
                : $routeName;

            // prepare id
            return view('Chatify::pages.app', [
                'id' => ($id == null) ? 0 : $route . '_' . $id,
                'route' => $route,
                'messengerColor' => Auth::user()->messenger_color,
                'dark_mode' => Auth::user()->dark_mode < 1 ? 'light' : 'dark',
                'currentWorkspace' => $currentWorkspace,
            ]);
        } else {
            return redirect()->back()->with('error', __('Permission Denied.'));
        }
    }



    public function idFetchData(Request $request)
    {
        // Favorite
        $favorite = Chatify::inFavorite($request['id']);

        $profile_img  = Utility::get_file(config('chatify.user_avatar.folder'));

        $thread = Thread::find($request['id']);
        $fetch = new \stdClass();
        $fetch->name = $thread->subject;
        $invites = [];
        $invites['workspaces'] = null;
        $invites['pods'] = null;

        // User data
        if ($thread->participants()->count() == 2) {
            $fetch = User::where('id', $request['id'])->first();
            $invites = [];
            $invites['workspaces'] = Auth::user()->invitableWorkspaces($request['id']);
            $invites['pods'] = Auth::user()->invitablePods($request['id']);
        }

        if (!empty($fetch->avatar)) {

            $avatar = Utility::get_file('/' . config('chatify.user_avatar.folder') . '/' . $fetch->avatar);
            // $avatar = url('/').$avatar;
        } else {

            $avatar = Utility::get_file('/' . config('chatify.user_avatar.folder') . '/avatar.png');

            //  $avatar = url('/').$avatar;
        }

        // send the response
        return Response::json([
            'favorite' => $favorite,
            'fetch' => $fetch,
            'user_avatar' => $avatar,
            'can_invite' => $invites,
            'basic_url' => config('app.url'),
        ]);
    }


    public function download($fileName)
    {
        $logo_img  = Utility::get_file('/app/public/');
        $profile_img  = Utility::get_file(config('chatify.attachments.folder'));
        $settings = Utility::getAdminPaymentSetting();
        if ($settings['storage_setting'] == 'local') {
            // $path = storage_path() . '/app/public/' . config('chatify.attachments.folder') . '/' . $fileName;
            $path = storage_path() . '/' . config('chatify.attachments.folder') . '/' . $fileName;
        } else {
            $path = $logo_img . $profile_img . '/' . $fileName;
        }

        if (file_exists($path)) {
            return Response::download($path, $fileName);
        } else {
            return abort(404, "Sorry, File does not exist in our server or may have been deleted!");
        }
    }





    public function send(Request $request)
    {
        $attachmentData = $this->handleAttachment($request);
    
        if ($attachmentData['error']->status) {
            return redirect()->back()->with('error', __($attachmentData['error']->message));
        }
    
        $userId = Auth::user()->id;
        $thread = Thread::find($request['id']);
    
        $message = CustomMessage::create([
            'thread_id' => $thread->id,
            'user_id' => $userId,
            'body' => htmlentities(trim($request['message']), ENT_QUOTES, 'UTF-8'),
            'attachment' => $this->formatAttachment($attachmentData),
        ]);
    
        $thread->touch();
    
        $messageData = $this->formatMessageData($message->id);
    
        return Response::json([
            'status' => '200',
            'error' => $attachmentData['error'],
            'message' => Chatify::messageCard($messageData),
            'tempID' => $request['temporaryMsgId'],
        ]);
    }
    
    public function fetch(Request $request)
    {
        $allMessages = null;
    
        $userId = Auth::user()->id;
        $thread = Thread::find($request['id']);
    
        if ($thread) {
            $messages = $thread->messages()->orderBy('created_at', 'asc')->get();
            if ($messages->count() > 0) {
                foreach ($messages as $message) {
                    $messageData = $this->formatMessageData($message->id);
                    $allMessages .= Chatify::messageCard($messageData);
                }
                $thread->markAsRead($userId);
                return Response::json([
                    'count' => $messages->count(),
                    'messages' => $allMessages,
                ]);
            }
        }
    
        return Response::json([
            'count' => 0,
            'messages' => '<p class="message-hint center-el"><span>Say \'hi\' and start messaging</span></p>',
        ]);
    }


    public function seen(Request $request)
    {
        $userId = Auth::user()->id;

        $thread = Thread::between([$userId, $request['id']])->first();

        // is created thread between users 
        if (!$thread || !$thread->isUnread($userId)) {
                return Response::json(
                    [
                        'status' => false,
                        'messengerCount' => 0
                    ],
                    200
                );
        }

        // unreadMessages counting
        $unreadMessagesCount = $thread->userUnreadMessagesCount($userId);

        // mark thread as read
        $thread->markAsRead($userId);

        return Response::json(
            [
                'status' => true,
                'messengerCount' => $unreadMessagesCount
            ],
            200
        );
    }

    public function getContacts(Request $request)
    {
        $currentWorkspace = Utility::getWorkspaceBySlug('');
        $userId = Auth::user()->id;
    
        // Get all threads the user is part of
        $threads = Thread::forUser($userId)->latest('updated_at')->get()->unique('id');
        // Fetch threads to contacts
        $contacts = '';
        foreach ($threads as $thread) {
            if ($thread->subject == "Saved Messages") {
                continue;
            }
                // Get user data
                $contacts .= self::getContactItem($request['messenger_id'], $thread, Auth::user());
        }
    
        // Get members of the current workspace
        if ($currentWorkspace) {
            $members = User::select('users.*', 'user_workspaces.permission', 'user_workspaces.is_active')
                ->join('user_workspaces', 'user_workspaces.user_id', '=', 'users.id')
                ->where('user_workspaces.workspace_id', '=', $currentWorkspace->id)
                ->where('users.id', '!=', $userId)
                ->get();
        } else {
            $members = User::where('type', '!=', 'admin')->get();
        }
    
        $getRecords = '';
        foreach ($members as $record) {
            $getRecords .= view('vendor.Chatify.layouts.listItem', [
                'get' => 'all_members',
                'type' => 'user',
                'user' => $record,
            ])->render();
        }
    
        return Response::json([
            'contacts' => $contacts,
            'allUsers' => $members->count() > 0 ? $getRecords : '<br><p class="message-hint"><span>' . __('Your member list is empty') . '</span></p>',
        ], 200);
    }


    public function updateContactItem(Request $request)
    {
        // Get user data
        $userCollection = User::where('id', $request['user_id'])->first();
        $contactItem    = Chatify::getContactItem($request['messenger_id'], $userCollection);
        $messageCount = Message::where('to_id', Auth::user()->id)->where('seen', 0)->count();
        // send the response
        return Response::json(
            [
                'contactItem' => $contactItem,
                'messengerCount' => $messageCount
            ],
            200
        );
    }

    /**
     * Put a user in the favorites list
     *
     * @param Request $request
     * @return void
     */
    public function favorite(Request $request)
    {
        // check action [star/unstar]
        if (Chatify::inFavorite($request['user_id'])) {
            // UnStar
            Chatify::makeInFavorite($request['user_id'], 0);
            $status = 0;
        } else {
            // Star
            Chatify::makeInFavorite($request['user_id'], 1);
            $status = 1;
        }

        // send the response
        return Response::json([
            'status' => @$status,
        ], 200);
    }

    /**
     * Get favorites list
     *
     * @param Request $request
     * @return void
     */
    public function getFavorites(Request $request)
    {
        $favoritesList = null;
        $favorites = Favorite::where('user_id', Auth::user()->id);
        foreach ($favorites->get() as $favorite) {
            // get user data
            $user = User::where('id', $favorite->favorite_id)->first();
            $favoritesList .= view('Chatify::layouts.favorite', [
                'user' => $user,
            ]);
        }
        // send the response
        return Response::json([
            'count' => $favorites->count(),
            'favorites' => $favorites->count() > 0
                ? $favoritesList
                : '<p class="message-hint"><span>Your favourite list is empty</span></p>',
        ], 200);
    }

    /**
     * Search in messenger
     *
     * @param Request $request
     * @return void
     */
    public function search(Request $request)
    {
        $getRecords = null;
        $input = trim(filter_var($request['input'], FILTER_SANITIZE_STRING));
        //$records = User::where('name', 'LIKE', "%{$input}%");
        $objUser          = Auth::user();
        $currentWorkspace = Utility::getWorkspaceBySlug('');
        if ($currentWorkspace) {
            $users = User::select('users.*', 'user_workspaces.permission', 'user_workspaces.is_active')
                ->join('user_workspaces', 'user_workspaces.user_id', '=', 'users.id')
                ->where('user_workspaces.workspace_id', '=', $currentWorkspace->id)
                ->where('users.id', '!=', $objUser->id);
        } else {
            // $records = $users->where('name', 'LIKE', "%{$input}%")->get();
        }

        $records = User::where('name', 'LIKE', "%{$input}%");

        foreach ($records->get() as $record) {
            $getRecords .= view('Chatify::layouts.listItem', [
                'get' => 'search_item',
                'type' => 'user',
                'user' => $record,
            ])->render();
        }
        // send the response
        return Response::json([
            'records' => $records->count() > 0
                ? $getRecords
                : '<p class="message-hint center-el"><span>Nothing to show.</span></p>',
            'addData' => 'html'
        ], 200);
    }
    /**
     * Get shared photos
     *
     * @param Request $request
     * @return void
     */
    public function sharedPhotos(Request $request)
    {
        $shared = Chatify::getSharedPhotos($request['user_id']);
        $sharedPhotos = null;

        // shared with its template
        for ($i = 0; $i < count($shared); $i++) {
            $sharedPhotos .= view('Chatify::layouts.listItem', [
                'get' => 'sharedPhoto',
                'image' => asset('storage/attachments/' . $shared[$i]),
            ])->render();
        }
        // send the response
        return Response::json([
            'shared' => count($shared) > 0 ? $sharedPhotos : '<p class="message-hint"><span>Nothing shared yet</span></p>',
        ], 200);
    }


    public function deleteConversation(Request $request)
    {
        // Get current authenticated user ID
        $userId = Auth::user()->id;

        // Find the thread between the authenticated user and the specified user ID
        $thread = Thread::between([$userId, $request['id']])->first();

        if ($thread) {
            // Delete all messages in the thread
            $thread->messages()->delete();

            // Delete the thread
            $thread->delete();

            // send the response
            return Response::json([
                'deleted' => 1,
            ], 200);
        } else {
            // Thread not found
            return Response::json([
                'deleted' => 0,
            ], 404);
        }
    }

    public function updateSettings(Request $request)
    {
        $msg = null;
        $error = $success = 0;

        $profile_img  = Utility::get_file(config('chatify.user_avatar.folder'));
        $file_path   = Utility::get_file('app/public/');
        $file_path2   = Utility::get_file('/public/');
        $settings = Utility::getAdminPaymentSetting();
        // dark mode
        if ($request['dark_mode']) {
            $request['dark_mode'] == "dark"
                ? User::where('id', Auth::user()->id)->update(['dark_mode' => 1])  // Make Dark
                : User::where('id', Auth::user()->id)->update(['dark_mode' => 0]); // Make Light
        }

        // If messenger color selected
        if ($request['messengerColor']) {

            $messenger_color = explode('-', trim(filter_var($request['messengerColor'], FILTER_SANITIZE_STRING)));
            $messenger_color = Chatify::getMessengerColors()[$messenger_color[1]];
            User::where('id', Auth::user()->id)
                ->update(['messenger_color' => $messenger_color]);
        }
        // if there is a [file]
        if ($request->hasFile('avatar')) {
            // allowed extensions
            $allowed_images = Chatify::getAllowedImages();

            $file = $request->file('avatar');
            // if size less than 150MB
            // if ($file->getSize() < 150000000) {
            if (in_array($file->getClientOriginalExtension(), $allowed_images)) {
                // delete the older one
                if (Auth::user()->avatar != config('chatify.user_avatar.default')) {
                    //$path = storage_path('app/public/' . config('chatify.user_avatar.folder') . '/' . Auth::user()->avatar);


                    if ($settings['storage_setting'] == 'local') {
                        $path = storage_path('app/public/' . config('chatify.user_avatar.folder') . '/' . Auth::user()->avatar);
                    } else {
                        $path = $file_path . $profile_img . '/' . Auth::user()->avatar;
                    }




                    if (file_exists($path)) {
                        @unlink($path);
                    }
                }
                // upload
                $avatar = Str::uuid() . "." . $file->getClientOriginalExtension();
                $update = User::where('id', Auth::user()->id)->update(['avatar' => $avatar]);

                if ($settings['storage_setting'] == 'local') {
                    $file->storeAs("public/" . config('chatify.user_avatar.folder'), $avatar);
                } else {
                    $dir = 'public/' . config('chatify.user_avatar.folder') . '/';
                    $path_store = Utility::upload_file($request, 'avatar', $avatar, $dir, []);
                    if ($path_store['flag'] == 1) {
                        $avatar_1 = $path_store['url'];
                    } else {
                        return redirect()->back()->with('error', __($path_store['msg']));
                    }
                }

                $success = $update ? 1 : 0;
            } else {
                $msg = "File extension not allowed!";
                $error = 1;
            }
            // } else {
            //     $msg = "File extension not allowed!";
            //     $error = 1;
            // }
        }

        // send the response
        return Response::json([
            'status' => $success ? 1 : 0,
            'error' => $error ? 1 : 0,
            'message' => $error ? $msg : 0,
        ], 200);
    }



    public function getMessagePopup()
    {
        $currentWorkspace = Utility::getWorkspaceBySlug('');
        $user             = Auth::user();
        $messages         = Message::whereIn(
            'id',
            function ($query) use ($currentWorkspace, $user) {
                $query->select(DB::raw('MAX(id)'))->from('ch_messages')->where('workspace_id', '=', $currentWorkspace->id)->where('to_id', $user->id)->where('seen', '=', 0);
            }
        )->orderBy('id', 'desc')->get();

        return view('vendor.popup', compact('messages', 'currentWorkspace'));
    }



    public function messageSeen()
    {
        $currentWorkspace = Utility::getWorkspaceBySlug('');
        $user             = Auth::user();
        Message::where('workspace_id', '=', $currentWorkspace->id)->where('to_id', '=', $user->id)->update(['seen' => 1]);

        return response()->json(['is_success' => true], 200);
    }




    public function setActiveStatus(Request $request)
    {
        $update = $request['status'] > 0
            ? User::where('id', $request['user_id'])->update(['active_status' => 1])
            : User::where('id', $request['user_id'])->update(['active_status' => 0]);
        // send the response
        return Response::json([
            'status' => $update,
        ], 200);
    }

    protected function handleAttachment(Request $request)
    {
        $attachment = null;
        $attachment_title = null;
        $error = (object)[
            'status' => 0,
            'message' => null
        ];

        if ($request->hasFile('file')) {
            $allowed_images = Chatify::getAllowedImages();
            $allowed_files  = Chatify::getAllowedFiles();
            $allowed = array_merge($allowed_images, $allowed_files);

            $file = $request->file('file');

            if (in_array($file->getClientOriginalExtension(), $allowed)) {
                $attachment_title = $file->getClientOriginalName();
                $attachment = Str::uuid() . "." . $file->getClientOriginalExtension();
                $dir = 'attachments/';

                $path_store = Utility::upload_file($request, 'file', $attachment, $dir, []);
                if ($path_store['flag'] == 1) {
                    $file = $path_store['url'];
                } else {
                    $error->status = 1;
                    $error->message = $path_store['msg'];
                }
            } else {
                $error->status = 1;
                $error->message = "File extension not allowed!";
            }
        }

        return compact('attachment', 'attachment_title', 'error');
    }

    protected function findOrCreateThread($userId, $recipientId)
    {
        if ($userId == $recipientId) {
            $thread = Thread::whereHas('participants', function ($query) use ($userId) {
                $query->where('user_id', $userId);
            })->where('subject', 'Saved Messages')->first();

            if (!$thread) {
                $thread = Thread::create([
                    'subject' => 'Saved Messages'
                ]);

                Participant::create([
                    'thread_id' => $thread->id,
                    'user_id' => $userId,
                    'last_read' => new \Carbon\Carbon,
                ]);
            }
        } else {
            $thread = Thread::between([$userId, $recipientId])->first();
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
                    'last_read' => new \Carbon\Carbon,
                ]);
            }
        }

        return $thread;
    }

    private function formatAttachment($attachmentData)
    {
        if ($attachmentData['attachment']) {
            return json_encode((object)[
                'new_name' => $attachmentData['attachment'],
                'old_name' => htmlentities(trim($attachmentData['attachment_title']), ENT_QUOTES, 'UTF-8'),
            ]);
        }

        return null;
    }

    private function formatMessageData($messageId)
    {
        $message = LaravelMessage::find($messageId);
        $attachment = null;
        $attachment_type = null;
        $attachment_title = null;

        if (isset($message->attachment)) {
            $attachmentOBJ = json_decode($message->attachment);
            $attachment = $attachmentOBJ->new_name;
            $attachment_title = htmlentities(trim($attachmentOBJ->old_name), ENT_QUOTES, 'UTF-8');

            $ext = pathinfo($attachment, PATHINFO_EXTENSION);
            $attachment_type = in_array($ext, Chatify::getAllowedImages()) ? 'image' : 'file';
        }

        return [
            'id' => $message->id,
            'from_id' => $message->user_id,
            'to_id' => $message->thread->participants->where('user_id', '!=', $message->user_id)->first()->user_id ?? $message->user_id,
            'message' => !isset($message->attachment) ? $message->body : null,
            'attachment' => [$attachment, $attachment_title, $attachment_type],
            'time' => $message->created_at->diffForHumans(),
            'fullTime' => $message->created_at->format('Y-m-d H:i:s'),
            'viewType' => ($message->user_id == Auth::user()->id) ? 'sender' : 'default',
            'seen' => false,
        ];
    }

    private function getContactItem($messenger_id, $thread, $objUser){
        // get last message
        $lastMessage = $thread->getLatestMessageAttribute(); // $this->getLastMessageQuery(!empty($user)?$user->id:0);
        // Get Unseen messages counter
        $unseenCounter = $thread->userUnreadMessagesCount($objUser->id); // $this->countUnseenMessages(!empty($user)?$user->id:0);
        
        $contact = new \stdClass();
        $contact->id = $thread->id;
        $contact->name = $thread->subject;
        $contact->avatar = null;
        $contact->active_status = 0;

        return view('Chatify::layouts.listItem', [
            'get' => 'users',
            'user' => $contact,
            'lastMessage' => $lastMessage,
            'unseenCounter' => $unseenCounter,
            'type'=>'user',
            'id' => $messenger_id,
        ])->render();
    }}