{{--
    Guided business onboarding / Company Profile (UNA-010). Rendered by CompanyOnboardingController@edit with:
    $workspace (company workspace, industry + workerRequirements.skills loaded), $breakdown
    (BusinessOnboardingCompletionService::breakdown), $step, $steps, $logoUrl, $industries, $jobTitles and
    $skills (with industries:id for prioritizing), $matchingModes, $approvalModes, $proficiencies,
    $workModes, $engagementTypes.
--}}
@extends('layouts.admin')

@php
    $complete = $breakdown['complete'];
    $pageTitle = $complete ? __('Company Profile') : __('Company Onboarding');
    $sections = $breakdown['sections'];
    $enumValue = fn ($value) => $value instanceof \BackedEnum ? $value->value : $value;

    // Steps and the completion sections they cover.
    $stepMeta = [
        'company' => ['icon' => 'building', 'label' => __('Company details'), 'sections' => ['company', 'industry']],
        'contact' => ['icon' => 'phone', 'label' => __('Contact and location'), 'sections' => ['contact']],
        'online' => ['icon' => 'world', 'label' => __('Online presence'), 'sections' => ['online']],
        'description' => ['icon' => 'file-text', 'label' => __('Company description'), 'sections' => ['description']],
        'requirements' => ['icon' => 'users', 'label' => __('Worker requirements'), 'sections' => ['requirements']],
        'matching' => ['icon' => 'affiliate', 'label' => __('Matching preferences'), 'sections' => ['matching']],
        'approvals' => ['icon' => 'checkbox', 'label' => __('Approval preferences'), 'sections' => ['approvals']],
        'review' => ['icon' => 'clipboard-check', 'label' => __('Review'), 'sections' => []],
    ];
    $stepDone = fn (string $key) => $key === 'review'
        ? $complete
        : collect($stepMeta[$key]['sections'])->every(fn ($s) => $sections[$s]['complete']);

    // Requirement rows: old input after a failed submit, otherwise the saved requirements.
    $submitted = old('requirements_present') !== null;
    $requirementRows = $submitted ? old('requirements', []) : $workspace->workerRequirements->map(fn ($r) => [
        'id' => $r->id,
        'job_title_id' => $r->job_title_id,
        'headcount' => $r->headcount,
        'skill_ids' => $r->skills->pluck('id')->all(),
        'minimum_years_experience' => $r->minimum_years_experience,
        'minimum_skill_proficiency' => $enumValue($r->minimum_skill_proficiency),
        'preferred_work_mode' => $enumValue($r->preferred_work_mode),
        'preferred_engagement_type' => $enumValue($r->preferred_engagement_type),
        'location' => $r->location,
        'description' => $r->description,
        'is_active' => $r->is_active,
    ])->all();
    $nextIndex = count($requirementRows) ? max(array_map('intval', array_keys($requirementRows))) + 1 : 0;
    $currentMatching = old('matching_mode', $enumValue($workspace->matching_mode));
@endphp

@section('page-title')
    {{ $pageTitle }}
@endsection

@section('links')
    <li class="breadcrumb-item"><a href="{{ route('home') }}">{{ __('Home') }}</a></li>
    <li class="breadcrumb-item">{{ $pageTitle }}</li>
@endsection

@section('action-button')
    <a href="{{ route('landing.company.show', $workspace->id) }}" class="btn btn-sm btn-primary" target="_blank" rel="noopener"
        title="{{ __('View public company page') }}">
        <i class="ti ti-eye"></i> <span class="d-none d-sm-inline">{{ __('View public company page') }}</span>
    </a>
@endsection

@push('css-page')
    <link rel="stylesheet" href="{{ asset('assets/custom/css/company-onboarding.css') }}">
@endpush

