{{-- Params: $index, $row --}}
@php $p = "portfolios.$index"; @endphp
<div class="una-profile-row" data-profile-row>
    <input type="hidden" name="portfolios[{{ $index }}][id]" value="{{ $row['id'] ?? '' }}">
    <div class="d-flex justify-content-between align-items-center mb-2">
        <span class="una-profile-row__title"><i class="ti ti-layout-grid"></i> {{ __('Portfolio item') }}</span>
        <button type="button" class="btn btn-sm btn-light-danger una-profile-remove" data-profile-remove
            title="{{ __('Remove portfolio item') }}" aria-label="{{ __('Remove portfolio item') }}"><i class="ti ti-trash"></i></button>
    </div>
    @error("$p.id") <div class="alert alert-danger py-2 small">{{ $message }}</div> @enderror
    <div class="row g-3">
        <div class="col-md-5">
            <label class="form-label" for="port-{{ $index }}-title">{{ __('Title') }} <span class="text-danger">*</span></label>
            <input type="text" class="form-control @error("$p.title") is-invalid @enderror" id="port-{{ $index }}-title"
                name="portfolios[{{ $index }}][title]" value="{{ $row['title'] ?? '' }}" maxlength="255">
            @error("$p.title") <span class="invalid-feedback" role="alert">{{ $message }}</span> @enderror
        </div>
        <div class="col-md-7">
            <label class="form-label" for="port-{{ $index }}-url">{{ __('URL') }} <span class="text-danger">*</span></label>
            <input type="url" class="form-control @error("$p.url") is-invalid @enderror" id="port-{{ $index }}-url"
                name="portfolios[{{ $index }}][url]" value="{{ $row['url'] ?? '' }}" maxlength="500" placeholder="https://">
            @error("$p.url") <span class="invalid-feedback" role="alert">{{ $message }}</span> @enderror
        </div>
        <div class="col-12">
            <label class="form-label" for="port-{{ $index }}-description">{{ __('Description') }}</label>
            <textarea class="form-control @error("$p.description") is-invalid @enderror" id="port-{{ $index }}-description" rows="2"
                name="portfolios[{{ $index }}][description]" maxlength="2000">{{ $row['description'] ?? '' }}</textarea>
            @error("$p.description") <span class="invalid-feedback" role="alert">{{ $message }}</span> @enderror
        </div>
    </div>
</div>
