@extends('layouts.admin')

@section('page-title')
    {{ $document->title }} (v{{ $version->version_number }}) - {{ __('Knowledge Base & Materials') }}
@endsection

@section('links')
    <li class="breadcrumb-item"><a href="{{ route('home') }}">{{ __('Home') }}</a></li>
    <li class="breadcrumb-item"><a href="{{ route('pods.index') }}">{{ __('Pod') }}</a></li>
    <li class="breadcrumb-item"><a href="{{ route('pods.show', $pod->id) }}">{{ $pod->title }}</a></li>
    <li class="breadcrumb-item"><a href="{{ route('pods.documents.index', $pod) }}">{{ __('Knowledge Base & Materials') }}</a></li>
    <li class="breadcrumb-item"><a href="{{ route('pods.documents.show', [$pod, $document]) }}">{{ $document->title }}</a></li>
    <li class="breadcrumb-item">{{ __('Version :num', ['num' => $version->version_number]) }}</li>
@endsection

@section('action-button')
    <a href="{{ route('pods.documents.show', [$pod, $document]) }}" class="btn btn-sm btn-outline-secondary me-2">
        <i class="ti ti-arrow-left"></i> {{ __('Back to Material') }}
    </a>
    @if ($version->isFile())
        <a href="{{ route('pods.documents.versions.download', [$pod, $document, $version]) }}" class="btn btn-sm btn-outline-success">
            <i class="ti ti-download"></i> {{ __('Download File') }}
        </a>
    @endif
@endsection

@section('content')
<div class="row">
    <div class="col-xl-2 col-lg-3">
        @include('pod.manage.nav', ['pod' => $pod, 'podId' => $pod->id, 'workspace' => $pod->workspace])
    </div>
    <div class="col-xl-10 col-lg-9">
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div>
                    <div class="d-flex align-items-center gap-2 mb-1">
                        <span class="badge bg-light text-dark border">{{ $document->type?->label() }}</span>
                        @if ($version->isPublished())
                            <span class="badge bg-success">{{ __('Published (Active)') }}</span>
                        @elseif ($version->isDraft())
                            <span class="badge bg-warning text-dark">{{ __('Draft') }}</span>
                        @else
                            <span class="badge bg-secondary">{{ __('Archived (Historical)') }}</span>
                        @endif
                        <span class="text-muted small">v{{ $version->version_number }}</span>

                        @if ($version->isFile())
                            <span class="badge bg-info text-white"><i class="ti ti-file-text me-1"></i>{{ $version->fileExtension() ?? 'FILE' }}</span>
                        @elseif ($version->isLink())
                            <span class="badge bg-secondary"><i class="ti ti-link me-1"></i>{{ __('Link') }}</span>
                        @endif

                        @if ($isManager)
                            @if ($version->isUploadReady())
                                <span class="badge bg-success-light text-success">{{ __('Ready') }}</span>
                            @elseif ($version->isUploadPending())
                                <span class="badge bg-warning-light text-warning">{{ __('Pending') }}</span>
                            @elseif ($version->isUploadFailed())
                                <span class="badge bg-danger" title="{{ $version->failure_message }}">{{ __('Failed') }}</span>
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
                    </div>
                    <h4 class="mb-0">{{ $document->title }}</h4>
                </div>

                @if ($isManager && $version->isDraft())
                    <div class="d-flex gap-2">
                        <a href="{{ route('pods.documents.versions.edit', [$pod, $document, $version]) }}" class="btn btn-sm btn-outline-primary">
                            <i class="ti ti-edit"></i> {{ __('Edit Draft') }}
                        </a>
                        @if ($version->isUploadReady())
                            <form method="POST" action="{{ route('pods.documents.versions.publish', [$pod, $document, $version]) }}" onsubmit="return confirm('{{ __('Publishing this version will archive the currently published version. Proceed?') }}');">
                                @csrf
                                <button type="submit" class="btn btn-sm btn-success">
                                    <i class="ti ti-check"></i> {{ __('Publish Version') }}
                                </button>
                            </form>
                        @endif
                    </div>
                @endif
            </div>

            <div class="card-body">
                @if ($version->change_summary)
                    <div class="alert alert-light border py-2 mb-3 small">
                        <strong>{{ __('Version Note:') }}</strong> {{ $version->change_summary }}
                    </div>
                @endif

                @if ($isManager && $version->isUploadFailed())
                    <div class="alert alert-danger py-2 mb-3 small">
                        <i class="ti ti-alert-triangle me-1"></i>
                        <strong>{{ __('Upload Failure:') }}</strong> {{ $version->failure_message }}
                    </div>
                @endif

                @if ($isManager && $version->isExtractionFailed())
                    <div class="alert alert-danger d-flex justify-content-between align-items-center flex-wrap gap-2 py-2 mb-3 small">
                        <div>
                            <i class="ti ti-alert-triangle me-1"></i>
                            <strong>{{ __('Extraction Failure:') }}</strong> {{ $version->extraction_failure_message ?: __('Unable to extract text.') }}
                        </div>
                        <form method="POST" action="{{ route('pods.documents.versions.retry_extraction', [$pod, $document, $version]) }}">
                            @csrf
                            <button type="submit" class="btn btn-xs btn-danger">
                                <i class="ti ti-refresh me-1"></i> {{ __('Retry Extraction') }}
                            </button>
                        </form>
                    </div>
                @endif

                {{-- File Box --}}
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

                {{-- Link Box --}}
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
                                <button class="btn btn-xs btn-outline-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#collapseExtractedTextVersion" aria-expanded="false" aria-controls="collapseExtractedTextVersion">
                                    {{ __('Toggle Preview') }}
                                </button>
                            </div>
                        </div>
                        <div class="collapse" id="collapseExtractedTextVersion">
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
                            <div><i class="ti ti-calendar"></i> {{ __('Published: :date', ['date' => $version->published_at->format('M d, Y H:i')]) }}</div>
                        @endif
                        @if ($version->archived_at)
                            <div><i class="ti ti-archive"></i> {{ __('Archived: :date', ['date' => $version->archived_at->format('M d, Y H:i')]) }}</div>
                        @endif
                        @if (!$version->published_at && !$version->archived_at)
                            <div><i class="ti ti-clock"></i> {{ __('Draft created: :date', ['date' => $version->created_at->format('M d, Y H:i')]) }}</div>
                        @endif
                    </div>
                    <div>
                        <span><i class="ti ti-user"></i> {{ $version->creator?->name }}</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
