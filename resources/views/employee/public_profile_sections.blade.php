{{--
    Structured public profile sections (UNA-007). Param: $profile (relations eager loaded by
    EmployeeController@show). Empty sections are hidden; all user content is escaped, and links are
    rendered only for http(s) URLs.
--}}
@php
    $safeUrl = fn ($url) => is_string($url) && preg_match('#^https?://#i', $url) ? $url : null;
    $label = fn ($enum) => $enum instanceof \BackedEnum && method_exists($enum, 'label') ? $enum->label() : null;
    $month = fn ($date) => $date ? $date->translatedFormat('M Y') : null;

    $preferences = array_filter([
        __('Availability') => $label($profile->availability_status)
            . ($profile->available_from && $profile->availability_status?->requiresDate() ? ' (' . $profile->available_from->translatedFormat('j M Y') . ')' : ''),
        __('Hours per week') => $profile->hours_per_week,
        __('Work mode') => $label($profile->preferred_work_mode),
        __('Engagement type') => $label($profile->preferred_engagement_type),
        __('Open to relocation') => $profile->open_to_relocation ? __('Yes') : null,
        __('Willing to travel') => $profile->willing_to_travel ? __('Yes') : null,
    ], fn ($value) => $value !== null && $value !== '');

    $links = array_filter([
        __('LinkedIn') => $safeUrl($profile->linkedin_url),
        __('GitHub') => $safeUrl($profile->github_url),
        __('Website') => $safeUrl($profile->website),
    ]);
@endphp

<div class="emp-profile">
    @if ($preferences || $profile->projectTypes->isNotEmpty() || $profile->preferredRoles->isNotEmpty())
        <div class="emp-profile__section">
            <div class="title"><span>{{ __('Work preferences') }}</span></div>
            @if ($preferences)
                <dl class="emp-profile__facts">
                    @foreach ($preferences as $name => $value)
                        <div><dt>{{ $name }}</dt><dd>{{ $value }}</dd></div>
                    @endforeach
                </dl>
            @endif
            @if ($profile->preferredRoles->isNotEmpty())
                <p class="emp-profile__label">{{ __('Preferred roles') }}</p>
                <ul class="emp-profile__chips">
                    @foreach ($profile->preferredRoles as $role)
                        @if ($role->jobTitle)
                            <li>{{ $role->jobTitle->title }}</li>
                        @endif
                    @endforeach
                </ul>
            @endif
            @if ($profile->projectTypes->isNotEmpty())
                <p class="emp-profile__label">{{ __('Preferred project types') }}</p>
                <ul class="emp-profile__chips">
                    @foreach ($profile->projectTypes as $type)
                        <li>{{ $type->project_type->label() }}</li>
                    @endforeach
                </ul>
            @endif
        </div>
    @endif

    @if ($profile->experiences->isNotEmpty())
        <div class="emp-profile__section">
            <div class="title"><span>{{ __('Experience') }}</span></div>
            <ul class="emp-profile__timeline">
                @foreach ($profile->experiences as $experience)
                    <li>
                        <strong>{{ $experience->job_title }}</strong> · {{ $experience->employer }}
                        <span class="emp-profile__meta">
                            {{ implode(' · ', array_filter([
                                $label($experience->engagement_type),
                                $experience->location,
                                trim(($month($experience->start_date) ?? '') . ' – ' . ($experience->is_current ? __('Present') : ($month($experience->end_date) ?? '')), ' –'),
                            ])) }}
                        </span>
                        @if ($experience->description)
                            <p>{{ $experience->description }}</p>
                        @endif
                    </li>
                @endforeach
            </ul>
        </div>
    @endif

    @if ($profile->educations->isNotEmpty())
        <div class="emp-profile__section">
            <div class="title"><span>{{ __('Education') }}</span></div>
            <ul class="emp-profile__timeline">
                @foreach ($profile->educations as $education)
                    <li>
                        <strong>{{ $education->institution }}</strong>
                        <span class="emp-profile__meta">
                            {{ implode(' · ', array_filter([
                                $education->degree,
                                $education->field_of_study,
                                trim(($education->start_year ?? '') . ' – ' . ($education->end_year ?? ''), ' –'),
                            ])) }}
                        </span>
                        @if ($education->description)
                            <p>{{ $education->description }}</p>
                        @endif
                    </li>
                @endforeach
            </ul>
        </div>
    @endif

    @if ($profile->certifications->isNotEmpty())
        <div class="emp-profile__section">
            <div class="title"><span>{{ __('Certifications') }}</span></div>
            <ul class="emp-profile__timeline">
                @foreach ($profile->certifications as $certification)
                    <li>
                        @if ($safeUrl($certification->credential_url))
                            <a href="{{ $certification->credential_url }}" target="_blank" rel="noopener nofollow"><strong>{{ $certification->name }}</strong></a>
                        @else
                            <strong>{{ $certification->name }}</strong>
                        @endif
                        <span class="emp-profile__meta">
                            {{ implode(' · ', array_filter([
                                $certification->issuing_organization,
                                $certification->issue_date ? __('Issued :date', ['date' => $month($certification->issue_date)]) : null,
                                $certification->expiration_date ? __('Expires :date', ['date' => $month($certification->expiration_date)]) : null,
                            ])) }}
                        </span>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif

    @if ($profile->portfolios->isNotEmpty() || $links)
        <div class="emp-profile__section">
            <div class="title"><span>{{ __('Portfolio and links') }}</span></div>
            @if ($links)
                <ul class="emp-profile__chips">
                    @foreach ($links as $name => $url)
                        <li><a href="{{ $url }}" target="_blank" rel="noopener nofollow">{{ $name }}</a></li>
                    @endforeach
                </ul>
            @endif
            @if ($profile->portfolios->isNotEmpty())
                <ul class="emp-profile__timeline">
                    @foreach ($profile->portfolios as $portfolio)
                        <li>
                            @if ($safeUrl($portfolio->url))
                                <a href="{{ $portfolio->url }}" target="_blank" rel="noopener nofollow"><strong>{{ $portfolio->title }}</strong></a>
                            @else
                                <strong>{{ $portfolio->title }}</strong>
                            @endif
                            @if ($portfolio->description)
                                <p>{{ $portfolio->description }}</p>
                            @endif
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    @endif
</div>
