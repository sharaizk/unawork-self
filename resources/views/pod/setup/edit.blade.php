{{--
    Guided POD setup (UNA-011). Rendered by PodSetupController@edit with: $pod (skills +
    workerRequirements.skills loaded), $breakdown (PodConfigurationService::breakdown), $company
    (company completeness), $canPublish, $step, $steps, $imageUrl, $industryId (company industry, for
    prioritizing selectors), $templates (company worker requirements), $jobTitles, $skills,
    $matchingModes, $proficiencies, $workModes, $engagementTypes.
    Reuses the guided-step components of company onboarding (company-onboarding.js/.css).
--}}
@extends('layouts.admin')

@php
    $sections = $breakdown['sections'];
    $isDraft = $pod->isDraft();
    $companyComplete = $company['percentage'] >= $company['required'];
    $enumValue = fn ($value) => $value instanceof \BackedEnum ? $value->value : $value;

    // Steps and the configuration sections they cover (names used by the shared panel partials).
    $stepMeta = [
        'basics' => ['icon' => 'file-text', 'label' => __('Basics'), 'sections' => ['title', 'description']],
        'skills' => ['icon' => 'bulb', 'label' => __('POD skills'), 'sections' => ['skills']],
        'requirements' => ['icon' => 'users', 'label' => __('Worker requirements'), 'sections' => ['requirements']],
        'matching' => ['icon' => 'affiliate', 'label' => __('Matching'), 'sections' => ['matching']],
        'review' => ['icon' => 'clipboard-check', 'label' => $isDraft ? __('Review and publish') : __('Review'), 'sections' => []],
    ];
    $stepDone = fn (string $key) => $key === 'review'
        ? $breakdown['complete']
        : collect($stepMeta[$key]['sections'])->every(fn ($s) => $sections[$s]['complete']);

    $submitted = old('requirements_present') !== null;
    $requirementRows = $submitted ? old('requirements', []) : $pod->workerRequirements->map(fn ($r) => [
        'id' => $r->id,
        'source_workspace_requirement_id' => $r->source_workspace_requirement_id,
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
    $selectedSkills = array_map('strval', $submitted || old('skills_present') !== null ? old('skills', []) : $pod->skills->pluck('id')->all());
    $currentMatching = old('matching_mode', $enumValue($pod->matching_mode));
    $pageTitle = $isDraft ? __('POD setup') : __('Edit POD');
@endphp

@section('page-title')
    {{ $pageTitle }}
@endsection

@section('links')
    <li class="breadcrumb-item"><a href="{{ route('home') }}">{{ __('Home') }}</a></li>
    <li class="breadcrumb-item"><a href="{{ route('pods.index') }}">{{ __('POD') }}</a></li>
    <li class="breadcrumb-item">{{ $pageTitle }}</li>
@endsection

@push('css-page')
    <link rel="stylesheet" href="{{ asset('assets/custom/css/company-onboarding.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/custom/css/pod-onboarding.css') }}">
@endpush

@section('content')
    <div class="row co-onboarding pod-onboarding">
        {{-- ============================================================ progress + steps --}}
        <div class="col-xl-4 col-lg-5">
            <div class="co-aside">
                <div class="card co-hero">
                    <div class="card-body">
                        <div class="d-flex align-items-center gap-3 mb-3">
                            <div class="co-ring" style="--co-pct: {{ (int) $breakdown['percentage'] }};" role="img"
                                aria-label="{{ __('POD :percentage% configured', ['percentage' => $breakdown['percentage']]) }}">
                                <span class="co-ring__value">{{ $breakdown['percentage'] }}<small>%</small></span>
                            </div>
                            <div class="min-w-0">
                                <h5 class="mb-1 text-break">{{ $pod->title ?: __('Untitled POD') }}</h5>
                                @if ($isDraft)
                                    <span class="badge co-badge co-badge--todo" data-pod-status="draft"><i class="ti ti-pencil"></i> {{ __('Draft') }}</span>
                                @else
                                    <span class="badge co-badge co-badge--done" data-pod-status="published"><i class="ti ti-world"></i> {{ __('Published') }}</span>
                                @endif
                            </div>
                        </div>
                        <div class="progress co-progress" role="progressbar" aria-label="{{ __('POD configuration') }}"
                            aria-valuenow="{{ $breakdown['percentage'] }}" aria-valuemin="0" aria-valuemax="100">
                            <div class="progress-bar" style="width: {{ (int) $breakdown['percentage'] }}%"></div>
                        </div>
                        <p class="small text-muted mt-3 mb-0">
                            @if ($isDraft)
                                {{ __('Drafts are private. Save at any time; publishing needs a :required% configured POD and a complete company profile.', ['required' => $breakdown['required']]) }}
                            @else
                                {{ __('This POD is published. Changes that would leave a completed section incomplete are not saved.') }}
                            @endif
                        </p>
                    </div>
                </div>

                <div class="card co-steps">
                    <div class="card-header"><h5 class="mb-0">{{ __('Steps') }}</h5></div>
                    <nav aria-label="{{ __('POD setup steps') }}">
                        <ol class="list-unstyled mb-0" role="tablist">
                            @foreach ($steps as $i => $key)
                                <li>
                                    <a href="{{ route('pods.setup.edit', ['pod' => $pod, 'step' => $key]) }}" class="co-step-link {{ $stepDone($key) ? 'is-complete' : '' }} {{ $step === $key ? 'is-active' : '' }}"
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

                @unless ($breakdown['complete'])
                    <div class="card co-missing">
                        <div class="card-header"><h5 class="mb-0">{{ __('Still missing') }}</h5></div>
                        <ul class="list-group list-group-flush">
                            @foreach ($sections as $section)
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
            <form id="pod-setup-form" data-co-form data-pod-setup method="POST" action="{{ route('pods.setup.update', $pod) }}" enctype="multipart/form-data" novalidate
                data-initial-step="{{ $step }}" data-industry-id="{{ $industryId }}"
                data-i18n="{{ json_encode([
                    'suggested' => __('Suggested for your industry'),
                    'general' => __('All industries'),
                    'other' => __('Other industries'),
                ]) }}">
                @csrf
                <input type="hidden" name="step" value="{{ $step }}" data-co-step-input>
                <input type="hidden" name="requirements_present" value="1">
                <input type="hidden" name="skills_present" value="1">

                @if ($errors->any())
                    <div class="alert alert-danger" role="alert">
                        <i class="ti ti-alert-circle"></i> {{ __('Some fields need your attention. Your changes have not been saved yet.') }}
                    </div>
                @endif

                {{-- 1. Basics --}}
                <section class="card co-panel" id="co-step-basics" data-co-step="basics" role="tabpanel">
                    @include('company.onboarding.panel_header', ['key' => 'basics'])
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label" for="title">{{ __('POD title') }} <span class="text-danger">*</span></label>
                            <input type="text" class="form-control @error('title') is-invalid @enderror" id="title" name="title" maxlength="255" value="{{ old('title', $pod->title) }}">
                            @error('title') <span class="invalid-feedback" role="alert">{{ $message }}</span> @enderror
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="description">{{ __('POD description') }} <span class="text-danger">*</span></label>
                            <textarea class="form-control @error('description') is-invalid @enderror" id="description" name="description" rows="6" maxlength="5000"
                                data-co-counter="{{ config('pod_onboarding.minimum_description_length', 50) }}"
                                placeholder="{{ __('What the POD works on, who it is for and how it collaborates.') }}">{{ old('description', $pod->description) }}</textarea>
                            @error('description') <span class="invalid-feedback" role="alert">{{ $message }}</span> @enderror
                            <p class="form-text mb-0" data-co-counter-text
                                data-template="{{ __(':count of :min characters minimum', ['count' => '__COUNT__', 'min' => config('pod_onboarding.minimum_description_length', 50)]) }}">
                                {{ __(':count of :min characters minimum', ['count' => mb_strlen((string) old('description', $pod->description)), 'min' => config('pod_onboarding.minimum_description_length', 50)]) }}
                            </p>
                        </div>
                        <div>
                            <label class="form-label" for="image">{{ __('Image (optional)') }}</label>
                            <div class="d-flex align-items-center gap-3">
                                <img @if ($imageUrl) src="{{ $imageUrl }}" @endif alt="{{ __('POD image preview') }}" class="co-logo-preview {{ $imageUrl ? '' : 'd-none' }}" data-co-logo-preview>
                                <div class="flex-grow-1">
                                    <input type="file" class="form-control @error('image') is-invalid @enderror" id="image" name="image" accept="image/png,image/jpeg,image/gif,image/webp" data-co-logo-input>
                                    @error('image') <span class="invalid-feedback" role="alert">{{ $message }}</span> @enderror
                                    <p class="form-text mb-0">{{ __('PNG, JPG, GIF or WebP, up to 2 MB.') }}</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    @include('company.onboarding.panel_footer', ['key' => 'basics'])
                </section>

                {{-- 2. Skills --}}
                <section class="card co-panel" id="co-step-skills" data-co-step="skills" role="tabpanel">
                    @include('company.onboarding.panel_header', ['key' => 'skills'])
                    <div class="card-body">
                        <label class="form-label" for="pod-skills">{{ __('POD skills') }} <span class="text-danger">*</span></label>
                        <select class="form-select js-co-skills js-co-industry-sorted @error('skills') is-invalid @enderror @error('skills.*') is-invalid @enderror"
                            id="pod-skills" name="skills[]" multiple size="8" data-placeholder="{{ __('Select skills') }}">
                            @foreach ($skills as $skill)
                                <option value="{{ $skill->id }}" data-industries="{{ $skill->industries->pluck('id')->implode(',') }}" @selected(in_array((string) $skill->id, $selectedSkills, true))>{{ $skill->name }}</option>
                            @endforeach
                        </select>
                        @error('skills') <span class="invalid-feedback d-block" role="alert">{{ $message }}</span> @enderror
                        @error('skills.*') <span class="invalid-feedback d-block" role="alert">{{ $message }}</span> @enderror
                        <p class="form-text mb-0">{{ __('These public tags describe what the POD does and help workers find it.') }}</p>
                    </div>
                    @include('company.onboarding.panel_footer', ['key' => 'skills'])
                </section>

                {{-- 3. Requirements --}}
                <section class="card co-panel" id="co-step-requirements" data-co-step="requirements" role="tabpanel">
                    @include('company.onboarding.panel_header', ['key' => 'requirements'])
                    <div class="card-body">
                        @if ($templates->isNotEmpty())
                            <div class="pod-templates mb-3">
                                <p class="fw-semibold mb-1">{{ __('Import from your company requirements') }}</p>
                                <p class="small text-muted">{{ __('Imported requirements are copied into this POD; editing them here never changes the company template.') }}</p>
                                <div class="d-flex flex-wrap gap-2">
                                    @foreach ($templates as $template)
                                        <button type="button" class="btn btn-sm btn-outline-secondary" data-pod-import
                                            data-template="{{ json_encode([
                                                'id' => $template->id,
                                                'job_title_id' => $template->job_title_id,
                                                'headcount' => $template->headcount,
                                                'skill_ids' => $template->skills->pluck('id')->all(),
                                                'minimum_years_experience' => $template->minimum_years_experience,
                                                'minimum_skill_proficiency' => $enumValue($template->minimum_skill_proficiency),
                                                'preferred_work_mode' => $enumValue($template->preferred_work_mode),
                                                'preferred_engagement_type' => $enumValue($template->preferred_engagement_type),
                                                'location' => $template->location,
                                                'description' => $template->description,
                                            ]) }}"
                                            aria-label="{{ __('Import :role', ['role' => $template->jobTitle?->title ?? __('requirement')]) }}">
                                            <i class="ti ti-download" aria-hidden="true"></i>
                                            {{ $template->jobTitle?->title ?? __('Untitled requirement') }}@if ($template->headcount) × {{ $template->headcount }}@endif
                                        </button>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                        @error('requirements') <div class="alert alert-danger py-2 small">{{ $message }}</div> @enderror
                        <div class="co-requirements" data-co-rows data-next-index="{{ $nextIndex }}">
                            @foreach ($requirementRows as $index => $row)
                                @include('company.onboarding.requirement_row', ['index' => $index, 'row' => (array) $row, 'withSource' => true])
                            @endforeach
                        </div>
                        <p class="small text-muted {{ count($requirementRows) ? 'd-none' : '' }}" data-co-rows-empty>{{ __('No worker requirements yet. Import one or add a POD-specific requirement.') }}</p>
                        <button type="button" class="btn btn-sm btn-outline-primary mt-2" data-co-add><i class="ti ti-plus"></i> {{ __('Add worker requirement') }}</button>
                    </div>
                    @include('company.onboarding.panel_footer', ['key' => 'requirements'])
                </section>

                {{-- 4. Matching --}}
                <section class="card co-panel" id="co-step-matching" data-co-step="matching" role="tabpanel">
                    @include('company.onboarding.panel_header', ['key' => 'matching'])
                    <div class="card-body">
                        <fieldset>
                            <legend class="form-label fs-6">{{ __('How should matches for this POD be handled?') }} <span class="text-danger">*</span></legend>
                            <div class="row g-3">
                                @foreach ($matchingModes as $mode)
                                    <div class="col-md-6">
                                        <input type="radio" class="btn-check" name="matching_mode" id="pod-matching-{{ $mode->value }}" value="{{ $mode->value }}" autocomplete="off" @checked($currentMatching === $mode->value)>
                                        <label class="co-choice h-100 @error('matching_mode') is-invalid @enderror" for="pod-matching-{{ $mode->value }}">
                                            <span class="fw-semibold d-block">{{ $mode->label() }}</span>
                                            <span class="small text-muted">{{ $mode->description() }}</span>
                                        </label>
                                    </div>
                                @endforeach
                            </div>
                            @error('matching_mode') <span class="invalid-feedback d-block" role="alert">{{ $message }}</span> @enderror
                        </fieldset>
                        <p class="small text-muted mt-3 mb-0">{{ __('Nobody joins automatically: every membership still follows the POD approval rules and the worker profile requirement.') }}</p>
                    </div>
                    @include('company.onboarding.panel_footer', ['key' => 'matching'])
                </section>

                {{-- 5. Review --}}
                <section class="card co-panel" id="co-step-review" data-co-step="review" role="tabpanel">
                    @include('company.onboarding.panel_header', ['key' => 'review'])
                    <div class="card-body">
                        <ul class="list-group co-review mb-3">
                            @foreach ($sections as $section)
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
                            <li class="list-group-item d-flex gap-3 align-items-start" data-pod-company-status>
                                <i class="ti {{ $companyComplete ? 'ti-circle-check text-success' : 'ti-circle-dashed text-warning' }} fs-5" aria-hidden="true"></i>
                                <div class="flex-grow-1">
                                    <span class="fw-semibold">{{ __('Company profile') }}</span>
                                    <span class="small text-muted">· {{ __(':percentage% complete', ['percentage' => $company['percentage']]) }}</span>
                                    @unless ($companyComplete)
                                        @foreach ($company['missing'] as $missing)
                                            <span class="d-block small text-muted">{{ $missing }}</span>
                                        @endforeach
                                        <a href="{{ route('company.onboarding.edit') }}" class="small">{{ __('Complete Company Profile') }}</a>
                                    @endunless
                                </div>
                            </li>
                        </ul>
                        @if ($isDraft)
                            <div class="alert {{ $canPublish ? 'alert-success' : 'alert-warning' }} mb-0" role="status">
                                @if ($canPublish)
                                    <i class="ti ti-circle-check"></i> {{ __('Everything is ready. Publishing makes the POD visible to workers.') }}
                                @else
                                    <i class="ti ti-alert-triangle"></i> {{ __('Publishing needs a :required% configured POD and a complete company profile.', ['required' => $breakdown['required']]) }}
                                @endif
                            </div>
                        @endif
                    </div>
                    <div class="card-footer co-panel__footer">
                        <button type="button" class="btn btn-light" data-co-prev>{{ __('Back') }}</button>
                        <span class="flex-grow-1"></span>
                        <button type="submit" class="btn btn-outline-primary" name="intent" value="draft" data-co-submit="review">
                            <i class="ti ti-device-floppy"></i> {{ $isDraft ? __('Save draft') : __('Save') }}
                        </button>
                        @if ($isDraft)
                            <button type="submit" class="btn btn-primary" form="pod-publish-form" @disabled(!$canPublish)
                                @unless ($canPublish) aria-describedby="pod-publish-hint" @endunless>
                                <i class="ti ti-rocket"></i> {{ __('Publish POD') }}
                            </button>
                        @endif
                    </div>
                    @if ($isDraft && !$canPublish)
                        <p class="visually-hidden" id="pod-publish-hint">{{ __('Publishing needs a :required% configured POD and a complete company profile.', ['required' => $breakdown['required']]) }}</p>
                    @endif
                </section>
            </form>
            @if ($isDraft)
                {{-- Separate form: publishing never saves unsaved edits. --}}
                <form id="pod-publish-form" method="POST" action="{{ route('pods.publish', $pod) }}" class="d-none" data-pod-publish
                    data-confirm-message="{{ __('Publish this POD? It becomes visible to workers.') }}">
                    @csrf
                </form>
            @endif
        </div>
    </div>

    <template id="co-requirement-template">@include('company.onboarding.requirement_row', ['index' => '__INDEX__', 'row' => [], 'withSource' => true])</template>
@endsection

@push('scripts')
    <script src="{{ asset('assets/custom/js/company-onboarding.js') }}"></script>
    <script src="{{ asset('assets/custom/js/pod-onboarding.js') }}"></script>
@endpush
