@extends('layouts.admin')

@section('page-title')
    {{ $document->title }} - {{ __('Knowledge Base & Materials') }}
@endsection

@section('links')
    <li class="breadcrumb-item"><a href="{{ route('home') }}">{{ __('Home') }}</a></li>
    <li class="breadcrumb-item"><a href="{{ route('pods.index') }}">{{ __('Pod') }}</a></li>
    <li class="breadcrumb-item"><a href="{{ route('pods.show', $pod->id) }}">{{ $pod->title }}</a></li>
    <li class="breadcrumb-item"><a href="{{ route('pods.documents.index', $pod) }}">{{ __('Knowledge Base & Materials') }}</a></li>
    <li class="breadcrumb-item">{{ $document->title }}</li>
@endsection

@section('action-button')
    <a href="{{ route('pods.documents.index', $pod) }}" class="btn btn-sm btn-outline-secondary me-2">
        <i class="ti ti-arrow-left"></i> {{ __('Back to Materials') }}
    </a>
    @if ($version && $version->isFile())
        <a href="{{ route('pods.documents.versions.download', [$pod, $document, $version]) }}" class="btn btn-sm btn-outline-success me-2">
            <i class="ti ti-download"></i> {{ __('Download File') }}
        </a>
    @endif
    @if ($isManager)
        <a href="{{ route('pods.documents.edit', [$pod, $document]) }}" class="btn btn-sm btn-outline-primary me-2">
            <i class="ti ti-edit"></i> {{ __('Edit Details') }}
        </a>
        <a href="{{ route('pods.documents.versions.create', [$pod, $document]) }}" class="btn btn-sm btn-primary">
            <i class="ti ti-git-branch"></i> {{ __('Draft New Version / Replace') }}
        </a>
    @endif
@endsection

