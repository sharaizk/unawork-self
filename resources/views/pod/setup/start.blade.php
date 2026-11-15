{{--
    Start or resume guided POD creation (UNA-011). Rendered by PodSetupController@create with
    $drafts (the company's drafts, most recently updated first), $company (completeness summary) and
    $draftToken (resubmitting the form resumes the same draft instead of creating another).
--}}
@extends('layouts.admin')

@section('page-title')
    {{ __('Create a POD') }}
@endsection

@section('links')
    <li class="breadcrumb-item"><a href="{{ route('home') }}">{{ __('Home') }}</a></li>
    <li class="breadcrumb-item"><a href="{{ route('pods.index') }}">{{ __('POD') }}</a></li>
    <li class="breadcrumb-item">{{ __('Create a POD') }}</li>
@endsection

@push('css-page')
    <link rel="stylesheet" href="{{ asset('assets/custom/css/company-onboarding.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/custom/css/pod-onboarding.css') }}">
@endpush

@section('content')
    <div class="row co-onboarding pod-onboarding">
        <div class="col-lg-7">
            <div class="card co-hero">
                <div class="card-header co-panel__header">
                    <div class="d-flex align-items-start gap-3">
                        <span class="co-panel__icon" aria-hidden="true"><i class="ti ti-rocket"></i></span>
                        <div>
                            <h5 class="mb-1">{{ __('Start a new POD') }}</h5>
                            <p class="small text-muted mb-0">{{ __('Give it a name to create a draft. You can configure skills, worker requirements and matching next; nothing is public until you publish.') }}</p>
                        </div>
                    </div>
                </div>
                <form method="POST" action="{{ route('pods.drafts.store') }}" novalidate>
                    @csrf
                    <input type="hidden" name="draft_token" value="{{ old('draft_token', $draftToken) }}">
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label" for="title">{{ __('POD title') }} <span class="text-danger">*</span></label>
                            <input type="text" class="form-control @error('title') is-invalid @enderror" id="title" name="title" maxlength="255" value="{{ old('title') }}" autofocus>
                            @error('title') <span class="invalid-feedback" role="alert">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="form-label" for="description">{{ __('Short description') }}</label>
                            <textarea class="form-control @error('description') is-invalid @enderror" id="description" name="description" rows="3" maxlength="5000">{{ old('description') }}</textarea>
                            @error('description') <span class="invalid-feedback" role="alert">{{ $message }}</span> @enderror
                        </div>
                    </div>
                    <div class="card-footer co-panel__footer">
                        <a href="{{ route('pods.index') }}" class="btn btn-light">{{ __('Cancel') }}</a>
                        <span class="flex-grow-1"></span>
                        <button type="submit" class="btn btn-primary">{{ __('Create draft and continue') }} <i class="ti ti-arrow-right"></i></button>
                    </div>
                </form>
            </div>
        </div>
        <div class="col-lg-5">
            @if ($drafts->isNotEmpty())
                <div class="card">
                    <div class="card-header"><h5 class="mb-0">{{ __('Resume a draft') }}</h5></div>
                    <ul class="list-group list-group-flush">
                        @foreach ($drafts as $draft)
                            <li class="list-group-item d-flex align-items-center gap-2">
                                <span class="flex-grow-1 text-break">{{ $draft->title ?: __('Untitled POD') }}
                                    <span class="d-block small text-muted">{{ __('Updated :time', ['time' => $draft->updated_at?->diffForHumans()]) }}</span></span>
                                <a href="{{ route('pods.setup.edit', $draft) }}" class="btn btn-sm btn-outline-primary">{{ __('Resume setup') }}</a>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif
            <div class="card">
                <div class="card-body">
                    <p class="fw-semibold mb-1">{{ __('Company profile: :percentage% complete', ['percentage' => $company['percentage']]) }}</p>
                    @if ($company['percentage'] >= $company['required'])
                        <p class="small text-success mb-0">{{ __('Your company profile is complete. You can publish PODs.') }}</p>
                    @else
                        <p class="small text-muted mb-2">{{ __('You can create and configure drafts now; publishing needs a :required% complete company profile.', ['required' => $company['required']]) }}</p>
                        <a href="{{ route('company.onboarding.edit') }}" class="btn btn-sm btn-light">{{ __('Complete Company Profile') }}</a>
                    @endif
                </div>
            </div>
        </div>
    </div>
@endsection
