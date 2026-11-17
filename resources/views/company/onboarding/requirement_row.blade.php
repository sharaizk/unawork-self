{{-- One worker requirement. Params: $index, $row (array), $jobTitles, $skills, $proficiencies, $workModes, $engagementTypes;
     optional $withSource (POD setup, UNA-011: keeps the company template a row was copied from). --}}
@php
    $p = "requirements.$index";
    $selectedSkills = array_map('strval', (array) ($row['skill_ids'] ?? []));
    $active = !array_key_exists('is_active', $row) || filter_var($row['is_active'], FILTER_VALIDATE_BOOLEAN);
@endphp
<div class="co-requirement" data-co-row>
    <input type="hidden" name="requirements[{{ $index }}][id]" value="{{ $row['id'] ?? '' }}">
    @if (!empty($withSource))
        <input type="hidden" name="requirements[{{ $index }}][source_workspace_requirement_id]" value="{{ $row['source_workspace_requirement_id'] ?? '' }}" data-co-source>
    @endif
    <div class="d-flex justify-content-between align-items-center mb-2">
        <span class="co-requirement__title"><i class="ti ti-users" aria-hidden="true"></i> {{ __('Worker requirement') }}
            @if (!empty($withSource))
                <span class="badge co-badge co-badge--todo ms-1 {{ empty($row['source_workspace_requirement_id']) ? 'd-none' : '' }}" data-co-source-badge>{{ __('Copied from company template') }}</span>
            @endif
        </span>
        <div class="d-flex align-items-center gap-3">
            <div class="form-check form-switch mb-0">
                <input type="hidden" name="requirements[{{ $index }}][is_active]" value="0">
                <input class="form-check-input" type="checkbox" role="switch" value="1" id="req-{{ $index }}-active"
                    name="requirements[{{ $index }}][is_active]" @checked($active)>
                <label class="form-check-label small" for="req-{{ $index }}-active">{{ __('Active') }}</label>
            </div>
            <button type="button" class="btn btn-sm btn-light-danger" data-co-remove
                title="{{ __('Remove requirement') }}" aria-label="{{ __('Remove requirement') }}"><i class="ti ti-trash"></i></button>
        </div>
    </div>
    @error("$p.id") <div class="alert alert-danger py-2 small">{{ $message }}</div> @enderror
    <div class="row g-3">
        <div class="col-md-8">
            <label class="form-label" for="req-{{ $index }}-role">{{ __('Professional role') }} <span class="text-danger">*</span></label>
            <select class="form-select js-co-industry-sorted @error("$p.job_title_id") is-invalid @enderror" id="req-{{ $index }}-role" name="requirements[{{ $index }}][job_title_id]">
                <option value="">{{ __('Select a role') }}</option>
                @foreach ($jobTitles as $jobTitle)
                    <option value="{{ $jobTitle->id }}" data-industries="{{ $jobTitle->industries->pluck('id')->implode(',') }}" @selected((string) ($row['job_title_id'] ?? '') === (string) $jobTitle->id)>{{ $jobTitle->title }}</option>
                @endforeach
            </select>
            @error("$p.job_title_id") <span class="invalid-feedback" role="alert">{{ $message }}</span> @enderror
        </div>
        <div class="col-md-4">
            <label class="form-label" for="req-{{ $index }}-headcount">{{ __('Headcount') }} <span class="text-danger">*</span></label>
            <input type="number" class="form-control @error("$p.headcount") is-invalid @enderror" id="req-{{ $index }}-headcount"
                name="requirements[{{ $index }}][headcount]" value="{{ $row['headcount'] ?? '' }}" min="1" max="{{ config('company_onboarding.max_headcount', 500) }}" step="1" inputmode="numeric">
            @error("$p.headcount") <span class="invalid-feedback" role="alert">{{ $message }}</span> @enderror
        </div>
        <div class="col-12">
            <label class="form-label" for="req-{{ $index }}-skills">{{ __('Required skills') }} <span class="text-danger">*</span></label>
            <select class="form-select js-co-skills js-co-industry-sorted @error("$p.skill_ids") is-invalid @enderror @error("$p.skill_ids.*") is-invalid @enderror"
                id="req-{{ $index }}-skills" name="requirements[{{ $index }}][skill_ids][]" multiple size="5" data-placeholder="{{ __('Select skills') }}">
                @foreach ($skills as $skill)
                    <option value="{{ $skill->id }}" data-industries="{{ $skill->industries->pluck('id')->implode(',') }}" @selected(in_array((string) $skill->id, $selectedSkills, true))>{{ $skill->name }}</option>
                @endforeach
            </select>
            @error("$p.skill_ids") <span class="invalid-feedback d-block" role="alert">{{ $message }}</span> @enderror
            @error("$p.skill_ids.*") <span class="invalid-feedback d-block" role="alert">{{ $message }}</span> @enderror
        </div>
        <div class="col-md-6">
            <label class="form-label" for="req-{{ $index }}-mode">{{ __('Work mode') }} <span class="text-danger">*</span></label>
            <select class="form-select @error("$p.preferred_work_mode") is-invalid @enderror" id="req-{{ $index }}-mode" name="requirements[{{ $index }}][preferred_work_mode]">
                <option value="">{{ __('Select') }}</option>
                @foreach ($workModes as $value => $label)
                    <option value="{{ $value }}" @selected(($row['preferred_work_mode'] ?? '') === $value)>{{ $label }}</option>
                @endforeach
            </select>
            @error("$p.preferred_work_mode") <span class="invalid-feedback" role="alert">{{ $message }}</span> @enderror
        </div>
        <div class="col-md-6">
            <label class="form-label" for="req-{{ $index }}-engagement">{{ __('Engagement type') }} <span class="text-danger">*</span></label>
            <select class="form-select @error("$p.preferred_engagement_type") is-invalid @enderror" id="req-{{ $index }}-engagement" name="requirements[{{ $index }}][preferred_engagement_type]">
                <option value="">{{ __('Select') }}</option>
                @foreach ($engagementTypes as $value => $label)
                    <option value="{{ $value }}" @selected(($row['preferred_engagement_type'] ?? '') === $value)>{{ $label }}</option>
                @endforeach
            </select>
            @error("$p.preferred_engagement_type") <span class="invalid-feedback" role="alert">{{ $message }}</span> @enderror
        </div>
        <div class="col-md-4 col-6">
            <label class="form-label" for="req-{{ $index }}-years">{{ __('Minimum years of experience') }}</label>
            <input type="number" class="form-control @error("$p.minimum_years_experience") is-invalid @enderror" id="req-{{ $index }}-years"
                name="requirements[{{ $index }}][minimum_years_experience]" value="{{ $row['minimum_years_experience'] ?? '' }}" min="0" max="60" step="0.5" inputmode="decimal">
            @error("$p.minimum_years_experience") <span class="invalid-feedback" role="alert">{{ $message }}</span> @enderror
        </div>
        <div class="col-md-4 col-6">
            <label class="form-label" for="req-{{ $index }}-proficiency">{{ __('Minimum proficiency') }}</label>
            <select class="form-select @error("$p.minimum_skill_proficiency") is-invalid @enderror" id="req-{{ $index }}-proficiency" name="requirements[{{ $index }}][minimum_skill_proficiency]">
                <option value="">{{ __('Any') }}</option>
                @foreach ($proficiencies as $value => $label)
                    <option value="{{ $value }}" @selected(($row['minimum_skill_proficiency'] ?? '') === $value)>{{ $label }}</option>
                @endforeach
            </select>
            @error("$p.minimum_skill_proficiency") <span class="invalid-feedback" role="alert">{{ $message }}</span> @enderror
        </div>
        <div class="col-md-4">
            <label class="form-label" for="req-{{ $index }}-location">{{ __('Location') }}</label>
            <input type="text" class="form-control @error("$p.location") is-invalid @enderror" id="req-{{ $index }}-location"
                name="requirements[{{ $index }}][location]" value="{{ $row['location'] ?? '' }}" maxlength="255">
            @error("$p.location") <span class="invalid-feedback" role="alert">{{ $message }}</span> @enderror
        </div>
        {{-- UNA-013 matching targets: every field is optional; an empty one is simply not scored. --}}
        <div class="col-12">
            <p class="fw-semibold small mb-0 mt-1">{{ __('Matching criteria') }}</p>
            <p class="small text-muted mb-0">{{ __('Only the criteria you configure are scored. Configured criteria are optional unless you tick them as mandatory: optional criteria only raise or lower the match score, while a mandatory criterion that is not met disqualifies the candidate whatever their score. Work mode and engagement type never change the score; they only disqualify when mandatory.') }}</p>
        </div>
        <div class="col-md-4 col-6">
            <label class="form-label" for="req-{{ $index }}-start">{{ __('Required start date') }}</label>
            <input type="date" class="form-control @error("$p.required_start_date") is-invalid @enderror" id="req-{{ $index }}-start"
                name="requirements[{{ $index }}][required_start_date]" value="{{ $row['required_start_date'] ?? '' }}">
            @error("$p.required_start_date") <span class="invalid-feedback" role="alert">{{ $message }}</span> @enderror
        </div>
        <div class="col-md-4 col-6">
            <label class="form-label" for="req-{{ $index }}-hours">{{ __('Minimum hours per week') }}</label>
            <input type="number" class="form-control @error("$p.minimum_hours_per_week") is-invalid @enderror" id="req-{{ $index }}-hours"
                name="requirements[{{ $index }}][minimum_hours_per_week]" value="{{ $row['minimum_hours_per_week'] ?? '' }}" min="1" max="168" step="1" inputmode="numeric">
            @error("$p.minimum_hours_per_week") <span class="invalid-feedback" role="alert">{{ $message }}</span> @enderror
        </div>
        <div class="col-md-4">
            <label class="form-label" for="req-{{ $index }}-project-type">{{ __('Project type') }}</label>
            <select class="form-select @error("$p.project_type") is-invalid @enderror" id="req-{{ $index }}-project-type" name="requirements[{{ $index }}][project_type]">
                <option value="">{{ __('Any') }}</option>
                @foreach (\App\Enums\Profile\ProjectType::options() as $value => $label)
                    <option value="{{ $value }}" @selected(($row['project_type'] ?? '') === $value)>{{ $label }}</option>
                @endforeach
            </select>
            @error("$p.project_type") <span class="invalid-feedback" role="alert">{{ $message }}</span> @enderror
        </div>
        <div class="col-md-8">
            <label class="form-label" for="req-{{ $index }}-timezone">{{ __('Timezone') }}</label>
            <select class="form-select @error("$p.timezone") is-invalid @enderror" id="req-{{ $index }}-timezone" name="requirements[{{ $index }}][timezone]">
                <option value="">{{ __('Any timezone') }}</option>
                @foreach (\DateTimeZone::listIdentifiers(\DateTimeZone::ALL) as $timezone)
                    <option value="{{ $timezone }}" @selected(($row['timezone'] ?? '') === $timezone)>{{ $timezone }}</option>
                @endforeach
            </select>
            @error("$p.timezone") <span class="invalid-feedback" role="alert">{{ $message }}</span> @enderror
        </div>
        <div class="col-md-4">
            <label class="form-label" for="req-{{ $index }}-tolerance">{{ __('Timezone tolerance (hours)') }}</label>
            <input type="number" class="form-control @error("$p.timezone_tolerance_hours") is-invalid @enderror" id="req-{{ $index }}-tolerance"
                name="requirements[{{ $index }}][timezone_tolerance_hours]" value="{{ $row['timezone_tolerance_hours'] ?? '' }}" min="0" max="{{ config('pod_matching.max_timezone_tolerance_hours', 14) }}" step="1" inputmode="numeric">
            @error("$p.timezone_tolerance_hours") <span class="invalid-feedback" role="alert">{{ $message }}</span> @enderror
        </div>
        <div class="col-12">
            @php $mandatory = array_map('strval', (array) ($row['mandatory_criteria'] ?? [])); @endphp
            <input type="hidden" name="requirements[{{ $index }}][mandatory_criteria_present]" value="1">
            <span class="form-label d-block">{{ __('Mandatory criteria') }} <span class="small text-muted fw-normal">{{ __('(failure disqualifies; only configured criteria can be mandatory)') }}</span></span>
            <div class="d-flex flex-wrap gap-3 @error("$p.mandatory_criteria") is-invalid @enderror @error("$p.mandatory_criteria.*") is-invalid @enderror">
                @foreach (\App\Enums\Matching\MatchCriterion::options() as $value => $label)
                    <div class="form-check mb-0">
                        <input class="form-check-input" type="checkbox" id="req-{{ $index }}-mandatory-{{ $value }}" value="{{ $value }}"
                            name="requirements[{{ $index }}][mandatory_criteria][]" data-co-mandatory @checked(in_array($value, $mandatory, true))>
                        <label class="form-check-label small" for="req-{{ $index }}-mandatory-{{ $value }}">{{ $label }}</label>
                    </div>
                @endforeach
            </div>
            @error("$p.mandatory_criteria") <span class="invalid-feedback d-block" role="alert">{{ $message }}</span> @enderror
            @error("$p.mandatory_criteria.*") <span class="invalid-feedback d-block" role="alert">{{ $message }}</span> @enderror
        </div>
        <div class="col-12">
            <label class="form-label" for="req-{{ $index }}-notes">{{ __('Notes') }}</label>
            <textarea class="form-control @error("$p.description") is-invalid @enderror" id="req-{{ $index }}-notes" rows="2"
                name="requirements[{{ $index }}][description]" maxlength="2000">{{ $row['description'] ?? '' }}</textarea>
            @error("$p.description") <span class="invalid-feedback" role="alert">{{ $message }}</span> @enderror
        </div>
    </div>
</div>
