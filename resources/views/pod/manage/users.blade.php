@extends('layouts.admin')
 
@section('content')
@php
    $userWorkspace = \App\Models\UserWorkspace::where('user_id', Auth::id())->where('workspace_id', $currentWorkspace->id)->first();
    if (!$userWorkspace) {
        return redirect()->back()->with('error', 'permission denied!');
    }
    $adminSetting = App\Models\Utility::getAdminPaymentSetting();
@endphp
<div class="row">
        
        @foreach ($users as $user)
            <div class="col-xl-3 col-lg-4 col-sm-6">
                <div class="card   text-center">
                    <div class="card-header border-0 pb-0">
                            @if (isset($user->pivot->permission))
                                @if ($user->pivot->permission == 'Owner')
                                    <span
                                        class="badge bg-primary">{{ __($user->pivot->permission) }}</span>
                                @elseif ($user->pivot->status == 'active')
                                    <span class="badge bg-dark">{{ __('Member') }}</span>
                                @elseif ($user->pivot->status == 'pod_pending')
                                    <span class="badge bg-dark">{{ __('pending pod') }}</span>
                                @elseif ($user->pivot->status == 'employee_pending')
                                    <span class="badge bg-dark">{{ __('pending employee') }}</span>
                                @else
                                    <span class="badge bg-dark">{{ __('declined') }}</span>
                                @endif
                            @endif
                            
                            @php
                                $hasOptions = false;
                                $isCreatorOrHasPermission = $pod->created_by == Auth::user()->id || $userWorkspace->hasPermissionTo('users_edit');
                                $isPendingUser = $user->pivot->status == 'pod_pending';
                                $isCurrentUser = $user->id == Auth::user()->id;
                                $isChatEnabled = $adminSetting['enable_chat'] == 'on';

                                // Check if there are any options to show
                                if ($isCreatorOrHasPermission) {
                                    if ($isPendingUser || $user->pivot->status != 'pod_pending') {
                                        $hasOptions = true;
                                    }
                                } elseif ($isCurrentUser) {
                                    if ($isPendingUser || $user->pivot->status != 'employee_pending') {
                                        $hasOptions = true;
                                    }
                                }

                                if (!$isCurrentUser && $isChatEnabled) {
                                    $hasOptions = true;
                                }
                            @endphp

                            <div class="card-header-right">
                                @if($hasOptions)
                                    <div class="btn-group card-option">
                                        <button type="button" class="btn dropdown-toggle" data-bs-toggle="dropdown"
                                                aria-haspopup="true" aria-expanded="false">
                                            <i class="feather icon-more-vertical"></i>
                                        </button>
                                        <div class="dropdown-menu dropdown-menu-end">
                                            @if ($isCreatorOrHasPermission)
                                                @if ($isPendingUser)
                                                    <a href="#" class="dropdown-item accept-user" data-url="{{ route('pods.accept', ['pod' => $pod, 'user' => $user]) }}""
                                                        data-title="{{ __('Accept user') }}">
                                                        <i class="ti ti-pencil"></i> 
                                                        <span>{{ __('Accept user') }}</span>
                                                    </a>
                                                    <a href="#" class="dropdown-item decline-user" data-url="{{ route('pods.decline', ['pod' => $pod, 'user' => $user]) }}""
                                                        data-title="{{ __('Decline user') }}">
                                                        <i class="ti ti-pencil"></i> 
                                                        <span>{{ __('Decline user') }}</span>
                                                    </a>
                                                @else
                                                    <!-- if ($user->id != Auth::id()) -->
                                                        <a href="#" class="dropdown-item" 
                                                                data-ajax-popup="true" 
                                                                data-size="lg" 
                                                                data-title="Edit Permissions"
                                                                data-url="{{ route('pod.users.editPermissions', ['pod' => $pod, 'user' => $user]) }}">
                                                            <i class="ti ti-flag"></i> 
                                                            Edit Permissions
                                                        </a>
                                                        @if ($pod->created_by != $user->id)
                                                        <a href="#" class="dropdown-item remove-user" data-url="{{ route('pods.remove', ['podId' => $pod->id, 'userId' => $user->id]) }}""
                                                            data-title="{{ __('Remove user from pod') }}">
                                                            <i class="ti ti-pencil"></i> 
                                                            <span>{{ __('Remove user from pod') }}</span>
                                                        </a>
                                                        @endif
                                                    <!-- endif -->
                                                @endif
                                            @elseif ($isCurrentUser)
                                                @if ($isPendingUser)
                                                    <a href="#" class="dropdown-item accept-user" data-url="{{ route('pods.accept', ['pod' => $pod, 'user' => $user]) }}""
                                                        data-title="{{ __('Accept user') }}">
                                                        <i class="ti ti-pencil"></i> 
                                                        <span>{{ __('Accept invite') }}</span>
                                                    </a>
                                                    <a href="#" class="dropdown-item decline-user" data-url="{{ route('pods.decline', ['pod' => $pod, 'user' => $user]) }}""
                                                        data-title="{{ __('Decline user') }}">
                                                        <i class="ti ti-pencil"></i> 
                                                        <span>{{ __('Decline invite') }}</span>
                                                    </a>
                                                @else
                                                    <a href="#" class="dropdown-item remove-user" data-url="{{ route('pods.remove', ['podId' => $pod->id, 'userId' => $user->id]) }}""
                                                        data-title="{{ __('Remove user from pod') }}">
                                                        <i class="ti ti-pencil"></i> 
                                                        <span>{{ __('Remove me from this pod') }}</span>
                                                    </a>
                                                @endif
                                            @endif
                                            @if (!$isCurrentUser && $isChatEnabled)
                                                <a href="#" class="dropdown-item sendMessageUser" data-employee-id="{{ $user->id }}">
                                                    <i class="ti ti-pencil"></i> 
                                                    <span> Message </span> 
                                                </a>
                                            @endif
                                        </div>
                                    </div>
                                @endif
                            </div>
                    </div>
                    <div class="card-body">
                        <img alt="user-image" class="img-fluid rounded-circle img_users_fix_size"
                            @if ($user->avatar) src="{{ App\Helpers\FileHelper::getUrl('users-avatar/' . $user->avatar, 'small') }}" @else avatar="{{ $user->name }}" @endif>
                            <h4 class="mt-2">
                                @if ($user->hasRole('employee'))
                                    <a href="{{ route('employee.show', ['employee' => $user]) }}">
                                        {{ $user->name }}
                                        <i class="ti ti-eye"></i>
                                    </a>
                                @else
                                    {{ $user->name }}
                                @endif
                            </h4>
                        <span>{{ $user->email }}</span>
                        <br>
                        <small>
                            @if (count($user->skills) >= 1) 
                                Skills: 
                            @endif
                            @foreach($user->skills as $skill)
                               {{ $skill->name }} @if(!$loop->last), @endif
                            @endforeach
                        </small>
                    </div>
                </div>
            </div>
        @endforeach

        @if ($pod->created_by == Auth::user()->id || $userWorkspace->hasPermissionTo('users_edit'))
        <div class="col-xl-3 col-lg-4 col-sm-6">
            <a href="#" class="btn-addnew-project" data-ajax-popup="true" data-size="lg"
                data-title="{{ __('Invite New User') }}" data-url="{{ route('pods.invite', $podId=$pod->id) }}"
                data-toggle="tooltip" title="{{ __('Invite New User') }}">
                <div class="bg-primary proj-add-icon">
                    <i class="ti ti-plus"></i>
                </div>
                <h6 class="mt-4 mb-2">{{__('Invite New User')}}</h6>
                <p class="text-muted text-center">{{__('Click here to invite New User')}}</p>
            </a>
        </div>
        @endif
    </div>

    <div class="modal" tabindex="-1" role="dialog" id="sendMessagePopup">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Send Message</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <input type="text" id="messageInput" class="form-control" placeholder="Type your message...">
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" id="sendMessage" data-employee-id="">Send</button>
            </div>
            </div>
        </div>
    </div>

    <script type="text/javascript">
        document.addEventListener("DOMContentLoaded", function () {
            document.querySelectorAll(".sendMessageUser").forEach(a => {
                a.addEventListener("click", function() {
                    let employeeId = this.dataset.employeeId;
                    console.log(employeeId);

                    $("#sendMessagePopup").modal('show');
                    document.getElementById("sendMessage").setAttribute('data-employee-id', employeeId);
                });
            });

            document.getElementById("sendMessage").addEventListener("click", function() {
                let message = document.getElementById("messageInput").value;
                let employeeId = this.getAttribute('data-employee-id');
                console.log(employeeId);
                let data = {
                    recipient_id: employeeId,
                    type: 'user',
                    message: message,
                    temporaryMsgId: 'temp_1',
                    _token: '{{ csrf_token() }}'
                };
                let url = `{{ route('api.send-message') }}`;

                fetch(url, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify(data),
                })
                .then(response => {
                    if (!response.ok) {
                        return response.json().then(error => {
                            throw new Error(error.message || 'Something went wrong');
                        });
                    }
                    return response.json();
                })
                .then(data => {
                    console.log('Success:', data);
                    showNotification('Message successfully sent', 'success');
                    $("#sendMessagePopup").modal('hide');
                })
                .catch(error => {
                    showNotification('Message sending failed: ' + error.message, 'danger');
                    console.error('Error:', error);
                });
            });
        });
    </script>

    <!-- add new user -->
    <script type="text/javascript">
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll(".btn-addnew-project").forEach(a => {
                a.addEventListener("click", function() {
                    $(document).on('shown.bs.modal', function (e) {
                        const form = document.querySelector('.invite-user-form');
                        const inviteUserButton = document.getElementById('inviteUserButton');
                        const podId = {{ $pod->id }};

                        inviteUserButton.addEventListener('click', async function(event) {
                            event.preventDefault();

                            const formData = new FormData(form);

                            try {
                                const response = await axios.post('{{ route('pods.user.exists', ['podId' => $pod->id]) }}', formData);
                                console.log(response);

                                form.reset();
                                document.getElementById('success-message').style.display = 'block';
                                setTimeout(function() {
                                    location.reload(); 
                                }, 3000);
                            } catch (error) {
                                console.log(error);

                                form.reset();
                                document.getElementById('failed-message').style.display = 'block';
                                
                                setTimeout(function() {
                                    location.reload(); 
                                }, 1000);
                            }
                        });
                    });
                });
            });
        });
    </script>
    <!-- remove user -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
        const removeUserLinks = document.querySelectorAll('.remove-user');

        removeUserLinks.forEach(link => {
            link.addEventListener('click', async function(event) {
                event.preventDefault();
                const url = this.getAttribute('data-url');
                try {
                    const response = await axios.delete(url);
                    window.location.href = response.data.location; 
                } catch (error) {
                    console.error('An error occurred:', error);
                }
            });
        });

        const acceptUserLinks = document.querySelectorAll('.accept-user');

        acceptUserLinks.forEach(link => {
            link.addEventListener('click', async function(event) {
                event.preventDefault();
                const url = this.getAttribute('data-url');
                try {
                    const response = await axios.post(url);
                    location.reload(); 
                } catch (error) {
                    console.error('An error occurred:', error);
                }
            });
        });

        const declineUserLinks = document.querySelectorAll('.decline-user');

        declineUserLinks.forEach(link => {
            link.addEventListener('click', async function(event) {
                event.preventDefault();
                const url = this.getAttribute('data-url');
                try {
                    const response = await axios.post(url);
                    location.reload(); 
                } catch (error) {
                    console.error('An error occurred:', error);
                }
            });
        });
    });
    </script>
@endsection
