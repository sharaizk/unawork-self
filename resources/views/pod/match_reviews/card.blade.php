{{-- One match-review card. Params: $item (App\Services\Matching\PodMatchReviewItem), $pod. --}}
@php
    $review = $item->review;
    $employee = $item->employee;
    $p = $item->presented;
    $id = $review->id;
    $num = fn ($value) => (string) (float) $value;
    $stale = $item->staleReason();
    $match = $item->match;
    $initials = collect(preg_split('/\s+/u', trim((string) $employee->name)) ?: [])->filter()->take(2)->map(fn ($w) => mb_strtoupper(mb_substr($w, 0, 1)))->implode('');
    $algorithmLabel = $p && $p['algorithm'] === 'deterministic' ? __('Structured profile matching') : __('Profile matching');
@endphp
<article class="mr-card {{ $stale ? 'mr-card--stale' : '' }}" id="mr-card-{{ $id }}" data-mr-card data-qualified="{{ $stale ? '0' : '1' }}"
    data-name="{{ $employee->name }}" data-url="{{ route('pods.match-reviews.decision', [$pod, $review]) }}" aria-labelledby="mr-name-{{ $id }}">
    <div class="mr-card__head">
        <div class="mr-avatar">
            @if ($item->avatarUrl)
                <img src="{{ $item->avatarUrl }}" alt="" loading="lazy">
            @else
                <span aria-hidden="true">{{ $initials }}</span>
            @endif
        </div>
        <div class="mr-card__who">
            <h3 class="mr-card__name" id="mr-name-{{ $id }}" tabindex="-1">{{ $employee->name }}</h3>
            @if ($item->headline)<p class="mr-card__headline">{{ $item->headline }}</p>@endif
            @if ($item->profile)
                <ul class="mr-facts">
                    @foreach ($item->profile as $fact)<li dir="auto">{{ $fact }}</li>@endforeach
                </ul>
            @endif
        </div>
        <div class="mr-score">
            @if ($match)
                <span class="mr-score__value">{{ $num($match->score) }}<small>%</small></span>
                <span class="mr-score__label">{{ __('Match score') }}</span>
            @else
                <span class="mr-score__value">—</span>
                <span class="mr-score__label">{{ __('No current score') }}</span>
            @endif
        </div>
    </div>

    <p class="mr-status {{ $stale ? 'mr-status--stale' : 'mr-status--ok' }}" id="mr-status-{{ $id }}">
        @if ($stale === null)
            <i class="ti ti-circle-check" aria-hidden="true"></i> {{ __('Qualified: meets every required criterion and the :score% minimum', ['score' => $pod->minimumMatchThreshold()]) }}
        @elseif ($stale === \App\Services\Matching\PodMatchReviewItem::STALE_BELOW_THRESHOLD)
            <i class="ti ti-alert-triangle" aria-hidden="true"></i> {{ __('This match no longer meets the current :score% minimum. Approval is disabled.', ['score' => $pod->minimumMatchThreshold()]) }}
        @elseif ($stale === \App\Services\Matching\PodMatchReviewItem::STALE_NOT_ELIGIBLE)
            <i class="ti ti-alert-triangle" aria-hidden="true"></i> {{ __('This match no longer meets a required criterion or the POD is not open. Approval is disabled.') }}
        @else
            <i class="ti ti-alert-triangle" aria-hidden="true"></i> {{ __('Current match details are unavailable (the requirement changed or matching is being refreshed). Approval is disabled.') }}
        @endif
    </p>

    @if ($match?->requirement?->jobTitle)
        <p class="mr-card__role">{{ __('Best fit: :role', ['role' => $match->requirement->jobTitle->title]) }}</p>
    @endif

    @if ($item->highlights)
        <ul class="mr-chips" aria-label="{{ __('Top reasons') }}">
            @foreach ($item->highlights as $highlight)
                <li class="mr-chip"><i class="ti ti-check" aria-hidden="true"></i> {{ $highlight['highlight'] }}</li>
            @endforeach
        </ul>
    @endif

    <p class="mr-card__time">
        {{ __('Proposed :time', ['time' => $review->created_at->diffForHumans()]) }}
        @if ($match) · {{ __('Match updated :time', ['time' => $match->evaluated_at->diffForHumans()]) }}@endif
    </p>

    <div class="mr-actions">
        <button type="button" class="btn mr-btn-primary" data-mr-action="approve" @disabled($stale !== null)
            @if ($stale) aria-describedby="mr-status-{{ $id }}" @endif
            aria-label="{{ __('Approve :name', ['name' => $employee->name]) }}">
            <i class="ti ti-check" aria-hidden="true"></i> {{ __('Approve') }}
        </button>
        <button type="button" class="btn mr-btn-ghost" data-mr-action="reject" aria-label="{{ __('Reject :name', ['name' => $employee->name]) }}">{{ __('Reject') }}</button>
        <button type="button" class="btn mr-btn-danger" data-mr-action="exclude" aria-label="{{ __('Exclude :name from future matching', ['name' => $employee->name]) }}">
            <i class="ti ti-ban" aria-hidden="true"></i> {{ __('Exclude from matching') }}
        </button>
        <a href="{{ route('employee.show', $employee->id) }}" class="btn mr-btn-link" aria-label="{{ __('View profile of :name', ['name' => $employee->name]) }}">{{ __('View profile') }}</a>
    </div>

    @if ($p)
        <button type="button" class="mr-why-toggle" data-mr-why-toggle aria-expanded="false" aria-controls="mr-why-{{ $id }}">
            <span>{{ __('Why this match?') }}</span><i class="ti ti-chevron-down" aria-hidden="true"></i>
        </button>
        <div class="mr-why" id="mr-why-{{ $id }}" hidden>
            <ol class="mr-why__list">
                @foreach ($p['criteria'] as $criterion)
                    @continue(!$criterion['active'])
                    @php $status = $criterion['passed'] ? 'met' : ((float) $criterion['awarded_points'] > 0 ? 'partial' : 'missed'); @endphp
                    <li class="mr-why__item mr-why__item--{{ $status }}">
                        <div class="mr-why__row">
                            <span class="mr-why__label">{{ $criterion['label'] }}</span>
                            <span class="mr-why__badges">
                                @if ($criterion['mandatory'])<span class="mr-badge mr-badge--mandatory">{{ __('Required') }}</span>@endif
                                <span class="mr-badge mr-badge--{{ $status }}">{{ $status === 'met' ? __('Met') : ($status === 'partial' ? __('Partly met') : __('Not met')) }}</span>
                                @if ($criterion['weighted'])<span class="mr-why__points" dir="auto">{{ __(':awarded of :possible pts', ['awarded' => $num($criterion['awarded_points']), 'possible' => $criterion['possible_points']]) }}</span>@endif
                            </span>
                        </div>
                        <p class="mr-why__text" dir="auto">{{ $criterion['explanation'] }}</p>
                    </li>
                @endforeach
            </ol>
            <p class="mr-why__meta">
                {{ __(':algorithm · version :version · generated :time', ['algorithm' => $algorithmLabel, 'version' => $p['algorithm_version'], 'time' => $match->evaluated_at->isoFormat('LLL')]) }}
                · {{ __('Current minimum: :score%', ['score' => $p['threshold']]) }}
            </p>
        </div>
    @endif
</article>
