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
            <button type="submit" class="pod-join btn btn-primary" disabled>Joined</button>
        @endif
    </div>
</div>
