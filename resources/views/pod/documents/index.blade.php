@extends('layouts.admin')

@section('page-title')
    {{ __('Knowledge Base & Onboarding Materials') }} - {{ $pod->title }}
@endsection

@section('links')
    <li class="breadcrumb-item"><a href="{{ route('home') }}">{{ __('Home') }}</a></li>
    <li class="breadcrumb-item"><a href="{{ route('pods.index') }}">{{ __('Pod') }}</a></li>
    <li class="breadcrumb-item"><a href="{{ route('pods.show', $pod->id) }}">{{ $pod->title }}</a></li>
    <li class="breadcrumb-item">{{ __('Knowledge Base & Materials') }}</li>
@endsection

@section('action-button')
    @if ($isManager)
        <a href="{{ route('pods.documents.create', $pod) }}" class="btn btn-sm btn-primary">
            <i class="ti ti-plus"></i> {{ __('Add Material') }}
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
                    <h5 class="mb-1">{{ __('Knowledge Base & Onboarding Materials') }}</h5>
                    <p class="text-muted small mb-0">{{ __('Official documentation, instructions, guidelines, uploaded files, and links for this POD.') }}</p>
                </div>
                @if ($isManager)
                    <div>
                        <a href="{{ route('pods.documents.create', $pod) }}" class="btn btn-sm btn-primary">
                            <i class="ti ti-plus"></i> {{ __('Add Material') }}
                        </a>
                    </div>
                @endif
            </div>
            <div class="card-body border-bottom py-3">
                <form method="GET" action="{{ route('pods.documents.index', $pod) }}" class="row g-2 align-items-center">
                    <div class="col-auto">
                        <label class="form-label small mb-0 text-muted">{{ __('Filter by Type:') }}</label>
                    </div>
                    <div class="col-auto">
                        <select name="type" class="form-select form-select-sm" onchange="this.form.submit()">
                            <option value="">{{ __('All Types') }}</option>
                            @foreach ($types as $type)
                                <option value="{{ $type->value }}" @if($typeFilter === $type->value) selected @endif>
                                    {{ $type->label() }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    @if ($isManager)
                        <div class="col-auto ms-3">
                            <label class="form-label small mb-0 text-muted">{{ __('Filter by Status:') }}</label>
                        </div>
                        <div class="col-auto">
                            <select name="status" class="form-select form-select-sm" onchange="this.form.submit()">
                                <option value="">{{ __('All Statuses') }}</option>
                                @foreach ($statuses as $status)
                                    <option value="{{ $status->value }}" @if($statusFilter === $status->value) selected @endif>
                                        {{ $status->label() }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    @endif

                    @if ($typeFilter || $statusFilter)
                        <div class="col-auto">
                            <a href="{{ route('pods.documents.index', $pod) }}" class="btn btn-sm btn-light">
                                <i class="ti ti-x"></i> {{ __('Clear') }}
                            </a>
                        </div>
                    @endif
                </form>
            </div>

            <div class="card-body p-0">
                @if ($documents->count() > 0)
                    @php
                        $docIds = $documents->pluck('id')->values()->all();
                    @endphp
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    @if ($isManager)
                                        <th style="width: 80px;">{{ __('Order') }}</th>
                                    @endif
                                    <th>{{ __('Material / Document') }}</th>
                                    <th>{{ __('Type') }}</th>
                                    <th>{{ __('Format') }}</th>
                                    @if ($isManager)
                                        <th>{{ __('Status') }}</th>
                                        <th>{{ __('Upload') }}</th>
                                    @endif
                                    <th>{{ __('Active Version') }}</th>
                                    <th>{{ __('Last Updated') }}</th>
                                    <th class="text-end pe-3">{{ __('Action') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($documents as $index => $doc)
                                    @php
                                        $pubVersion = $doc->publishedVersion();
                                        $activeVersion = $isManager ? ($pubVersion ?? $doc->latestVersion()) : $pubVersion;
                                    @endphp
                                    <tr>
                                        @if ($isManager)
                                            <td>
                                                <div class="btn-group btn-group-sm">
                                                    @if ($index > 0)
                                                        @php
                                                            $upOrder = $docIds;
                                                            $temp = $upOrder[$index - 1];
                                                            $upOrder[$index - 1] = $upOrder[$index];
                                                            $upOrder[$index] = $temp;
                                                        @endphp
                                                        <form method="POST" action="{{ route('pods.documents.reorder', $pod) }}" class="d-inline">
                                                            @csrf
                                                            @foreach ($upOrder as $orderedId)
                                                                <input type="hidden" name="ordered_ids[]" value="{{ $orderedId }}">
                                                            @endforeach
                                                            <button type="submit" class="btn btn-xs btn-outline-secondary" title="{{ __('Move Up') }}">
                                                                <i class="ti ti-arrow-up"></i>
                                                            </button>
                                                        </form>
                                                    @else
                                                        <button class="btn btn-xs btn-outline-secondary" disabled>
                                                            <i class="ti ti-arrow-up text-muted"></i>
                                                        </button>
                                                    @endif

                                                    @if ($index < count($docIds) - 1)
                                                        @php
                                                            $downOrder = $docIds;
                                                            $temp = $downOrder[$index + 1];
                                                            $downOrder[$index + 1] = $downOrder[$index];
                                                            $downOrder[$index] = $temp;
                                                        @endphp
                                                        <form method="POST" action="{{ route('pods.documents.reorder', $pod) }}" class="d-inline">
                                                            @csrf
                                                            @foreach ($downOrder as $orderedId)
                                                                <input type="hidden" name="ordered_ids[]" value="{{ $orderedId }}">
                                                            @endforeach
                                                            <button type="submit" class="btn btn-xs btn-outline-secondary" title="{{ __('Move Down') }}">
                                                                <i class="ti ti-arrow-down"></i>
                                                            </button>
                                                        </form>
                                                    @else
                                                        <button class="btn btn-xs btn-outline-secondary" disabled>
                                                            <i class="ti ti-arrow-down text-muted"></i>
                                                        </button>
                                                    @endif
                                                </div>
                                            </td>
                                        @endif
                                        <td>
                                            <a href="{{ route('pods.documents.show', [$pod, $doc]) }}" class="fw-bold text-dark d-block">
                                                {{ $doc->title }}
                                            </a>
                                            @if ($doc->description)
                                                <small class="text-muted d-block text-truncate" style="max-width: 320px;">
                                                    {{ $doc->description }}
                                                </small>
                                            @endif
                                        </td>
                                        <td>
                                            <span class="badge bg-light text-dark border">
                                                {{ $doc->type?->label() }}
                                            </span>
                                        </td>
                                        <td>
                                            @if ($activeVersion && $activeVersion->isFile())
                                                <span class="badge bg-info text-white">
                                                    <i class="ti ti-file-text me-1"></i>{{ $activeVersion->fileExtension() ?? 'FILE' }}
                                                    @if ($activeVersion->readableFileSize())
                                                        <span class="opacity-75">({{ $activeVersion->readableFileSize() }})</span>
                                                    @endif
                                                </span>
                                            @elseif ($activeVersion && $activeVersion->isLink())
                                                <span class="badge bg-secondary">
                                                    <i class="ti ti-link me-1"></i>{{ __('Link') }}
                                                </span>
                                            @else
                                                <span class="badge bg-light text-dark border">
                                                    <i class="ti ti-article me-1"></i>{{ __('Text') }}
                                                </span>
                                            @endif
                                        </td>
                                        @if ($isManager)
                                            <td>
                                                @if ($doc->isPublished())
                                                    <span class="badge bg-success">{{ __('Published') }}</span>
                                                @elseif ($doc->isDraft())
                                                    <span class="badge bg-warning text-dark">{{ __('Draft') }}</span>
                                                @else
                                                    <span class="badge bg-secondary">{{ __('Archived') }}</span>
                                                @endif
                                            </td>
                                            <td>
                                                @if ($activeVersion)
                                                    @if ($activeVersion->isUploadReady())
                                                        <span class="badge bg-success" title="{{ __('Ready') }}"><i class="ti ti-check"></i> {{ __('Ready') }}</span>
                                                    @elseif ($activeVersion->isUploadPending())
                                                        <span class="badge bg-warning text-dark" title="{{ __('Processing upload') }}"><i class="ti ti-clock"></i> {{ __('Pending') }}</span>
                                                    @elseif ($activeVersion->isUploadFailed())
                                                        <span class="badge bg-danger" title="{{ $activeVersion->failure_message }}"><i class="ti ti-alert-triangle"></i> {{ __('Failed') }}</span>
                                                    @endif
                                                @else
                                                    <span class="text-muted small">—</span>
                                                @endif
                                            </td>
                                        @endif
                                        <td>
                                            @if ($pubVersion)
                                                <span class="badge bg-primary">v{{ $pubVersion->version_number }}</span>
                                            @else
                                                <span class="text-muted small">{{ __('None (draft)') }}</span>
                                            @endif
                                        </td>
                                        <td>
                                            <small class="text-muted">
                                                {{ $doc->updated_at?->diffForHumans() }}
                                            </small>
                                        </td>
                                        <td class="text-end pe-3">
                                            @if ($activeVersion && $activeVersion->isFile())
                                                <a href="{{ route('pods.documents.versions.download', [$pod, $doc, $activeVersion]) }}" class="btn btn-sm btn-outline-success me-1" title="{{ __('Download File') }}">
                                                    <i class="ti ti-download"></i>
                                                </a>
                                            @elseif ($activeVersion && $activeVersion->isLink())
                                                <a href="{{ $activeVersion->link_url }}" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-outline-info me-1" title="{{ __('Open External Link') }}">
                                                    <i class="ti ti-external-link"></i>
                                                </a>
                                            @endif

                                            <a href="{{ route('pods.documents.show', [$pod, $doc]) }}" class="btn btn-sm btn-outline-primary" title="{{ __('View Details') }}">
                                                <i class="ti ti-eye"></i> {{ __('View') }}
                                            </a>

                                            @if ($isManager)
                                                <div class="btn-group">
                                                    <button type="button" class="btn btn-sm btn-light dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                                                        <i class="ti ti-dots-vertical"></i>
                                                    </button>
                                                    <ul class="dropdown-menu dropdown-menu-end">
                                                        <li>
                                                            <a class="dropdown-item" href="{{ route('pods.documents.edit', [$pod, $doc]) }}">
                                                                <i class="ti ti-edit me-2"></i> {{ __('Edit Details') }}
                                                            </a>
                                                        </li>
                                                        <li>
                                                            <a class="dropdown-item" href="{{ route('pods.documents.versions.create', [$pod, $doc]) }}">
                                                                <i class="ti ti-git-branch me-2"></i> {{ __('Draft New Version / Replace') }}
                                                            </a>
                                                        </li>
                                                        @if ($activeVersion && $activeVersion->isFile())
                                                            <li>
                                                                <a class="dropdown-item" href="{{ route('pods.documents.versions.download', [$pod, $doc, $activeVersion]) }}">
                                                                    <i class="ti ti-download me-2"></i> {{ __('Download Current File') }}
                                                                </a>
                                                            </li>
                                                        @endif
                                                        @if (!$doc->isArchived())
                                                            <li>
                                                                <form method="POST" action="{{ route('pods.documents.archive', [$pod, $doc]) }}" onsubmit="return confirm('{{ __('Are you sure you want to archive this material?') }}');">
                                                                    @csrf
                                                                    <button type="submit" class="dropdown-item text-secondary">
                                                                        <i class="ti ti-archive me-2"></i> {{ __('Archive Material') }}
                                                                    </button>
                                                                </form>
                                                            </li>
                                                        @endif
                                                        @if ($doc->isDraft() && !$doc->versions()->whereNotNull('published_at')->exists())
                                                            <li><hr class="dropdown-divider"></li>
                                                            <li>
                                                                <form method="POST" action="{{ route('pods.documents.destroy', [$pod, $doc]) }}" onsubmit="return confirm('{{ __('Are you sure you want to delete this draft material? This cannot be undone.') }}');">
                                                                    @csrf
                                                                    @method('DELETE')
                                                                    <button type="submit" class="dropdown-item text-danger">
                                                                        <i class="ti ti-trash me-2"></i> {{ __('Delete Draft') }}
                                                                    </button>
                                                                </form>
                                                            </li>
                                                        @endif
                                                    </ul>
                                                </div>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    @if ($documents->hasPages())
                        <div class="card-footer py-2">
                            {{ $documents->links() }}
                        </div>
                    @endif
                @else
                    <div class="text-center py-5">
                        <i class="ti ti-files text-muted fs-1 mb-2"></i>
                        <h6 class="text-muted">{{ __('No materials or documents found.') }}</h6>
                        @if ($isManager)
                            <p class="text-muted small mb-3">{{ __('Add onboarding instructions, policies, PDF guides, documents, or resource links for this POD.') }}</p>
                            <a href="{{ route('pods.documents.create', $pod) }}" class="btn btn-sm btn-primary">
                                <i class="ti ti-plus"></i> {{ __('Add Material') }}
                            </a>
                        @endif
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
