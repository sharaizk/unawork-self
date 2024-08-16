<div class="hire-card">
    <div class="card__header">
        <div class="card__image" class=">
                <img @if ($employee->avatar) src="{{ App\Helpers\FileHelper::getUrl('users-avatar/' . $employee->avatar, 'small') }}" @else avatar="{{ $employee->name }}" @endif alt="">
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
            <p><strong>Skills:</strong> 
                @forelse ($employee->skills as $index => $skill)
                    {{ $skill->name }}{{ $index < $employee->skills->count() - 1 ? ',' : '' }}
                @empty
                    n/a
                @endforelse
            </p>
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
                    @endif
                @else
                    <a href="{{ route('login') }}"><span>Message</span></a>
                @endif

                <!-- <a href="#"><span>Video</span></a> -->
        </div>
    </div>
</div>
