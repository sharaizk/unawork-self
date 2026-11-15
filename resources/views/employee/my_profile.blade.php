{{--
    Employee profile onboarding / editing (UNA-007). Rendered by MyProfileController@edit with:
    $user, $profile, $breakdown (ProfileCompletionService::breakdown), $industries, $jobTitles and $skills
    (each with industries:id loaded, for the industry filter in profile.js), $timezones,
    $proficiencies, $availabilityOptions, $workModes, $engagementTypes, $projectTypes.
--}}
@extends('layouts.admin')

@section('page-title')
    {{ __('My Profile') }}
@endsection

@section('links')
    <li class="breadcrumb-item"><a href="{{ route('home') }}">{{ __('Home') }}</a></li>
    <li class="breadcrumb-item">{{ __('My Profile') }}</li>
@endsection

@section('action-button')
    <a href="{{ route('employee.show', $user->id) }}" class="btn btn-sm btn-primary" target="_blank" rel="noopener"
        title="{{ __('View public profile') }}">
        <i class="ti ti-eye"></i> <span class="d-none d-sm-inline">{{ __('View public profile') }}</span>
    </a>
@endsection

@push('css-page')
    <link rel="stylesheet" href="{{ asset('assets/custom/css/profile.css') }}">
@endpush

@php
    // A submitted form (validation failure) is re-rendered from old input; otherwise from the database.
    $submitted = old('sync') !== null;
    $date = fn ($value) => $value ? $value->format('Y-m-d') : '';

    $skillRows = $submitted ? old('skills', []) : $user->skills->map(fn ($s) => [
        'skill_id' => $s->id, 'proficiency' => $s->pivot->proficiency,
    ])->all();
    $experienceRows = $submitted ? old('experiences', []) : $profile->experiences->map(fn ($e) => [
        'id' => $e->id, 'employer' => $e->employer, 'job_title' => $e->job_title, 'job_title_id' => $e->job_title_id,
        'engagement_type' => $e->engagement_type?->value, 'location' => $e->location,
        'start_date' => $date($e->start_date), 'end_date' => $date($e->end_date), 'is_current' => $e->is_current,
        'description' => $e->description,
    ])->all();
    $educationRows = $submitted ? old('educations', []) : $profile->educations->map(fn ($e) => $e->only(['id', 'institution', 'degree', 'field_of_study', 'start_year', 'end_year', 'description']))->all();
    $certificationRows = $submitted ? old('certifications', []) : $profile->certifications->map(fn ($c) => [
        'id' => $c->id, 'name' => $c->name, 'issuing_organization' => $c->issuing_organization,
        'issue_date' => $date($c->issue_date), 'expiration_date' => $date($c->expiration_date),
        'credential_id' => $c->credential_id, 'credential_url' => $c->credential_url,
    ])->all();
    $portfolioRows = $submitted ? old('portfolios', []) : $profile->portfolios->map(fn ($p) => $p->only(['id', 'title', 'url', 'description']))->all();
    $selectedRoles = array_map('strval', $submitted ? old('preferred_role_ids', []) : $profile->preferredRoles->pluck('job_title_id')->all());
    $selectedProjectTypes = $submitted ? old('project_types', []) : $profile->projectTypes->map(fn ($t) => $t->project_type->value)->all();

    $enumValue = fn ($value) => $value instanceof \BackedEnum ? $value->value : $value;
    $availability = old('availability_status', $enumValue($profile->availability_status));
    $boolValue = fn (string $field) => filter_var(old($field, $profile->{$field}), FILTER_VALIDATE_BOOLEAN);

    $percentage = $breakdown['percentage'];
    $required = $breakdown['required'];
    $completeCount = collect($breakdown['sections'])->where('complete', true)->count();
@endphp

