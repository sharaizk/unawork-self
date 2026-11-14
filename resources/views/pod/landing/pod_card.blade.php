<div class="pod-list col-lg-3 col-md-3 mb-3">
    <div class="pod-list-item__wrap">
        <a href="{{ route('landing.pods.show', $pod->id) }}" class="pod-list-item" style="{{ $pod->image ? 'background-image: url(' . asset('storage/' . $pod->image) . ');' : '' }}">
            <div class="pod-list-item__overlay"></div>
            <h4 class="pod-title">{{ $pod->title }}</h4>
        </a href="{{ route('landing.pods.show', $pod->id) }}">
        @if ($joinable == 1)
            <form class="join-form" data-pod-id="{{ $pod->id }}">
                <button type="submit" class="pod-join btn btn-primary">Join</button>
            </form>
        @else
            @php
                $podState = \App\Enums\PodUser\State::tryFrom((string) $pod->currentUserPivot()?->state);
            @endphp
            @if($podState === \App\Enums\PodUser\State::REJECTED)
                <button type="submit" class="pod-join btn btn-primary" disabled>DECLINED</button>
            @elseif(in_array($podState, \App\Enums\PodUser\State::applicants(), true))
                <button type="submit" class="pod-join btn btn-primary" disabled>Sent</button>
            @elseif(in_array($podState, \App\Enums\PodUser\State::activeMembers(), true))
                <button type="submit" class="pod-join btn btn-primary" disabled>Joined</button>
            @elseif($podState === \App\Enums\PodUser\State::SUSPENDED)
                <button type="submit" class="pod-join btn btn-primary" disabled>{{ $podState->label() }}</button>
            @endif
        @endif
    </div>
</div>
