{{-- Params: $index, $row --}}
@php $p = "educations.$index"; @endphp
<div class="una-profile-row" data-profile-row>
    <input type="hidden" name="educations[{{ $index }}][id]" value="{{ $row['id'] ?? '' }}">
    <div class="d-flex justify-content-between align-items-center mb-2">
        <span class="una-profile-row__title"><i class="ti ti-school"></i> {{ __('Education') }}</span>
        <button type="button" class="btn btn-sm btn-light-danger una-profile-remove" data-profile-remove
            title="{{ __('Remove education') }}" aria-label="{{ __('Remove education') }}"><i class="ti ti-trash"></i></button>
    </div>
    @error("$p.id") <div class="alert alert-danger py-2 small">{{ $message }}</div> @enderror
    <div class="row g-3">
        <div class="col-md-6">
            <label class="form-label" for="edu-{{ $index }}-institution">{{ __('Institution') }} <span class="text-danger">*</span></label>
            <input type="text" class="form-control @error("$p.institution") is-invalid @enderror" id="edu-{{ $index }}-institution"
                name="educations[{{ $index }}][institution]" value="{{ $row['institution'] ?? '' }}" maxlength="255">
            @error("$p.institution") <span class="invalid-feedback" role="alert">{{ $message }}</span> @enderror
        </div>
        <div class="col-md-6">
            <label class="form-label" for="edu-{{ $index }}-degree">{{ __('Degree') }}</label>
            <input type="text" class="form-control @error("$p.degree") is-invalid @enderror" id="edu-{{ $index }}-degree"
                name="educations[{{ $index }}][degree]" value="{{ $row['degree'] ?? '' }}" maxlength="255">
            @error("$p.degree") <span class="invalid-feedback" role="alert">{{ $message }}</span> @enderror
        </div>
        <div class="col-md-6">
            <label class="form-label" for="edu-{{ $index }}-field_of_study">{{ __('Field of study') }}</label>
            <input type="text" class="form-control @error("$p.field_of_study") is-invalid @enderror" id="edu-{{ $index }}-field_of_study"
                name="educations[{{ $index }}][field_of_study]" value="{{ $row['field_of_study'] ?? '' }}" maxlength="255">
            @error("$p.field_of_study") <span class="invalid-feedback" role="alert">{{ $message }}</span> @enderror
        </div>
        <div class="col-md-3 col-6">
            <label class="form-label" for="edu-{{ $index }}-start_year">{{ __('Start year') }}</label>
            <input type="number" class="form-control @error("$p.start_year") is-invalid @enderror" id="edu-{{ $index }}-start_year"
                name="educations[{{ $index }}][start_year]" value="{{ $row['start_year'] ?? '' }}" min="1950" max="{{ date('Y') + 10 }}" step="1">
            @error("$p.start_year") <span class="invalid-feedback" role="alert">{{ $message }}</span> @enderror
        </div>
        <div class="col-md-3 col-6">
            <label class="form-label" for="edu-{{ $index }}-end_year">{{ __('End year') }}</label>
            <input type="number" class="form-control @error("$p.end_year") is-invalid @enderror" id="edu-{{ $index }}-end_year"
                name="educations[{{ $index }}][end_year]" value="{{ $row['end_year'] ?? '' }}" min="1950" max="{{ date('Y') + 10 }}" step="1">
            @error("$p.end_year") <span class="invalid-feedback" role="alert">{{ $message }}</span> @enderror
        </div>
        <div class="col-12">
            <label class="form-label" for="edu-{{ $index }}-description">{{ __('Description') }}</label>
            <textarea class="form-control @error("$p.description") is-invalid @enderror" id="edu-{{ $index }}-description" rows="2"
                name="educations[{{ $index }}][description]" maxlength="5000">{{ $row['description'] ?? '' }}</textarea>
            @error("$p.description") <span class="invalid-feedback" role="alert">{{ $message }}</span> @enderror
        </div>
    </div>
</div>
