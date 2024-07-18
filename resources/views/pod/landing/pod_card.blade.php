<div class="pod-list col-lg-2 col-md-3 col-sm-6 mb-4">
    <div class="pod-list-item" style="{{ $pod->image ? 'background-image: url(' . asset('storage/' . $pod->image) . ');' : '' }}">
        <a href="{{ route('landing.pods.show', $pod->id) }}">
            <h4 class="pod-title">{{ $pod->title }}</h4>
        </a>
        @if ($joinable == 1)
        <form class="join-form" data-pod-id="{{ $pod->id }}">
            <button type="submit" class="pod-join btn btn-primary">Join</button>
        </form>
        @endif
    </div>
</div>