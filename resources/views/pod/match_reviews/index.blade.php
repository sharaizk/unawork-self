{{--
    Business match-review queue (UNA-018). Rendered by PodMatchReviewController@index with:
    $pod (Pod), $items (LengthAwarePaginator of App\Services\Matching\PodMatchReviewItem),
    $summary (PodMatchReviewService::summary()). Only matching proposals appear; manual join
    requests stay in the member management screen.
--}}
@extends('layouts.admin')

@section('page-title')
    {{ __('Match review queue') }}
@endsection

@section('links')
    <li class="breadcrumb-item"><a href="{{ route('home') }}">{{ __('Home') }}</a></li>
    <li class="breadcrumb-item"><a href="{{ route('pods.index') }}">{{ __('Pod') }}</a></li>
    <li class="breadcrumb-item">{{ __('Match review queue') }}</li>
@endsection

@push('css-page')
    <link rel="stylesheet" href="{{ asset('assets/custom/css/pod-match-reviews.css') }}">
@endpush

@php
    $num = fn ($value) => (string) (float) $value;
    $mode = $pod->matching_mode;
@endphp

@section('content')
    <div class="pod-match-reviews" data-mr-root
        data-msg-error="{{ __('Something went wrong. Please try again.') }}"
        data-msg-working="{{ __('Saving…') }}"
        data-title-success="{{ __('Done') }}"
        data-title-error="{{ __('Not completed') }}"
        data-title-info="{{ __('Already reviewed') }}">
        <script>if (document.querySelector('link[href*="style-dark.css"]')) { document.currentScript.parentElement.classList.add('mr-theme-dark'); }</script>

        <p class="visually-hidden" role="status" aria-live="polite" data-mr-live></p>

        <section class="mr-hero" aria-labelledby="mr-heading">
            <div>
                <p class="mr-eyebrow">{{ $pod->title }}</p>
                <h2 id="mr-heading" class="mr-hero__title">{{ __('Match review queue') }}</h2>
                <p class="mr-hero__lead">{{ __('These employees met this POD’s matching requirements and are waiting for your decision. Manual join requests are handled separately under Users.') }}</p>
                <ul class="mr-config" aria-label="{{ __('Matching configuration') }}">
                    <li><i class="ti ti-adjustments" aria-hidden="true"></i> {{ __('Mode: :mode', ['mode' => $mode?->label() ?? __('Not set')]) }}</li>
                    <li><i class="ti ti-target" aria-hidden="true"></i> {{ __('Minimum match score: :score%', ['score' => $pod->minimumMatchThreshold()]) }}</li>
                </ul>
            </div>
            <a href="{{ route('pods.users', $pod->id) }}" class="btn mr-btn-ghost"><i class="ti ti-arrow-back-up" aria-hidden="true"></i> {{ __('Back to POD management') }}</a>
        </section>

        <section class="mr-metrics" aria-label="{{ __('Review summary') }}">
            <div class="mr-metric"><span class="mr-metric__value" data-mr-count="pending">{{ $summary['pending'] }}</span><span class="mr-metric__label">{{ __('Pending reviews') }}</span></div>
            <div class="mr-metric"><span class="mr-metric__value">{{ $summary['top_score'] !== null ? __(':score%', ['score' => $num($summary['top_score'])]) : '—' }}</span><span class="mr-metric__label">{{ __('Highest current score') }}</span></div>
            <div class="mr-metric"><span class="mr-metric__value" data-mr-count="qualified">{{ $summary['qualified'] }}</span><span class="mr-metric__label">{{ __('Ready to approve') }}</span></div>
            <div class="mr-metric"><span class="mr-metric__value" data-mr-count="stale">{{ $summary['stale'] }}</span><span class="mr-metric__label">{{ __('No longer qualified') }}</span></div>
        </section>

        <section aria-labelledby="mr-heading">
            <div class="mr-list" data-mr-list>
                @foreach ($items as $item)
                    @include('pod.match_reviews.card', ['item' => $item, 'pod' => $pod])
                @endforeach
            </div>

            <div class="mr-empty" data-mr-empty @if ($items->count() > 0) hidden @endif>
                <span class="mr-empty__icon" aria-hidden="true"><i class="ti ti-circle-check"></i></span>
                <h3 class="mr-empty__title" tabindex="-1" data-mr-empty-title>{{ __('No matches waiting for review') }}</h3>
                <p class="mr-empty__text">{{ __('New proposals appear here when qualified employees are matched to this POD in pending-approval mode.') }}</p>
                <a href="{{ route('pods.users', $pod->id) }}" class="btn mr-btn-ghost">{{ __('Back to POD management') }}</a>
            </div>

            @if ($items->hasPages())
                <nav class="mr-pagination" aria-label="{{ __('Review pages') }}">{{ $items->links('pagination::bootstrap-5') }}</nav>
            @endif
        </section>

        {{-- One confirmation dialog, configured per action by pod-match-reviews.js. --}}
        <div class="modal fade" id="mr-dialog" tabindex="-1" aria-labelledby="mr-dialog-title" aria-describedby="mr-dialog-text" aria-hidden="true" data-mr-dialog
            data-title-approve="{{ __('Approve :name?') }}"
            data-text-approve="{{ __(':name will be placed in the Outer Circle of this POD and get Outer Circle access to its workspace. Approval never grants Inner Circle access.') }}"
            data-confirm-approve="{{ __('Approve and place in Outer Circle') }}"
            data-title-reject="{{ __('Reject :name?') }}"
            data-text-reject="{{ __(':name’s proposal will be rejected. They are not excluded from future matching.') }}"
            data-confirm-reject="{{ __('Reject match') }}"
            data-title-exclude="{{ __('Exclude :name from future matching?') }}"
            data-text-exclude="{{ __(':name’s proposal will be rejected and future automated matching for this POD will skip them. They can still find the POD manually, and their past match details are kept for audit.') }}"
            data-confirm-exclude="{{ __('Reject and exclude') }}">
            <div class="modal-dialog modal-dialog-centered">
                <form class="modal-content pod-match-reviews mr-dialog" data-mr-form novalidate>
                    <div class="modal-header">
                        <h5 class="modal-title" id="mr-dialog-title" data-mr-dialog-title></h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('Close') }}"></button>
                    </div>
                    <div class="modal-body">
                        <p id="mr-dialog-text" data-mr-dialog-text></p>
                        <div class="mb-0" data-mr-reason-group hidden>
                            <label class="form-label" for="mr-reason">{{ __('Reason') }} <span class="text-muted fw-normal">({{ __('optional') }})</span></label>
                            <textarea class="form-control" id="mr-reason" name="reason" rows="3" maxlength="1000" aria-describedby="mr-reason-help"></textarea>
                            <p class="form-text" id="mr-reason-help">{{ __('Optional. Stored in the membership history for your team.') }}</p>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn mr-btn-ghost" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
                        <button type="submit" class="btn" data-mr-confirm></button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script src="{{ asset('assets/custom/js/pod-match-reviews.js') }}"></script>
@endpush
