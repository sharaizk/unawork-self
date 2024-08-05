@php
    $currentWorkspace = \App\Models\Utility::getWorkspaceBySlug();
@endphp
@extends('layouts.admin')

@section('page-title')
    {{ __('Notifications') }}
@endsection


@section('links')
    <li class="breadcrumb-item"><a href="{{ route('home') }}">{{ __('Home') }}</a></li>
    <li class="breadcrumb-item"> {{ __('Notifications') }}</li>
@endsection
@php
$unreadNotificationCounter = App\Models\Notification::where('user_id', Auth::user()->id)
        ->where('is_read', 0)
        ->count();
@endphp
@section('content')
<style>
    .unread-dot {
        display: inline-block;
        width: 10px;
        height: 10px;
        background-color: red;
        border-radius: 50%;
        cursor: pointer;
        margin-right: 10px;
    }
    .d-flex {
        display: flex;
        align-items: center;
    }
    .flex-fill {
        flex: 1;
    }
    .flex-grow-1 {
        flex-grow: 1;
    }
    .ml-3 {
        margin-left: 10px;
    }
    .justify-content-between {
        justify-content: space-between;
    }
    .action-button-container {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 20px;
    }
</style>
<main>
    <div class="action-button-container">
        <span></span>
        @if ($unreadNotificationCounter > 0)
        <a href="#" data-url="{{ route('delete_all.notifications', $currentWorkspace->slug) }}" class="btn btn-sm btn-secondary clear_all_notifications">Read All</a>
        @endif
    </div>

