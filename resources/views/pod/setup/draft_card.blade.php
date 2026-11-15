{{-- Draft POD card on the management page (UNA-011). Params: $pod, $setup (PodConfigurationService::breakdown + missing) --}}
<div class="col-xl-3 col-lg-4 col-sm-6">
    <div class="card text-center pod-draft-card" data-pod-draft="{{ $pod->id }}">
        <div class="card-header border-0 pb-0 d-flex justify-content-between align-items-center">
            <span class="badge bg-warning text-dark"><i class="ti ti-pencil" aria-hidden="true"></i> {{ __('Draft') }}</span>
            <a href="#" class="btn btn-sm btn-light-danger bs-pass-para"
                data-confirm="{{ __('Are You Sure?') }}"
                data-text="{{ __('This action can not be undone. Do you want to continue?') }}"
                data-confirm-yes="delete_pod_{{ $pod->id }}"
                title="{{ __('Delete draft') }}" aria-label="{{ __('Delete draft :title', ['title' => $pod->title ?: __('Untitled POD')]) }}"><i class="ti ti-trash"></i></a>
            <form action="{{ route('pods.delete', $pod->id) }}" method="post" id="delete_pod_{{ $pod->id }}" class="d-none">
                @csrf
                @method('DELETE')
            </form>
        </div>
        <div class="card-body">
            <h4 class="mt-2 text-break">{{ $pod->title ?: __('Untitled POD') }}</h4>
            <p class="small text-muted mb-2">{{ __(':percentage% configured', ['percentage' => $setup['percentage']]) }}</p>
            <div class="progress pod-draft-card__progress mb-3" role="progressbar" aria-label="{{ __('POD configuration') }}"
                aria-valuenow="{{ $setup['percentage'] }}" aria-valuemin="0" aria-valuemax="100">
                <div class="progress-bar" style="width: {{ (int) $setup['percentage'] }}%"></div>
            </div>
            @if ($setup['missing'])
                <ul class="small text-muted ps-3 pod-draft-card__missing">
                    @foreach (array_slice($setup['missing'], 0, 3) as $missing)
                        <li>{{ $missing }}</li>
                    @endforeach
                    @if (count($setup['missing']) > 3)
                        <li>{{ __('and :count more', ['count' => count($setup['missing']) - 3]) }}</li>
                    @endif
                </ul>
            @endif
            <a href="{{ route('pods.setup.edit', $pod) }}" class="btn btn-sm btn-primary"><i class="ti ti-player-play"></i> {{ __('Resume setup') }}</a>
        </div>
    </div>
</div>