@section('content')
<div class="row">
    <div class="col-xl-2 col-lg-3">
        @include('pod.manage.nav', ['pod' => $pod, 'podId' => $pod->id, 'workspace' => $pod->workspace])
    </div>
    <div class="col-xl-10 col-lg-9">
        {{-- Document Header Card --}}
        <div class="card mb-4">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
                    <div>
                        <div class="d-flex align-items-center gap-2 mb-2">
                            <span class="badge bg-light text-dark border">{{ $document->type?->label() }}</span>
                            @if ($document->isMandatory())
                                <span class="badge bg-danger"><i class="ti ti-star me-1"></i>{{ __('Mandatory') }}</span>
                            @else
                                <span class="badge bg-secondary">{{ __('Optional') }}</span>
                            @endif
                            @if ($isManager)
                                @if ($document->isPublished())
                                    <span class="badge bg-success">{{ __('Published') }}</span>
                                @elseif ($document->isDraft())
                                    <span class="badge bg-warning text-dark">{{ __('Draft') }}</span>
                                @else
                                    <span class="badge bg-secondary">{{ __('Archived') }}</span>
                                @endif
                            @endif
                            @if ($version && $version->isPublished())
                                <span class="badge bg-primary">v{{ $version->version_number }} ({{ __('Active') }})</span>
                            @elseif ($version && $version->isDraft())
                                <span class="badge bg-warning text-dark">v{{ $version->version_number }} ({{ __('Draft') }})</span>
                            @endif

                            @if ($version && $version->isFile())
                                <span class="badge bg-info text-white"><i class="ti ti-file-text me-1"></i>{{ $version->fileExtension() ?? 'FILE' }}</span>
                            @elseif ($version && $version->isLink())
                                <span class="badge bg-secondary"><i class="ti ti-link me-1"></i>{{ __('External Link') }}</span>
                            @endif
                        </div>
                        <h4 class="mb-2">{{ $document->title }}</h4>
                        @if ($document->description)
                            <p class="text-muted mb-0">{{ $document->description }}</p>
                        @endif
                    </div>
                    <div class="text-end text-muted small">
                        <div>{{ __('Created by :name', ['name' => $document->creator?->name ?? __('Unknown')]) }}</div>
                        <div>{{ $document->created_at?->format('M d, Y') }}</div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Upload Failure Warning (Managers Only) --}}
        @if ($isManager && $version && $version->isUploadFailed())
            <div class="alert alert-danger d-flex align-items-start gap-2 mb-4">
                <i class="ti ti-alert-triangle fs-4 mt-1"></i>
                <div>
                    <h6 class="alert-heading mb-1">{{ __('Upload or Processing Failed') }}</h6>
                    <p class="mb-1">{{ $version->failure_message ?: __('The uploaded file could not be stored or validated properly.') }}</p>
                    <small class="text-muted">{{ __('This version cannot be published until a valid file is uploaded or replaced.') }}</small>
                </div>
            </div>
        @endif

        {{-- Text Extraction Failure Warning & Retry (Managers Only) --}}
        @if ($isManager && $version && $version->isExtractionFailed())
            <div class="alert alert-danger d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
                <div class="d-flex align-items-start gap-2">
                    <i class="ti ti-alert-triangle fs-4 mt-1"></i>
                    <div>
                        <h6 class="alert-heading mb-1">{{ __('Text Extraction Failed') }}</h6>
                        <p class="mb-0">{{ $version->extraction_failure_message ?: __('The searchable text could not be extracted from this document.') }}</p>
                    </div>
                </div>
                <form method="POST" action="{{ route('pods.documents.versions.retry_extraction', [$pod, $document, $version]) }}">
                    @csrf
                    <button type="submit" class="btn btn-sm btn-danger">
                        <i class="ti ti-refresh me-1"></i> {{ __('Retry Extraction') }}
                    </button>
                </form>
            </div>
        @endif

        {{-- Version Content Card --}}
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div>
                    <h5 class="mb-0">
                        @if ($version)
                            {{ __('Version :num', ['num' => $version->version_number]) }}
                            @if ($version->isPublished())
                                <span class="badge bg-success ms-2">{{ __('Published') }}</span>
                            @elseif ($version->isDraft())
                                <span class="badge bg-warning text-dark ms-2">{{ __('Draft') }}</span>
                            @else
                                <span class="badge bg-secondary ms-2">{{ __('Archived') }}</span>
                            @endif

                            @if ($isManager)
                                @if ($version->isUploadReady())
                                    <span class="badge bg-success-light text-success ms-1">{{ __('Ready') }}</span>
                                @elseif ($version->isUploadPending())
                                    <span class="badge bg-warning-light text-warning ms-1">{{ __('Pending') }}</span>
                                @elseif ($version->isUploadFailed())
                                    <span class="badge bg-danger ms-1">{{ __('Failed') }}</span>
                                @endif

                                @if ($version->isExtractionCompleted())
                                    <span class="badge bg-success-light text-success ms-1"><i class="ti ti-file-search me-1"></i>{{ __('Text Extracted') }}</span>
                                @elseif ($version->isExtractionProcessing())
                                    <span class="badge bg-info-light text-info ms-1"><i class="ti ti-loader me-1"></i>{{ __('Extracting Text...') }}</span>
                                @elseif ($version->isExtractionPending())
                                    <span class="badge bg-warning-light text-warning ms-1"><i class="ti ti-clock me-1"></i>{{ __('Extraction Pending') }}</span>
                                @elseif ($version->isExtractionFailed())
                                    <span class="badge bg-danger ms-1" title="{{ $version->extraction_failure_message }}"><i class="ti ti-alert-triangle me-1"></i>{{ __('Extraction Failed') }}</span>
                                @endif
                            @endif
                        @else
                            {{ __('Material Content') }}
                        @endif
                    </h5>
                    @if ($version && $version->change_summary)
                        <small class="text-muted">{{ __('Note: :summary', ['summary' => $version->change_summary]) }}</small>
                    @endif
                </div>

                @if ($isManager && $version)
                    <div class="d-flex gap-2">
                        @if ($version->isDraft())
                            <a href="{{ route('pods.documents.versions.edit', [$pod, $document, $version]) }}" class="btn btn-sm btn-outline-primary">
                                <i class="ti ti-edit"></i> {{ __('Edit Draft') }}
                            </a>
                            @if ($version->isUploadReady())
                                <form method="POST" action="{{ route('pods.documents.versions.publish', [$pod, $document, $version]) }}" onsubmit="return confirm('{{ __('Publishing this version will archive the currently published version. Do you want to proceed?') }}');">
                                    @csrf
                                    <button type="submit" class="btn btn-sm btn-success">
                                        <i class="ti ti-check"></i> {{ __('Publish Version') }}
                                    </button>
                                </form>
                            @else
                                <button class="btn btn-sm btn-secondary" disabled title="{{ __('Cannot publish with incomplete upload') }}">
                                    <i class="ti ti-ban"></i> {{ __('Publish Unavailable') }}
                                </button>
                            @endif
                        @endif
                    </div>
                @endif
            </div>

            <div class="card-body">
                @if ($version)
                    {{-- File Attachment Box --}}
                    @if ($version->isFile())
                        <div class="p-3 mb-3 bg-light border rounded d-flex justify-content-between align-items-center flex-wrap gap-2">
                            <div class="d-flex align-items-center gap-3">
                                <div class="bg-primary text-white rounded p-3">
                                    <i class="ti ti-file-text fs-2"></i>
                                </div>
                                <div>
                                    <h6 class="mb-1">{{ $version->file_name ?: basename($version->file_path) }}</h6>
                                    <div class="text-muted small">
                                        <span class="badge bg-secondary me-2">{{ $version->fileExtension() ?? 'FILE' }}</span>
                                        @if ($version->readableFileSize())
                                            <span class="me-2">{{ $version->readableFileSize() }}</span>
                                        @endif
                                        <span><i class="ti ti-shield-check text-success"></i> {{ __('Stored privately') }}</span>
                                    </div>
                                </div>
                            </div>
                            <div>
                                <a href="{{ route('pods.documents.versions.download', [$pod, $document, $version]) }}" class="btn btn-success">
                                    <i class="ti ti-download me-1"></i> {{ __('Download File') }}
                                </a>
                            </div>
                        </div>
                    @endif

                    {{-- External Link Box --}}
                    @if ($version->isLink())
                        <div class="p-3 mb-3 bg-light border rounded d-flex justify-content-between align-items-center flex-wrap gap-2">
                            <div class="d-flex align-items-center gap-3">
                                <div class="bg-info text-white rounded p-3">
                                    <i class="ti ti-link fs-2"></i>
                                </div>
                                <div>
                                    <h6 class="mb-1">{{ __('External Resource Link') }}</h6>
                                    <div class="text-muted small text-break">
                                        <a href="{{ $version->link_url }}" target="_blank" rel="noopener noreferrer" class="text-decoration-none">
                                            {{ $version->link_url }} <i class="ti ti-external-link"></i>
                                        </a>
                                    </div>
                                </div>
                            </div>
                            <div>
                                <a href="{{ $version->link_url }}" target="_blank" rel="noopener noreferrer" class="btn btn-outline-info">
                                    <i class="ti ti-external-link me-1"></i> {{ __('Open Link') }}
                                </a>
                            </div>
                        </div>
                    @endif

                    {{-- Extracted Searchable Text (Managers Only) --}}
                    @if ($isManager && !empty($version->extracted_text))
                        <div class="card border mb-3">
                            <div class="card-header bg-light py-2 d-flex justify-content-between align-items-center">
                                <span class="small fw-bold text-muted">
                                    <i class="ti ti-file-search me-1"></i>{{ __('Extracted Searchable Text') }}
                                    <span class="badge bg-secondary ms-1">{{ strlen($version->extracted_text) }} {{ __('characters') }}</span>
                                </span>
                                <div class="d-flex align-items-center gap-2">
                                    <form method="POST" action="{{ route('pods.documents.versions.retry_extraction', [$pod, $document, $version]) }}" class="d-inline">
                                        @csrf
                                        <button type="submit" class="btn btn-xs btn-outline-secondary" title="{{ __('Re-run Text Extraction') }}">
                                            <i class="ti ti-refresh"></i> {{ __('Re-extract') }}
                                        </button>
                                    </form>
                                    <button class="btn btn-xs btn-outline-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#collapseExtractedText" aria-expanded="false" aria-controls="collapseExtractedText">
                                        {{ __('Toggle Preview') }}
                                    </button>
                                </div>
                            </div>
                            <div class="collapse" id="collapseExtractedText">
                                <div class="card-body bg-white small text-muted font-monospace" style="max-height: 250px; overflow-y: auto; white-space: pre-wrap;">{{ $version->extracted_text }}</div>
                            </div>
                        </div>
                    @endif

                    {{-- Text Content --}}
                    @if ($version->content && (!$version->isFile() && !$version->isLink() || strlen($version->content) > 30))
                        <div class="p-4 bg-light rounded border" style="min-height: 200px; font-size: 1.05rem; line-height: 1.7; white-space: pre-wrap; font-family: inherit;">{{ $version->content }}</div>
                    @endif

                    <div class="d-flex justify-content-between align-items-center mt-3 text-muted small">
                        <div>
                            @if ($version->published_at)
                                <span><i class="ti ti-calendar"></i> {{ __('Published: :date', ['date' => $version->published_at->format('M d, Y H:i')]) }}</span>
                            @else
                                <span><i class="ti ti-clock"></i> {{ __('Draft created: :date', ['date' => $version->created_at->format('M d, Y H:i')]) }}</span>
                            @endif
                        </div>
                        <div>
                            <span><i class="ti ti-user"></i> {{ $version->creator?->name }}</span>
                        </div>
                    </div>
                @else
                    <div class="text-center py-4 text-muted">
                        <i class="ti ti-file-alert fs-2 mb-2"></i>
                        <p class="mb-0">{{ __('No published content is available for this material.') }}</p>
                    </div>
                @endif
            </div>
        </div>

        {{-- Version History Card (Business Managers Only) --}}
        @if ($isManager)
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <div>
                        <h5 class="mb-1">{{ __('Version History') }}</h5>
                        <p class="text-muted small mb-0">{{ __('Complete historical record. Published and archived versions are permanently preserved.') }}</p>
                    </div>
                    <a href="{{ route('pods.documents.versions.create', [$pod, $document]) }}" class="btn btn-sm btn-outline-primary">
                        <i class="ti ti-plus"></i> {{ __('Draft New Version / Replace') }}
                    </a>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>{{ __('Version') }}</th>
                                    <th>{{ __('Status') }}</th>
                                    <th>{{ __('Format') }}</th>
                                    <th>{{ __('Upload') }}</th>
                                    <th>{{ __('Extraction') }}</th>
                                    <th>{{ __('Change Summary') }}</th>
                                    <th>{{ __('Author') }}</th>
                                    <th>{{ __('Date') }}</th>
                                    <th class="text-end pe-3">{{ __('Actions') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($versions as $v)
                                    <tr @if($version && $version->id === $v->id) class="table-active" @endif>
                                        <td>
                                            <span class="fw-bold">v{{ $v->version_number }}</span>
                                            @if ($version && $version->id === $v->id)
                                                <span class="badge bg-secondary ms-1">{{ __('Viewing') }}</span>
                                            @endif
                                        </td>
                                        <td>
                                            @if ($v->isPublished())
                                                <span class="badge bg-success">{{ __('Published') }}</span>
                                            @elseif ($v->isDraft())
                                                <span class="badge bg-warning text-dark">{{ __('Draft') }}</span>
                                            @else
                                                <span class="badge bg-secondary">{{ __('Archived') }}</span>
                                            @endif
                                        </td>
                                        <td>
                                            @if ($v->isFile())
                                                <span class="badge bg-info text-white">{{ $v->fileExtension() ?? 'FILE' }}</span>
                                            @elseif ($v->isLink())
                                                <span class="badge bg-secondary">{{ __('Link') }}</span>
                                            @else
                                                <span class="badge bg-light text-dark border">{{ __('Text') }}</span>
                                            @endif
                                        </td>
                                        <td>
                                            @if ($v->isUploadReady())
                                                <span class="badge bg-success">{{ __('Ready') }}</span>
                                            @elseif ($v->isUploadPending())
                                                <span class="badge bg-warning text-dark">{{ __('Pending') }}</span>
                                            @elseif ($v->isUploadFailed())
                                                <span class="badge bg-danger" title="{{ $v->failure_message }}">{{ __('Failed') }}</span>
                                            @endif
                                        </td>
                                        <td>
                                            @if ($v->isExtractionCompleted())
                                                <span class="badge bg-success-light text-success">{{ __('Extracted') }}</span>
                                            @elseif ($v->isExtractionProcessing())
                                                <span class="badge bg-info-light text-info">{{ __('Processing') }}</span>
                                            @elseif ($v->isExtractionPending())
                                                <span class="badge bg-warning-light text-warning">{{ __('Pending') }}</span>
                                            @elseif ($v->isExtractionFailed())
                                                <span class="badge bg-danger" title="{{ $v->extraction_failure_message }}">{{ __('Failed') }}</span>
                                            @else
                                                <span class="text-muted small">-</span>
                                            @endif
                                        </td>
                                        <td>
                                            <span class="text-muted small">{{ $v->change_summary ?? '—' }}</span>
                                        </td>
                                        <td>
                                            <small>{{ $v->creator?->name ?? __('Unknown') }}</small>
                                        </td>
                                        <td>
                                            <small class="text-muted">
                                                @if ($v->published_at)
                                                    {{ $v->published_at->format('M d, Y') }}
                                                @else
                                                    {{ $v->created_at->format('M d, Y') }}
                                                @endif
                                            </small>
                                        </td>
                                        <td class="text-end pe-3">
                                            @if ($v->isFile())
                                                <a href="{{ route('pods.documents.versions.download', [$pod, $document, $v]) }}" class="btn btn-sm btn-light text-success" title="{{ __('Download File') }}">
                                                    <i class="ti ti-download"></i>
                                                </a>
                                                <form method="POST" action="{{ route('pods.documents.versions.retry_extraction', [$pod, $document, $v]) }}" class="d-inline">
                                                    @csrf
                                                    <button type="submit" class="btn btn-sm btn-light text-secondary" title="{{ __('Retry Text Extraction') }}">
                                                        <i class="ti ti-refresh"></i>
                                                    </button>
                                                </form>
                                            @endif

                                            <a href="{{ route('pods.documents.versions.show', [$pod, $document, $v]) }}" class="btn btn-sm btn-light" title="{{ __('View Version Content') }}">
                                                <i class="ti ti-eye"></i>
                                            </a>

                                            @if ($v->isDraft())
                                                <a href="{{ route('pods.documents.versions.edit', [$pod, $document, $v]) }}" class="btn btn-sm btn-light text-primary" title="{{ __('Edit Draft') }}">
                                                    <i class="ti ti-pencil"></i>
                                                </a>
                                                @if ($v->isUploadReady())
                                                    <form method="POST" action="{{ route('pods.documents.versions.publish', [$pod, $document, $v]) }}" class="d-inline" onsubmit="return confirm('{{ __('Publishing this version will archive the previous version. Proceed?') }}');">
                                                        @csrf
                                                        <button type="submit" class="btn btn-sm btn-light text-success" title="{{ __('Publish Version') }}">
                                                            <i class="ti ti-check"></i>
                                                        </button>
                                                    </form>
                                                @endif
                                                @if ($versions->count() > 1)
                                                    <form method="POST" action="{{ route('pods.documents.versions.destroy', [$pod, $document, $v]) }}" class="d-inline" onsubmit="return confirm('{{ __('Are you sure you want to delete this draft version?') }}');">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit" class="btn btn-sm btn-light text-danger" title="{{ __('Delete Draft') }}">
                                                            <i class="ti ti-trash"></i>
                                                        </button>
                                                    </form>
                                                @endif
                                            @else
                                                <span class="badge bg-light text-muted border" title="{{ __('Immutable historical record') }}">
                                                    <i class="ti ti-lock"></i> {{ __('Preserved') }}
                                                </span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        @endif
    </div>
</div>
@endsection
