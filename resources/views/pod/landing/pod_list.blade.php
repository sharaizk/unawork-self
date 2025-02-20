<div class="row-master">
    @foreach($pods as $pod)
        @php
            $joinable = ($user && !in_array($pod->id, $joinedPods)) ? 1 : 0;
        @endphp
        @include('pod.landing.pod_card', ['pod' => $pod, 'joinable' => $joinable])
    @endforeach
</div>
