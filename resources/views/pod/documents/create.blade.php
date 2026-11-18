@extends('layouts.admin')

@section('page-title')
    {{ __('Add Onboarding Material & Documents') }} - {{ $pod->title }}
@endsection

@section('links')
    <li class="breadcrumb-item"><a href="{{ route('home') }}">{{ __('Home') }}</a></li>
    <li class="breadcrumb-item"><a href="{{ route('pods.index') }}">{{ __('Pod') }}</a></li>
    <li class="breadcrumb-item"><a href="{{ route('pods.show', $pod->id) }}">{{ $pod->title }}</a></li>
    <li class="breadcrumb-item"><a href="{{ route('pods.documents.index', $pod) }}">{{ __('Knowledge Base & Materials') }}</a></li>
    <li class="breadcrumb-item">{{ __('Add Material') }}</li>
@endsection

@section('content')
<div class="row">
    <div class="col-xl-2 col-lg-3">
        @include('pod.manage.nav', ['pod' => $pod, 'podId' => $pod->id, 'workspace' => $pod->workspace])
    </div>
    <div class="col-xl-10 col-lg-9">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-1">{{ __('Add Onboarding Material or Document') }}</h5>
                <p class="text-muted small mb-0">{{ __('Upload files (PDF, Word), add external links, or author text guides and policies. Version 1 will be initialized.') }}</p>
            </div>
            <div class="card-body">
                <form method="POST" action="{{ route('pods.documents.store', $pod) }}" enctype="multipart/form-data">
                    @csrf

                    <div class="row g-3">
                        <div class="col-md-8">
                            <label class="form-label" for="title">{{ __('Title') }} <span class="text-danger">*</span></label>
                            <input type="text" name="title" id="title" class="form-control @error('title') is-invalid @enderror" value="{{ old('title') }}" placeholder="{{ __('e.g., Engineer Onboarding Guide, Security Policy, or Codebase Architecture') }}" required>
                            @error('title')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-4">
                            <label class="form-label" for="type">{{ __('Document Type') }} <span class="text-danger">*</span></label>
                            <select name="type" id="type" class="form-select @error('type') is-invalid @enderror" required>
                                <option value="">{{ __('Select type...') }}</option>
                                @foreach ($types as $type)
                                    <option value="{{ $type->value }}" @if(old('type') === $type->value) selected @endif>
                                        {{ $type->label() }}
                                    </option>
                                @endforeach
                            </select>
                            @error('type')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-12">
                            <label class="form-label" for="description">{{ __('Summary / Description') }}</label>
                            <textarea name="description" id="description" rows="2" class="form-control @error('description') is-invalid @enderror" placeholder="{{ __('Brief summary of the purpose or scope of this material...') }}">{{ old('description') }}</textarea>
                            @error('description')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        {{-- Material Sources: File, Link, Text --}}
                        <div class="col-12">
                            <div class="card border bg-light mb-0">
                                <div class="card-header bg-white py-2">
                                    <h6 class="mb-0 fw-bold">{{ __('Material Content & Attachments') }}</h6>
                                    <small class="text-muted">{{ __('Provide text content, upload a private document, and/or provide an external URL.') }}</small>
                                </div>
                                <div class="card-body">
                                    <div class="row g-3">
                                        {{-- File Upload --}}
                                        <div class="col-md-6">
                                            <label class="form-label fw-bold" for="file">
                                                <i class="ti ti-upload me-1 text-primary"></i> {{ __('Upload Private File (PDF, DOC, DOCX)') }}
                                            </label>
                                            <input type="file" name="file" id="file" class="form-control @error('file') is-invalid @enderror" accept=".pdf,.doc,.docx,application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document">
                                            <div class="form-text small">
                                                {{ __('Allowed: :types. Maximum size: :limit MB. Files are stored securely on private storage.', ['types' => strtoupper(implode(', ', $allowedExtensions)), 'limit' => $maxFileSizeMb]) }}
                                            </div>
                                            @error('file')
                                                <div class="invalid-feedback d-block">{{ $message }}</div>
                                            @enderror
                                        </div>

                                        {{-- Link URL --}}
                                        <div class="col-md-6">
                                            <label class="form-label fw-bold" for="link_url">
                                                <i class="ti ti-link me-1 text-info"></i> {{ __('External Resource Link') }}
                                            </label>
                                            <input type="url" name="link_url" id="link_url" class="form-control @error('link_url') is-invalid @enderror" value="{{ old('link_url') }}" placeholder="https://...">
                                            <div class="form-text small">
                                                {{ __('Optional link to an external document, Loom, Notion, or GitHub repository.') }}
                                            </div>
                                            @error('link_url')
                                                <div class="invalid-feedback d-block">{{ $message }}</div>
                                            @enderror
                                        </div>

                                        {{-- Text Content --}}
                                        <div class="col-12">
                                            <label class="form-label fw-bold" for="content">
                                                <i class="ti ti-article me-1 text-secondary"></i> {{ __('Written Instructions / Text Content') }}
                                            </label>
                                            <textarea name="content" id="content" rows="8" class="form-control @error('content') is-invalid @enderror" placeholder="{{ __('Write any guidelines, instructions, steps, or policies here...') }}">{{ old('content') }}</textarea>
                                            @error('content')
                                                <div class="invalid-feedback d-block">{{ $message }}</div>
                                            @enderror
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label" for="change_summary">{{ __('Initial Version Note') }}</label>
                            <input type="text" name="change_summary" id="change_summary" class="form-control @error('change_summary') is-invalid @enderror" value="{{ old('change_summary', __('Initial draft')) }}" placeholder="{{ __('e.g., Initial onboarding pack') }}">
                            @error('change_summary')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-6 d-flex align-items-center">
                            <div class="form-check form-switch mt-3">
                                <input class="form-check-input" type="checkbox" name="publish" value="1" id="publish" @if(old('publish')) checked @endif>
                                <label class="form-check-label fw-bold" for="publish">
                                    {{ __('Publish immediately') }}
                                </label>
                                <div class="form-text small">{{ __('Published materials become accessible to associated applicants and members.') }}</div>
                            </div>
                        </div>
                    </div>

                    <div class="d-flex justify-content-end gap-2 mt-4 pt-3 border-top">
                        <a href="{{ route('pods.documents.index', $pod) }}" class="btn btn-light">
                            {{ __('Cancel') }}
                        </a>
                        <button type="submit" class="btn btn-primary">
                            <i class="ti ti-check"></i> {{ __('Save Material') }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
