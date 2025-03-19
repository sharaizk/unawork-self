<div class="hire-card">
    <div>
        <div class="card__header">
            <div class="card__image" class="">
                    <img @if($employee->avatar)  src="{{ App\Helpers\FileHelper::getUrl('users-avatar/' . $employee->avatar, 'small') }}" @else avatar="{{ $employee->name }}" @endif alt="">
            </div>
            <div class="card__title">
                <a href="{{ route('employee.show', ['employee' => $employee->id]) }}">{{$employee->name}}</a>
            </div>
        </div>
        <div class="card__body">
            <div class="card__copy">
                <p><strong>Country:</strong> {{$employee->profile->country ?? 'n/a'}}</p>
                <p><strong>State:</strong> {{$employee->profile->state ?? 'n/a'}}</p>
                <p><strong>City:</strong> {{$employee->profile->city ?? 'n/a'}}</p>
            </div>
            <div class="card__skills">
                <div class="content">
                    <strong>Skills: </strong>
                    <!-- <strong>Country:</strong> {{$user->profile->country ?? 'n/a'}}
                    <strong>State:</strong> {{$user->profile->state ?? 'n/a'}}
                    <strong>City:</strong> {{$user->profile->city ?? 'n/a'}}
                    <strong>Skills:</strong> -->
                    @forelse ($employee->skills as $index => $skill)
                        <div class="content__skill">{{$skill->name}}</div>
                    @empty
                        n/a
                    @endforelse
                </div>
            </div>
        </div>
    </div>
    <div class="card__footer">
        <div class="card__actions">

                <!-- <a href="#"><span>View HubShop</span></a> -->
                <!-- <a href="#"><span>Interview</span></a> -->

                @if (Auth::user())
                    @if (Auth::user()->id != $employee->id)
                        <button class="sendMessageButton" data-employee-id="{{ $employee->id }}">
                            <span> Message </span>
                        </button>

                        <a href="#" data-member-id="{{ $employee->id }}" data-bs-toggle="modal" data-bs-target="#newMeetingPopup"><span>Video</span></a>

                    @endif
                @else
                    <a href="{{ route('login') }}"><span>Message</span></a>
                    <a href="{{ route('login') }}" data-member-id="{{ $employee->id }}" ><span>Video</span></a>

                @endif

        </div>
    </div>
</div>
