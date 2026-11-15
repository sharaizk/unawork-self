{{-- Params: $index, $row, $jobTitles, $engagementTypes --}}
@php $p = "experiences.$index"; $current = filter_var($row['is_current'] ?? false, FILTER_VALIDATE_BOOLEAN); @endphp
<div class="una-profile-row" data-profile-row>
    <input type="hidden" name="experiences[{{ $index }}][id]" value="{{ $row['id'] ?? '' }}">
    <div class="d-flex justify-content-between align-items-center mb-2">
        <span class="una-profile-row__title"><i class="ti ti-briefcase"></i> {{ __('Position') }}</span>
        <button type="button" class="btn btn-sm btn-light-danger una-profile-remove" data-profile-remove
            title="{{ __('Remove experience') }}" aria-label="{{ __('Remove experience') }}"><i class="ti ti-trash"></i></button>
    </div>
    @error("$p.id")
        <div class="alert alert-danger py-2 small">{{ $message }}</div>
    @enderror
    <div class="row g-3">
        <div class="col-md-6">
            <label class="form-label" for="exp-{{ $index }}-job_title">{{ __('Job title') }} <span class="text-danger">*</span></label>
            <input type="text" class="form-control @error("$p.job_title") is-invalid @enderror" id="exp-{{ $index }}-job_title"
                name="experiences[{{ $index }}][job_title]" value="{{ $row['job_title'] ?? '' }}" maxlength="255">
            @error("$p.job_title") <span class="invalid-feedback" role="alert">{{ $message }}</span> @enderror
        </div>
        <div class="col-md-6">
            <label class="form-label" for="exp-{{ $index }}-employer">{{ __('Employer') }} <span class="text-danger">*</span></label>
            <input type="text" class="form-control @error("$p.employer") is-invalid @enderror" id="exp-{{ $index }}-employer"
                name="experiences[{{ $index }}][employer]" value="{{ $row['employer'] ?? '' }}" maxlength="255">
            @error("$p.employer") <span class="invalid-feedback" role="alert">{{ $message }}</span> @enderror
        </div>
        <div class="col-md-6">
            <label class="form-label" for="exp-{{ $index }}-job_title_id">{{ __('Matching job title (optional)') }}</label>
            <select class="form-select @error("$p.job_title_id") is-invalid @enderror" id="exp-{{ $index }}-job_title_id" name="experiences[{{ $index }}][job_title_id]">
                <option value="">{{ __('None') }}</option>
                @foreach ($jobTitles as $jobTitle)
                    <option value="{{ $jobTitle->id }}" @selected((string) ($row['job_title_id'] ?? '') === (string) $jobTitle->id)>{{ $jobTitle->title }}</option>
                @endforeach
            </select>
            @error("$p.job_title_id") <span class="invalid-feedback" role="alert">{{ $message }}</span> @enderror
        </div>
        <div class="col-md-3">
            <label class="form-label" for="exp-{{ $index }}-engagement_type">{{ __('Type') }}</label>
            <select class="form-select @error("$p.engagement_type") is-invalid @enderror" id="exp-{{ $index }}-engagement_type" name="experiences[{{ $index }}][engagement_type]">
                <option value="">{{ __('Select') }}</option>
                @foreach ($engagementTypes as $value => $label)
                    <option value="{{ $value }}" @selected(($row['engagement_type'] ?? '') === $value)>{{ $label }}</option>
                @endforeach
            </select>
            @error("$p.engagement_type") <span class="invalid-feedback" role="alert">{{ $message }}</span> @enderror
        </div>
        <div class="col-md-3">
            <label class="form-label" for="exp-{{ $index }}-location">{{ __('Location') }}</label>
            <input type="text" class="form-control @error("$p.location") is-invalid @enderror" id="exp-{{ $index }}-location"
                name="experiences[{{ $index }}][location]" value="{{ $row['location'] ?? '' }}" maxlength="255">
            @error("$p.location") <span class="invalid-feedback" role="alert">{{ $message }}</span> @enderror
        </div>
        <div class="col-md-4 col-6">
            <label class="form-label" for="exp-{{ $index }}-start_date">{{ __('Start date') }}</label>
            <input type="date" class="form-control @error("$p.start_date") is-invalid @enderror" id="exp-{{ $index }}-start_date"
                name="experiences[{{ $index }}][start_date]" value="{{ $row['start_date'] ?? '' }}">
            @error("$p.start_date") <span class="invalid-feedback" role="alert">{{ $message }}</span> @enderror
        </div>
        <div class="col-md-4 col-6">
            <label class="form-label" for="exp-{{ $index }}-end_date">{{ __('End date') }}</label>
            <input type="date" class="form-control js-experience-end @error("$p.end_date") is-invalid @enderror" id="exp-{{ $index }}-end_date"
                name="experiences[{{ $index }}][end_date]" value="{{ $current ? '' : ($row['end_date'] ?? '') }}" @disabled($current)>
            @error("$p.end_date") <span class="invalid-feedback" role="alert">{{ $message }}</span> @enderror
        </div>
        <div class="col-md-4 d-flex align-items-end">
            <div class="form-check form-switch mb-2">
                <input type="hidden" name="experiences[{{ $index }}][is_current]" value="0">
                <input class="form-check-input js-experience-current" type="checkbox" role="switch" value="1"
                    id="exp-{{ $index }}-is_current" name="experiences[{{ $index }}][is_current]" @checked($current)>
                <label class="form-check-label" for="exp-{{ $index }}-is_current">{{ __('I currently work here') }}</label>
            </div>
        </div>
        <div class="col-12">
            <label class="form-label" for="exp-{{ $index }}-description">{{ __('Description') }}</label>
            <textarea class="form-control @error("$p.description") is-invalid @enderror" id="exp-{{ $index }}-description" rows="2"
                name="experiences[{{ $index }}][description]" maxlength="5000">{{ $row['description'] ?? '' }}</textarea>
            @error("$p.description") <span class="invalid-feedback" role="alert">{{ $message }}</span> @enderror
        </div>
    </div>
</div>
