@extends('layouts.admin')

@section('page-title')
    {{ __('Edit Document') }} - {{ $document->title }}
@endsection

@section('links')
    <li class="breadcrumb-item"><a href="{{ route('home') }}">{{ __('Home') }}</a></li>
    <li class="breadcrumb-item"><a href="{{ route('pods.index') }}">{{ __('Pod') }}</a></li>
    <li class="breadcrumb-item"><a href="{{ route('pods.show', $pod->id) }}">{{ $pod->title }}</a></li>
    <li class="breadcrumb-item"><a href="{{ route('pods.documents.index', $pod) }}">{{ __('Knowledge Base & Materials') }}</a></li>
    <li class="breadcrumb-item"><a href="{{ route('pods.documents.show', [$pod, $document]) }}">{{ $document->title }}</a></li>
    <li class="breadcrumb-item">{{ __('Edit') }}</li>
@endsection

@section('content')
<div class="row">
    <div class="col-xl-2 col-lg-3">
        @include('pod.manage.nav', ['pod' => $pod, 'podId' => $pod->id, 'workspace' => $pod->workspace])
    </div>
    <div class="col-xl-10 col-lg-9">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-1">{{ __('Edit Material Details') }}</h5>
                <p class="text-muted small mb-0">{{ __('Update metadata and optionally update or draft a new version of the material content/file.') }}</p>
            </div>
            <div class="card-body">
                @if ($document->isPublished())
                    <div class="alert alert-info py-2 small mb-4">
                        <i class="ti ti-info-circle me-1"></i>
                        {{ __('This material has published version history. Updating content or replacing files here will safely create a new draft version, preserving the active published version until you publish.') }}
                    </div>
                @endif

                <form method="POST" action="{{ route('pods.documents.update', [$pod, $document]) }}" enctype="multipart/form-data">
                    @csrf
                    @method('PUT')

                    <div class="row g-3">
                        <div class="col-md-8">
                            <label class="form-label" for="title">{{ __('Title') }} <span class="text-danger">*</span></label>
                            <input type="text" name="title" id="title" class="form-control @error('title') is-invalid @enderror" value="{{ old('title', $document->title) }}" required>
                            @error('title')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-4">
                            <label class="form-label" for="type">{{ __('Document Type') }} <span class="text-danger">*</span></label>
                            <select name="type" id="type" class="form-select @error('type') is-invalid @enderror" required>
                                @foreach ($types as $type)
                                    <option value="{{ $type->value }}" @if(old('type', $document->type?->value) === $type->value) selected @endif>
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
                            <textarea name="description" id="description" rows="2" class="form-control @error('description') is-invalid @enderror">{{ old('description', $document->description) }}</textarea>
                            @error('description')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        {{-- Material Sources --}}
                        <div class="col-12">
                            <div class="card border bg-light mb-0">
                                <div class="card-header bg-white py-2">
                                    <h6 class="mb-0 fw-bold">{{ __('Material Content & Attachments') }}</h6>
                                </div>
                                <div class="card-body">
                                    <div class="row g-3">
                                        {{-- File Upload / Replacement --}}
                                        <div class="col-md-6">
                                            <label class="form-label fw-bold" for="file">
                                                <i class="ti ti-upload me-1 text-primary"></i> {{ __('Upload New File / Replace File') }}
                                            </label>
                                            @if ($latestVersion && $latestVersion->isFile())
                                                <div class="mb-2 small text-muted">
                                                    {{ __('Current file:') }} <strong>{{ $latestVersion->file_name ?: basename($latestVersion->file_path) }}</strong>
                                                    @if ($latestVersion->readableFileSize())
                                                        ({{ $latestVersion->readableFileSize() }})
                                                    @endif
                                                </div>
                                            @endif
                                            <input type="file" name="file" id="file" class="form-control @error('file') is-invalid @enderror" accept=".pdf,.doc,.docx,application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document">
                                            <div class="form-text small">
                                                {{ __('Leave empty to keep current file. Allowed: :types. Max: :limit MB.', ['types' => strtoupper(implode(', ', $allowedExtensions)), 'limit' => $maxFileSizeMb]) }}
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
                                            <input type="url" name="link_url" id="link_url" class="form-control @error('link_url') is-invalid @enderror" value="{{ old('link_url', $latestVersion?->link_url) }}" placeholder="https://...">
                                            @error('link_url')
                                                <div class="invalid-feedback d-block">{{ $message }}</div>
                                            @enderror
                                        </div>

                                        {{-- Text Content --}}
                                        <div class="col-12">
                                            <label class="form-label fw-bold" for="content">
                                                <i class="ti ti-article me-1 text-secondary"></i> {{ __('Written Instructions / Content') }}
                                            </label>
                                            <textarea name="content" id="content" rows="8" class="form-control @error('content') is-invalid @enderror">{{ old('content', $latestVersion?->content) }}</textarea>
                                            @error('content')
                                                <div class="invalid-feedback d-block">{{ $message }}</div>
                                            @enderror
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label" for="change_summary">{{ __('Change Note / Summary') }}</label>
                            <input type="text" name="change_summary" id="change_summary" class="form-control @error('change_summary') is-invalid @enderror" value="{{ old('change_summary') }}" placeholder="{{ __('e.g., Updated handbook policy') }}">
                            @error('change_summary')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-6 d-flex align-items-center">
                            <div class="form-check form-switch mt-3">
                                <input class="form-check-input" type="checkbox" name="publish" value="1" id="publish" @if(old('publish')) checked @endif>
                                <label class="form-check-label fw-bold" for="publish">
                                    {{ __('Publish updated version') }}
                                </label>
                                <div class="form-text small">{{ __('If checked, the draft will become the active published version.') }}</div>
                            </div>
                        </div>
                    </div>

                    <div class="d-flex justify-content-end gap-2 mt-4 pt-3 border-top">
                        <a href="{{ route('pods.documents.show', [$pod, $document]) }}" class="btn btn-light">
                            {{ __('Cancel') }}
                        </a>
                        <button type="submit" class="btn btn-primary">
                            <i class="ti ti-check"></i> {{ __('Save Changes') }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
