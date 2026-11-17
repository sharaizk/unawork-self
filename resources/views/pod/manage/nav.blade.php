@php
    // UNA-018: keep the POD model; the legacy links below reassign $pod to an id ($pod=$podId).
    $reviewPod = isset($pod) && $pod instanceof \App\Models\Pod ? $pod : null;
@endphp
<!-- <div class="navbar-wrapper">
    <div class="navbar-content">
        <div class="list-group list-group-flush" style="top:30px">
            <div class="list-group list-group-flush" id="users-sidenav">
                <a href="{{ route('pods.users', $podId=$podId) }}"
                    class="list-group-item list-group-item-action border-0 active">
                    <span class="dash-micon"><i class="ti ti-home"></i></span>
                    <span class="dash-mtext">{{ __('Users') }}</span>
                    <div class="float-end"><i class="ti ti-chevron-right"></i></div>
                </a>
            </div>
            <div class="list-group list-group-flush" id="news-sidenav">
                <a href="{{ route('pods.news', $podId=$podId) }}"
                    class="list-group-item list-group-item-action border-0 active">
                    <span class="dash-micon"><i class="ti ti-home"></i></span>
                    <span class="dash-mtext">{{ __('News') }}</span>
                    <div class="float-end"><i class="ti ti-chevron-right"></i></div>
                </a>
            </div>
        </div>       
    </div>
</div> -->
<div class="card sticky-top" style="top:30px">
    <div class="list-group list-group-flush" id="useradd-sidenav">
        <a href="{{ route('pods.users', $podId=$podId) }}" class="list-group-item list-group-item-action border-0 @if(request()->routeIs('pods.users')) active @endif">Users <div class="float-end"><i class="ti ti-chevron-right"></i></div></a>

        <a href="{{ route('pods.news', $podId=$podId) }}" class="list-group-item list-group-item-action border-0 @if(request()->routeIs('pods.news')) active @endif">News <div class="float-end"><i class="ti ti-chevron-right"></i></div></a>
        
        <a href="{{ route('pods.projects', $pod=$podId) }}" class="list-group-item list-group-item-action border-0 @if(request()->routeIs('pods.projects')) active @endif">Projects <div class="float-end"><i class="ti ti-chevron-right"></i></div></a>

        {{-- UNA-018: policy-gated; no counts are queried here. --}}
        @if ($reviewPod && auth()->user()->can('viewMatchReviews', $reviewPod))
            <a href="{{ route('pods.match-reviews.index', $reviewPod) }}" class="list-group-item list-group-item-action border-0 @if(request()->routeIs('pods.match-reviews.*')) active @endif">{{ __('Match reviews') }} <div class="float-end"><i class="ti ti-chevron-right"></i></div></a>
        @endif
        
        
        
    </div>
</div>