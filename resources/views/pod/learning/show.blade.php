@extends('layouts.admin')

@section('page-title')
    {{ $document->title }} - {{ __('Outer Circle Learning') }} - {{ $pod->title }}
@endsection

@section('links')
    <li class="breadcrumb-item"><a href="{{ route('home') }}">{{ __('Home') }}</a></li>
    <li class="breadcrumb-item"><a href="{{ route('pods.index') }}">{{ __('Pod') }}</a></li>
    <li class="breadcrumb-item"><a href="{{ route('pods.show', $pod->id) }}">{{ $pod->title }}</a></li>
    <li class="breadcrumb-item"><a href="{{ route('pods.learning.index', $pod) }}">{{ __('Outer Circle Learning') }}</a></li>
    <li class="breadcrumb-item">{{ \Illuminate\Support\Str::limit($document->title, 25) }}</li>
@endsection

@section('content')
<div class="row">
    <div class="col-xl-2 col-lg-3">
        @include('pod.manage.nav', ['pod' => $pod, 'podId' => $pod->id, 'workspace' => $pod->workspace])
    </div>
    <div class="col-xl-10 col-lg-9">
        {{-- Navigation Header --}}
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
            <div>
                <a href="{{ route('pods.learning.index', $pod) }}" class="btn btn-sm btn-light text-muted">
                    <i class="ti ti-arrow-left me-1"></i> {{ __('Back to Learning Overview') }}
                </a>
            </div>
            <div class="d-flex align-items-center gap-2">
                <span class="badge bg-primary px-3 py-2">
                    <i class="ti ti-chart-pie me-1"></i> {{ $progress['percentage'] }}% {{ __('Outer Circle Completed') }}
                </span>
                @if ($document->isMandatory())
                    <span class="badge bg-danger px-3 py-2">
                        <i class="ti ti-star me-1"></i> {{ __('Mandatory Material') }}
                    </span>
                @else
                    <span class="badge bg-secondary px-3 py-2">
                        <i class="ti ti-book me-1"></i> {{ __('Optional Resource') }}
                    </span>
                @endif
            </div>
        </div>

        {{-- Material Content Card --}}
        <div class="card mb-4 shadow-sm border-0">
            <div class="card-header bg-white py-3 border-bottom">
                <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
                    <div>
                        <div class="d-flex align-items-center gap-2 mb-2">
                            <span class="badge bg-light text-dark border">{{ $document->type?->label() }}</span>
                            @if ($version->isFile())
                                <span class="badge bg-info text-white"><i class="ti ti-file-text me-1"></i>{{ $version->fileExtension() ?? 'FILE' }}</span>
                            @elseif ($version->isLink())
                                <span class="badge bg-secondary"><i class="ti ti-link me-1"></i>{{ __('External Link') }}</span>
                            @else
                                <span class="badge bg-light text-dark border">{{ __('Text Guide') }}</span>
                            @endif

                            @if ($isCompleted)
                                <span class="badge bg-success-light text-success"><i class="ti ti-check me-1"></i>{{ __('Completed') }}</span>
                            @else
                                <span class="badge bg-warning-light text-warning"><i class="ti ti-clock me-1"></i>{{ __('Incomplete') }}</span>
                            @endif
                        </div>
                        <h3 class="fw-bold mb-2 text-dark">{{ $document->title }}</h3>
                        @if ($document->description)
                            <p class="text-muted mb-0 fs-6">{{ $document->description }}</p>
                        @endif
                    </div>
                    <div class="text-end text-muted small">
                        <div>{{ __('Published on :date', ['date' => $version->published_at?->format('M d, Y') ?? $version->created_at->format('M d, Y')]) }}</div>
                        <div>{{ __('Author: :name', ['name' => $version->creator?->name ?? __('Business Manager')]) }}</div>
                    </div>
                </div>
            </div>

            <div class="card-body p-4">
                {{-- File Material Box --}}
                @if ($version->isFile())
                    <div class="p-4 mb-4 bg-light border rounded d-flex justify-content-between align-items-center flex-wrap gap-3">
                        <div class="d-flex align-items-center gap-3">
                            <div class="bg-primary text-white rounded p-3">
                                <i class="ti ti-file-text fs-1"></i>
                            </div>
                            <div>
                                <h5 class="mb-1 fw-bold">{{ $version->file_name ?: basename($version->file_path) }}</h5>
                                <div class="text-muted small">
                                    <span class="badge bg-secondary me-2">{{ $version->fileExtension() ?? 'FILE' }}</span>
                                    @if ($version->readableFileSize())
                                        <span class="me-2">{{ $version->readableFileSize() }}</span>
                                    @endif
                                    <span><i class="ti ti-shield-lock text-success me-1"></i> {{ __('Private Protected Document') }}</span>
                                </div>
                            </div>
                        </div>
                        <div>
                            <a href="{{ route('pods.documents.versions.download', [$pod, $document, $version]) }}" class="btn btn-success px-4">
                                <i class="ti ti-download me-1"></i> {{ __('Download Material File') }}
                            </a>
                        </div>
                    </div>
                @endif

                {{-- Link Material Box --}}
                @if ($version->isLink())
                    <div class="p-4 mb-4 bg-light border rounded d-flex justify-content-between align-items-center flex-wrap gap-3">
                        <div class="d-flex align-items-center gap-3">
                            <div class="bg-info text-white rounded p-3">
                                <i class="ti ti-external-link fs-1"></i>
                            </div>
                            <div>
                                <h5 class="mb-1 fw-bold">{{ __('External Resource Link') }}</h5>
                                <div class="text-muted small text-break">
                                    <a href="{{ $version->link_url }}" target="_blank" rel="noopener noreferrer" class="text-decoration-none">
                                        {{ $version->link_url }} <i class="ti ti-external-link ms-1"></i>
                                    </a>
                                </div>
                            </div>
                        </div>
                        <div>
                            <a href="{{ $version->link_url }}" target="_blank" rel="noopener noreferrer" class="btn btn-outline-info px-4">
                                <i class="ti ti-external-link me-1"></i> {{ __('Open Resource') }}
                            </a>
                        </div>
                    </div>
                @endif

                {{-- Text Content Guide --}}
                @if ($version->content && (!$version->isFile() && !$version->isLink() || strlen($version->content) > 30))
                    <div class="p-4 bg-white rounded border" style="font-size: 1.1rem; line-height: 1.8; white-space: pre-wrap; font-family: inherit;">{{ $version->content }}</div>
                @endif
            </div>

            {{-- Completion Action & Navigation Card --}}
            <div class="card-footer bg-light p-4 border-top">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
                    <div>
                        @if ($isCompleted)
                            <div class="d-flex align-items-center gap-2 text-success">
                                <i class="ti ti-circle-check fs-2"></i>
                                <div>
                                    <div class="fw-bold">{{ __('Material Completed') }}</div>
                                    @if ($completion && $completion->completed_at)
                                        <small class="text-muted">{{ __('Completed on :date', ['date' => $completion->completed_at->format('M d, Y H:i')]) }}</small>
                                    @endif
                                </div>
                            </div>
                        @else
                            <form method="POST" action="{{ route('pods.learning.complete', [$pod, $document]) }}">
                                @csrf
                                <input type="hidden" name="version_id" value="{{ $version->id }}">
                                <button type="submit" class="btn btn-success btn-lg px-4 shadow-sm">
                                    <i class="ti ti-check me-1"></i> {{ __('Mark as Completed & Continue') }}
                                </button>
                            </form>
                        @endif
                    </div>

                    {{-- Next & Prev Nav --}}
                    <div class="d-flex gap-2">
                        @if ($prevItem)
                            <a href="{{ route('pods.learning.show', [$pod, $prevItem]) }}" class="btn btn-outline-secondary">
                                <i class="ti ti-chevron-left me-1"></i> {{ __('Previous') }}
                            </a>
                        @endif
                        @if ($nextItem)
                            <a href="{{ route('pods.learning.show', [$pod, $nextItem]) }}" class="btn btn-outline-primary">
                                {{ __('Next') }} <i class="ti ti-chevron-right ms-1"></i>
                            </a>
                        @else
                            <a href="{{ route('pods.learning.index', $pod) }}" class="btn btn-outline-secondary">
                                {{ __('Finish & Back to Overview') }}
                            </a>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
