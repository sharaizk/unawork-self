<div class="hire-card">
    <div class="card__header">
        <div class="card__image" class=">
            <a href="/commerce-shop.php">
                <img src="{{ asset('assets/images/landing/card-avatar-1.png') }}" alt="">
            </a>
        </div>
        <div class="card__title">
            <a href="#">{{$employee->name}}</a>
        </div>
    </div>
    <div class="card__body">
        <div class="card__copy">
            <p><strong>Country:</strong> {{$employee->profile->country ?? 'n/a'}}</p>
            <p><strong>State:</strong> {{$employee->profile->state ?? 'n/a'}}</p>
            <p><strong>City:</strong> {{$employee->profile->city ?? 'n/a'}}</p>
        </div>
        <div class="card__skills">
            <p><strong>Skills:</strong></p>
            <ul>
                @forelse ($employee->skills as $skill)
                    <li>{{$skill->name}}</li>
                @empty
                    <li>n/a</li>
                @endforelse
            </ul>
        </div>
    </div>
    <div class="card__footer">
        <div class="card__actions">
        <div class="container-master">

            <div class="row-master">
                <a href="#"><span>View HubShop</span></a>
                <a href="#"><span>Interview</span></a>
            </div>
            <div class="row-master">
                <button class="sendMessageButton" data-employee-id="{{ $employee->id }}">
                    <span> Message </span> 
                </button>
                <a href="#"><span>Video</span></a>
            </div>            
        </div>
        </div>
    </div>
</div>
