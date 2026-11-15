{{-- Modal fragment shown instead of the create-POD form while the company profile is incomplete (UNA-010). Param: $businessProfile --}}
<div class="modal-body">
    <div class="alert alert-warning mb-3" role="alert">
        <i class="ti ti-lock" aria-hidden="true"></i>
        {{ __('Complete your company profile before publishing a POD. Your profile is :percentage% complete; :required% is required.', ['percentage' => $businessProfile['percentage'], 'required' => $businessProfile['required']]) }}
    </div>
    <p class="fw-semibold mb-2">{{ __('Still missing') }}</p>
    <ul class="small mb-0">
        @foreach ($businessProfile['missing'] as $missing)
            <li>{{ $missing }}</li>
        @endforeach
    </ul>
</div>
<div class="modal-footer">
    <button type="button" class="btn btn-light" data-bs-dismiss="modal">{{ __('Close') }}</button>
    <a href="{{ route('company.onboarding.edit') }}" class="btn btn-primary"><i class="ti ti-building"></i> {{ __('Complete Company Profile') }}</a>
</div>
