{{-- One recommendation card. Params: $recommendation (App\Services\Matching\PodRecommendation). --}}
@php
    $pod = $recommendation->pod;
    $p = $recommendation->presented;
    $id = $pod->id;
    $num = fn ($value) => (string) (float) $value; // "100.00" -> "100", "68.75" -> "68.75", "12.50" -> "12.5"
    $score = $num($p['score']);
    $state = $recommendation->membershipState;
    $generatedAt = $recommendation->match->evaluated_at;
    $algorithmLabel = $p['algorithm'] === 'deterministic' ? __('Structured profile matching') : __('Profile matching');
    $detailIcons = ['work_mode' => 'ti-device-laptop', 'engagement' => 'ti-briefcase', 'location' => 'ti-map-pin', 'project_type' => 'ti-layout-kanban'];
    $detailLabels = ['work_mode' => __('Work mode'), 'engagement' => __('Engagement type'), 'location' => __('Location'), 'project_type' => __('Project type')];
@endphp
<article class="pr-card" id="pr-card-{{ $id }}" data-pr-card data-pod-id="{{ $id }}" aria-labelledby="pr-title-{{ $id }}">
    <div class="pr-card__head">
        <div class="pr-card__media">
            @if ($pod->image)
                <img src="{{ asset('storage/' . $pod->image) }}" alt="" loading="lazy">
            @else
                <span class="pr-card__placeholder" aria-hidden="true"><i class="ti ti-users"></i></span>
            @endif
        </div>
        <div class="pr-card__heading">
            <h3 class="pr-card__title" id="pr-title-{{ $id }}">
                <a href="{{ route('landing.pods.show', $id) }}" class="pr-card__link" data-pr-title-link>{{ $pod->title }}</a>
            </h3>
            <p class="pr-card__sub">
                @if ($recommendation->companyName)<span>{{ $recommendation->companyName }}</span>@endif
                @if ($recommendation->roleTitle)<span>{{ __('Best fit: :role', ['role' => $recommendation->roleTitle]) }}</span>@endif
            </p>
        </div>
        <div class="pr-score" aria-label="{{ __('Profile match :score%', ['score' => $score]) }}">
            <span class="pr-score__value" aria-hidden="true">{{ $score }}<small>%</small></span>
            <span class="pr-score__label" aria-hidden="true">{{ __('Profile match') }}</span>
            <span class="pr-score__bar" aria-hidden="true"><span style="inline-size: {{ min(100, max(0, (float) $p['score'])) }}%"></span></span>
        </div>
    </div>

    @if ($recommendation->highlights)
        <ul class="pr-chips" aria-label="{{ __('Top reasons') }}">
            @foreach ($recommendation->highlights as $highlight)
                <li class="pr-chip"><i class="ti ti-check" aria-hidden="true"></i> {{ $highlight['highlight'] }}</li>
            @endforeach
        </ul>
    @endif

    @if ($recommendation->details)
        <ul class="pr-meta">
            @foreach ($recommendation->details as $key => $value)
                <li><i class="ti {{ $detailIcons[$key] }}" aria-hidden="true"></i><span class="visually-hidden">{{ $detailLabels[$key] }}: </span>{{ $value }}</li>
            @endforeach
        </ul>
    @endif

    <p class="pr-card__time">
        {{ __('Matched from your profile') }} · <time datetime="{{ $generatedAt->toIso8601String() }}">{{ $generatedAt->diffForHumans() }}</time>
    </p>

    <div class="pr-actions">
        <span data-pr-apply-slot>
            @if ($recommendation->canApply())
                <button type="button" class="btn pr-btn-primary" data-pr-apply
                    data-url="{{ route('api.pods.join', ['id' => $id]) }}"
                    aria-label="{{ __('Apply to :pod', ['pod' => $pod->title]) }}">
                    <i class="ti ti-send" aria-hidden="true"></i> {{ __('Apply') }}
                </button>
            @elseif ($state === \App\Enums\PodUser\State::PENDING_BUSINESS_APPROVAL)
                <span class="pr-status"><i class="ti ti-clock" aria-hidden="true"></i> {{ __('Application sent') }}</span>
            @elseif ($state === \App\Enums\PodUser\State::MATCHED)
                <span class="pr-status"><i class="ti ti-mail" aria-hidden="true"></i> {{ __('Invitation pending') }}</span>
            @elseif ($state !== null && in_array($state, \App\Enums\PodUser\State::activeMembers(), true))
                <span class="pr-status pr-status--done"><i class="ti ti-circle-check" aria-hidden="true"></i> {{ __('Joined') }}</span>
            @elseif ($state !== null)
                <span class="pr-status">{{ $state->label() }}</span>
            @endif
        </span>
        <a href="{{ route('landing.pods.show', $id) }}" class="btn pr-btn-ghost" aria-label="{{ __('View :pod', ['pod' => $pod->title]) }}">{{ __('View POD') }}</a>
        <button type="button" class="btn pr-btn-dismiss" data-pr-dismiss
            data-url="{{ route('pod-recommendations.dismiss', $id) }}"
            aria-label="{{ __('Dismiss recommendation :pod', ['pod' => $pod->title]) }}">
            <i class="ti ti-eye-off" aria-hidden="true"></i> {{ __('Dismiss') }}
        </button>
    </div>

    <button type="button" class="pr-why-toggle" data-pr-why-toggle aria-expanded="false" aria-controls="pr-why-{{ $id }}">
        <span>{{ __('Why this matches') }}</span><i class="ti ti-chevron-down" aria-hidden="true"></i>
    </button>
    <div class="pr-why" id="pr-why-{{ $id }}" hidden>
        <ol class="pr-why__list">
            @foreach ($p['criteria'] as $criterion)
                @continue(!$criterion['active'])
                @php
                    $status = $criterion['passed'] ? 'met' : ((float) $criterion['awarded_points'] > 0 ? 'partial' : 'missed');
                @endphp
                <li class="pr-why__item pr-why__item--{{ $status }}">
                    <div class="pr-why__row">
                        <span class="pr-why__label">{{ $criterion['label'] }}</span>
                        <span class="pr-why__badges">
                            @if ($criterion['mandatory'])
                                <span class="pr-badge pr-badge--mandatory">{{ __('Required') }}</span>
                            @endif
                            <span class="pr-badge pr-badge--{{ $status }}">{{ $status === 'met' ? __('Met') : ($status === 'partial' ? __('Partly met') : __('Not met')) }}</span>
                            @if ($criterion['weighted'])
                                <span class="pr-why__points" dir="auto">{{ __(':awarded of :possible pts', ['awarded' => $num($criterion['awarded_points']), 'possible' => $criterion['possible_points']]) }}</span>
                            @endif
                        </span>
                    </div>
                    <p class="pr-why__text" dir="auto">{{ $criterion['explanation'] }}</p>
                </li>
            @endforeach
        </ol>
        <p class="pr-why__meta">
            {{ __(':algorithm · version :version · generated :time', ['algorithm' => $algorithmLabel, 'version' => $p['algorithm_version'], 'time' => $generatedAt->isoFormat('LLL')]) }}
        </p>
    </div>
</article>
