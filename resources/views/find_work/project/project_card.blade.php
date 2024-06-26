<div class="col-12 col-md-4 col-xl-4">
    <div class="project__card" style="margin-bottom: 5px;">
        <div class="project__header">
            <h4>{{ $project->name }}</h4>
            <button class="btn btn-dot">
                <i class="fa fa-circle" aria-hidden="true"></i>
                <i class="fa fa-circle" aria-hidden="true"></i>
                <i class="fa fa-circle" aria-hidden="true"></i>
            </button>
        </div>
        <div class="project__body">
            <div class="project__status">
                <a href="#"><span>{{ $project->status }}</span> </a>
            </div>
            <div class="project__text">
                {{ $project->description }} 
            </div>
        </div>
        <div class="project__footer">
            <ul class="project-list__person">
                @php
                    $displayedUsers = 0;
                    $totalUsers = $project->users->count();
                @endphp

                @foreach($project->users as $user)
                    @if($user->pivot->is_active)
                        @if($displayedUsers < 3)
                            <li>
                                <a href="#section__water" class="project-person__link" data-toggle="tooltip" data-placement="top" title="{{$user->name}}">
                                    <img alt="{{$user->name}}" @if($user->avatar) src="{{asset($logo.$user->avatar)}}" @else avatar="{{ $user->name }}" @endif>
                                </a>
                            </li>
                            @php $displayedUsers++; @endphp
                        @else
                            @break
                        @endif
                    @endif
                @endforeach

                @if($totalUsers - $displayedUsers > 0)
                    <li>
                        <span>+{{ $totalUsers - $displayedUsers }} others</span>
                    </li>
                @endif
            </ul>
        </div>
    </div>
</div>