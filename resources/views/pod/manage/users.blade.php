@extends('layouts.admin')
 
@section('content')
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
                        <div class="card-header-right">
                            <div class="btn-group card-option">
                                <button type="button" class="btn dropdown-toggle" data-bs-toggle="dropdown"
                                    aria-haspopup="true" aria-expanded="false">
                                    <i class="feather icon-more-vertical"></i>
                                </button>
                                <div class="dropdown-menu dropdown-menu-end">
                                @if ($pod->created_by == Auth::user()->id )
                                    @if ($user->pivot->status == 'pod_pending')
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
                                        <a href="#" class="dropdown-item remove-user" data-url="{{ route('pods.remove', ['podId' => $pod->id, 'userId' => $user->id]) }}""
                                            data-title="{{ __('Remove user from pod') }}">
                                            <i class="ti ti-pencil"></i> 
                                            <span>{{ __('Remove user from pod') }}</span>
                                        </a>
                                    @endif
                                @elseif ($user->id == Auth::user()->id )
                                    @if ($user->pivot->status == 'employee_pending')
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
                                            <span>{{ __('Remove me from thispod') }}</span>
                                        </a>
                                    @endif
                                @endif
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="card-body">
                        <img alt="user-image" class="img-fluid rounded-circle img_users_fix_size"
                            @if ($user->avatar) src="{{ App\Helpers\FileHelper::getUrl('users-avatar/' . $user->avatar, 'small') }}" @else avatar="{{ $user->title }}" @endif>
                        <h4 class="mt-2">{{ $user->name }}</h4>
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
    </div>

    <script type="text/javascript">
    document.addEventListener('DOMContentLoaded', function() {
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
                    }, 100);
                }
            });
        });
    });
</script>
<script>
    document.addEventListener('DOMContentLoaded', function() {
    const removeUserLinks = document.querySelectorAll('.remove-user');

    removeUserLinks.forEach(link => {
        link.addEventListener('click', async function(event) {
            event.preventDefault();
            const url = this.getAttribute('data-url');
            try {
                const response = await axios.delete(url);
                location.reload(); 
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
