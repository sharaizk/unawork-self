{{-- Card header for a profile section. Params: $key (ProfileCompletionService section key, optional), $icon, $title, $subtitle, $breakdown --}}
@php $section = isset($key) ? ($breakdown['sections'][$key] ?? null) : null; @endphp
<div class="card-header una-profile-card__header">
    <div class="d-flex align-items-start gap-3">
        <span class="una-profile-card__icon" aria-hidden="true"><i class="ti ti-{{ $icon }}"></i></span>
        <div class="flex-grow-1">
            <h5 class="mb-1">{{ $title }}</h5>
            @if (!empty($subtitle))
                <p class="text-muted small mb-0">{{ $subtitle }}</p>
            @endif
        </div>
        @if ($section)
            @if ($section['complete'])
                <span class="badge una-profile-badge una-profile-badge--done"><i class="ti ti-check"></i> {{ __('Complete') }}</span>
            @else
                <span class="badge una-profile-badge una-profile-badge--todo">{{ __('+:weight%', ['weight' => $section['weight']]) }}</span>
            @endif
        @endif
    </div>
</div>
