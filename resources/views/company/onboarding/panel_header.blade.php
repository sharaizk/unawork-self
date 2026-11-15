{{-- Step panel header. Params: $key; uses $stepMeta, $stepDone, $steps from company.onboarding. --}}
@php $position = array_search($key, $steps, true) + 1; @endphp
<div class="card-header co-panel__header">
    <div class="d-flex align-items-start gap-3">
        <span class="co-panel__icon" aria-hidden="true"><i class="ti ti-{{ $stepMeta[$key]['icon'] }}"></i></span>
        <div class="flex-grow-1">
            <span class="small text-muted d-block">{{ __('Step :current of :total', ['current' => $position, 'total' => count($steps)]) }}</span>
            <h5 class="mb-0">{{ $stepMeta[$key]['label'] }}</h5>
        </div>
        @if ($key !== 'review')
            @if ($stepDone($key))
                <span class="badge co-badge co-badge--done"><i class="ti ti-check"></i> {{ __('Complete') }}</span>
            @else
                <span class="badge co-badge co-badge--todo">{{ __('Incomplete') }}</span>
            @endif
        @endif
    </div>
</div>