@section('content')
    <div class="row una-profile">
        {{-- ============================================================ completion sidebar --}}
        <div class="col-xl-4 col-lg-5">
            <div class="una-profile-aside">
                <div class="card una-profile-hero">
                    <div class="card-body">
                        <div class="d-flex align-items-center gap-3 mb-4">
                            <div class="una-profile-ring" style="--una-pct: {{ (int) $percentage }};" role="img"
                                aria-label="{{ __('Profile :percentage% complete', ['percentage' => $percentage]) }}">
                                <span class="una-profile-ring__value">{{ $percentage }}<small>%</small></span>
                            </div>
                            <div class="min-w-0">
                                <h5 class="mb-1 text-break">{{ $user->name }}</h5>
                                <p class="text-muted small mb-2">{{ $profile->jobTitle->title ?? __('Add your current job title') }}</p>
                                @if ($breakdown['eligible'])
                                    <span class="badge una-profile-badge una-profile-badge--done"><i class="ti ti-award"></i> {{ __('Ready for jobs and PODs') }}</span>
                                @else
                                    <span class="badge una-profile-badge una-profile-badge--todo"><i class="ti ti-lock"></i> {{ __('Jobs and active PODs locked') }}</span>
                                @endif
                            </div>
                        </div>

                        <div class="d-flex justify-content-between small mb-1">
                            <span class="fw-semibold">{{ __('Profile completion') }}</span>
                            <span class="text-muted">{{ __(':done of :total sections', ['done' => $completeCount, 'total' => count($breakdown['sections'])]) }}</span>
                        </div>
                        <div class="progress una-profile-progress" role="progressbar" aria-label="{{ __('Profile completion') }}"
                            aria-valuenow="{{ $percentage }}" aria-valuemin="0" aria-valuemax="100">
                            <div class="progress-bar" style="width: {{ (int) $percentage }}%"></div>
                            <span class="una-profile-progress__marker" style="--una-required: {{ (int) $required }}%;" title="{{ __(':required% required', ['required' => $required]) }}"></span>
                        </div>
                        <p class="small text-muted mt-3 mb-0">
                            @if ($breakdown['eligible'])
                                {{ __('Your profile meets the :required% minimum. You can browse jobs and join active POD circles. Keep it up to date to get better matches.', ['required' => $required]) }}
                            @else
                                {{ __('A profile at least :required% complete is required to access jobs and to enter an active POD circle. You can save a draft at any time and come back later.', ['required' => $required]) }}
                            @endif
                        </p>
                    </div>
                </div>

                <div class="card una-profile-checklist">
                    <div class="card-header">
                        <h5 class="mb-0">{{ __('Checklist') }}</h5>
                    </div>
                    <ul class="list-group list-group-flush">
                        @foreach ($breakdown['sections'] as $key => $section)
                            <li class="list-group-item">
                                <a href="#profile-section-{{ $key }}" class="una-profile-checklist__item {{ $section['complete'] ? 'is-complete' : '' }}">
                                    <i class="ti {{ $section['complete'] ? 'ti-circle-check' : 'ti-circle-dashed' }}" aria-hidden="true"></i>
                                    <span class="flex-grow-1">
                                        <span class="d-block fw-semibold">{{ $section['label'] }}</span>
                                        @foreach ($section['missing'] as $missing)
                                            <span class="d-block small text-muted">{{ $missing }}</span>
                                        @endforeach
                                    </span>
                                    <span class="small text-muted">{{ $section['complete'] ? __('Done') : __(':weight%', ['weight' => $section['weight']]) }}</span>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </div>
            </div>
        </div>

        {{-- ============================================================ profile form --}}
        <div class="col-xl-8 col-lg-7">
            <form id="my-profile-form" method="POST" action="{{ route('my-profile.update') }}" enctype="multipart/form-data" novalidate
                data-i18n="{{ json_encode([
                    'resetTitle' => __('Change industry?'),
                    'resetText' => __('Your skills, current job title and preferred roles will be cleared so you can choose ones for the new industry. Nothing is saved until you click Save profile.'),
                    'resetConfirm' => __('Change and clear'),
                    'cancel' => __('Cancel'),
                    'remove' => __('Remove :role'),
                    'removeTitle' => __('Remove'),
                ]) }}">
                @csrf
                @foreach (\App\Services\ProfileService::SYNCABLE as $collection)
                    <input type="hidden" name="sync[]" value="{{ $collection }}">
                @endforeach

                @if ($errors->any())
                    <div class="alert alert-danger" role="alert">
                        <i class="ti ti-alert-circle"></i> {{ __('Some fields need your attention. Your changes have not been saved yet.') }}
                    </div>
                @endif

                {{-- 1. Professional summary --}}
                <div class="card una-profile-card" id="profile-section-summary">
                    @include('employee.my_profile.section_header', ['key' => 'summary', 'icon' => 'user', 'title' => __('Professional summary'), 'subtitle' => __('A short introduction companies see first.')])
                    <div class="card-body">
                        <label class="form-label" for="about">{{ __('About you') }}</label>
                        <textarea class="form-control @error('about') is-invalid @enderror" id="about" name="about" rows="5" maxlength="25500"
                            placeholder="{{ __('Describe your expertise, the work you enjoy and what you are looking for.') }}">{{ old('about', $profile->about) }}</textarea>
                        @error('about') <span class="invalid-feedback" role="alert">{{ $message }}</span> @enderror
                    </div>
                </div>

                {{-- 2. Current role --}}
                <div class="card una-profile-card" id="profile-section-headline">
                    @include('employee.my_profile.section_header', ['key' => 'headline', 'icon' => 'id', 'title' => __('Current role and experience'), 'subtitle' => __('Your industry, current job title and total years of experience.')])
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-8">
                                <label class="form-label" for="industry_id">{{ __('Industry') }}</label>
                                <select class="form-select @error('industry_id') is-invalid @enderror" id="industry_id" name="industry_id" aria-describedby="industry-help">
                                    <option value="">{{ __('Select your industry') }}</option>
                                    @foreach ($industries as $industry)
                                        <option value="{{ $industry->id }}" @selected((string) old('industry_id', $profile->industry_id) === (string) $industry->id)>{{ $industry->name }}</option>
                                    @endforeach
                                </select>
                                @error('industry_id') <span class="invalid-feedback" role="alert">{{ $message }}</span> @enderror
                                <p class="form-text mb-0" id="industry-help">{{ __('Choosing an industry narrows the skills and roles below to that industry plus general ones.') }}</p>
                            </div>
                            <div class="col-md-4 d-flex align-items-md-center">
                                <div class="form-check form-switch mt-md-3">
                                    <input class="form-check-input" type="checkbox" role="switch" id="profile-show-all-catalog">
                                    <label class="form-check-label small" for="profile-show-all-catalog">{{ __('Show skills and roles from all industries') }}</label>
                                </div>
                            </div>
                            <div class="col-md-8">
                                <label class="form-label" for="job_title_id">{{ __('Current job title') }}</label>
                                <select class="form-select js-industry-filtered @error('job_title_id') is-invalid @enderror" id="job_title_id" name="job_title_id">
                                    <option value="">{{ __('Select your job title') }}</option>
                                    @foreach ($jobTitles as $jobTitle)
                                        <option value="{{ $jobTitle->id }}" data-industries="{{ $jobTitle->industries->pluck('id')->implode(',') }}" @selected((string) old('job_title_id', $profile->job_title_id) === (string) $jobTitle->id)>{{ $jobTitle->title }}</option>
                                    @endforeach
                                </select>
                                @error('job_title_id') <span class="invalid-feedback" role="alert">{{ $message }}</span> @enderror
                            </div>
                            <div class="col-md-4">
                                <label class="form-label" for="years_experience">{{ __('Years of experience') }}</label>
                                <input type="number" class="form-control @error('years_experience') is-invalid @enderror" id="years_experience" name="years_experience"
                                    value="{{ old('years_experience', $profile->years_experience) }}" min="0" max="60" step="0.5" inputmode="decimal">
                                @error('years_experience') <span class="invalid-feedback" role="alert">{{ $message }}</span> @enderror
                            </div>
                        </div>
                    </div>
                </div>

                {{-- 3. Location --}}
                <div class="card una-profile-card" id="profile-section-location">
                    @include('employee.my_profile.section_header', ['key' => 'location', 'icon' => 'map-pin', 'title' => __('Location and timezone'), 'subtitle' => __('Country, city and timezone are used for matching. Street address and phone are optional and private.')])
                    <div class="card-body">
                        <div class="row g-3">
                            @foreach (['country' => __('Country'), 'city' => __('City'), 'state' => __('State / region'), 'zip_code' => __('Postal code'), 'address' => __('Street address'), 'phone_number' => __('Phone')] as $field => $label)
                                <div class="col-md-6">
                                    <label class="form-label" for="{{ $field }}">{{ $label }}</label>
                                    <input type="{{ $field === 'phone_number' ? 'tel' : 'text' }}" class="form-control @error($field) is-invalid @enderror" id="{{ $field }}" name="{{ $field }}"
                                        value="{{ old($field, $profile->{$field}) }}" maxlength="{{ in_array($field, ['zip_code', 'phone_number']) ? 20 : 255 }}">
                                    @error($field) <span class="invalid-feedback" role="alert">{{ $message }}</span> @enderror
                                </div>
                            @endforeach
                            <div class="col-md-6">
                                <label class="form-label" for="timezone">{{ __('Timezone') }}</label>
                                <select class="form-select @error('timezone') is-invalid @enderror" id="timezone" name="timezone">
                                    <option value="">{{ __('Select your timezone') }}</option>
                                    @foreach ($timezones as $timezone)
                                        <option value="{{ $timezone }}" @selected(old('timezone', $profile->timezone) === $timezone)>{{ str_replace('_', ' ', $timezone) }}</option>
                                    @endforeach
                                </select>
                                @error('timezone') <span class="invalid-feedback" role="alert">{{ $message }}</span> @enderror
                            </div>
                        </div>
                    </div>
                </div>

                {{-- 4. Availability --}}
                <div class="card una-profile-card" id="profile-section-availability">
                    @include('employee.my_profile.section_header', ['key' => 'availability', 'icon' => 'calendar-time', 'title' => __('Availability'), 'subtitle' => __('When you can start and how much time you can commit.')])
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-5">
                                <label class="form-label" for="availability_status">{{ __('Availability') }}</label>
                                <select class="form-select @error('availability_status') is-invalid @enderror" id="availability_status" name="availability_status">
                                    <option value="">{{ __('Select') }}</option>
                                    @foreach ($availabilityOptions as $value => $label)
                                        <option value="{{ $value }}" @selected($availability === $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                                @error('availability_status') <span class="invalid-feedback" role="alert">{{ $message }}</span> @enderror
                            </div>
                            <div class="col-md-4 {{ $availability === 'specific_date' ? '' : 'd-none' }}" id="available-from-group">
                                <label class="form-label" for="available_from">{{ __('Available from') }}</label>
                                <input type="date" class="form-control @error('available_from') is-invalid @enderror" id="available_from" name="available_from"
                                    value="{{ old('available_from', $profile->available_from?->format('Y-m-d')) }}">
                                @error('available_from') <span class="invalid-feedback" role="alert">{{ $message }}</span> @enderror
                            </div>
                            <div class="col-md-3">
                                <label class="form-label" for="hours_per_week">{{ __('Hours per week') }}</label>
                                <input type="number" class="form-control @error('hours_per_week') is-invalid @enderror" id="hours_per_week" name="hours_per_week"
                                    value="{{ old('hours_per_week', $profile->hours_per_week) }}" min="0" max="80" step="1" inputmode="numeric">
                                @error('hours_per_week') <span class="invalid-feedback" role="alert">{{ $message }}</span> @enderror
                            </div>
                            <div class="col-12 d-flex flex-wrap gap-4">
                                @foreach (['open_to_relocation' => __('Open to relocation'), 'willing_to_travel' => __('Willing to travel')] as $field => $label)
                                    <div class="form-check form-switch">
                                        <input type="hidden" name="{{ $field }}" value="0">
                                        <input class="form-check-input" type="checkbox" role="switch" id="{{ $field }}" name="{{ $field }}" value="1" @checked($boolValue($field))>
                                        <label class="form-check-label" for="{{ $field }}">{{ $label }}</label>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>

                {{-- 5. Skills --}}
                <div class="card una-profile-card" id="profile-section-skills">
                    @include('employee.my_profile.section_header', ['key' => 'skills', 'icon' => 'bulb', 'title' => __('Skills'), 'subtitle' => __('Add at least :count skills and rate your proficiency in each.', ['count' => config('profile.minimum_skills', 3)])])
                    <div class="card-body">
                        @error('skills') <div class="alert alert-danger py-2 small">{{ $message }}</div> @enderror
                        <div class="una-profile-rows" data-profile-rows="skill" data-next-index="{{ count($skillRows) ? max(array_map('intval', array_keys($skillRows))) + 1 : 0 }}">
                            @foreach ($skillRows as $index => $row)
                                @include('employee.my_profile.skill_row', ['index' => $index, 'row' => $row])
                            @endforeach
                        </div>
                        <div class="d-flex flex-wrap gap-3 align-items-center mt-3">
                            <button type="button" class="btn btn-sm btn-outline-primary" data-profile-add="skill"><i class="ti ti-plus"></i> {{ __('Add skill') }}</button>
                            {{-- Skills are an administrator-managed catalog; employees only select from it. --}}
                            <span class="small text-muted"><i class="ti ti-info-circle" aria-hidden="true"></i> {{ __('Can\'t find your skill? Please contact an administrator.') }}</span>
                        </div>
                    </div>
                </div>

                {{-- 6. Experience --}}
                <div class="card una-profile-card" id="profile-section-experience">
                    @include('employee.my_profile.section_header', ['key' => 'experience', 'icon' => 'briefcase', 'title' => __('Employment experience'), 'subtitle' => __('Your recent positions, newest first.')])
                    <div class="card-body">
                        <div class="una-profile-rows" data-profile-rows="experience" data-next-index="{{ count($experienceRows) ? max(array_map('intval', array_keys($experienceRows))) + 1 : 0 }}">
                            @foreach ($experienceRows as $index => $row)
                                @include('employee.my_profile.experience_row', ['index' => $index, 'row' => $row])
                            @endforeach
                        </div>
                        <button type="button" class="btn btn-sm btn-outline-primary mt-3" data-profile-add="experience"><i class="ti ti-plus"></i> {{ __('Add experience') }}</button>
                    </div>
                </div>

                {{-- 7. Education and certifications --}}
                <div class="card una-profile-card" id="profile-section-education">
                    @include('employee.my_profile.section_header', ['key' => 'education', 'icon' => 'school', 'title' => __('Education and certifications'), 'subtitle' => __('At least one education or certification record.')])
                    <div class="card-body">
                        <h6 class="una-profile-subtitle">{{ __('Education') }}</h6>
                        <div class="una-profile-rows" data-profile-rows="education" data-next-index="{{ count($educationRows) ? max(array_map('intval', array_keys($educationRows))) + 1 : 0 }}">
                            @foreach ($educationRows as $index => $row)
                                @include('employee.my_profile.education_row', ['index' => $index, 'row' => $row])
                            @endforeach
                        </div>
                        <button type="button" class="btn btn-sm btn-outline-primary mt-3" data-profile-add="education"><i class="ti ti-plus"></i> {{ __('Add education') }}</button>

                        <hr class="my-4">
                        <h6 class="una-profile-subtitle">{{ __('Certifications') }}</h6>
                        <div class="una-profile-rows" data-profile-rows="certification" data-next-index="{{ count($certificationRows) ? max(array_map('intval', array_keys($certificationRows))) + 1 : 0 }}">
                            @foreach ($certificationRows as $index => $row)
                                @include('employee.my_profile.certification_row', ['index' => $index, 'row' => $row])
                            @endforeach
                        </div>
                        <button type="button" class="btn btn-sm btn-outline-primary mt-3" data-profile-add="certification"><i class="ti ti-plus"></i> {{ __('Add certification') }}</button>
                    </div>
                </div>

                {{-- 8. Preferred roles --}}
                <div class="card una-profile-card" id="profile-section-preferred_roles">
                    @include('employee.my_profile.section_header', ['key' => 'preferred_roles', 'icon' => 'target', 'title' => __('Preferred roles'), 'subtitle' => __('The roles you would like to be matched with.')])
                    <div class="card-body">
                        {{--
                            Chip picker (profile.js): chips hold the selected roles as hidden preferred_role_ids[] inputs;
                            the list offers the remaining roles, narrowed by industry and the search box.
                        --}}
                        @php $rolesById = $jobTitles->keyBy('id'); @endphp
                        <div class="una-role-picker @error('preferred_role_ids') is-invalid @enderror @error('preferred_role_ids.*') is-invalid @enderror" data-role-picker>
                            <span class="form-label d-block" id="preferred-roles-label">{{ __('Your preferred roles') }}</span>
                            <div class="una-role-chips" data-role-chips role="list" aria-labelledby="preferred-roles-label">
                                @foreach ($selectedRoles as $roleId)
                                    @if ($rolesById->has((int) $roleId))
                                        <span class="una-role-chip" role="listitem" data-role-chip="{{ $roleId }}">
                                            <span>{{ $rolesById[(int) $roleId]->title }}</span>
                                            <input type="hidden" name="preferred_role_ids[]" value="{{ $roleId }}">
                                            <button type="button" class="una-role-chip__remove" data-role-remove
                                                aria-label="{{ __('Remove :role', ['role' => $rolesById[(int) $roleId]->title]) }}" title="{{ __('Remove') }}"><i class="ti ti-x" aria-hidden="true"></i></button>
                                        </span>
                                    @endif
                                @endforeach
                            </div>
                            <p class="small text-muted mb-0 {{ count($selectedRoles) ? 'd-none' : '' }}" data-role-chips-empty>{{ __('No preferred roles selected yet. Pick them from the list below.') }}</p>

                            <label class="form-label mt-3" for="preferred-role-search">{{ __('Available roles') }}</label>
                            <input type="search" class="form-control form-control-sm mb-2" id="preferred-role-search" data-role-search
                                placeholder="{{ __('Search roles') }}" autocomplete="off">
                            <div class="una-role-options" data-role-options role="list" aria-label="{{ __('Available roles') }}">
                                @foreach ($jobTitles as $jobTitle)
                                    <button type="button" class="una-role-option" role="listitem" data-role-option="{{ $jobTitle->id }}"
                                        data-industries="{{ $jobTitle->industries->pluck('id')->implode(',') }}"
                                        aria-label="{{ __('Add :role', ['role' => $jobTitle->title]) }}"
                                        @if (in_array((string) $jobTitle->id, $selectedRoles, true)) hidden @endif>
                                        <i class="ti ti-plus" aria-hidden="true"></i> <span>{{ $jobTitle->title }}</span>
                                    </button>
                                @endforeach
                            </div>
                            <p class="small text-muted mb-0 mt-2 d-none" data-role-options-empty>{{ __('No more roles match. Try another search or show roles from all industries.') }}</p>
                        </div>
                        @error('preferred_role_ids') <span class="invalid-feedback d-block" role="alert">{{ $message }}</span> @enderror
                        @error('preferred_role_ids.*') <span class="invalid-feedback d-block" role="alert">{{ $message }}</span> @enderror
                    </div>
                </div>

                {{-- 9. Work preferences --}}
                <div class="card una-profile-card" id="profile-section-work_preferences">
                    @include('employee.my_profile.section_header', ['key' => 'work_preferences', 'icon' => 'adjustments-horizontal', 'title' => __('Work preferences'), 'subtitle' => __('Project types, work mode and engagement you prefer.')])
                    <div class="card-body">
                        <span class="form-label d-block">{{ __('Preferred project types') }}</span>
                        <div class="una-profile-chips mb-1" role="group" aria-label="{{ __('Preferred project types') }}">
                            @foreach ($projectTypes as $value => $label)
                                <input type="checkbox" class="btn-check" id="project-type-{{ $value }}" name="project_types[]" value="{{ $value }}" autocomplete="off"
                                    @checked(in_array($value, $selectedProjectTypes, true))>
                                <label class="btn btn-sm una-profile-chip" for="project-type-{{ $value }}">{{ $label }}</label>
                            @endforeach
                        </div>
                        @error('project_types') <span class="invalid-feedback d-block" role="alert">{{ $message }}</span> @enderror
                        @error('project_types.*') <span class="invalid-feedback d-block" role="alert">{{ $message }}</span> @enderror
                        <div class="row g-3 mt-1">
                            <div class="col-md-6">
                                <label class="form-label" for="preferred_work_mode">{{ __('Work mode') }}</label>
                                <select class="form-select @error('preferred_work_mode') is-invalid @enderror" id="preferred_work_mode" name="preferred_work_mode">
                                    <option value="">{{ __('Select') }}</option>
                                    @foreach ($workModes as $value => $label)
                                        <option value="{{ $value }}" @selected(old('preferred_work_mode', $enumValue($profile->preferred_work_mode)) === $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                                @error('preferred_work_mode') <span class="invalid-feedback" role="alert">{{ $message }}</span> @enderror
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="preferred_engagement_type">{{ __('Engagement type') }}</label>
                                <select class="form-select @error('preferred_engagement_type') is-invalid @enderror" id="preferred_engagement_type" name="preferred_engagement_type">
                                    <option value="">{{ __('Select') }}</option>
                                    @foreach ($engagementTypes as $value => $label)
                                        <option value="{{ $value }}" @selected(old('preferred_engagement_type', $enumValue($profile->preferred_engagement_type)) === $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                                @error('preferred_engagement_type') <span class="invalid-feedback" role="alert">{{ $message }}</span> @enderror
                            </div>
                        </div>
                    </div>
                </div>

                {{-- 10. Links and portfolio --}}
                <div class="card una-profile-card" id="profile-section-links">
                    @include('employee.my_profile.section_header', ['key' => 'links', 'icon' => 'link', 'title' => __('Professional links and portfolio'), 'subtitle' => __('At least one of LinkedIn, GitHub, a personal website or a portfolio item.')])
                    <div class="card-body">
                        <div class="row g-3">
                            @foreach (['linkedin_url' => ['brand-linkedin', __('LinkedIn')], 'github_url' => ['brand-github', __('GitHub')], 'website' => ['world', __('Personal website')]] as $field => [$icon, $label])
                                <div class="col-md-4">
                                    <label class="form-label" for="{{ $field }}">{{ $label }}</label>
                                    <div class="input-group has-validation">
                                        <span class="input-group-text"><i class="ti ti-{{ $icon }}" aria-hidden="true"></i></span>
                                        <input type="url" class="form-control @error($field) is-invalid @enderror" id="{{ $field }}" name="{{ $field }}"
                                            value="{{ old($field, $profile->{$field}) }}" maxlength="500" placeholder="https://">
                                        @error($field) <span class="invalid-feedback" role="alert">{{ $message }}</span> @enderror
                                    </div>
                                </div>
                            @endforeach
                        </div>

                        <hr class="my-4">
                        <h6 class="una-profile-subtitle">{{ __('Portfolio') }}</h6>
                        <div class="una-profile-rows" data-profile-rows="portfolio" data-next-index="{{ count($portfolioRows) ? max(array_map('intval', array_keys($portfolioRows))) + 1 : 0 }}">
                            @foreach ($portfolioRows as $index => $row)
                                @include('employee.my_profile.portfolio_row', ['index' => $index, 'row' => $row])
                            @endforeach
                        </div>
                        <button type="button" class="btn btn-sm btn-outline-primary mt-3" data-profile-add="portfolio"><i class="ti ti-plus"></i> {{ __('Add portfolio item') }}</button>
                    </div>
                </div>

                {{-- CV (optional) --}}
                <div class="card una-profile-card" id="profile-section-cv">
                    @include('employee.my_profile.section_header', ['icon' => 'file-text', 'title' => __('CV'), 'subtitle' => __('Optional. PDF, Word, ODT or text, up to 10 MB.')])
                    <div class="card-body">
                        <label class="form-label" for="cv_file">{{ $profile->cv_file ? __('Replace CV') : __('Upload CV') }}</label>
                        <input type="file" class="form-control @error('cv_file') is-invalid @enderror" id="cv_file" name="cv_file" accept=".pdf,.doc,.docx,.odt,.txt">
                        @error('cv_file') <span class="invalid-feedback" role="alert">{{ $message }}</span> @enderror
                        @if ($profile->cv_file)
                            <p class="small mt-2 mb-0">
                                <i class="ti ti-file"></i> {{ __('Current CV:') }}
                                <a href="{{ asset('storage/' . $profile->cv_file) }}" target="_blank" rel="noopener">{{ basename($profile->cv_file) }}</a>
                            </p>
                        @endif
                    </div>
                </div>

                <div class="una-profile-savebar">
                    <span class="small text-muted d-none d-md-inline">{{ __('Incomplete sections are saved as a draft.') }}</span>
                    <button type="submit" class="btn btn-primary"><i class="ti ti-device-floppy"></i> {{ __('Save profile') }}</button>
                </div>
            </form>
        </div>
    </div>

    {{-- Row templates for profile.js; __INDEX__ is replaced with a unique index. --}}
    <template id="profile-template-skill">@include('employee.my_profile.skill_row', ['index' => '__INDEX__', 'row' => []])</template>
    <template id="profile-template-experience">@include('employee.my_profile.experience_row', ['index' => '__INDEX__', 'row' => []])</template>
    <template id="profile-template-education">@include('employee.my_profile.education_row', ['index' => '__INDEX__', 'row' => []])</template>
    <template id="profile-template-certification">@include('employee.my_profile.certification_row', ['index' => '__INDEX__', 'row' => []])</template>
    <template id="profile-template-portfolio">@include('employee.my_profile.portfolio_row', ['index' => '__INDEX__', 'row' => []])</template>
@endsection

@push('scripts')
    <script src="{{ asset('assets/custom/js/profile.js') }}"></script>
@endpush
