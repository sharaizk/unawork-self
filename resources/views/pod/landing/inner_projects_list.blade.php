@foreach ($projects as $project)
    <div class="circle col-12 col-xl-6">
        <div class="section__list_inner_circle">

            <div class="circle__info_list--item">
                <div class="circle_img">
                    <img src="{{ asset('/assets/images/gallery.png') }}" alt="">
                </div>
                <div class="circle_content">
                    <div class="title">
                        <span>{{ $project->name }}</span>
                        <b>$ {{ $project->budget }}</b>
                    </div>
                    <div class="text">
                        {{ \Illuminate\Support\Str::limit($project->description, 120, '...') }}
                    </div>
                </div>
                <div class="cicrle_btn">
                    {{--                                <a href="#" class="btn-accept">Accept</a>--}}
                    {{--                                <button class="btn-reject">Reject</button>--}}
                    @if (in_array($project->id, \Auth::user()->projects->where('pivot.status', 'active')->pluck('id')->toArray()))
                        <button class="btn-reject">Joined</button>
                    @elseif (in_array($project->id, \Auth::user()->projects->where('pivot.status', 'pending')->pluck('id')->toArray()))
                        <button class="btn-reject">Request Sent</button>
                    @elseif (in_array($project->id, \Auth::user()->projects->where('pivot.status', 'declined')->pluck('id')->toArray()))
                        <button class="btn-reject">Request Declined</button>
                    @elseif (!in_array($project->id, \Auth::user()->projects->pluck('id')->toArray()) && \Auth::user()->can('create', [\App\Models\UserProject::class, $project]))
                        <a href="{{ route('pods.projects.join', ['project' => $project->id]) }}" class="btn-accept">Join</a>
                    @endif
                </div>
            </div>

        </div>
    </div>
@endforeach
