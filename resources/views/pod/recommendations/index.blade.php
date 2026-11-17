{{--
    Employee POD recommendations (UNA-017). Rendered by PodRecommendationController@index with:
    $employee (User), $recommendations (LengthAwarePaginator of App\Services\Matching\PodRecommendation),
    $summary (PodRecommendationService::summary()), $emptyReason ('preparing'|'none'|null).
    Manual discovery (pods.list) stays one click away everywhere on this page.
--}}
@extends('layouts.admin')

@section('page-title')
    {{ __('POD Recommendations') }}
@endsection

@section('links')
    <li class="breadcrumb-item"><a href="{{ route('home') }}">{{ __('Home') }}</a></li>
    <li class="breadcrumb-item">{{ __('POD Recommendations') }}</li>
@endsection

@push('css-page')
    <link rel="stylesheet" href="{{ asset('assets/custom/css/pod-recommendations.css') }}">
@endpush

@php
    // First name for the greeting, skipping honorifics such as "Dr." (tokens ending in a dot).
    $firstName = collect(preg_split('/\s+/u', trim((string) $employee->name)) ?: [])
        ->first(fn ($part) => $part !== '' && !str_ends_with($part, '.')) ?? '';
    $hasItems = $recommendations->count() > 0;
@endphp

@section('content')
    <div class="pod-recommendations" data-pr-root
        data-msg-applied="{{ __('Application sent. The POD will review your request.') }}"
        data-msg-dismissed="{{ __('Recommendation dismissed.') }}"
        data-msg-error="{{ __('Something went wrong. Please try again.') }}"
        data-msg-applying="{{ __('Applying…') }}"
        data-msg-dismissing="{{ __('Dismissing…') }}"
        data-label-sent="{{ __('Application sent') }}"
        data-title-success="{{ __('Done') }}"
        data-title-error="{{ __('Not completed') }}">
        {{-- Mirror the theme's dark stylesheet on the feature root before first paint (pod-recommendations.js repeats it). --}}
        <script>if (document.querySelector('link[href*="style-dark.css"]')) { document.currentScript.parentElement.classList.add('pr-theme-dark'); }</script>

        <p class="visually-hidden" role="status" aria-live="polite" data-pr-live></p>

        {{-- Personal heading --}}
        <section class="pr-hero" aria-labelledby="pr-heading">
            <div class="pr-hero__text">
                <p class="pr-eyebrow">{{ $firstName !== '' ? __('Hello, :name', ['name' => $firstName]) : __('Hello') }}</p>
                <h2 id="pr-heading" class="pr-hero__title">{{ __('PODs recommended for you') }}</h2>
                <p class="pr-hero__lead">{{ __('Based on your skills, experience, preferences, and availability.') }}</p>
            </div>
            <div class="pr-hero__actions">
                <a href="{{ route('pods.list') }}" class="btn pr-btn-browse" data-pr-browse>
                    <i class="ti ti-search" aria-hidden="true"></i> {{ __('Browse all PODs') }}
                </a>
                <a href="{{ route('my-profile.edit') }}" class="btn btn-link pr-link">{{ __('Update profile') }}</a>
            </div>
        </section>

        {{-- Summary --}}
        <section class="pr-metrics" aria-label="{{ __('Your matching summary') }}">
            <div class="pr-metric">
                <span class="pr-metric__icon" aria-hidden="true"><i class="ti ti-bulb"></i></span>
                <span class="pr-metric__value" data-pr-count>{{ $summary['count'] }}</span>
                <span class="pr-metric__label">{{ __('Recommended PODs') }}</span>
            </div>
            <div class="pr-metric">
                <span class="pr-metric__icon" aria-hidden="true"><i class="ti ti-target"></i></span>
                <span class="pr-metric__value">{{ $summary['top_score'] !== null ? __(':score%', ['score' => (string) (float) $summary['top_score']]) : '—' }}</span>
                <span class="pr-metric__label">{{ __('Best profile match') }}</span>
            </div>
            <div class="pr-metric">
                <span class="pr-metric__icon" aria-hidden="true"><i class="ti ti-star"></i></span>
                <span class="pr-metric__value">{{ $summary['strong'] }}</span>
                <span class="pr-metric__label">{{ __('Strong matches (:score%+)', ['score' => $summary['strong_score']]) }}</span>
            </div>
            <div class="pr-metric">
                <span class="pr-metric__icon" aria-hidden="true"><i class="ti ti-user-check"></i></span>
                <span class="pr-metric__value">{{ __(':score%', ['score' => $summary['profile_completion']]) }}</span>
                <span class="pr-metric__label">{{ __('Profile complete') }}</span>
            </div>
        </section>

        {{-- Recommendations --}}
        <section aria-labelledby="pr-heading">
            <div class="pr-list" data-pr-list>
                @foreach ($recommendations as $recommendation)
                    @include('pod.recommendations.card', ['recommendation' => $recommendation])
                @endforeach
            </div>

            <div class="pr-empty" data-pr-empty @if ($hasItems) hidden @endif>
                <span class="pr-empty__icon" aria-hidden="true"><i class="ti {{ $emptyReason === 'preparing' ? 'ti-loader' : 'ti-compass' }}"></i></span>
                <h3 class="pr-empty__title" tabindex="-1" data-pr-empty-title>
                    {{ $emptyReason === 'preparing' ? __('We’re preparing your POD recommendations.') : __('No strong POD matches yet.') }}
                </h3>
                <p class="pr-empty__text">
                    {{ $emptyReason === 'preparing'
                        ? __('Matching runs in the background after you update your profile. Check back shortly; in the meantime you can explore every POD yourself.')
                        : __('Keeping your skills, roles and availability up to date helps us find PODs that fit you. You can also explore every POD yourself.') }}
                </p>
                <div class="pr-empty__actions">
                    <a href="{{ route('pods.list') }}" class="btn pr-btn-primary">{{ __('Browse all PODs') }}</a>
                    <a href="{{ route('my-profile.edit') }}" class="btn pr-btn-ghost">{{ __('Update profile') }}</a>
                </div>
            </div>

            @if ($recommendations->hasPages())
                <nav class="pr-pagination" aria-label="{{ __('Recommendation pages') }}">
                    {{ $recommendations->links('pagination::bootstrap-5') }}
                </nav>
            @endif
        </section>
    </div>
@endsection

@push('scripts')
    <script src="{{ asset('assets/custom/js/pod-recommendations.js') }}"></script>
@endpush