<div class="limited">
    @foreach ($notifications as $notification)
        @php
            $project = $notification->project;
            $task = $notification->task;
            $notifyingUser = $notification->user;

            // Define variables for the notification data
            $projectTitle = $project ? $project->title : '';
            $taskTitle = $task ? $task->title : '';
            $notifyingUserName = $notifyingUser->name;

            // Define other variables you need for HTML
            $link = ''; // Replace with the actual link
            $name = ''; // Replace with the notification icon or name
            $text = ''; // Replace with the notification text
            $date = $notification->created_at->diffForHumans();
            $data = json_decode($notification->data);
        @endphp
        @if ($notification->user && trim($notification->user->name) != '')
            @php
                $name = '';
                $nameParts = explode(' ', $notification->user->name);
            @endphp

            @foreach ($nameParts as $word)
                @php
                    $name .= strtoupper($word[0]);
                @endphp
            @endforeach
        @endif

        @if ($notification->type == 'task_assign')
            @php
                if ($project) {
                    $workspace = $project->workspaceData;
                    $link = route('projects.task.board', [$workspace->slug, $notification->project_id]);
                    $text = __('New task assign') . ' <b>' . $data->title . '</b> ' . __('in project') . ' <b>' . $project->name . '</b>';
                    $icon = 'fa fa-clock-o';
                    if ($data->priority == 'Low') {
                        $icon_color = 'bg-success';
                    } elseif ($data->priority == 'High') {
                        $icon_color = 'bg-danger';
                    }
                } else {
                    continue;
                }
            @endphp
            @elseif($notification->type == 'project_assign')
                @php
                    $project = \App\Models\Project::find($data->id);
                    $workspace = \App\Models\Workspace::find($notification->workspace_id);
                    if (!$project || !$workspace) {
                        continue;
                    }
                    $link = route('projects.show', [$workspace->slug, $project->id]);
                    $text = __('You were assigned to new project: ') . ' <b>' . $project->name . '</b>';
                    $icon = 'fa fa-suitcase';
                @endphp
            @elseif($notification->type == 'pod_invite')
            @php
                    $pod = \App\Models\Pod::find($data->id);
                    $user = \Auth::user();
                    if (!$pod || !$user) {
                        continue;
                    }
                    $acceptLink = route('pods.accept', ['pod' => $pod, 'user' => $user]);
                    $declineLink = route('pods.decline', ['pod' => $pod, 'user' => $user]);
                    $text = __('New invite for joining pod: ') . ' <b>' . $pod->title . '</b>
                        <br>
                        <form id="acceptForm-' . $user->id . '" 
                            action="' . $acceptLink . '" 
                            method="POST" 
                            style="display: none;">
                            ' . csrf_field() . '
                        </form>
                        <button class="btn btn-success btn-sm p-1 align-baseline accept-btn" style="margin-right: 8px;" data-user-id="' . $user->id . '">
                            ACCEPT
                        </button>
                        <button class="btn btn-danger btn-sm p-1 m-0 align-baseline decline-btn" 
                            data-url="' . $declineLink . '">DECLINE</button>';
                    $icon = 'fa fa-bug';
                    $name = 'POD';
                @endphp
            @elseif($notification->type == 'pod_remove')
                @php
                    $text = __('You were removed from pod: ') . ' <b>' . $data->title . '</b>';
                    $icon = 'fa fa-bug';
                    $name = 'POD';
                @endphp
            @elseif($notification->type == 'pod_request')
                @php
                    $pod = \App\Models\Pod::find($data->pod_id);
                    $user = \App\Models\User::find($data->user_id);
                    if (!$pod || !$user) {
                        continue;
                    }
                    $acceptLink = route('pods.accept', ['pod' => $pod, 'user' => $user]);
                    $declineLink = route('pods.decline', ['pod' => $pod, 'user' => $user]);
                    $text = __('New request for joining pod: ') . ' <b>' . $pod->title . '</b>
                        <br>
                        <form id="acceptForm-' . $user->id . '" 
                            action="' . $acceptLink . '" 
                            method="POST" 
                            style="display: none;">
                            ' . csrf_field() . '
                        </form>
                        <button class="btn btn-success btn-sm p-1 align-baseline accept-btn" style="margin-right: 8px;" data-user-id="' . $user->id . '">
                            ACCEPT
                        </button>
                        <button class="btn btn-danger btn-sm p-1 m-0 align-baseline decline-btn" 
                            data-url="' . $declineLink . '">DECLINE</button>';
                    $icon = 'fa fa-bug';
                    $name = 'POD';
                @endphp
            @elseif($notification->type == 'pod_request_accept_owner')
                @php
                    $pod = \App\Models\Pod::find($data->pod_id);
                    $user = \App\Models\User::find($data->user_id);
                    if (!$pod || !$user) {
                        continue;
                    }
                    $link = route('pods.users', ['podId' => $data->pod_id]);
                    $text = __('User : ') . ' <b>' . $user->name . '</b> joined your pod:  <b>'. $pod->title . ' </b>';
                    $icon = 'fa fa-bug';
                    $name = 'POD';
                @endphp
            @elseif($notification->type == 'pod_request_accept_user')
                @php
                    $pod = \App\Models\Pod::find($data->pod_id);
                    if (!$pod) {
                        continue;
                    }
                    $link = route('pods.users', ['podId' => $data->pod_id]);
                    $text = __('You have joined pod : ') . ' <b>' . $pod->title . '</b>';
                    $icon = 'fa fa-bug';
                    $name = 'POD';
                @endphp
            @elseif($notification->type == 'pod_request_decline_owner')
                @php
                    $pod = \App\Models\Pod::find($data->pod_id);
                    $user = \App\Models\User::find($data->user_id);
                    if (!$pod || !$user) {
                        continue;
                    }
                    $link = route('pods.users', ['podId' => $data->pod_id]);
                    $text = __('User : ') . ' <b>' . $user->name . '</b> declined to join your pod:  <b>'. $pod->title . ' </b>';
                    $icon = 'fa fa-bug';
                    $name = 'POD';
                @endphp
            @elseif($notification->type == 'pod_request_decline_user')
                @php
                    $pod = \App\Models\Pod::find($data->pod_id);
                    if (!$pod) {
                        continue;
                    }
                    $text = __('You have beed declined to join pod : ') . ' <b>' . $pod->title . '</b>';
                    $icon = 'fa fa-bug';
                    $name = 'POD';
                @endphp
            @elseif($notification->type == 'project_request')
                @php
                    $user = \App\Models\User::find($data->user_id);
                    $project = \App\Models\Project::find($data->project_id);
                    if (!$project || !$user) {
                        continue;
                    }
                    $acceptLink = route('pods.projects.join.accept', ['record' => $data->id]);
                    $declineLink = route('pods.projects.join.decline', ['record' => $data->id]);
                    $text = __('User : ') . ' 
                        <a href="'. route('employee.show', ['employee' => $data->user_id]) .'"><b>' . $user->name . '</b></a>
                        sended join request to your project:  
                        <b><a href="'. route('projects.show',['slug' => $project->workspaceData->slug, 'id' => $project->id]) .'">'. $project->name . ' </a></b>
                        <br>
                        <br>
                        <button class="btn btn-success btn-sm p-1 align-baseline" style="margin-right: 8px;"
                            onclick="window.location.href=\'' . $acceptLink . '\'">ACCEPT</button>
                        <button class="btn btn-danger btn-sm p-1 m-0 align-baseline" 
                            onclick="window.location.href=\'' . $declineLink . '\'">DECLINE</button>
                    ';
                @endphp
            @elseif($notification->type == 'project_accepted')
                @php
                    $project = \App\Models\Project::find($data->id);
                    if (!$project) {
                        continue;
                    }
                    $link = route('projects.show',['slug' => $project->workspaceData->slug, 'id' => $project->id]);
                    $text = __('Your request to join project : ') . ' <b>' . $project->name . '</b> was accepted';
                    $icon = 'fa fa-bug';
                    $name = 'Pro';
                @endphp
            @elseif($notification->type == 'project_decline')
                @php
                    $project = \App\Models\Project::find($data->id);
                    if (!$project) {
                        continue;
                    }
                    $link = route('home');
                    $text = __('Your request to join project : ') . ' <b>' . $project->name . '</b> was declined';
                    $icon = 'fa fa-bug';
                    $name = 'Pro';
                @endphp
            @elseif($notification->type == 'workspace_join')
                @php
                    $user = \App\Models\User::find($data->user_id);
                    $workspace = \App\Models\Workspace::find($data->workspace_id);
                    if (!$workspace || !$user) {
                        continue;
                    }
                    $acceptLink = route('company.join.accept', ['userWorkspace' => $data->id]);
                    $declineLink = route('company.join.decline', ['userWorkspace' => $data->id]);
                    $text = __('User : ') . ' 
                        <a href="'. route('employee.show', ['employee' => $data->user_id]) .'"><b>' . $user->name . '</b></a>
                        sended join request to your workspace:  
                        <b><a href="'. route('users.index', ['slug' => $workspace->slug]) .'">'. $workspace->name . ' </a></b>
                        <br>
                        <br>
                        <button class="btn btn-success btn-sm p-1 align-baseline" style="margin-right: 8px;"
                            onclick="window.location.href=\'' . $acceptLink . '\'">ACCEPT</button>
                        <button class="btn btn-danger btn-sm p-1 m-0 align-baseline" 
                            onclick="window.location.href=\'' . $declineLink . '\'">DECLINE</button>
                    ';
                @endphp
            @elseif($notification->type == 'workspace_invite')
                @php
                    $data = \App\Models\UserWorkspace::find($data->id);
                    if (!$data) {
                        continue;
                    }
                    $acceptLink = route('users.accept', ['workspace' => $data->workspace->id]);
                    $declineLink = route('users.decline', ['workspace' => $data->workspace->id]);
                    $text = __('New invite into: ') . ' <b>' . $data->workspace->name . '</b>
                        <br>
                        <br>
                            <button class="btn btn-success btn-sm p-1 align-baseline" style="margin-right: 8px;"
                                onclick="window.location.href=\'' . $acceptLink . '\'">ACCEPT</button>
                            <button class="btn btn-danger btn-sm p-1 m-0 align-baseline" 
                                onclick="window.location.href=\'' . $declineLink . '\'">DECLINE</button>
                    ';
                    $icon = 'fa fa-bug';
                    $name = 'WP';
                    $workspaceId = $data->id;
                @endphp
            @elseif($notification->type == 'workspace_request_accept_owner')
                @php
                    $workspace = \App\Models\Workspace::find($data->workspace_id);
                    $user = \App\Models\User::find($data->user_id);
                    if (!$workspace || !$user) {
                        continue;
                    }
                    $text = __('User : ') . ' <b>' . $user->name . '</b> joined your workspace:  <b>'. $workspace->name . ' </b>';
                    $icon = 'fa fa-bug';
                    $name = 'WP';
                @endphp
            @elseif($notification->type == 'workspace_request_accept_user')
                @php
                    $workspace = \App\Models\Workspace::find($data->workspace_id);
                    if (!$workspace) {
                        continue;
                    }
                    $text = __('You joined workspace:  <b>'. $workspace->name . ' </b>');
                    $icon = 'fa fa-bug';
                    $name = 'WP';
                @endphp
            @elseif($notification->type == 'workspace_request_decline_owner')
                @php
                    $workspace = \App\Models\Workspace::find($data->workspace_id);
                    $user = \App\Models\User::find($data->user_id);
                    if (!$workspace || !$user) {
                        continue;
                    }
                    $text = __('User : ') . ' <b>' . $user->name . '</b> declined to join your workspace:  <b>'. $workspace->name . ' </b>';
                    $icon = 'fa fa-bug';
                    $name = 'WP';
                @endphp
            @elseif($notification->type == 'workspace_request_decline_user')
                @php
                    $workspace = \App\Models\Workspace::find($data->workspace_id);
                    $user = \App\Models\User::find($data->user_id);
                    if (!$workspace || !$user) {
                        continue;
                    }
                    $text = __('Your request to join workspace : ') . ' <b>' . $workspace->name . '</b> were declined';
                    $icon = 'fa fa-bug';
                    $name = 'WP';
                @endphp
            @elseif($notification->type == 'project_new')
                @php
                    $workspace = \App\Models\Workspace::find($data->workspace);
                    if (!$workspace) {
                        continue;
                    }
                    $link = route('projects.index', ['slug' => $workspace->slug]);
                    $text = __('New project : ') . ' <b>' . $data->name . '</b> in your workspace:  <b>'. $workspace->name . ' </b>';
                    $icon = 'fa fa-bug';
                    $name = 'Proj';
                @endphp
            @elseif($notification->type == 'bug_assign')
                @php
                    if ($project) {
                        $link = route('projects.bug.report', [$notification->workspace_id, $notification->project_id]);
                        $text = __('New bug assign') . ' <b>' . $data->title . '</b> ' . __('in project') . ' <b>' . $project->name . '</b>';
                        $icon = 'fa fa-bug';
                        if ($data->priority == 'Low') {
                            $icon_color = 'bg-success';
                        } elseif ($data->priority == 'High') {
                            $icon_color = 'bg-danger';
                        }
                    }
                @endphp
            @elseif($notification->type == 'job_application_new')
                @php
                    $vacancy = \App\Models\JobVacancy::find($data->job_vacancy_id);
                    if (!$vacancy) {
                        continue;
                    }
                    $link = route('job-applications.index', ['vacancy' => $data->job_vacancy_id]);
                    $text = __('You have new application for your vacancy : ') . ' <b>' . $vacancy->title . '</b>';
                    $icon = 'fa fa-bug';
                    $name = 'JOB';
                @endphp
        @endif
        <div class="list-group-item list-group-item-action">
            <div class="d-flex align-items-center">
                @if ($notification->is_read == 0)
                    <span id="isReadStatus-{{ $notification->id }}" class="unread-dot" onclick="markAsRead({{ $notification->id }})"></span>
                @endif
                @if ($link) 
                <a href="{{ $link }}" class="flex-fill">
                @endif
                    <div class="d-flex align-items-center flex-fill" data-toggle="tooltip" data-placement="right" data-title="{{ $date }}">
                        <div class="notification_icon_size">
                            <span class="avatar bg-primary text-white rounded-circle px-2 py-1">{{ $name }}</span>
                        </div>
                        <div class="flex-grow-1 ml-3">
                            <div class="h6 text-sm mb-0 d-flex justify-content-between">
                                <span>{{ $notification->user->name }}</span>
                                <small class="text-muted">{{ $date }}</small>
                            </div>
                            <div class="text-sm lh-140 mb-0">
                                <span>{!! $text !!}</span>
                            </div>
                        </div>
                    </div>
                @if ($link) 
                </a>
                @endif
                <form method="POST" action="{{ route('notification.delete', ['notification' => $notification->id]) }}" style="display:inline;">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-link text-danger p-1">
                        <i class="fas fa-times"></i>
                    </button>
                </form>
            </div>
        </div>
        <hr>
    @endforeach
    <div class="d-flex justify-content-center">
        {{ $notifications->onEachSide(1)->links('pagination::bootstrap-4') }}
    </div>
