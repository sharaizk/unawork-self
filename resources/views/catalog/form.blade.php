{{--
    Create / rename form for an administrator catalog entry, as a #commonModal fragment
    (data-ajax-popup). Also included by catalog.page for the full-page fallback.
    Params: $catalog (CatalogController::labels() + field + tagged), $entry (Model|null),
    $industries (Collection, skills and roles only), $selectedIndustries (int[]).
--}}
@php
    $field = $catalog['field'];
    $action = $entry
        ? route($catalog['route'] . '.update', [$catalog['param'] => $entry])
        : route($catalog['route'] . '.store');
@endphp
<form method="POST" action="{{ $action }}" class="catalog-form" novalidate>
    @csrf
    @if ($entry)
        @method('PUT')
    @endif
    <div class="modal-body">
        <div class="form-group mb-0">
            <label for="catalog-{{ $field }}" class="form-label">{{ $catalog['field_label'] }} <span class="text-danger">*</span></label>
            <input type="text" id="catalog-{{ $field }}" name="{{ $field }}" maxlength="255" required autofocus
                class="form-control @error($field) is-invalid @enderror"
                value="{{ old($field, $entry?->getAttribute($field)) }}" placeholder="{{ $catalog['placeholder'] }}">
            @error($field)
                <span class="invalid-feedback" role="alert">{{ $message }}</span>
            @enderror
            <p class="form-text mb-0">{{ __('Names must be unique in this catalog (case-insensitive). Extra spaces are removed.') }}</p>
        </div>
        @if ($catalog['tagged'])
            @php $checked = array_map('intval', (array) old('industry_ids', $selectedIndustries)); @endphp
            <fieldset class="form-group mt-3 mb-0">
                <legend class="form-label fs-6 mb-1">{{ __('Industries') }}</legend>
                <p class="form-text mt-0 mb-2">{{ __('Employees who choose one of these industries are offered this entry. Leave all unticked to offer it for every industry.') }}</p>
                <input type="hidden" name="industry_ids_present" value="1">
                @if ($industries->isEmpty())
                    <p class="small text-muted mb-0">{{ __('No industries exist yet.') }}</p>
                @else
                    <div class="catalog-industries border rounded p-2 @error('industry_ids') border-danger @enderror @error('industry_ids.*') border-danger @enderror">
                        <div class="row g-1">
                            @foreach ($industries as $industry)
                                <div class="col-sm-6">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="industry_ids[]" value="{{ $industry->id }}"
                                            id="catalog-industry-{{ $industry->id }}" @checked(in_array($industry->id, $checked, true))>
                                        <label class="form-check-label small" for="catalog-industry-{{ $industry->id }}">{{ $industry->name }}</label>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif
                @error('industry_ids') <span class="invalid-feedback d-block" role="alert">{{ $message }}</span> @enderror
                @error('industry_ids.*') <span class="invalid-feedback d-block" role="alert">{{ $message }}</span> @enderror
            </fieldset>
        @endif
    </div>
    <div class="modal-footer">
        @if (request()->ajax())
            <button type="button" class="btn btn-light" data-bs-dismiss="modal">{{ __('Cancel') }}</button>
        @else
            <a href="{{ route($catalog['route'] . '.index') }}" class="btn btn-light">{{ __('Cancel') }}</a>
        @endif
        <button type="submit" class="btn btn-primary">{{ $entry ? __('Save Changes') : __('Create') }}</button>
    </div>
</form>

<style>
    .catalog-industries { max-height: 260px; overflow-y: auto; }
</style>
