<div class="hire-card">
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

                        <a href="#" data-member-id="{{ $employee->id }}" data-bs-toggle="modal" data-bs-target="#newMeetingPopup"><span>Video</span></a>

                    @endif
                @else
                    <a href="{{ route('login') }}"><span>Message</span></a>
                    <a href="{{ route('login') }}" ><span>Video</span></a>

                @endif

        </div>
    </div>
</div>

<script type="text/javascript">
    document.addEventListener("DOMContentLoaded", function () {
        $('#newMeetingPopup').on('show.bs.modal', function (event) {
            var button = $(event.relatedTarget); // button which show modal
            var memberId = button.data('member-id'); // value data-member-id

            var modal = $(this);
            document.getElementById('memberIdInput').value = memberId; // set value to input field
        });
    });
</script>
