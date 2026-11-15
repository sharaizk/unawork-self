{{-- Params: $index, $row --}}
@php $p = "certifications.$index"; @endphp
<div class="una-profile-row" data-profile-row>
    <input type="hidden" name="certifications[{{ $index }}][id]" value="{{ $row['id'] ?? '' }}">
    <div class="d-flex justify-content-between align-items-center mb-2">
        <span class="una-profile-row__title"><i class="ti ti-certificate"></i> {{ __('Certification') }}</span>
        <button type="button" class="btn btn-sm btn-light-danger una-profile-remove" data-profile-remove
            title="{{ __('Remove certification') }}" aria-label="{{ __('Remove certification') }}"><i class="ti ti-trash"></i></button>
    </div>
    @error("$p.id") <div class="alert alert-danger py-2 small">{{ $message }}</div> @enderror
    <div class="row g-3">
        <div class="col-md-6">
            <label class="form-label" for="cert-{{ $index }}-name">{{ __('Name') }} <span class="text-danger">*</span></label>
            <input type="text" class="form-control @error("$p.name") is-invalid @enderror" id="cert-{{ $index }}-name"
                name="certifications[{{ $index }}][name]" value="{{ $row['name'] ?? '' }}" maxlength="255">
            @error("$p.name") <span class="invalid-feedback" role="alert">{{ $message }}</span> @enderror
        </div>
        <div class="col-md-6">
            <label class="form-label" for="cert-{{ $index }}-issuing_organization">{{ __('Issuing organization') }}</label>
            <input type="text" class="form-control @error("$p.issuing_organization") is-invalid @enderror" id="cert-{{ $index }}-issuing_organization"
                name="certifications[{{ $index }}][issuing_organization]" value="{{ $row['issuing_organization'] ?? '' }}" maxlength="255">
            @error("$p.issuing_organization") <span class="invalid-feedback" role="alert">{{ $message }}</span> @enderror
        </div>
        <div class="col-md-3 col-6">
            <label class="form-label" for="cert-{{ $index }}-issue_date">{{ __('Issue date') }}</label>
            <input type="date" class="form-control @error("$p.issue_date") is-invalid @enderror" id="cert-{{ $index }}-issue_date"
                name="certifications[{{ $index }}][issue_date]" value="{{ $row['issue_date'] ?? '' }}">
            @error("$p.issue_date") <span class="invalid-feedback" role="alert">{{ $message }}</span> @enderror
        </div>
        <div class="col-md-3 col-6">
            <label class="form-label" for="cert-{{ $index }}-expiration_date">{{ __('Expiration date') }}</label>
            <input type="date" class="form-control @error("$p.expiration_date") is-invalid @enderror" id="cert-{{ $index }}-expiration_date"
                name="certifications[{{ $index }}][expiration_date]" value="{{ $row['expiration_date'] ?? '' }}">
            @error("$p.expiration_date") <span class="invalid-feedback" role="alert">{{ $message }}</span> @enderror
        </div>
        <div class="col-md-6">
            <label class="form-label" for="cert-{{ $index }}-credential_id">{{ __('Credential ID') }}</label>
            <input type="text" class="form-control @error("$p.credential_id") is-invalid @enderror" id="cert-{{ $index }}-credential_id"
                name="certifications[{{ $index }}][credential_id]" value="{{ $row['credential_id'] ?? '' }}" maxlength="255">
            @error("$p.credential_id") <span class="invalid-feedback" role="alert">{{ $message }}</span> @enderror
        </div>
        <div class="col-12">
            <label class="form-label" for="cert-{{ $index }}-credential_url">{{ __('Credential URL') }}</label>
            <input type="url" class="form-control @error("$p.credential_url") is-invalid @enderror" id="cert-{{ $index }}-credential_url"
                name="certifications[{{ $index }}][credential_url]" value="{{ $row['credential_url'] ?? '' }}" maxlength="500" placeholder="https://">
            @error("$p.credential_url") <span class="invalid-feedback" role="alert">{{ $message }}</span> @enderror
        </div>
    </div>
</div>