</div>
<script type="text/javascript">
    document.addEventListener("DOMContentLoaded", function () {
        document.querySelectorAll(".accept-btn").forEach(button => {
            button.addEventListener("click", function() {
                let userId = this.dataset.userId;
                let form = document.getElementById(`acceptForm-${userId}`);
                let formData = new FormData(form);

                fetch(form.action, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    },
                    body: formData
                })
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Network response was not ok');
                    }
                    return response.json();
                })
                .then(data => {
                    console.log('Success:', data);
                    showNotification('Request accepted successfully', 'success');
                    setTimeout(function() {
                        location.reload();
                    }, 500);
                })
                .catch(error => {
                    showNotification('Request failed: ' + error.message, 'danger');
                    console.error('Error:', error);
                });
            });
        });

        document.querySelectorAll(".decline-btn").forEach(button => {
            button.addEventListener("click", function() {
                let url = this.dataset.url;

                fetch(url, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Content-Type': 'application/json'
                    }
                })
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Network response was not ok');
                    }
                    return response.json();
                })
                .then(data => {
                    console.log('Success:', data);
                    showNotification('Request declined successfully', 'success');
                    setTimeout(function() {
                        location.reload();
                    }, 500);
                })
                .catch(error => {
                    showNotification('Request failed: ' + error.message, 'danger');
                    console.error('Error:', error);
                });
            });
        });
    });
</script>
</main>
@endsection

@push('scripts')
@endpush
<script>
    function markAsRead(notificationId) {
        fetch(`/account/notifications/${notificationId}`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            }
        })
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            return response.json();
        })
        .then(data => {
            if (data === 'success') {
                showNotification('Notification marked as read!', 'success');
                document.getElementById(`isReadStatus-${notificationId}`).style.display = 'none';
            } else {
                throw new Error('Unexpected response');
            }
        })
        .catch(error => {
            showNotification('Failed to mark notification as read: ' + error.message, 'danger');
            console.error('Error:', error);
        });
    }
</script>