@section('content')
    <div class="row co-onboarding">
        {{-- ============================================================ progress + steps --}}
        <div class="col-xl-4 col-lg-5">
            <div class="co-aside">
                <div class="card co-hero">
                    <div class="card-body">
                        <div class="d-flex align-items-center gap-3 mb-3">
                            <div class="co-ring" style="--co-pct: {{ (int) $breakdown['percentage'] }};" role="img"
                                aria-label="{{ __('Company profile :percentage% complete', ['percentage' => $breakdown['percentage']]) }}">
                                <span class="co-ring__value">{{ $breakdown['percentage'] }}<small>%</small></span>
                            </div>
                            <div class="min-w-0">
                                <h5 class="mb-1 text-break">{{ $workspace->company ?: __('Your company') }}</h5>
                                @if ($complete)
                                    <span class="badge co-badge co-badge--done"><i class="ti ti-circle-check"></i> {{ __('Ready to publish PODs') }}</span>
                                @else
                                    <span class="badge co-badge co-badge--todo"><i class="ti ti-lock"></i> {{ __('POD publishing locked') }}</span>
                                @endif
                            </div>
                        </div>
                        <div class="progress co-progress" role="progressbar" aria-label="{{ __('Company profile completion') }}"
                            aria-valuenow="{{ $breakdown['percentage'] }}" aria-valuemin="0" aria-valuemax="100">
                            <div class="progress-bar" style="width: {{ (int) $breakdown['percentage'] }}%"></div>
                        </div>
                        <p class="small text-muted mt-3 mb-0">
                            @if ($complete)
                                {{ __('Your company profile is complete. Keep it up to date; removing required information blocks new PODs again.') }}
                            @else
                                {{ __('A :required% complete company profile is required to publish a POD. Save a draft at any time and come back later.', ['required' => $breakdown['required']]) }}
                            @endif
                        </p>
                    </div>
                </div>

                <div class="card co-steps">
                    <div class="card-header"><h5 class="mb-0">{{ __('Steps') }}</h5></div>
                    <nav aria-label="{{ __('Onboarding steps') }}">
                        <ol class="list-unstyled mb-0" role="tablist">
                            @foreach ($steps as $i => $key)
                                <li>
                                    <a href="{{ route('company.onboarding.edit', ['step' => $key]) }}" class="co-step-link {{ $stepDone($key) ? 'is-complete' : '' }} {{ $step === $key ? 'is-active' : '' }}"
                                        data-co-step-link="{{ $key }}" role="tab" aria-controls="co-step-{{ $key }}" aria-selected="{{ $step === $key ? 'true' : 'false' }}">
                                        <span class="co-step-link__index" aria-hidden="true">
                                            @if ($stepDone($key)) <i class="ti ti-check"></i> @else {{ $i + 1 }} @endif
                                        </span>
                                        <span class="flex-grow-1">{{ $stepMeta[$key]['label'] }}</span>
                                        <span class="visually-hidden">{{ $stepDone($key) ? __('Complete') : __('Incomplete') }}</span>
                                    </a>
                                </li>
                            @endforeach
                        </ol>
                    </nav>
                </div>

                @unless ($complete)
                    <div class="card co-missing">
                        <div class="card-header"><h5 class="mb-0">{{ __('Still missing') }}</h5></div>
                        <ul class="list-group list-group-flush">
                            @foreach ($sections as $key => $section)
                                @foreach ($section['missing'] as $missing)
                                    <li class="list-group-item small"><i class="ti ti-circle-dashed text-warning" aria-hidden="true"></i> {{ $missing }}
                                        <span class="text-muted">· {{ __(':weight%', ['weight' => $section['weight']]) }}</span></li>
                                @endforeach
                            @endforeach
                        </ul>
                    </div>
                @endunless
            </div>
        </div>

        {{-- ============================================================ form --}}
        <div class="col-xl-8 col-lg-7">
            <form id="company-onboarding-form" data-co-form method="POST" action="{{ route('company.onboarding.update') }}" enctype="multipart/form-data" novalidate
                data-initial-step="{{ $step }}"
                data-i18n="{{ json_encode([
                    'suggested' => __('Suggested for your industry'),
                    'general' => __('All industries'),
                    'other' => __('Other industries'),
                ]) }}">
                @csrf
                <input type="hidden" name="step" value="{{ $step }}" data-co-step-input>
                <input type="hidden" name="requirements_present" value="1">

                @if ($errors->any())
                    <div class="alert alert-danger" role="alert">
                        <i class="ti ti-alert-circle"></i> {{ __('Some fields need your attention. Your changes have not been saved yet.') }}
                    </div>
                @endif

                {{-- 1. Company details --}}
                <section class="card co-panel" id="co-step-company" data-co-step="company" role="tabpanel">
                    @include('company.onboarding.panel_header', ['key' => 'company'])
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-7">
                                <label class="form-label" for="company">{{ __('Company name') }} <span class="text-danger">*</span></label>
                                <input type="text" class="form-control @error('company') is-invalid @enderror" id="company" name="company" maxlength="255"
                                    value="{{ old('company', $workspace->company) }}">
                                @error('company') <span class="invalid-feedback" role="alert">{{ $message }}</span> @enderror
                            </div>
                            <div class="col-md-5">
                                <label class="form-label" for="industry_id">{{ __('Industry') }} <span class="text-danger">*</span></label>
                                <select class="form-select @error('industry_id') is-invalid @enderror" id="industry_id" name="industry_id">
                                    <option value="">{{ __('Select your industry') }}</option>
                                    @foreach ($industries as $industry)
                                        <option value="{{ $industry->id }}" @selected((string) old('industry_id', $workspace->industry_id) === (string) $industry->id)>{{ $industry->name }}</option>
                                    @endforeach
                                </select>
                                @error('industry_id') <span class="invalid-feedback" role="alert">{{ $message }}</span> @enderror
                            </div>
                            <div class="col-12">
                                <label class="form-label" for="logo">{{ __('Company logo') }} <span class="text-danger">*</span></label>
                                <div class="d-flex align-items-center gap-3">
                                    <img @if ($logoUrl) src="{{ $logoUrl }}" @endif alt="{{ __('Company logo preview') }}" class="co-logo-preview {{ $logoUrl ? '' : 'd-none' }}" data-co-logo-preview>
                                    <div class="flex-grow-1">
                                        <input type="file" class="form-control @error('logo') is-invalid @enderror" id="logo" name="logo" accept="image/png,image/jpeg,image/gif,image/webp" data-co-logo-input>
                                        @error('logo') <span class="invalid-feedback" role="alert">{{ $message }}</span> @enderror
                                        <p class="form-text mb-0">{{ __('PNG, JPG, GIF or WebP, up to 2 MB.') }}</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    @include('company.onboarding.panel_footer', ['key' => 'company'])
                </section>

                {{-- 2. Contact and location --}}
                <section class="card co-panel" id="co-step-contact" data-co-step="contact" role="tabpanel">
                    @include('company.onboarding.panel_header', ['key' => 'contact'])
                    <div class="card-body">
                        <div class="row g-3">
                            @foreach ([
                                'contact_name' => [__('Contact name'), 'text', true, 255],
                                'contact_position' => [__('Contact position'), 'text', false, 255],
                                'contact_email' => [__('Contact email'), 'email', true, 255],
                                'telephone' => [__('Telephone'), 'tel', true, 30],
                                'address' => [__('Street address'), 'text', false, 255],
                                'city' => [__('City'), 'text', true, 255],
                                'state' => [__('State / region'), 'text', false, 255],
                                'zipcode' => [__('Postal code'), 'text', false, 20],
                                'country' => [__('Country'), 'text', true, 255],
                            ] as $field => [$label, $type, $required, $max])
                                <div class="col-md-6">
                                    <label class="form-label" for="{{ $field }}">{{ $label }} @if ($required)<span class="text-danger">*</span>@endif</label>
                                    <input type="{{ $type }}" class="form-control @error($field) is-invalid @enderror" id="{{ $field }}" name="{{ $field }}"
                                        value="{{ old($field, $workspace->{$field}) }}" maxlength="{{ $max }}">
                                    @error($field) <span class="invalid-feedback" role="alert">{{ $message }}</span> @enderror
                                </div>
                            @endforeach
                        </div>
                    </div>
                    @include('company.onboarding.panel_footer', ['key' => 'contact'])
                </section>

                {{-- 3. Online presence --}}
                <section class="card co-panel" id="co-step-online" data-co-step="online" role="tabpanel">
                    @include('company.onboarding.panel_header', ['key' => 'online'])
                    <div class="card-body">
                        <div class="row g-3">
                            @foreach (['website' => ['world', __('Website'), true], 'linkedin' => ['brand-linkedin', __('LinkedIn'), false], 'facebook' => ['brand-facebook', __('Facebook'), false]] as $field => [$icon, $label, $required])
                                <div class="col-md-{{ $field === 'website' ? '12' : '6' }}">
                                    <label class="form-label" for="{{ $field }}">{{ $label }} @if ($required)<span class="text-danger">*</span>@endif</label>
                                    <div class="input-group has-validation">
                                        <span class="input-group-text"><i class="ti ti-{{ $icon }}" aria-hidden="true"></i></span>
                                        <input type="url" class="form-control @error($field) is-invalid @enderror" id="{{ $field }}" name="{{ $field }}"
                                            value="{{ old($field, $workspace->{$field}) }}" maxlength="500" placeholder="https://">
                                        @error($field) <span class="invalid-feedback" role="alert">{{ $message }}</span> @enderror
                                    </div>
                                </div>
                            @endforeach
                            <p class="form-text col-12 mb-0">{{ __('Add at least one professional link: LinkedIn or Facebook.') }}</p>
                        </div>
                    </div>
                    @include('company.onboarding.panel_footer', ['key' => 'online'])
                </section>

                {{-- 4. Description --}}
                <section class="card co-panel" id="co-step-description" data-co-step="description" role="tabpanel">
                    @include('company.onboarding.panel_header', ['key' => 'description'])
                    <div class="card-body">
                        <label class="form-label" for="description">{{ __('About your company') }} <span class="text-danger">*</span></label>
                        <textarea class="form-control @error('description') is-invalid @enderror" id="description" name="description" rows="7" maxlength="10000"
                            data-co-counter="{{ config('company_onboarding.minimum_description_length', 100) }}"
                            placeholder="{{ __('What your company does, the projects you run and the people you want to work with.') }}">{{ old('description', $workspace->description) }}</textarea>
                        @error('description') <span class="invalid-feedback" role="alert">{{ $message }}</span> @enderror
                        <p class="form-text mb-0" data-co-counter-text
                            data-template="{{ __(':count of :min characters minimum', ['count' => '__COUNT__', 'min' => config('company_onboarding.minimum_description_length', 100)]) }}">
                            {{ __(':count of :min characters minimum', ['count' => mb_strlen((string) old('description', $workspace->description)), 'min' => config('company_onboarding.minimum_description_length', 100)]) }}
                        </p>
                    </div>
                    @include('company.onboarding.panel_footer', ['key' => 'description'])
                </section>

                {{-- 5. Worker requirements --}}
                <section class="card co-panel" id="co-step-requirements" data-co-step="requirements" role="tabpanel">
                    @include('company.onboarding.panel_header', ['key' => 'requirements'])
                    <div class="card-body">
                        @error('requirements') <div class="alert alert-danger py-2 small">{{ $message }}</div> @enderror
                        <div class="co-requirements" data-co-rows data-next-index="{{ $nextIndex }}">
                            @foreach ($requirementRows as $index => $row)
                                @include('company.onboarding.requirement_row', ['index' => $index, 'row' => (array) $row])
                            @endforeach
                        </div>
                        <p class="small text-muted {{ count($requirementRows) ? 'd-none' : '' }}" data-co-rows-empty>{{ __('No worker requirements yet. Add the roles you want to staff.') }}</p>
                        <button type="button" class="btn btn-sm btn-outline-primary mt-2" data-co-add><i class="ti ti-plus"></i> {{ __('Add worker requirement') }}</button>
                    </div>
                    @include('company.onboarding.panel_footer', ['key' => 'requirements'])
                </section>

                {{-- 6. Matching --}}
                <section class="card co-panel" id="co-step-matching" data-co-step="matching" role="tabpanel">
                    @include('company.onboarding.panel_header', ['key' => 'matching'])
                    <div class="card-body">
                        <fieldset>
                            <legend class="form-label fs-6">{{ __('Matching mode') }} <span class="text-danger">*</span></legend>
                            <div class="row g-3">
                                @foreach ($matchingModes as $mode)
                                    <div class="col-md-4">
                                        <input type="radio" class="btn-check" name="matching_mode" id="matching-{{ $mode->value }}" value="{{ $mode->value }}" autocomplete="off" @checked($currentMatching === $mode->value)>
                                        <label class="co-choice h-100 @error('matching_mode') is-invalid @enderror" for="matching-{{ $mode->value }}">
                                            <span class="fw-semibold d-block">{{ $mode->label() }}</span>
                                            <span class="small text-muted">{{ $mode->description() }}</span>
                                        </label>
                                    </div>
                                @endforeach
                            </div>
                            @error('matching_mode') <span class="invalid-feedback d-block" role="alert">{{ $message }}</span> @enderror
                        </fieldset>
                    </div>
                    @include('company.onboarding.panel_footer', ['key' => 'matching'])
                </section>

                {{-- 7. Approvals --}}
                <section class="card co-panel" id="co-step-approvals" data-co-step="approvals" role="tabpanel">
                    @include('company.onboarding.panel_header', ['key' => 'approvals'])
                    <div class="card-body">
                        <p class="small text-muted">{{ __('These are your company defaults. Every membership change still follows the POD rules, including the worker profile requirement.') }}</p>
                        <div class="row g-3">
                            @foreach (['default_pod_application_approval' => __('POD applications into the Outer Circle'), 'default_inner_circle_approval' => __('Promotion to the Inner Circle')] as $field => $label)
                                <div class="col-md-6">
                                    <label class="form-label" for="{{ $field }}">{{ $label }} <span class="text-danger">*</span></label>
                                    <select class="form-select @error($field) is-invalid @enderror" id="{{ $field }}" name="{{ $field }}">
                                        <option value="">{{ __('Select') }}</option>
                                        @foreach ($approvalModes as $value => $optionLabel)
                                            <option value="{{ $value }}" @selected(old($field, $enumValue($workspace->{$field})) === $value)>{{ $optionLabel }}</option>
                                        @endforeach
                                    </select>
                                    @error($field) <span class="invalid-feedback" role="alert">{{ $message }}</span> @enderror
                                </div>
                            @endforeach
                        </div>
                    </div>
                    @include('company.onboarding.panel_footer', ['key' => 'approvals'])
                </section>

                {{-- 8. Review --}}
                <section class="card co-panel" id="co-step-review" data-co-step="review" role="tabpanel">
                    @include('company.onboarding.panel_header', ['key' => 'review'])
                    <div class="card-body">
                        <div class="alert {{ $complete ? 'alert-success' : 'alert-warning' }} mb-3" role="status">
                            @if ($complete)
                                <i class="ti ti-circle-check"></i> {{ __('Your company profile is complete. You can publish PODs.') }}
                            @else
                                <i class="ti ti-alert-triangle"></i> {{ __('Your company profile is :percentage% complete. :required% is required to publish a POD.', ['percentage' => $breakdown['percentage'], 'required' => $breakdown['required']]) }}
                            @endif
                        </div>
                        {{-- UNA-011: optional first POD. Creating one is not part of the company score. --}}
                        <div class="co-first-pod mb-3" data-first-pod="{{ $publishedPod ? 'published' : ($draftPod ? 'draft' : 'none') }}">
                            @if ($publishedPod)
                                <p class="fw-semibold mb-1"><i class="ti ti-world" aria-hidden="true"></i> {{ __('Your POD :title is published.', ['title' => $publishedPod->title]) }}</p>
                                <a href="{{ route('pods.index') }}" class="btn btn-sm btn-outline-primary">{{ __('Manage your PODs') }}</a>
                            @elseif ($draftPod)
                                <p class="fw-semibold mb-1"><i class="ti ti-pencil" aria-hidden="true"></i> {{ __('Your first POD is a draft (:percentage% configured).', ['percentage' => $draftPodPercentage]) }}</p>
                                <a href="{{ route('pods.setup.edit', $draftPod) }}" class="btn btn-sm btn-primary">{{ __('Resume POD setup') }}</a>
                            @else
                                <p class="fw-semibold mb-1"><i class="ti ti-rocket" aria-hidden="true"></i> {{ __('Create your first POD') }}</p>
                                <p class="small text-muted">{{ __('Optional: start a draft now. It stays private and can be published once your company profile and the POD setup are complete.') }}</p>
                                <a href="{{ route('pods.create') }}" class="btn btn-sm btn-primary">{{ __('Create your first POD') }}</a>
                            @endif
                        </div>
                        <ul class="list-group co-review">
                            @foreach ($sections as $key => $section)
                                <li class="list-group-item d-flex gap-3 align-items-start">
                                    <i class="ti {{ $section['complete'] ? 'ti-circle-check text-success' : 'ti-circle-dashed text-warning' }} fs-5" aria-hidden="true"></i>
                                    <div class="flex-grow-1">
                                        <span class="fw-semibold">{{ $section['label'] }}</span>
                                        <span class="small text-muted">· {{ __(':weight%', ['weight' => $section['weight']]) }}</span>
                                        @foreach ($section['missing'] as $missing)
                                            <span class="d-block small text-muted">{{ $missing }}</span>
                                        @endforeach
                                    </div>
                                    <span class="small {{ $section['complete'] ? 'text-success' : 'text-muted' }}">{{ $section['complete'] ? __('Complete') : __('Incomplete') }}</span>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                    @include('company.onboarding.panel_footer', ['key' => 'review'])
                </section>
            </form>
        </div>
    </div>

    <template id="co-requirement-template">@include('company.onboarding.requirement_row', ['index' => '__INDEX__', 'row' => []])</template>
@endsection

@push('scripts')
    <script src="{{ asset('assets/custom/js/company-onboarding.js') }}"></script>
@endpush
